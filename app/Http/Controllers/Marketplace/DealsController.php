<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Promotions\PromotionMarketplace;
use App\Promotions\PromotionTracker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealsController extends Controller
{
    public function __invoke(
        Request $request,
        PromotionMarketplace $promotionMarketplace,
        PromotionTracker $tracker,
        MarketplaceQuery $marketplace,
    ): View {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::exists('venues', 'city_slug')],
        ]);
        $promotions = $promotionMarketplace->deals($validated['city'] ?? null);
        $tracker->recordImpressions($request, $promotions);
        $canonical = route('marketplace.deals');

        return view('marketplace.deals', [
            'promotions' => $promotions,
            'cities' => $marketplace->cities(),
            'selectedCity' => $validated['city'] ?? null,
            'seo' => [
                'title' => 'Court promotions and deals',
                'description' => 'Browse current promotions and real court deals from published sports venues.',
                'canonical' => $canonical,
                'robots' => $request->query() || $promotions->isEmpty() ? 'noindex,follow' : 'index,follow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }
}
