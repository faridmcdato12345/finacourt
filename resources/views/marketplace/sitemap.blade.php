<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('marketplace.home') }}</loc></url>
    <url><loc>{{ route('marketplace.for-owners') }}</loc></url>
    <url><loc>{{ route('marketplace.pricing') }}</loc></url>
    <url><loc>{{ route('marketplace.privacy') }}</loc></url>
    <url><loc>{{ route('marketplace.terms') }}</loc></url>
    <url><loc>{{ route('marketplace.courts.index') }}</loc></url>
    <url><loc>{{ route('marketplace.directory.index') }}</loc></url>
    @if ($hasDeals)<url><loc>{{ route('marketplace.deals') }}</loc></url>@endif
    @foreach ($venues as $venue)
        <url><loc>{{ route('marketplace.venues.show', $venue->slug) }}</loc><lastmod>{{ $venue->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
    @foreach ($directoryListings as $listing)
        <url><loc>{{ route('marketplace.directory.show', $listing->slug) }}</loc><lastmod>{{ $listing->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
    @foreach ($cities as $city)
        <url><loc>{{ route('marketplace.courts.city', $city->city_slug) }}</loc></url>
    @endforeach
    @foreach ($combinations as $combination)
        <url><loc>{{ route('marketplace.courts.sport-city', [$combination['sport'], $combination['city']]) }}</loc></url>
    @endforeach
</urlset>
