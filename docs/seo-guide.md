# SEO and site content guide

This guide explains what the website already does for search engines and social media, how to manage it in **Administration → SEO & Site Content**, and what to do when you go live. It is written for administrators and the *Content & SEO Manager* role (permission `seo.manage`).

## What is built in

Everything below happens automatically on the public website. You do not need to add tags by hand.

| Area | What the site does |
|---|---|
| **Titles and descriptions** | Every public page has a unique `<title>` and meta description, with sensible defaults and your overrides. Titles are completed with your brand using a template (default `%s \| Village City`). |
| **Canonical URLs** | Each page declares its canonical address, so duplicate URLs (search, pagination, tracking parameters) do not compete. Paginated pages canonicalise to themselves; internal search results are `noindex`. |
| **Open Graph and X cards** | `og:title`, `og:description`, `og:image` (1200×630 with width, height and alt), `og:url`, `og:type`, `og:site_name`, `og:locale`, plus `twitter:card=summary_large_image` and product price/availability tags. Previews on WhatsApp, Facebook, LinkedIn, Slack and X are branded. |
| **Sharing images** | Seven branded 1200×630 images are bundled (home, products, about, sell, contact, FAQ, default). Products use their own photo. You can upload your own for the site default and for each page. |
| **Structured data (JSON-LD)** | *Organization* (name, logo, contact point, address, social profiles), *WebSite* with a sitelinks search box, *WebPage* types (About, Contact, Collection, Item), *BreadcrumbList*, *Product* with price, currency (KES) and availability, *ItemList* for catalogue pages, and *FAQPage* for the FAQ page. |
| **Sitemap** | `/sitemap.xml` lists every public page, category and product with last-modified dates, change frequency, priority and an **image sitemap** entry per URL. Pages set to noindex and inactive products are left out; the FAQ page appears once at least one FAQ is published. |
| **robots.txt** | `/robots.txt` is generated: it allows the public site, blocks the staff/customer app, login pages, Livewire endpoints and private uploads (inspection photos, proof of delivery), and links to the sitemap. A single switch can block the whole site (for staging). |
| **Keeping the app out of search** | Every page outside the public website sends `X-Robots-Tag: noindex, nofollow, noarchive` and a robots meta tag. Error pages are noindex. |
| **Clean URLs** | `/produce`, `/produce/category/<slug>`, `/produce/<product-slug>`, `/faq`, `/sell-with-us`, `/about`, `/contact`. Old `?category=<id>` links 301-redirect to the category page. |
| **Semantic HTML and accessibility** | One `<h1>` per page, ordered headings, landmarks (`header`, `nav`, `main`, `footer`), skip link, breadcrumbs, descriptive `alt` text, `lang="en-KE"`, labelled navigation. |
| **Performance** | Hero image is preloaded with high priority; below-the-fold images are lazy-loaded and async-decoded; fonts use `display=swap`; assets are fingerprinted by Vite. |
| **Icons and install metadata** | `favicon.ico`, SVG favicon, PNG icons (16–512), Apple touch icon, a maskable icon and `site.webmanifest`, with theme colour. |
| **Verification and analytics** | Google Search Console and Bing Webmaster verification tags, and optional Google Analytics 4. Signed-in staff are never tracked. |
| **Internal linking** | Footer links to every category; product pages link to related products; breadcrumbs everywhere; FAQ link in the header and footer. |

## The SEO & Site Content page

Open **Administration → SEO & Site Content**. Six tabs:

### 1. Overview
A health score out of 100 with a prioritised checklist (red = fix now, amber = recommended, blue = optional, green = done). Each item has a **Fix** button that jumps to the right tab. It checks indexing, canonical URL, HTTPS, the default sharing image, Search Console verification, analytics, page title/description lengths and duplicates, product descriptions and photos, category introductions, FAQ count, social profiles, business contact details, and the sitemap. Handy links open the sitemap, robots.txt and external testing tools.

### 2. Global SEO
Applies to the whole site:

- **Brand & defaults:** site name, title template, default meta description, default sharing image, logo, locale.
- **Indexing:** the **Allow search engines to index** switch (turn it **off** on test sites), the **Canonical site URL** (your live address, e.g. `https://www.villagecity.co.ke`), and optional extra robots.txt lines.
- **Search consoles & analytics:** Google and Bing verification codes, GA4 measurement ID (`G-XXXXXXXXXX`).
- **Organization:** legal name, year founded, area served. (Phone, email and address come from Administration → Settings.)
- **Social profiles:** X handle and Facebook, X, Instagram, LinkedIn, YouTube URLs. They appear in the footer and in structured data.

### 3. Pages
Pick a page (Home, Products, About, Sell with us, Contact, FAQ) and edit its **title**, **meta description**, optional **social title/description**, **sharing image** and a **noindex** switch. A live **Google result preview** and **social card preview** update as you type, and character counters turn amber outside the recommended range (title 30–60, description 70–160).

### 4. Site content
Edit the website's words without touching code: the home page hero (headline, text, button labels, hero image and its alt text), "how it works" steps, counties text, farmers section, call to action; the About page (mission, vision, what we do, values); intro text for Products, Sell with us, Contact and FAQ; and the site-wide **announcement bar** and footer description.
Formats: *list* fields take one item per line; *pairs* take `Title | description` per line. Clearing a field restores the default text. All text is escaped, so markup cannot be injected.

### 5. FAQs
Add, edit, reorder (▲▼), publish/unpublish and delete questions. Tick **Show on the home page** for the best ones. Published FAQs appear on `/faq` with FAQ structured data. Eight starter FAQs are seeded.

### 6. Products & categories
Per product: **URL slug**, **meta title** and **meta description** (with a character counter) and a filter for products that still lack a meta description. Per category: slug, meta title/description and an **introduction text** shown at the top of the category page — the main content search engines read there. Product descriptions and photos are edited on the Products page.

> Changing a product's slug changes its public URL. Do this only for new products. Slugs are generated automatically, stay stable when a product is renamed, and must be unique.

## AI assistant (Google Gemini)

With a Gemini API key configured, the SEO page can draft copy for you. Look for the **✨ sparkle buttons**. **Every suggestion is a draft:** it fills the form, and nothing changes on the live site until you review it and click **Save**.

| Where | What it drafts |
|---|---|
| **Pages** | Title, meta description, social title and social description for the page — guided by optional *target keywords* — plus **Keyword ideas** you can click to add to the keyword box |
| **Global SEO** | The site-wide default meta description |
| **Site content** | **Improve with AI** on any text, list or steps field (clearer, more specific wording; same meaning) |
| **FAQs** | **Generate FAQs with AI**: six new questions and answers you tick and add as **unpublished drafts**, so you read each answer before it goes live |
| **Products & categories** | Meta title and description for one product, or **all products on the page that lack one in a single request**; optionally a product description too. Category meta text and the category introduction |

Tips:
- Add **target keywords** (for example `wholesale vegetables kenya`) to steer the wording; the assistant works them in only where they fit.
- Titles are fitted to leave room for the brand suffix, descriptions to about 155 characters. You can still edit anything.
- The assistant is told the **verified facts** about how Village City works and not to invent prices, certifications, statistics or guarantees. **Still check every draft** — especially product descriptions and FAQ answers — before saving or publishing.
- There is an hourly limit per user (default 60 requests) to protect the API quota. If Gemini is busy you will see a friendly message; simply try again.
- **Overview → Test connection** confirms the key works and shows which model answered.

**What is sent to Google:** only public catalogue and website facts — product names, categories, units, storage notes, existing descriptions, page text and FAQ questions — plus the instructions. **Never** sent: buying prices or costs, customers, farmers, orders, staff details, contact details or financial data. Every AI use is recorded in the audit log (the action, not the content).

**Setup:** add the key to the server's `.env` file (never in code or the admin screens) — see [setup.md](setup.md#ai-assistant-google-gemini).

## Writing guidelines

- **Title:** 30–60 characters, the main keyword first, written for people (`Tomatoes – Wholesale Prices in Kenya`). One unique title per page.
- **Meta description:** 70–160 characters; say what the visitor gets and invite the click. It is not a ranking factor, but it decides who clicks.
- **Product descriptions:** at least 150 characters of original copy — variety, grade, pack size, shelf life, how it is sourced. This is the page's main content.
- **Category introductions:** one short paragraph describing the range and who it is for.
- **Photos:** real, clear photos with the product in focus. Alt text is set automatically from the product name.
- **FAQs:** use the exact questions buyers ask; keep answers direct and honest.
- **Sharing image:** 1200×630 px, key content in the centre, under 300 KB.

## Go-live checklist

1. **Set the live address:** Global SEO → *Canonical site URL* = `https://www.yourdomain` and `APP_URL` in `.env` to the same, with `APP_ENV=production`, `APP_DEBUG=false`.
2. **Switch indexing on** (Global SEO → *Allow search engines to index*).
3. **HTTPS and one hostname:** install a certificate; redirect `http→https` and `non-www→www` (or the reverse) at the web server. Search engines treat each variant as a different site.
4. **Google Search Console:** add the property, paste the verification code in Global SEO, then **submit `https://yourdomain/sitemap.xml`**. Repeat in Bing Webmaster Tools.
5. **Business details:** fill in phone, email and address in Administration → Settings, and link your social profiles.
6. **Content:** replace anything that is still sample text; publish at least five FAQs; give every product a real description and photo; add category introductions.
7. **Check the Overview tab:** aim for 80+ with no red items.
8. **Test:** run the home page, a product page and the FAQ through Google's *Rich Results Test*, share a link in the Facebook *Sharing Debugger* (it refreshes the cache), and run *PageSpeed Insights*.
9. **Server performance:** enable gzip/Brotli and HTTP/2, long-lived caching for `/build/*` and `/images/*`, and run `php artisan optimize`.

## Verifying it works

- View source on any public page and look for the `<title>`, `canonical`, `og:` and `application/ld+json` blocks.
- Open `/sitemap.xml` and `/robots.txt`.
- Check a private page (for example `/login`) in your browser's network tab: it carries `X-Robots-Tag: noindex`.
- In Search Console, watch *Pages → Indexing*, *Sitemaps*, and *Enhancements* (Breadcrumbs, FAQ, Products).

## Limitations and notes

- **Sharing images are static.** The bundled 1200×630 images and icons were generated once (the server has no image library to create them on demand). Products share their own photo; upload page-specific images in the admin if you want different ones. To regenerate the bundled set after a rebrand, replace the files in `public/images/og/` and `public/icons/`.
- **AI suggestions are drafts, not facts.** Language models can be wrong or generic. Always review, and keep claims you can back up.
- **Rankings are not guaranteed.** The site provides the technical foundation and tools; ranking depends on content quality, links, speed, and time. Local SEO benefits from a Google Business Profile, which is set up outside this system.
- **One language.** The site is English (`en-KE`). Adding Swahili would need translated pages and `hreflang` alternates.
- **Analytics and consent.** GA4 is optional and off by default. If you enable it, publish a privacy notice that describes it (Kenya's Data Protection Act applies); a consent banner is not included.
- **Prices in structured data are indicative.** They come from the catalogue selling price; availability is "in stock" when saleable stock exists and "back-order" otherwise. No stock quantities or costs are ever published.
- **Uploaded files are public.** Files in `storage/app/public` are web-accessible by URL; robots.txt blocks the private folders from crawlers, but treat them as public links.
