<?php

namespace App\Providers;

use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Google\BusinessProfile\GoogleBusinessProfileHttpClient;
use App\Google\BusinessProfile\NullGoogleBusinessProfileClient;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Organization;
use App\Models\Promotion;
use App\Models\ReactivationCampaign;
use App\Models\Venue;
use App\Models\VenueReview;
use App\Notifications\Contracts\WebPushGateway;
use App\Notifications\NullWebPushGateway;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaymentProviderRegistry;
use App\Payments\Providers\ManualPaymentProvider;
use App\Payments\Providers\PayMongoPaymentProvider;
use App\Policies\BookingPolicy;
use App\Policies\CourtResourcePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\ReactivationCampaignPolicy;
use App\Policies\VenuePolicy;
use App\Policies\VenueReviewPolicy;
use App\Tenancy\TenantContext;
use App\Visibility\Contracts\BusinessProfileGateway;
use App\Visibility\Contracts\PlacesProvider;
use App\Visibility\NullPlacesProvider;
use App\Visibility\StoredBusinessProfileGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
        $this->app->singleton(WebPushGateway::class, NullWebPushGateway::class);
        // Google remains optional. Venue onboarding and booking never depend
        // on either provider boundary being configured or reachable.
        $this->app->singleton(PlacesProvider::class, NullPlacesProvider::class);
        $this->app->singleton(GoogleBusinessProfileClient::class, function () {
            $configured = (bool) config('google.business_profile.enabled')
                && filled(config('google.business_profile.client_id'))
                && filled(config('google.business_profile.client_secret'))
                && filled(config('google.business_profile.redirect_uri'));

            return $configured
                ? new GoogleBusinessProfileHttpClient
                : new NullGoogleBusinessProfileClient;
        });
        $this->app->singleton(BusinessProfileGateway::class, StoredBusinessProfileGateway::class);
        $this->app->singleton(ManualPaymentProvider::class);
        $this->app->singleton(PayMongoPaymentProvider::class);
        $this->app->singleton(PaymentProviderRegistry::class, function ($app) {
            $providers = [$app->make(ManualPaymentProvider::class)];
            $defaultProvider = (string) config('payments.default', 'manual');
            $payMongoEnabled = (bool) config('payments.providers.paymongo.enabled', false);

            if ($payMongoEnabled || $defaultProvider === 'paymongo') {
                $providers[] = $app->make(PayMongoPaymentProvider::class);
            }

            return new PaymentProviderRegistry($providers);
        });
        $this->app->bind(PaymentProvider::class, fn ($app) => $app->make(PaymentProviderRegistry::class)->default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', AppleProvider::class);
        });

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(ReactivationCampaign::class, ReactivationCampaignPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
        Gate::policy(CourtResource::class, CourtResourcePolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(VenueReview::class, VenueReviewPolicy::class);
        Gate::define('access-platform', fn ($user) => $user->is_platform_admin);

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
        RateLimiter::for('social-login', fn (Request $request) => Limit::perMinute(20)
            ->by($request->ip()));
        RateLimiter::for('google-business-profile', fn (Request $request) => Limit::perHour(12)
            ->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));

        RateLimiter::for('player-booking', fn (Request $request) => Limit::perMinute(10)
            ->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(120)
            ->by($request->route('provider').'|'.$request->ip()));
        RateLimiter::for('marketplace', fn (Request $request) => Limit::perMinute(180)
            ->by($request->ip()));
        RateLimiter::for('directory-report', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip()));
        RateLimiter::for('directory-claim', fn (Request $request) => Limit::perHour(3)
            ->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('directory-claim-proof', fn (Request $request) => [
            Limit::perMinute(5)->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()),
            Limit::perDay(20)->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()),
        ]);
        RateLimiter::for('health', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip()));
        RateLimiter::for('authenticated', fn (Request $request) => Limit::perMinute(240)
            ->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));
    }
}
