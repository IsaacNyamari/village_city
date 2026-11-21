# Architecture

## Overview

Village City is a conventional Laravel 12 application. Screens are **Livewire components** rendered as full pages (no separate SPA). Business rules live in **service classes**, not in components, so every screen and seeder shares the same logic.

```
Browser ─▶ routes/web.php ─▶ Livewire page ─▶ Service ─▶ Eloquent models ─▶ MySQL
                                  │              │
                                  │              ├─ InventoryService  (stock ledger)
                                  │              ├─ FinanceService    (payments, settlements, cash ledger)
                                  │              ├─ OrderService / ProcurementService / LogisticsService
                                  │              └─ Notifier, AuditLogger
                                  └─ Blade view (Flux UI + Tailwind)
```

## Directory map

```
app/
  Enums/                 OrderStatus, ProcurementStatus, DeliveryStatus (labels, colours, allowed transitions)
  Http/Middleware/       EnsureUserIsActive (signs out deactivated users)
  Livewire/
    Pages/Help.php       in-app documentation viewer
    Dashboard.php, GlobalSearch.php, NotificationBell.php, CartButton.php
    Concerns/Page.php    layout, guard(), toast(), attempt() helpers
    Crud/ResourcePage.php  reusable list + create/edit/delete page
    Pages/               one class per module screen (Farmers, Orders, Inventory, Reports …)
    Site/                public website components (Catalogue, Contact, FarmerSignup)
  Models/                Eloquent models; Concerns/ has Auditable and HasReference
  Notifications/         SystemNotification (database channel)
  Services/              all business logic (see below)
  Support/               Options (lists, labels, money formatting), Cart (session cart), Docs (in-app documentation)
config/villagecity.php   permissions, default roles, sidebar navigation
config/site.php          SEO defaults, public page metadata, editable site copy, starter FAQs
database/
  migrations/            full schema + Spatie permission tables + contact_messages
  seeders/               roles/permissions, reference data, demo data
resources/views/
  components/layouts/    app (sidebar), auth, site (public website)
  components/ui/         header, panel, stat, status, chart, empty, dl …
  components/site/       page-hero
  livewire/…             Blade views for the Livewire components
public/images/           bundled photography (site/ and products/)
tests/Feature/           135 feature tests
```

## Request flow and authorization

1. Every app route is inside `Route::middleware(['auth'])`; the public website routes are outside it.
2. `EnsureUserIsActive` (global web middleware) logs out any user whose `is_active` is false.
3. Each Livewire page calls `guard('<module>.<action>')` in `mount()`, and again in every state-changing method — never rely on a hidden button.
4. Permissions come from **spatie/laravel-permission**. `AppServiceProvider` registers `Gate::before` so a **Super Administrator** passes every check, and inactive users fail every check. `orders.access` is a custom gate: staff (`orders.view`) or customers (`orders.own`).
5. Customers only ever query orders where `customer_id` is their own; opening another customer's order returns 403.

## Permissions model

Permission names are `module.action` (for example `farmers.manage`, `orders.view`). The catalogue of modules and actions, the default role definitions and the sidebar are all in **`config/villagecity.php`**:

- `permissions` — which actions exist per module (43 in total).
- `roles` — default permission patterns per role. Patterns support `*`, `module.*` and `*.view`.
- `navigation` — the sidebar groups, routes, icons and the ability required to show each item.

Seeding applies role definitions only when a role is **first created**; afterwards administrators own them. New permissions introduced later are added to existing roles whose patterns match (see [setup.md](setup.md#re-seeding-an-existing-database)).

## Data model

67 tables including framework tables. Domain tables by area:

| Area | Tables |
|---|---|
| Geography | `counties`, `sub_counties` |
| People & access | `users` (+ `county_id`, `phone`, `is_active`, `last_login_at`), Spatie `roles`, `permissions`, pivots |
| Supply | `farmers`, `farmer_product`, `harvest_schedules`, `suppliers`, `product_supplier`, `collection_centres`, `collection_centre_user` |
| Catalogue | `product_categories`, `products` |
| Procurement & QC | `procurements`, `procurement_items`, `quality_inspections` |
| Inventory | `stock_batches`, `stock_movements` (append-only) |
| Commerce | `customers`, `orders`, `order_items`, `invoices`, `payments` |
| Logistics | `transporters`, `vehicles`, `delivery_routes`, `dispatches`, `deliveries`, `delivery_events` |
| Finance | `expenses`, `supplier_settlements`, `financial_transactions` (append-only) |
| Platform | `audit_logs`, `notifications`, `settings`, `contact_messages`, `faqs` |

Key relationships:

- A **Procurement** belongs to a farmer *or* a supplier, a collection centre, and optionally the **Order** that triggered it. Its **items** record ordered, received, accepted and rejected quantities and the grade; each inspection is stored in `quality_inspections`.
- Accepted quantity becomes a **StockBatch** (centre, product, expiry, purchase price, grade). Every change to a batch writes a **StockMovement** with a signed quantity and a polymorphic reference (procurement, order, transfer…).
- An **Order** has items (with `fulfilled_quantity` and the weighted `unit_cost` of allocated stock), one active **Invoice**, many **Payments**, and **Deliveries** (via **Dispatches**).
- Money records (`Payment`, `SupplierSettlement`, `Expense`) each write a **FinancialTransaction** in the cash ledger.

Reference numbers (`FRM-00001`, `ORD-00001`, `PRC-`, `INV-`, `PAY-`, `SET-`, `DSP-`, `DLV-`, `CTR-`, `PRD-`, `SUP-`, `CUS-`, `EXP-`, `BAT-`) are generated after insert by the `HasReference` trait.

## Business rules (the important ones)

### State machines
Each enum declares `allowedTransitions()`; services call `transition()` and throw if a move is not allowed.

| Order | Procurement | Delivery |
|---|---|---|
| Draft → Pending, Cancelled | Requested → Confirmed, Cancelled | Scheduled → Assigned, Dispatched, Failed |
| Pending → Confirmed, Cancelled | Confirmed → Received, Cancelled | Assigned → Dispatched, Failed |
| Confirmed → Processing, Awaiting procurement, Cancelled | Received → Inspected | Dispatched → In transit, Delivered, Failed, Returned |
| Awaiting procurement → Processing, Cancelled | Inspected, Cancelled → (terminal) | In transit → Delivered, Failed, Returned |
| Processing → Ready, Cancelled | | Delivered / Failed / Returned → (terminal) |
| Ready → Dispatched, Cancelled | | |
| Dispatched → In transit, Delivered, Ready (failed delivery) | | |
| In transit → Delivered, Ready | | |

### Inventory (`InventoryService`)
- The **only** code that changes stock. Every change is wrapped in a DB transaction and writes an immutable `StockMovement` (update/delete on movements throws).
- Stock is consumed **FIFO** (oldest `received_at`, then id) with row locks, never beyond what exists, and expired batches are excluded from sales.
- Operations: `receive`, `consume`, `returnOrderStock`, `writeOff` (damage/expiry/wastage), `adjust`, `transfer` (creates a destination batch preserving cost, grade and expiry).
- After consumption, if on-hand stock is at or below a product's minimum, users with `inventory.view` / `procurement.manage` get a low-stock notification (deduplicated while unread).

### Orders (`OrderService`)
- `place()` validates the customer is active, minimum order quantities, and the **credit limit** (customers on `net_*` terms). Marketplace orders force the catalogue price and no discount.
- `confirm()` issues the invoice, then `allocate()` consumes stock per line and records the weighted unit cost. A shortfall sets *Awaiting procurement* and raises a procurement request at the first active warehouse/centre.
- `allocateAwaiting()` runs after every inspection to fulfil waiting orders oldest-first.
- `proceedPartial()` shrinks quantities and totals to what was allocated and syncs the invoice.
- `cancel()` refuses if payments exist, returns stock batch-by-batch, voids the invoice and cancels open procurement requests.

### Procurement (`ProcurementService`)
- Inspection grades A/B/C/Rejected; accepted ≤ received; **payable = accepted × agreed price**. Accepted stock is received into inventory with the agreed price as batch cost.

### Logistics (`LogisticsService`)
- A dispatch can only include orders that are *Ready for dispatch* and not already on an active delivery. Departing marks orders Dispatched, the vehicle On trip, and books the transport cost as an expense. Delivery outcomes drive the order status; failed/returned deliveries send the order back to Ready.

### Finance (`FinanceService`)
- Payments can't exceed an invoice balance; settlements can't exceed the procurement balance; both write a ledger entry.
- **Reversals, not deletions:** `reversePayment`, `reverseSettlement`, `voidExpense` mark the record and append an opposite ledger entry. `FinancialTransaction` rows can't be updated or deleted.

### Metrics and definitions (`Metrics`)
| Measure | Definition |
|---|---|
| Revenue | Sum of order totals where status is Processing, Ready, Dispatched, In transit or Delivered, by `confirmed_at` |
| Goods revenue | Revenue excluding delivery charges (subtotal − discount) |
| Cost of goods sold | Σ `fulfilled_quantity × unit_cost` of those orders |
| **Gross profit** | Goods revenue − COGS. **Not net profit** |
| Procurement spend | Payable value (`total_amount`) of inspected procurements |
| Receivables | Σ (invoice total − paid), excluding void invoices |
| Supplier liabilities | Σ (procurement payable − paid) on inspected procurements |
| Stock value | Σ remaining quantity × batch purchase price |

### Intelligence (`IntelligenceService`)
Everything is a rule-based estimate over the history in your database:

- **Forecast** = average weekly ordered quantity over the history window × (1 + 0.5 × recent-vs-older trend, capped ±100%) × a seasonal factor (0.5–1.5, used only when ≥ 11 months of history exist). Confidence is High/Medium/Low by how many weeks had demand.
- **Recommendations** = forecast × (7 + safety-stock days)/7 + minimum stock − on hand − incoming open procurement. Priority is Urgent/High/Normal by days of cover.
- **Supplier score (0–100)** = quality 40 + reliability 25 + price 20 + availability 15 (listed commodity, harvest expected within 45 days). Missing history scores neutral (0.5).
- **Insights**: top sellers, most profitable, slow movers (stock but no orders), wastage, best counties, delivery on-time rate, overdue-payment risks.

## The reusable CRUD page

`App\Livewire\Crud\ResourcePage` powers most master-data screens (farmers, suppliers, cooperatives, centres, products, categories, customers, vehicles, transporters, routes, expenses, counties, users). A subclass declares:

```php
protected function model(): string { return Farmer::class; }
protected function title(): string { return 'Farmers'; }
protected function module(): string { return 'farmers'; }     // farmers.view / farmers.manage
protected function columns(): array { … }                       // label + closure (+ badge)
protected function fields(): array { … }                        // type, label, rules, options
protected function filterDefinitions(): array { … }
// optional: searchable(), query(), showRoute(), banner(), rowActions(),
//           beforeSave(), persist(), afterSave(), deletable(), editable()
```

Field types: `text`, `email`, `number`, `date`, `datetime`, `password`, `select`, `textarea`, `switch`, `checkboxes` (a relation), `image` (upload). Override `persist()` to route saves through a service (the Expenses page does this so every expense reaches the ledger).

Read-only profile pages (`FarmerShow`, `SupplierShow`, `CentreShow`, `CustomerShow`) extend `DetailPage` and return a structure of stats, details and history tables.

## Cross-cutting features

- **Audit log** — models using the `Auditable` trait log create/update/delete (status changes get their own action); logins, role/permission changes and settings changes are logged explicitly. Each entry stores user, action, description, changes and IP.
- **Notifications** — `Notifier::send($permissions, $title, $message, $url, $icon, $key)` delivers in-app notifications to every active user holding any listed permission (plus Super Administrators). A `$key` dedupes while an identical notification is unread. Events covered: new orders/confirmations, low stock, new procurement, supplier payment due, customer payment received, dispatch, delivery completed/failed, website enquiry, new farmer registration.
- **Cart** — `App\Support\Cart` is a session cart shared by the public catalogue, the marketplace and Reorder. It survives login.
- **SEO engine** — `App\Support\Seo` builds each public page's payload (title via the template, description, canonical, robots, Open Graph, Twitter, verification tags, analytics ID) from `config/site.php` defaults overlaid with admin overrides in the `settings` table (`seo.*`, `seo.page.<page>.*`, `content.<group>.<key>`); `App\Support\Schema` builds JSON-LD (Organization, WebSite, WebPage types, BreadcrumbList, Product, ItemList, FAQPage); `App\Support\SiteContent` reads editable copy. `components/seo/head.blade.php` renders it all and `components/layouts/site.blade.php` is the public layout. `SeoController` generates `/sitemap.xml` and `/robots.txt`; `SeoHeaders` middleware sends `X-Robots-Tag: noindex` for everything that is not the public site; `HasSlug` gives products and categories stable unique slugs. `App\Services\SeoAudit` produces the health score. The admin UI is `App\Livewire\Pages\Seo` (permission `seo.manage`). Saving a value equal to its default deletes the override, so later default changes still apply.
- **AI assistant** — `App\Services\Ai\GeminiClient` calls the Gemini REST API for structured JSON (key from `config('services.gemini.key')` in a header; models tried in order with one retry per model on 429/5xx/timeouts, skip on 404, immediate stop on a rejected key, overall deadline). `App\Services\Ai\SeoAi` builds prompts from public facts only (grounded by a verified-facts block), clamps and sanitises output (`SeoAi::fit`), ignores product IDs it did not ask about, and enforces a per-user hourly limit. `App\Livewire\Pages\Seo` exposes `ai*` actions that only *fill the forms*; saving is always a separate admin action, AI FAQs are created unpublished, and each use is audit-logged. `TestCase` calls `Http::preventStrayRequests()` and clears the key so the test suite can never reach the real API.
- **In-app documentation** — `App\Support\Docs` reads the Markdown files in `docs/`, renders them to safe HTML (raw HTML stripped, heading anchors, guide-to-guide links rewritten to `/help/<guide>`) and decides which guides a user may open (`registry()` holds the audience rule for each). `App\Livewire\Pages\Help` serves `/help` and `/help/{guide}`; the landing page extracts the user's own section from `docs/roles.md`. To add a guide: create the `.md` file, add it to `Docs::registry()`.
- **Error pages** — esources/views/errors/ holds branded pages for 401, 403, 404, 419, 429, 500 and 503 on a shared, self-contained layout (errors/layout.blade.php: no database, Livewire or session dependencies, so it renders even when the app is unhealthy). The 403 page names the signed-in user's role, 429 shows the wait time from Retry-After, and 500 never exposes exception details. Laravel's debug page still appears for unhandled exceptions when APP_DEBUG=true, so keep it alse in production. JSON requests keep JSON errors.
- **Charts** — `resources/js/app.js` exposes `vcChart()` (Chart.js through Alpine) and the `vcFly()` add-to-cart animation. `<x-ui.chart>` renders an empty state when there is no data.
- **Public website** — `routes/web.php` closures and `App\Livewire\Site\*`. Product prices only are exposed publicly.

## Security notes

- Server-side authorization on every mutating action; customer data isolation on orders.
- Privilege escalation guard: only Super Administrators can assign or edit that role; users can't deactivate or delete themselves.
- Public forms use a honeypot and per-IP rate limiting (contact 5 per 10 min, farmer registration 3 per hour).
- CSV export prefixes cells starting with `= + - @` to prevent spreadsheet formula injection.
- Passwords are hashed; uploads are validated (images ≤ 2–4 MB, proof of delivery image/PDF ≤ 4 MB) and stored on the public disk.
- Login throttling (5 attempts) comes from the starter kit; inactive users cannot sign in.

## Extension points

| To add… | Do this |
|---|---|
| **M-Pesa Daraja** | Add a service that creates a `Payment` via `FinanceService::recordPayment()` from the callback. The payment method list and `transaction_ref` field already exist. Keep credentials in `.env`/`config/services.php`. |
| **Email / SMS** | Add channels to `SystemNotification::via()`, or build notifications for specific events; hook the website forms in `Site\Contact` and `Site\FarmerSignup`. |
| **An AI provider** | Replace or wrap `IntelligenceService` (single seam) and bind the new implementation in `AppServiceProvider`. Keep the "estimate" labelling. |
| **A new module** | Migration + model (use `Auditable`, `HasReference`) → service if there are rules → `ResourcePage` subclass or custom Livewire page → route → permission in `config/villagecity.php` → nav item → seed permissions → tests. |
| **A new report** | Add a method `reportXyz(array $f)` to `ReportBuilder` and register it in `REPORTS` and `FILTERS`. |
| **A new permission** | Add it to `config/villagecity.php`, run `php artisan db:seed --class=RolesAndPermissionsSeeder`. |
| **Live GPS** | Add a tracking provider that appends `delivery_events` and updates `Delivery`; the timeline UI already renders events. |

## Conventions

- Business rules throw `RuntimeException` (or `InvalidArgumentException`); Livewire pages wrap calls in `attempt()` to show the message as a toast.
- Use database transactions for anything touching stock, orders or money; lock rows with `lockForUpdate()` when balances change.
- Never edit ledgers; add a reversing or adjusting record.
- Don't hard-code statuses in views — use the enums or `Options`.
- Keep views free of queries; compute in the component's `render()`.
