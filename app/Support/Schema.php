<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * schema.org JSON-LD builders. Each method returns a plain array ready for json_encode().
 */
class Schema
{
    public static function organization(): array
    {
        $sameAs = array_values(array_filter(array_map(
            fn ($k) => Seo::setting($k),
            ['social_facebook', 'social_x', 'social_instagram', 'social_linkedin', 'social_youtube'],
        )));

        $phone = Setting::get('company_phone');
        $email = Setting::get('company_email');
        $address = Setting::get('company_address');
        $logo = Seo::mediaUrl(Seo::setting('logo'));

        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => Seo::url('/').'#organization',
            'name' => Seo::siteName(),
            'url' => Seo::url('/'),
            'description' => Seo::setting('default_description'),
            'areaServed' => Seo::setting('org_area_served') ?: 'Kenya',
            'slogan' => 'From the farm to the city, within 24 hours.',
        ];

        if ($legal = Seo::setting('org_legal_name')) {
            $org['legalName'] = $legal;
        }

        if ($year = Seo::setting('org_founding_year')) {
            $org['foundingDate'] = $year;
        }

        if ($logo) {
            $org['logo'] = ['@type' => 'ImageObject', 'url' => $logo, 'width' => 512, 'height' => 512];
            $org['image'] = $logo;
        }

        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }

        if ($phone || $email) {
            $org['contactPoint'] = array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => $phone ?: null,
                'email' => $email ?: null,
                'areaServed' => 'KE',
                'availableLanguage' => ['English', 'Swahili'],
            ]);
        }

        if ($address) {
            $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address, 'addressCountry' => 'KE'];
        }

        return $org;
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => Seo::url('/').'#website',
            'url' => Seo::url('/'),
            'name' => Seo::siteName(),
            'inLanguage' => str_replace('_', '-', Seo::setting('locale') ?: 'en-KE'),
            'publisher' => ['@id' => Seo::url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => Seo::url('produce').'?search={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function webPage(string $type, string $url, string $name, string $description, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'isPartOf' => ['@id' => Seo::url('/').'#website'],
            'about' => ['@id' => Seo::url('/').'#organization'],
            'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image] : null,
            'inLanguage' => str_replace('_', '-', Seo::setting('locale') ?: 'en-KE'),
        ]);
    }

    /** @param  list<array{0:string, 1:?string}>  $items  [label, url|null] (last item may have null url) */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1] ?? null,
            ]), $items, array_keys($items)),
        ];
    }

    /** @param  Collection<int, \App\Models\Faq>  $faqs */
    public static function faqPage(Collection $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => nl2br(e($f->answer))],
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public static function itemList(Collection $products, string $name): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $products->values()->map(fn (Product $p, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => route('site.product', $p->slug),
                'name' => $p->name,
            ])->all(),
        ];
    }

    public static function product(Product $product, bool $inStock, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => route('site.product', $product->slug).'#product',
            'name' => $product->name,
            'sku' => $product->code,
            'description' => Seo::excerpt($product->description ?: $product->name.' supplied by '.Seo::siteName(), 500),
            'image' => $image ? [$image] : null,
            'category' => $product->category?->name,
            'brand' => ['@type' => 'Brand', 'name' => Seo::siteName()],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('site.product', $product->slug),
                'priceCurrency' => 'KES',
                'price' => number_format((float) $product->selling_price, 2, '.', ''),
                'priceValidUntil' => now()->endOfYear()->toDateString(),
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => Seo::url('/').'#organization'],
                'eligibleQuantity' => ['@type' => 'QuantitativeValue', 'minValue' => (float) $product->min_order_qty, 'unitText' => $product->unit],
            ],
        ]);
    }

    /** Render nodes as a safe JSON-LD script body (</script> sequences are escaped). */
    public static function encode(array $node): string
    {
        return json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
    }
}
