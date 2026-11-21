# Village City

**From the farm to the city, within 24 hours.**

Village City is an agricultural aggregation, digital commerce, wholesale distribution and logistics platform for Kenya. It connects rural farmers and cooperatives to wholesalers, supermarkets, restaurants, hotels, schools and hospitals, and runs the whole chain in one system: farmer registration, procurement, quality control, inventory, ordering, dispatch, delivery, invoicing, supplier settlements and reporting.

It has two faces:

- **A public website** (`/`) with a catalogue, a guest shopping cart, buyer sign-up, a farmer registration form and a contact form.
- **An operations platform** behind login, with role-based access for staff, customers and farmers.

## Documentation

| Guide | What it covers |
|---|---|
| [docs/setup.md](docs/setup.md) | Installation, configuration, seeding, accounts, deployment, troubleshooting |
| [docs/user-guide.md](docs/user-guide.md) | How each role uses the system; step-by-step workflows |
| [docs/architecture.md](docs/architecture.md) | Code structure, data model, business rules, security, conventions |
| [docs/modules.md](docs/modules.md) | Every module: pages, permissions, rules, and the reports available |
| [docs/roles.md](docs/roles.md) | A quick start for each of the 15 roles |
| [docs/seo-guide.md](docs/seo-guide.md) | SEO built into the website, the SEO & Site Content admin page, and the go-live checklist |
| [docs/customer-guide.md](docs/customer-guide.md) | Buying on Village City: ordering, tracking, paying, reordering |

**The guides are also built into the app.** Every signed-in user — staff, customers and farmers — can open **Help & Documentation** from the sidebar or the `?` button in the top bar. It opens on a quick start for the user's own role, shows only the guides suited to that role (customers see the buying guide, staff see the workflows and module reference, administrators also see setup and architecture), and has search, a table of contents and print support. The Markdown files in `docs/` are the single source: edit them and the in-app pages update.

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+; developed on 8.5) |
| UI | Livewire 4 with Volt (auth/settings), Flux UI (free), Tailwind CSS 4 |
| Permissions | `spatie/laravel-permission` |
| Charts | Chart.js |
| Database | MySQL / MariaDB (SQLite in-memory for tests) |
| Build | Vite |

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
# create an empty database named village_city, then set DB_* in .env
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Open <http://127.0.0.1:8000>. Log in at `/login` with the **development** administrator:

| Email | Password | Role |
|---|---|---|
| `admin@villagecity.test` | `password` | Super Administrator |

Demo staff accounts (`ops@`, `procurement@`, `finance@`, `sales@`, `logistics@`, `quality@`, `warehouse@` — all `@villagecity.test`, password `password`) are seeded too. These exist only outside production; see [docs/setup.md](docs/setup.md) for the production procedure.

For development with hot reload, run `composer dev` (server, queue worker, log tail and Vite together).

## What is in the box

- **Supply chain:** farmers, suppliers, cooperatives, collection centres, product catalogue, demand-driven procurement, quality grading, batch-level FIFO inventory.
- **Commerce:** marketplace with cart, customers with credit limits, orders with stock allocation (never oversold), sales, invoices, payments.
- **Logistics:** vehicles, transporters, routes, dispatch, delivery tracking with timeline and proof of delivery.
- **Finance:** expenses, supplier settlements, an append-only cash ledger, reversals instead of deletions.
- **Intelligence:** demand forecasting, procurement recommendations, supplier matching and business insights (rule-based, clearly labelled as estimates).
- **Reports:** 15 filterable reports with CSV export.
- **AI SEO assistant:** optional Google Gemini integration drafts page titles and descriptions, social text, product and category copy, FAQs and keyword ideas — always as reviewable drafts, using only public catalogue facts.
- **Public website & SEO:** branded home, catalogue, product and category pages, FAQ, farmer registration and contact; meta tags, Open Graph/X cards, JSON-LD structured data, sitemap, robots.txt, and an admin page to manage SEO, page copy and FAQs with a health check.
- **Platform:** 15 roles / 43 permissions, in-app notifications, audit log, global search, counties that can be extended nationally.

## Tests

```bash
php artisan test
```

164 feature tests cover the access rules, the procurement → stock → order → dispatch → delivery → payment workflow, the cart, the public forms, every report, the error pages, the in-app help and the SEO output and admin tools. They run against in-memory SQLite and never touch your database.

## Project status

This is a working MVP. Integrations such as M-Pesa Daraja, SMS/email delivery and live GPS tracking are **not** connected; the code is structured so they can be added (see [docs/architecture.md](docs/architecture.md#extension-points)). Known limitations are listed in [docs/setup.md](docs/setup.md#known-limitations).

## Licence

Proprietary — all rights reserved unless you decide otherwise. The Laravel framework is MIT licensed. Photographs in `public/images` come from [Pexels](https://www.pexels.com) under the Pexels licence.
