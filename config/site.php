<?php

/*
|--------------------------------------------------------------------------
| Public website: SEO and editable content
|--------------------------------------------------------------------------
| Everything below can be overridden by an administrator in Administration → SEO & Site Content.
| Values here are the defaults used until something is saved. Overrides live in the `settings` table
| under `seo.*` (global), `seo.page.<page>.*` (per page) and `content.<group>.<key>` (copy).
*/

return [

    'global' => [
        'site_name' => ['label' => 'Site name', 'type' => 'text', 'default' => 'Village City', 'help' => 'Used as the brand in titles, Open Graph and structured data.'],
        'title_template' => ['label' => 'Title template', 'type' => 'text', 'default' => '%s | Village City', 'help' => 'How page titles are completed. %s is replaced by the page title.'],
        'default_description' => ['label' => 'Default meta description', 'type' => 'textarea', 'default' => 'Village City connects Kenyan farmers to wholesalers, supermarkets, restaurants, hotels and institutions with fresh produce delivered within 24 hours.', 'help' => 'Fallback when a page has no description of its own (70–160 characters is ideal).'],
        'default_og_image' => ['label' => 'Default social sharing image (Open Graph)', 'type' => 'image', 'default' => 'images/og/default.jpg', 'help' => 'Shown when a page is shared on WhatsApp, Facebook, LinkedIn, X. Use 1200×630 px (JPG/PNG/WebP).'],
        'logo' => ['label' => 'Logo (for search results / structured data)', 'type' => 'image', 'default' => 'icons/icon-512.png', 'help' => 'Square, at least 112×112 px. Used in the Organization markup.'],
        'locale' => ['label' => 'Locale', 'type' => 'text', 'default' => 'en_KE', 'help' => 'Open Graph locale, e.g. en_KE.'],
        'allow_indexing' => ['label' => 'Allow search engines to index the site', 'type' => 'switch', 'default' => '1', 'help' => 'Turn OFF on test/staging sites. When off, robots.txt blocks everything and pages send "noindex".'],
        'canonical_base' => ['label' => 'Canonical site URL', 'type' => 'url', 'default' => '', 'help' => 'The public address of the live site, e.g. https://www.villagecity.co.ke. Leave blank to use APP_URL. Must be https in production.'],
        'twitter_handle' => ['label' => 'X (Twitter) handle', 'type' => 'text', 'default' => '', 'help' => 'Without the @, e.g. villagecityke.'],
        'google_site_verification' => ['label' => 'Google Search Console verification code', 'type' => 'text', 'default' => '', 'help' => 'The content value of the google-site-verification meta tag.'],
        'bing_site_verification' => ['label' => 'Bing Webmaster verification code', 'type' => 'text', 'default' => '', 'help' => 'The content value of the msvalidate.01 meta tag.'],
        'ga4_id' => ['label' => 'Google Analytics 4 measurement ID', 'type' => 'text', 'default' => '', 'help' => 'Looks like G-XXXXXXXXXX. Leave blank to disable analytics. Signed-in staff are never tracked.'],
        'robots_extra' => ['label' => 'Extra robots.txt rules', 'type' => 'textarea', 'default' => '', 'help' => 'Optional lines appended to robots.txt (one rule per line).'],
        'org_legal_name' => ['label' => 'Legal business name', 'type' => 'text', 'default' => '', 'help' => 'Optional. Shown in structured data.'],
        'org_founding_year' => ['label' => 'Year founded', 'type' => 'text', 'default' => '', 'help' => 'Optional, e.g. 2025.'],
        'org_area_served' => ['label' => 'Area served', 'type' => 'text', 'default' => 'Kenya', 'help' => 'Country or regions you deliver to.'],
        'social_facebook' => ['label' => 'Facebook page URL', 'type' => 'url', 'default' => ''],
        'social_x' => ['label' => 'X (Twitter) profile URL', 'type' => 'url', 'default' => ''],
        'social_instagram' => ['label' => 'Instagram profile URL', 'type' => 'url', 'default' => ''],
        'social_linkedin' => ['label' => 'LinkedIn page URL', 'type' => 'url', 'default' => ''],
        'social_youtube' => ['label' => 'YouTube channel URL', 'type' => 'url', 'default' => ''],
    ],

    /*
     | Public pages that can carry their own SEO. `og` is the bundled default sharing image.
     */
    'pages' => [
        'home' => [
            'label' => 'Home', 'path' => '/', 'schema' => 'WebPage', 'changefreq' => 'weekly', 'priority' => '1.0',
            'title' => 'Village City | Fresh Produce from Kenyan Farms in 24 Hours',
            'description' => 'Fresh vegetables, fruit and cereals sourced directly from Western Kenya farmers and delivered to wholesalers, hotels and supermarkets within 24 hours.',
            'og' => 'images/og/home.jpg',
        ],
        'produce' => [
            'label' => 'Products (catalogue)', 'path' => '/produce', 'schema' => 'CollectionPage', 'changefreq' => 'daily', 'priority' => '0.9',
            'title' => 'Wholesale Fresh Produce & Cereals in Kenya',
            'description' => 'Browse tomatoes, onions, kale, potatoes, maize, beans, rice and more at wholesale prices. Quality-graded produce from Kenyan farmers.',
            'og' => 'images/og/produce.jpg',
        ],
        'about' => [
            'label' => 'About us', 'path' => '/about', 'schema' => 'AboutPage', 'changefreq' => 'monthly', 'priority' => '0.6',
            'title' => 'About Village City – Kenyan Farmers to City Buyers',
            'description' => 'Village City aggregates produce from rural Kenyan farmers and delivers it to urban buyers within 24 hours, paying farmers fairly and transparently.',
            'og' => 'images/og/about.jpg',
        ],
        'sell' => [
            'label' => 'Sell with us (farmers)', 'path' => '/sell-with-us', 'schema' => 'WebPage', 'changefreq' => 'monthly', 'priority' => '0.7',
            'title' => 'Sell Your Harvest – Farmer Registration | Village City',
            'description' => 'Register as a farmer or cooperative and sell your produce at fair prices. We buy on demand, grade at the centre and pay for everything accepted.',
            'og' => 'images/og/sell.jpg',
        ],
        'contact' => [
            'label' => 'Contact', 'path' => '/contact', 'schema' => 'ContactPage', 'changefreq' => 'yearly', 'priority' => '0.5',
            'title' => 'Contact Village City – Wholesale & Partnership Enquiries',
            'description' => 'Talk to Village City about wholesale and institutional supply, farmer registration, partnerships or order support. We reply quickly.',
            'og' => 'images/og/contact.jpg',
        ],
        'faq' => [
            'label' => 'FAQ', 'path' => '/faq', 'schema' => 'WebPage', 'changefreq' => 'monthly', 'priority' => '0.6',
            'title' => 'FAQs: Ordering, Delivery & Payment',
            'description' => 'Answers about ordering produce, minimum quantities, delivery times, payment terms and becoming a Village City supplier.',
            'og' => 'images/og/faq.jpg',
        ],
    ],

    /*
     | Editable copy. Types: text, textarea, list (one item per line), pairs ("Title | text" per line), image, switch, url.
     */
    'content' => [
        'site' => [
            'label' => 'Site-wide',
            'fields' => [
                'announcement_enabled' => ['label' => 'Show announcement bar', 'type' => 'switch', 'default' => '0'],
                'announcement_text' => ['label' => 'Announcement text', 'type' => 'text', 'default' => ''],
                'announcement_link' => ['label' => 'Announcement link (optional)', 'type' => 'url', 'default' => ''],
                'footer_tagline' => ['label' => 'Footer description', 'type' => 'textarea', 'default' => 'Aggregation, distribution and logistics for Kenyan agriculture.'],
            ],
        ],
        'home' => [
            'label' => 'Home page',
            'fields' => [
                'hero_eyebrow' => ['label' => 'Hero eyebrow', 'type' => 'text', 'default' => 'Agricultural aggregation & distribution'],
                'hero_title' => ['label' => 'Hero headline (H1)', 'type' => 'text', 'default' => 'From the farm to the city,'],
                'hero_title_accent' => ['label' => 'Hero headline — highlighted part', 'type' => 'text', 'default' => 'within 24 hours.'],
                'hero_text' => ['label' => 'Hero text', 'type' => 'textarea', 'default' => 'Village City sources fresh produce and cereals directly from farmers across Western Kenya and delivers them to wholesalers, supermarkets, restaurants, hotels, schools and hospitals.'],
                'hero_cta_primary' => ['label' => 'Primary button label', 'type' => 'text', 'default' => 'Browse produce'],
                'hero_cta_secondary' => ['label' => 'Secondary button label', 'type' => 'text', 'default' => 'I am a farmer'],
                'hero_image' => ['label' => 'Hero image', 'type' => 'image', 'default' => 'images/site/hero-farmer.jpg', 'help' => 'Wide photo, at least 1600 px. Keep it under 300 KB for speed.'],
                'hero_image_alt' => ['label' => 'Hero image description (alt text)', 'type' => 'text', 'default' => 'A farmer tending crops in a field'],
                'supply_heading' => ['label' => '“Who we supply” heading', 'type' => 'text', 'default' => 'Reliable supply for every kitchen and shelf'],
                'how_heading' => ['label' => '“How it works” heading', 'type' => 'text', 'default' => 'Demand-driven, from field to doorstep'],
                'how_steps' => ['label' => 'How it works — steps', 'type' => 'pairs', 'default' => "You order | Browse the marketplace, add commodities to your cart and choose your delivery date and location.\nWe source | We buy from farmers and cooperatives at our rural collection centres, based on real customer demand.\nWe inspect | Every delivery is graded. Only produce that passes quality control goes into your order.\nWe deliver | Your order is dispatched and tracked to your door — farm to city within 24 hours.", 'help' => 'One step per line: Title | description'],
                'ranges_heading' => ['label' => '“Our ranges” heading', 'type' => 'text', 'default' => 'What we supply'],
                'counties_heading' => ['label' => 'Sourcing counties heading', 'type' => 'text', 'default' => 'Grown across Western Kenya and beyond'],
                'counties_text' => ['label' => 'Sourcing counties text', 'type' => 'textarea', 'default' => 'Our rural aggregation centres collect from smallholder farmers and cooperatives in these counties — and we are expanding across Kenya and East Africa.'],
                'farmers_heading' => ['label' => 'Farmers section heading', 'type' => 'text', 'default' => 'Sell your harvest at a fair, transparent price'],
                'farmers_points' => ['label' => 'Farmers section bullet points', 'type' => 'list', 'default' => "Register once and be matched to your nearest collection centre\nPaid for what is accepted, with a record of every delivery\nPlan your harvests with buyers who order ahead"],
                'cta_heading' => ['label' => 'Closing call to action — heading', 'type' => 'text', 'default' => 'Ready to source smarter?'],
                'cta_text' => ['label' => 'Closing call to action — text', 'type' => 'textarea', 'default' => 'Open a buyer account in minutes, or talk to us about wholesale and institutional supply agreements.'],
            ],
        ],
        'about' => [
            'label' => 'About page',
            'fields' => [
                'hero_title' => ['label' => 'Page headline (H1)', 'type' => 'text', 'default' => 'Closing the gap between the farm and the city'],
                'hero_text' => ['label' => 'Intro text', 'type' => 'textarea', 'default' => 'Smallholder farmers lose income to long, fragmented supply chains while city buyers struggle with inconsistent quality and supply. Village City connects the two with a single, accountable, technology-enabled chain.'],
                'mission' => ['label' => 'Mission', 'type' => 'textarea', 'default' => 'To deliver fresh, quality agricultural produce from rural farmers to urban consumers and institutions within 24 hours — while paying farmers fairly and transparently.'],
                'vision' => ['label' => 'Vision', 'type' => 'textarea', 'default' => 'The most trusted agricultural aggregation and distribution platform in Kenya, expanding across East Africa.'],
                'what_we_do' => ['label' => 'What we do (one per line)', 'type' => 'list', 'default' => "Register and support farmers, farmer groups and cooperatives\nOperate rural aggregation centres where produce is received and graded\nMaintain batch-level stock records with full traceability\nRun a digital marketplace for wholesale and institutional buyers\nCoordinate transport and track every delivery\nPay farmers and suppliers with a clear settlement trail"],
                'values' => ['label' => 'Values', 'type' => 'pairs', 'default' => "Fairness | Farmers are paid for what is accepted, on a transparent record.\nQuality | Inspection and grading at the source, every time.\nReliability | Dependable supply and delivery commitments."],
            ],
        ],
        'produce' => [
            'label' => 'Products page',
            'fields' => [
                'hero_title' => ['label' => 'Page headline (H1)', 'type' => 'text', 'default' => 'Our produce'],
                'hero_text' => ['label' => 'Intro text', 'type' => 'textarea', 'default' => 'Indicative prices for fresh produce and cereals. Add items to your cart now and check out when you log in.'],
            ],
        ],
        'sell' => [
            'label' => 'Sell with us (farmers)',
            'fields' => [
                'hero_title' => ['label' => 'Page headline (H1)', 'type' => 'text', 'default' => 'Sell your harvest through Village City'],
                'hero_text' => ['label' => 'Intro text', 'type' => 'textarea', 'default' => 'Register once and we will match you to your nearest collection centre. We buy what buyers have ordered, grade it at the centre and pay you for everything accepted.'],
                'steps' => ['label' => 'What happens next (one per line)', 'type' => 'list', 'default' => "Our team reviews your registration\nWe confirm your details by phone\nYou are linked to a collection centre\nWe order from you as demand arrives, and pay for accepted produce"],
            ],
        ],
        'contact' => [
            'label' => 'Contact page',
            'fields' => [
                'hero_title' => ['label' => 'Page headline (H1)', 'type' => 'text', 'default' => 'Contact us'],
                'hero_text' => ['label' => 'Intro text', 'type' => 'textarea', 'default' => 'Wholesale and institutional supply, farmer registration, partnerships or order support — tell us how we can help.'],
            ],
        ],
        'faq' => [
            'label' => 'FAQ page',
            'fields' => [
                'hero_title' => ['label' => 'Page headline (H1)', 'type' => 'text', 'default' => 'Frequently asked questions'],
                'hero_text' => ['label' => 'Intro text', 'type' => 'textarea', 'default' => 'Everything you need to know about ordering, delivery, payment and supplying produce to Village City.'],
            ],
        ],
    ],

    /*
     | Starter FAQs inserted by the seeder when none exist (editable in the admin).
     */
    'starter_faqs' => [
        ['How fast do you deliver?', 'We aim to deliver within 24 hours of the produce leaving the farm. Your delivery date is confirmed when your order is accepted, and you can track progress from your account.', true],
        ['Is there a minimum order?', 'Each product has its own minimum order quantity, shown on the product page and in the cart. Wholesale and institutional buyers can order in bulk.', true],
        ['How do I pay?', 'You can pay by cash, bank transfer or M-Pesa. Customers on credit terms (net 7, 14 or 30 days) are invoiced after delivery. An invoice is issued when your order is confirmed.', true],
        ['What if some produce I ordered is out of stock?', 'We source the balance from farmers after you confirm and tell you if timing changes. You can also choose to proceed with only the quantity we already have.', false],
        ['Can I cancel an order?', 'You can cancel an order yourself while it is still pending confirmation. After that, contact us and we will help.', false],
        ['I am a farmer. How do I start supplying?', 'Register on the Sell with us page. Our team will confirm your details, link you to your nearest collection centre and contact you when buyers need your commodities. You are paid for the quantity accepted after quality grading.', true],
        ['How is produce quality checked?', 'Every delivery is inspected and graded (A, B, C) at the collection centre. Only accepted produce goes into customer orders, and rejected produce is never sold.', false],
        ['Which areas do you deliver to?', 'We deliver across Kenya, with our main sourcing in Western Kenya and Nyanza. Contact us if you are unsure whether we cover your location.', false],
    ],
];
