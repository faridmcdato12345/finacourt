<?php

namespace App\Analytics;

use App\Enums\AcquisitionSource;
use App\Enums\SalesPartnerStatus;
use App\Models\Promotion;
use App\Models\ReactivationCampaign;
use App\Models\SalesPartnerProfile;
use App\Models\VisibilityLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrafficAttribution
{
    private const SESSION_KEY = 'analytics.acquisition_context';

    private const TRUSTED_PARTNER_KEY = 'sales_partner.trusted_referral';

    /**
     * @return array{
     *   source: AcquisitionSource, detail: string|null, medium: string|null,
     *   campaign: string|null, referral_code: string|null, partner_code: string|null,
     *   landing_path: string|null, referrer_host: string|null, seen_at: string,
     *   first_touch: array<string, mixed>, last_touch: array<string, mixed>
     * }
     */
    public function current(Request $request, ?Promotion $promotion = null): array
    {
        $now = CarbonImmutable::now('UTC');
        $stored = $request->session()->get(self::SESSION_KEY);

        if (! is_array($stored)) {
            $stored = $this->legacyContext($request, $now);
        }

        if (! is_array($stored) || $this->expired($stored, $now)) {
            $stored = null;
        }

        $signal = $this->signal($request, $promotion, $now);

        if ($stored === null) {
            $signal ??= $this->touch($request, AcquisitionSource::Direct, $now);
            $stored = ['first_touch' => $signal, 'last_touch' => $signal];
        } elseif ($signal !== null) {
            $stored['last_touch'] = $signal;
        }

        $stored = $this->normalizeContext($stored, $request, $now);
        $request->session()->put(self::SESSION_KEY, $stored);
        $request->session()->forget('analytics.attribution');
        $first = $this->hydrateTouch($stored['first_touch']);
        $last = $this->hydrateTouch($stored['last_touch']);

        return [
            ...$last,
            'detail' => $this->detail($last),
            'reactivation_campaign_token' => $stored['reactivation_campaign_token'] ?? null,
            'first_touch' => $first,
            'last_touch' => $last,
        ];
    }

    /**
     * Record a server-validated comeback-campaign click. Browser query strings
     * cannot create this trusted source; only an owned recipient link can.
     *
     * @return array<string, mixed>
     */
    public function reactivation(Request $request, ReactivationCampaign $campaign): array
    {
        $now = CarbonImmutable::now('UTC');
        $stored = $request->session()->get(self::SESSION_KEY);

        if (! is_array($stored) || $this->expired($stored, $now)) {
            $stored = null;
        }

        $touch = $this->touch(
            $request,
            AcquisitionSource::CustomerReactivation,
            $now,
            medium: 'in_app',
            campaign: $campaign->campaign_token,
        );
        $stored = [
            'first_touch' => $stored['first_touch'] ?? $touch,
            'last_touch' => $touch,
            'reactivation_campaign_token' => $campaign->campaign_token,
        ];
        $stored = $this->normalizeContext($stored, $request, $now);
        $request->session()->put(self::SESSION_KEY, $stored);
        $request->session()->forget('analytics.attribution');
        $first = $this->hydrateTouch($stored['first_touch']);
        $last = $this->hydrateTouch($stored['last_touch']);

        return [
            ...$last,
            'detail' => $this->detail($last),
            'reactivation_campaign_token' => $stored['reactivation_campaign_token'],
            'first_touch' => $first,
            'last_touch' => $last,
        ];
    }

    /**
     * Record a server-resolved visibility link. Unlike the legacy informational
     * query marker, this campaign token is issued and tenant-bound by the app.
     *
     * @return array<string, mixed>
     */
    public function visibilityLink(Request $request, VisibilityLink $link): array
    {
        $now = CarbonImmutable::now('UTC');
        $stored = $request->session()->get(self::SESSION_KEY);

        if (! is_array($stored) || $this->expired($stored, $now)) {
            $stored = null;
        }

        $touch = $this->touch(
            $request,
            AcquisitionSource::QrCode,
            $now,
            medium: 'qr',
            campaign: $link->token,
        );
        $stored = [
            'first_touch' => $stored['first_touch'] ?? $touch,
            'last_touch' => $touch,
            'reactivation_campaign_token' => null,
        ];
        $stored = $this->normalizeContext($stored, $request, $now);
        $request->session()->put(self::SESSION_KEY, $stored);
        $request->session()->forget('analytics.attribution');
        $first = $this->hydrateTouch($stored['first_touch']);
        $last = $this->hydrateTouch($stored['last_touch']);

        return [
            ...$last,
            'detail' => $this->detail($last),
            'reactivation_campaign_token' => null,
            'first_touch' => $first,
            'last_touch' => $last,
        ];
    }

    /**
     * Record a server-resolved partner referral. Browser `partner` query
     * markers remain informational and never create this trusted evidence.
     *
     * @return array<string, mixed>
     */
    public function salesPartner(Request $request, SalesPartnerProfile $partner): array
    {
        abort_unless($partner->isActive(), 404);
        $now = CarbonImmutable::now('UTC');
        $stored = $request->session()->get(self::SESSION_KEY);

        if (! is_array($stored) || $this->expired($stored, $now)) {
            $stored = null;
        }

        $touch = $this->touch(
            $request,
            AcquisitionSource::SalesPartner,
            $now,
            medium: 'referral',
            campaign: $partner->public_id,
            partnerCode: $partner->referral_code,
        );
        $stored = [
            'first_touch' => $stored['first_touch'] ?? $touch,
            'last_touch' => $touch,
            'reactivation_campaign_token' => null,
        ];
        $request->session()->put(self::SESSION_KEY, $this->normalizeContext($stored, $request, $now));
        $request->session()->put(self::TRUSTED_PARTNER_KEY, [
            'profile_id' => $partner->getKey(),
            'referral_code' => $partner->referral_code,
            'seen_at' => $now->toIso8601String(),
        ]);

        return $this->current($request);
    }

    public function trustedSalesPartner(Request $request): ?SalesPartnerProfile
    {
        $trusted = $request->session()->get(self::TRUSTED_PARTNER_KEY);

        if (! is_array($trusted) || ! is_numeric($trusted['profile_id'] ?? null)) {
            return null;
        }

        try {
            $seenAt = CarbonImmutable::parse((string) ($trusted['seen_at'] ?? ''), 'UTC');
        } catch (\Throwable) {
            return null;
        }

        if ($seenAt->lt(CarbonImmutable::now('UTC')->subDays((int) config('sales_partners.referral_lookback_days')))) {
            return null;
        }

        return SalesPartnerProfile::query()
            ->whereKey((int) $trusted['profile_id'])
            ->where('status', SalesPartnerStatus::Active)
            ->where('referral_code', (string) ($trusted['referral_code'] ?? ''))
            ->first();
    }

    /** @return array<string, string|null>|null */
    private function signal(Request $request, ?Promotion $promotion, CarbonImmutable $now): ?array
    {
        if ($promotion !== null) {
            return $this->touch(
                $request,
                AcquisitionSource::MarketplacePromotion,
                $now,
                medium: 'promotion',
                campaign: $promotion->campaign_token,
            );
        }

        $qr = $this->token($request->query('qr') ?? $request->query('qr_code'), 80);

        if ($qr !== null) {
            return $this->touch($request, AcquisitionSource::QrCode, $now, medium: 'qr', campaign: $qr);
        }

        $partner = $this->token($request->query('partner') ?? $request->query('partner_code'), 80);

        if ($partner !== null) {
            return $this->touch(
                $request,
                AcquisitionSource::SalesPartner,
                $now,
                medium: 'referral',
                partnerCode: $partner,
            );
        }

        $referral = $this->token($request->query('ref') ?? $request->query('referral_code'), 80);

        if ($referral !== null) {
            return $this->touch(
                $request,
                AcquisitionSource::Referral,
                $now,
                medium: 'referral',
                referralCode: $referral,
            );
        }

        if ($request->query->has('acq_source')) {
            $rawSource = $this->token($request->query('acq_source'), 40);
            $source = $rawSource === null
                ? AcquisitionSource::Unknown
                : AcquisitionSource::tryFrom(Str::lower($rawSource)) ?? AcquisitionSource::Unknown;

            // Promotion credit requires a Promotion resolved by the server.
            // A generic browser marker cannot impersonate that trusted path.
            if (in_array($source, [
                AcquisitionSource::MarketplacePromotion,
                AcquisitionSource::CustomerReactivation,
            ], true)) {
                $source = AcquisitionSource::Unknown;
            }

            return $this->touch(
                $request,
                $source,
                $now,
                medium: $this->token($request->query('utm_medium'), 64),
                campaign: $this->text($request->query('utm_campaign'), 120),
            );
        }

        $utmSource = $this->token($request->query('utm_source'), 80);

        if ($utmSource !== null) {
            $medium = $this->token($request->query('utm_medium'), 64);

            return $this->touch(
                $request,
                $this->mapSource($utmSource, $medium),
                $now,
                medium: $medium,
                campaign: $this->text($request->query('utm_campaign'), 120),
            );
        }

        $referer = (string) $request->headers->get('referer');
        $referrerHost = parse_url($referer, PHP_URL_HOST);

        if (! is_string($referrerHost) || $referrerHost === '') {
            return null;
        }

        $referrerHost = Str::lower($referrerHost);

        if (hash_equals(Str::lower($request->getHost()), $referrerHost)) {
            return null;
        }

        $source = $this->sourceFromReferrer($referrerHost, (string) parse_url($referer, PHP_URL_PATH));

        return $this->touch(
            $request,
            $source,
            $now,
            medium: 'referral',
            referrerHost: $this->text($referrerHost, 160),
        );
    }

    private function mapSource(string $source, ?string $medium): AcquisitionSource
    {
        $source = Str::lower($source);
        $medium = Str::lower((string) $medium);

        return match (true) {
            in_array($source, ['marketplace', 'court', 'court_marketplace'], true) => AcquisitionSource::MarketplaceOrganic,
            in_array($source, ['google_maps', 'googlemaps', 'maps'], true),
            $source === 'google' && in_array($medium, ['maps', 'local', 'business-profile'], true) => AcquisitionSource::GoogleMaps,
            $source === 'google' && ! in_array($medium, ['cpc', 'ppc', 'paid', 'display'], true) => AcquisitionSource::GoogleOrganic,
            in_array($source, ['facebook', 'fb'], true) => AcquisitionSource::Facebook,
            in_array($source, ['instagram', 'ig'], true) => AcquisitionSource::Instagram,
            $source === 'tiktok' => AcquisitionSource::TikTok,
            in_array($source, ['qr', 'qr_code'], true) => AcquisitionSource::QrCode,
            in_array($source, ['referral', 'invite'], true) => AcquisitionSource::Referral,
            in_array($source, ['sales_partner', 'partner'], true) => AcquisitionSource::SalesPartner,
            $source === 'direct' => AcquisitionSource::Direct,
            default => AcquisitionSource::Unknown,
        };
    }

    /** @return array{first_touch: array<string, mixed>, last_touch: array<string, mixed>}|null */
    private function legacyContext(Request $request, CarbonImmutable $now): ?array
    {
        $legacy = $request->session()->get('analytics.attribution');

        if (! is_array($legacy) || ! isset($legacy['source'])) {
            return null;
        }

        $legacySource = Str::lower((string) $legacy['source']);
        $detail = $this->text($legacy['detail'] ?? null, 160);
        $parts = $detail === null ? [] : array_map('trim', explode('/', $detail, 2));
        $source = match ($legacySource) {
            'promotion' => AcquisitionSource::MarketplacePromotion,
            'campaign' => $this->mapSource($parts[0] ?? 'unknown', 'legacy'),
            'referral' => AcquisitionSource::Referral,
            'direct' => AcquisitionSource::Direct,
            default => AcquisitionSource::tryFrom($legacySource) ?? AcquisitionSource::Unknown,
        };
        $touch = $this->touch(
            $request,
            $source,
            $now,
            medium: 'legacy',
            campaign: in_array($legacySource, ['promotion', 'campaign'], true)
                ? ($parts[1] ?? $detail)
                : null,
            referrerHost: $legacySource === 'referral' ? $detail : null,
        );

        return ['first_touch' => $touch, 'last_touch' => $touch];
    }

    private function sourceFromReferrer(string $host, string $path): AcquisitionSource
    {
        return match (true) {
            Str::contains($host, 'google.') && (Str::contains($host, 'maps.') || Str::startsWith($path, '/maps')) => AcquisitionSource::GoogleMaps,
            Str::contains($host, 'google.') => AcquisitionSource::GoogleOrganic,
            Str::contains($host, 'facebook.') || Str::endsWith($host, 'fb.com') => AcquisitionSource::Facebook,
            Str::contains($host, 'instagram.') => AcquisitionSource::Instagram,
            Str::contains($host, 'tiktok.') => AcquisitionSource::TikTok,
            default => AcquisitionSource::Referral,
        };
    }

    /** @return array<string, string|null> */
    private function touch(
        Request $request,
        AcquisitionSource $source,
        CarbonImmutable $now,
        ?string $medium = null,
        ?string $campaign = null,
        ?string $referralCode = null,
        ?string $partnerCode = null,
        ?string $referrerHost = null,
    ): array {
        return [
            'source' => $source->value,
            'medium' => $medium,
            'campaign' => $campaign,
            'referral_code' => $referralCode,
            'partner_code' => $partnerCode,
            'landing_path' => $this->text($request->getPathInfo(), 255),
            'referrer_host' => $referrerHost,
            'seen_at' => $now->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function expired(array $context, CarbonImmutable $now): bool
    {
        $seenAt = data_get($context, 'last_touch.seen_at');

        if (! is_string($seenAt)) {
            return true;
        }

        try {
            $lastSeen = CarbonImmutable::parse($seenAt, 'UTC');
        } catch (\Throwable) {
            return true;
        }

        return $lastSeen->lessThan($now->subDays((int) config('attribution.lookback_days', 30)));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{first_touch: array<string, mixed>, last_touch: array<string, mixed>, reactivation_campaign_token: string|null}
     */
    private function normalizeContext(array $context, Request $request, CarbonImmutable $now): array
    {
        $fallback = $this->touch($request, AcquisitionSource::Direct, $now);

        return [
            'first_touch' => $this->normalizeTouch($context['first_touch'] ?? null, $fallback),
            'last_touch' => $this->normalizeTouch($context['last_touch'] ?? null, $fallback),
            'reactivation_campaign_token' => $this->token($context['reactivation_campaign_token'] ?? null, 40),
        ];
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function normalizeTouch(mixed $touch, array $fallback): array
    {
        if (! is_array($touch)) {
            return $fallback;
        }

        $source = AcquisitionSource::tryFrom((string) ($touch['source'] ?? '')) ?? AcquisitionSource::Unknown;

        return [
            'source' => $source->value,
            'medium' => $this->token($touch['medium'] ?? null, 64),
            'campaign' => $this->text($touch['campaign'] ?? null, 120),
            'referral_code' => $this->token($touch['referral_code'] ?? null, 80),
            'partner_code' => $this->token($touch['partner_code'] ?? null, 80),
            'landing_path' => $this->text($touch['landing_path'] ?? null, 255),
            'referrer_host' => $this->text($touch['referrer_host'] ?? null, 160),
            'seen_at' => is_string($touch['seen_at'] ?? null) ? $touch['seen_at'] : $fallback['seen_at'],
        ];
    }

    /** @param array<string, mixed> $touch
     * @return array<string, mixed>
     */
    private function hydrateTouch(array $touch): array
    {
        return [
            ...$touch,
            'source' => AcquisitionSource::tryFrom((string) $touch['source']) ?? AcquisitionSource::Unknown,
        ];
    }

    /** @param array<string, mixed> $touch */
    private function detail(array $touch): ?string
    {
        return $touch['campaign']
            ?? $touch['referral_code']
            ?? $touch['partner_code']
            ?? $touch['referrer_host'];
    }

    private function token(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = Str::of((string) $value)
            ->squish()
            ->replaceMatches('/[^\pL\pN._\-]/u', '')
            ->limit($limit, '')
            ->toString();

        return $clean === '' ? null : $clean;
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = Str::of((string) $value)
            ->squish()
            ->replaceMatches('/[^\pL\pN ._\-\/]/u', '')
            ->limit($limit, '')
            ->toString();

        return $clean === '' ? null : $clean;
    }
}
