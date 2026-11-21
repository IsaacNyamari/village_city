# Module reference

Routes and permissions for every screen. "View" and "manage" refer to `<module>.view` and `<module>.manage`. Super Administrators bypass all checks.

## Public website (no login)

| Page | URL | Notes |
|---|---|---|
| Home | `/` | Hero, live counts, ranges, counties, calls to action |
| Products | `/produce` | Search, category links, **Add to cart**, selling prices only |
| Category | `/produce/category/{slug}` | Range landing page with introduction text |
| Product | `/produce/{slug}` | Photo, price, minimum order, related products, Product structured data |
| FAQ | `/faq` | Published FAQs with FAQ structured data |
| Sitemap / robots | `/sitemap.xml`, `/robots.txt` | Generated; see the [SEO guide](seo-guide.md) |
| Sell with us | `/sell-with-us` | Farmer registration → pending farmer, notifies `farmers.manage` users |
| About | `/about` | |
| Contact | `/contact` | Stores a message and notifies `inquiries.view` users |
| Auth | `/login`, `/register`, `/forgot-password` | Register creates a Customer role user + customer record |

## Overview

| Page | URL | Permission | Notes |
|---|---|---|---|
| Dashboard | `/dashboard` | `dashboard.view` | 10 KPIs, 6 charts, 9 operational panels; period 7/30/90 days. Customers are redirected to the marketplace; users with no operational pages (such as farmers) are redirected to Help |
| Help & Documentation | `/help`, `/help/{guide}` | any signed-in user | Role quick start, guide library filtered by role, search. Guides: `roles`, `customer-guide` (everyone); `user-guide`, `modules` (need `dashboard.view`); `setup`, `architecture` (need `settings.manage` or `roles.manage`) |

## Supply chain

| Page | URL | Permission | Highlights |
|---|---|---|---|
| Farmers | `/farmers`, `/farmers/{id}` | `farmers.*` | Auto number, location + GPS, commodities, cooperative, centre; filters by county/commodity/centre/status; approve website registrations; harvest schedules; supply, procurement and payment history |
| Suppliers | `/suppliers`, `/suppliers/{id}` | `suppliers.*` | Individual, commercial; performance and transaction history |
| Cooperatives | `/cooperatives` | `suppliers.*` | Farmer groups and cooperatives; member counts |
| Collection Centres | `/centres`, `/centres/{id}` | `centres.*` | Collection centres and warehouses; assigned officers and farmers; stock, daily receipts, capacity used, dispatches |
| Products | `/products`, `/products-categories` | `products.*` | Code, category, unit, buying/selling price, min stock, min order quantity, perishable + shelf life, storage, image; dynamic categories |
| Procurement | `/procurement`, `/procurement/{id}` | `procurement.*` | Multi-item requests; confirm → receive; assign source; links to QC and settlement |
| Inventory | `/inventory` | `inventory.*` | Stock by product, batches, movement history; transfer/adjust/write-off |
| Quality Control | `/quality-control` | `quality.*` | Pending queue, grading modal with photo, inspection history and acceptance rate |

## Commerce

| Page | URL | Permission | Highlights |
|---|---|---|---|
| Marketplace | `/marketplace` | `marketplace.view` | Product grid, search, categories, cart with steppers, checkout; staff can order on a customer's behalf |
| Customers | `/customers`, `/customers/{id}` | `customers.*` | 10 customer types, payment terms, credit limit, outstanding balance, order/invoice/payment history |
| Orders | `/orders`, `/orders/{id}` | `orders.access` (`orders.view` staff, `orders.own` customers) | Create/confirm/allocate/partial/ready/cancel/reorder; progress tracker; delivery timeline |
| Sales | `/sales` | `orders.view` | Sales value, orders, goods revenue, gross profit for a period |
| Invoices | `/invoices`, `/invoices/{id}` | `invoices.view` | Printable; overdue flag; record payment |
| Payments | `/payments` | `payments.*` | Record and reverse customer payments |

## Logistics

All logistics pages use `logistics.view` / `logistics.manage`.

| Page | URL | Highlights |
|---|---|---|
| Vehicles | `/vehicles` | Registration, type, capacity, transporter, driver, insurance/inspection dates, availability |
| Transporters | `/transporters` | Contacts, contract details, fleet |
| Dispatch | `/dispatches` | Group orders onto a vehicle; depart / in transit / cancel; records transport cost as expense |
| Deliveries | `/deliveries`, `/deliveries/{id}` | Status updates, recipient, proof of delivery, timeline, late flag |
| Routes | `/routes` | Origin, destination, distance, estimated hours (manual figures) |

## Finance

| Page | URL | Permission | Highlights |
|---|---|---|---|
| Expenses | `/expenses` | `expenses.*` | 9 categories; void instead of delete |
| Supplier Settlements | `/settlements` | `settlements.*` | Amounts owed, pay (partial allowed), history, reverse |
| Financial Transactions | `/transactions` | `finance.view` | Append-only ledger with totals in/out |
| Reports | `/reports` | `reports.view` | See below |

## Intelligence

All use `intelligence.view`.

| Page | URL | Highlights |
|---|---|---|
| Analytics | `/analytics` | Sales vs procurement by month, margin, top/profitable/wastage charts |
| Demand Forecasting | `/forecasting` | Weekly forecast per commodity, stock cover, confidence, procurement recommendations |
| Market Intelligence | `/market-intelligence` | Supplier matching, slow movers, wastage, counties, delivery performance, payment risks |

## Administration

| Page | URL | Permission |
|---|---|---|
| Users | `/users` | `users.*` |
| Roles & Permissions | `/roles` | `roles.*` |
| Counties | `/counties` | `counties.*` |
| Settings | `/system-settings` | `settings.manage` |
| SEO & Site Content | `/seo-content` | `seo.manage` |
| Website Inquiries | `/inquiries` | `inquiries.view` |
| Audit Logs | `/audit-logs` | `audit.view` |
| My profile / password | `/settings/profile`, `/settings/password` | any signed-in user |

## Reports

`/reports?report=<key>` — each report honours the filters marked below (D = date range, Co = county, Ce = centre, P = product, S = supplier/farmer, Cu = customer, St = status). Every report exports to CSV (UTF-8 with BOM, so Excel opens it correctly). The screen shows up to 500 rows; the export has all.

| Key | Report | Filters |
|---|---|---|
| `farmers` | Farmer registration | D, Co, Ce, St |
| `suppliers` | Supplier performance (acceptance rate, reliability, value, outstanding) | D, Co, S |
| `procurement` | Procurement lines | D, Co, Ce, P, S, St |
| `product_sales` | Product sales and gross profit | D, P, Cu |
| `inventory_valuation` | Stock valuation by batch | Ce, P |
| `stock_movement` | Stock movement ledger | D, Ce, P |
| `wastage` | Damaged / expired / wasted stock | D, Ce, P |
| `customer_orders` | Orders by customer | D, Cu, St |
| `delivery` | Delivery performance and on-time status | D, St |
| `financial` | Revenue, COGS, gross profit, expenses, cash flow, receivables, liabilities | D |
| `centres` | Collection centre performance | D, Co |
| `counties` | County performance | D |
| `daily_sales` | Daily sales | D |
| `liabilities` | Supplier liabilities | S |
| `receivables` | Outstanding customer invoices | Cu |

## Permission catalogue

`dashboard.view` · `farmers`, `suppliers`, `centres`, `products`, `procurement`, `quality`, `inventory`, `customers`, `payments`, `logistics`, `expenses`, `settlements`, `users`, `roles`, `counties` each with `.view` and `.manage` · `marketplace.view` · `orders.view`, `orders.manage`, `orders.own` · `invoices.view` · `finance.view` · `reports.view` · `intelligence.view` · `settings.manage` · `audit.view` · `inquiries.view` · `seo.manage`
