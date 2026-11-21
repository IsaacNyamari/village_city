# Setup, configuration and operations

## Requirements

- PHP 8.2 or newer with extensions: `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `zip` (Composer needs `zip` or `unzip`/`7z` to unpack packages)
- Composer 2
- Node.js 20+ and npm
- MySQL 8 or MariaDB 10.4+ (the project was developed on XAMPP's MariaDB 10.11)

## Installation

```bash
composer install
cp .env.example .env          # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Create an empty database (for example `village_city`, `utf8mb4_unicode_ci`) and set it in `.env`:

```dotenv
APP_NAME="Village City"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=village_city
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link      # exposes uploaded product images, inspection photos, proof of delivery
npm install
npm run build                 # or `npm run dev` while developing
php artisan serve
```

> `.env.example` ships with SQLite defaults from the Laravel starter kit. Change the `DB_*` block as above before migrating.

## What seeding creates

`php artisan migrate --seed` runs three seeders:

| Seeder | Creates | Runs in production? |
|---|---|---|
| `RolesAndPermissionsSeeder` | 43 permissions and 15 roles (from `config/villagecity.php`) | Yes |
| `ReferenceDataSeeder` | The 11 sourcing counties, 3 product categories, 20 starter commodities | Yes |
| `FaqSeeder` | 8 starter FAQs for the public website (only when none exist) | Yes |
| `DemoDataSeeder` | Admin and demo staff users, 3 centres, farmers, suppliers, customers, vehicles, routes, and a realistic set of procurements, orders, a delivery, payments and an expense | **No** — skipped when `APP_ENV=production` |

The demo data is created through the real services, so stock, invoices, ledger entries and audit logs are all consistent.

### Development accounts

| Account | Role | Password |
|---|---|---|
| `admin@villagecity.test` | Super Administrator | `password` |
| `ops@villagecity.test` | Operations Manager | `password` |
| `procurement@villagecity.test` | Procurement Officer | `password` |
| `finance@villagecity.test` | Finance Manager | `password` |
| `sales@villagecity.test` | Sales Manager | `password` |
| `logistics@villagecity.test` | Logistics Manager | `password` |
| `quality@villagecity.test` | Quality Control Officer | `password` |
| `warehouse@villagecity.test` | Warehouse Manager | `password` |

**Never deploy these.** They exist only so you can try every role locally.

## Going to production

1. Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and a strong `APP_KEY`.
2. Run `php artisan migrate --force --seed`. In production only the roles, permissions and reference data are seeded — **no users are created**.
3. Create the first administrator:

   ```bash
   php artisan tinker
   ```
   ```php
   $user = App\Models\User::create(['name' => 'Your Name', 'email' => 'you@company.co.ke', 'password' => 'a-strong-password', 'is_active' => true]);
   $user->assignRole('Super Administrator');
   ```
4. Build assets (`npm run build`), then cache: `php artisan optimize`.
5. Run a queue worker if you add queued jobs: `php artisan queue:work` (the queue driver is `database`; nothing queues work yet).
6. Configure a real mail driver in `.env` before relying on password-reset emails (`MAIL_MAILER=log` writes mail to the log by default).
7. Set `timezone` in `config/app.php` to `Africa/Nairobi`; it defaults to UTC, which affects "today", due dates and daily charts.
8. Back up the database regularly. The stock-movement and financial-transaction tables are append-only ledgers and are your audit trail.

### Re-seeding an existing database

`php artisan db:seed --class=RolesAndPermissionsSeeder` is safe to re-run: existing roles keep any permissions an administrator customised, and only **newly introduced** permissions are added to the roles whose patterns match them. Never use `migrate:fresh` on a database that holds real data.

## Settings

Administration → Settings stores values in the `settings` table:

| Setting | Used for |
|---|---|
| Company name, tagline, phone, email, address | Invoices and the public website footer |
| Default delivery charge (KES) | Pre-filled on new orders and marketplace checkouts |
| Forecast history window (weeks, 2–12) | Demand forecasting |
| Safety stock (days of demand) | Procurement recommendations |

Currency is Kenyan shillings, formatted by `App\Support\Options::money()`.

## AI assistant (Google Gemini)

The SEO & Site Content page can draft titles, descriptions, FAQs and product copy with Google Gemini. It is optional; everything works without it.

```dotenv
GEMINI_API_KEY=your-key-here
GEMINI_MODELS=gemini-flash-lite-latest,gemini-flash-latest
# Optional tuning
# GEMINI_TIMEOUT=40          # seconds per request
# GEMINI_DEADLINE=55         # total seconds across retries and fallback models
# GEMINI_HOURLY_LIMIT=60     # suggestions per user per hour
```

- **Create a key** at <https://aistudio.google.com/apikey>. Keep it **only in `.env`** (which is not committed); it is sent in a request header, never in a URL, never logged and never shown in the browser. After editing `.env` run `php artisan config:clear` (or `php artisan optimize` in production).
- **Models are tried in order.** If the first is missing (404), overloaded (429/5xx) or times out, the next one is used. A mistyped model name is therefore harmless but wasteful — the shipped `.env.example` has the correct names. Check with **SEO & Site Content → Overview → Test connection**.
- **If a key is exposed** (pasted into chat, email or a ticket, or committed by mistake), delete it in Google AI Studio and create a new one.
- **Quota and cost:** each suggestion is one request (bulk product SEO is one request for up to 10 products). The hourly per-user limit prevents runaway use; Google's own quotas and billing for your key still apply.
- **Data sent:** public catalogue and website facts only (see the [SEO guide](seo-guide.md#ai-assistant-google-gemini)). Do not add confidential information to product descriptions or page content if you do not want it sent for suggestions.

## SEO go-live (summary)

Search engines only see what the live site tells them, so before launch: set `APP_URL` and the **Canonical site URL** (Administration → SEO & Site Content → Global SEO) to your https address, switch **Allow search engines to index** on, redirect `http→https` and one hostname variant, verify the site in Google Search Console and submit `/sitemap.xml`. The full checklist is in the [SEO & content guide](seo-guide.md#go-live-checklist). `robots.txt` and `sitemap.xml` are generated by the application — do not add static files with those names to `public/`.

## Media and images

- **Product images:** upload on the Product form (stored on the `public` disk). If none is uploaded, the system falls back to `public/images/products/<product-name-slug>.jpg` when that file exists (for example `tomatoes.jpg`, `green-grams.jpg`).
- **Site photos:** `public/images/site/` holds the hero and section photos used by the website and by page banners.
- **Sharing images and icons:** `public/images/og/*.jpg` (1200×630 Open Graph images) and `public/icons/`, `public/favicon.*`, `public/apple-touch-icon.png`, `public/site.webmanifest`. Replace these files to rebrand; uploaded images in the SEO admin page override the bundled ones.
- Currently bundled product photos cover 17 of the 20 seeded commodities; wheat, beans and cowpeas show a placeholder until you upload one.

## Running the tests

```bash
php artisan test
```

Tests use an in-memory SQLite database (`phpunit.xml`) and the array cache/session, so they are safe to run anywhere. Do not point them at a real database.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Composer says "zip extension and unzip/7z commands are both missing" | Enable `extension=zip` in `php.ini`, or run `php -d extension=zip composer.phar …` |
| Pages render unstyled | Run `npm run build` (or `npm run dev`). Delete a stale `public/hot` file if no dev server is running |
| Uploaded images are 404 | Run `php artisan storage:link` |
| `Constant PDO::MYSQL_ATTR_SSL_CA is deprecated` | Already handled in `config/database.php` for PHP 8.5; re-copy that file if you replaced it |
| Login says the account is deactivated | An administrator set the user inactive (Administration → Users) |
| A role cannot see a new menu item | Grant the permission in Administration → Roles & Permissions |
| Dashboard figures look empty | Revenue counts only orders that are confirmed with stock allocated; procurement counts only inspected deliveries |

## Known limitations

- **No payment gateway.** Customer and supplier payments are manual records (cash, bank, M-Pesa, cheque, other). The M-Pesa Daraja integration is a planned extension point, not a connected service.
- **No live GPS or map tracking.** Delivery status is entered by the logistics team; routes carry manually entered distance and travel time.
- **No outbound email or SMS.** Notifications are in-app only; the contact form and farmer sign-up store records rather than send mail.
- **Intelligence is rule-based.** Forecasts, recommendations and supplier scores come from simple formulas over your own history; they are estimates, and no external AI provider is connected.
- **Gross profit is not net profit.** It is goods revenue less the purchase cost of stock sold; operating expenses are reported separately.
- **Single currency (KES) and single language (English).**
- **Queues are configured but unused.** All work currently runs synchronously.
- **Three commodity photos are missing** (see Media and images above).
- **Sharing images are generated once, not per page.** There is no server-side image library, so product pages share the product photo and other pages use the bundled or uploaded images.
- **AI suggestions depend on Google's service.** Gemini is sometimes busy; the app retries, falls back to the next model, then asks you to try again. Tests never call the real API.
- **No cookie-consent banner.** Google Analytics is off by default; if you enable it, publish a privacy notice.
