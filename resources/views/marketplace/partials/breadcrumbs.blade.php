@if ($breadcrumbs)
    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('marketplace.home') }}" class="hover:text-court-700">Home</a></li>
            @foreach ($breadcrumbs as $breadcrumb)
                <li aria-hidden="true">/</li>
                <li>
                    @if ($loop->last)
                        <span aria-current="page" class="font-medium text-slate-700">{{ $breadcrumb['name'] }}</span>
                    @else
                        <a href="{{ $breadcrumb['url'] }}" class="hover:text-court-700">{{ $breadcrumb['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
