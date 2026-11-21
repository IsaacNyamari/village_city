{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
@if (! empty($entry['lastmod']))
        <lastmod>{{ \Illuminate\Support\Carbon::parse($entry['lastmod'])->toAtomString() }}</lastmod>
@endif
        <changefreq>{{ $entry['changefreq'] }}</changefreq>
        <priority>{{ $entry['priority'] }}</priority>
@foreach ($entry['images'] as $image)
        <image:image>
            <image:loc>{{ $image }}</image:loc>
@if (! empty($entry['image_title']))
            <image:title>{{ $entry['image_title'] }}</image:title>
@endif
        </image:image>
@endforeach
    </url>
@endforeach
</urlset>
