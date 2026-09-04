<?php

use App\Http\Controllers\AccountPasswordResetController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthenticationController;
use App\Http\Controllers\Auth\SocialOwnerSetupController;
use App\Http\Controllers\Marketplace\DealsController;
use App\Http\Controllers\Marketplace\DiscoveryController;
use App\Http\Controllers\Marketplace\HomeController;
use App\Http\Controllers\Marketplace\LegalController;
use App\Http\Controllers\Marketplace\OwnerAcquisitionController;
use App\Http\Controllers\Marketplace\RobotsController;
use App\Http\Controllers\Marketplace\SalesPartnerQrController;
use App\Http\Controllers\Marketplace\SalesPartnerReferralController;
use App\Http\Controllers\Marketplace\SitemapController;
use App\Http\Controllers\Marketplace\VenueController as MarketplaceVenueController;
use App\Http\Controllers\Marketplace\VenueDirectoryController as MarketplaceVenueDirectoryController;
use App\Http\Controllers\Marketplace\VenueDirectoryReportController;
use App\Http\Controllers\Marketplace\VisibilityLinkController as MarketplaceVisibilityLinkController;
use App\Http\Controllers\Marketplace\VisibilityQrController;
use App\Http\Controllers\Owner\AnalyticsController as OwnerAnalyticsController;
use App\Http\Controllers\Owner\BookingAvailabilityController;
use App\Http\Controllers\Owner\BookingController;
use App\Http\Controllers\Owner\CourtResourceController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\GoogleBusinessProfileController;
use App\Http\Controllers\Owner\GrowthRecommendationController as OwnerGrowthRecommendationController;
use App\Http\Controllers\Owner\GrowthRecommendationStateController as OwnerGrowthRecommendationStateController;
use App\Http\Controllers\Owner\OperatingHoursController;
use App\Http\Controllers\Owner\OrganizationContextController;
use App\Http\Controllers\Owner\PaymentController;
use App\Http\Controllers\Owner\PayoutProfileController as OwnerPayoutProfileController;
use App\Http\Controllers\Owner\PayoutRequestController as OwnerPayoutRequestController;
use App\Http\Controllers\Owner\PayoutStatementController as OwnerPayoutStatementController;
use App\Http\Controllers\Owner\PromotionController;
use App\Http\Controllers\Owner\PsgcLocationController;
use App\Http\Controllers\Owner\ReactivationCampaignController;
use App\Http\Controllers\Owner\SettlementController as OwnerSettlementController;
use App\Http\Controllers\Owner\VenueClaimController;
use App\Http\Controllers\Owner\VenueController;
use App\Http\Controllers\Owner\VenuePhotoController;
use App\Http\Controllers\Owner\VenuePlaceController;
use App\Http\Controllers\Owner\VisibilityController as OwnerVisibilityController;
use App\Http\Controllers\Owner\VisibilityLinkController as OwnerVisibilityLinkController;
use App\Http\Controllers\Partner\DashboardController as PartnerDashboardController;
use App\Http\Controllers\Partner\LeadController as PartnerLeadController;
use App\Http\Controllers\Platform\AnalyticsController as PlatformAnalyticsController;
use App\Http\Controllers\Platform\CommissionEntryController as PlatformCommissionEntryController;
use App\Http\Controllers\Platform\CommissionRuleController as PlatformCommissionRuleController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\GrowthRecommendationController as PlatformGrowthRecommendationController;
use App\Http\Controllers\Platform\OwnerPayoutController as PlatformOwnerPayoutController;
use App\Http\Controllers\Platform\OwnerPayoutStatementController as PlatformOwnerPayoutStatementController;
use App\Http\Controllers\Platform\OwnerSettlementController as PlatformOwnerSettlementController;
use App\Http\Controllers\Platform\PartnerPayoutController as PlatformPartnerPayoutController;
use App\Http\Controllers\Platform\PaymentRefundController as PlatformPaymentRefundController;
use App\Http\Controllers\Platform\PaymentSettingsController as PlatformPaymentSettingsController;
use App\Http\Controllers\Platform\SalesController as PlatformSalesController;
use App\Http\Controllers\Platform\SalesLeadController as PlatformSalesLeadController;
use App\Http\Controllers\Platform\SalesPartnerController as PlatformSalesPartnerController;
use App\Http\Controllers\Platform\VenueDirectoryController as PlatformVenueDirectoryController;
use App\Http\Controllers\Platform\VenueReviewController as PlatformVenueReviewController;
use App\Http\Controllers\Player\Auth\AuthenticatedSessionController as PlayerAuthenticatedSessionController;
use App\Http\Controllers\Player\Auth\RegisteredUserController as PlayerRegisteredUserController;
use App\Http\Controllers\Player\BookingController as PlayerBookingController;
use App\Http\Controllers\Player\MarketingPreferenceController as PlayerMarketingPreferenceController;
use App\Http\Controllers\Player\NotificationController as PlayerNotificationController;
use App\Http\Controllers\Player\ReactivationClickController;
use App\Http\Controllers\Player\VenueReviewController as PlayerVenueReviewController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:marketplace')->group(function () {
    Route::get('/', HomeController::class)->name('marketplace.home');
    Route::get('/for-court-owners', [OwnerAcquisitionController::class, 'show'])
        ->name('marketplace.for-owners');
    Route::get('/pricing', [OwnerAcquisitionController::class, 'pricing'])
        ->name('marketplace.pricing');
    Route::get('/privacy', [LegalController::class, 'privacy'])
        ->name('marketplace.privacy');
    Route::get('/terms', [LegalController::class, 'terms'])
        ->name('marketplace.terms');
    Route::get('/courts', [DiscoveryController::class, 'index'])->name('marketplace.courts.index');
    Route::get('/deals', DealsController::class)->name('marketplace.deals');
    Route::get('/directory', [MarketplaceVenueDirectoryController::class, 'index'])
        ->name('marketplace.directory.index');
    Route::get('/directory/{listingSlug}', [MarketplaceVenueDirectoryController::class, 'show'])
        ->name('marketplace.directory.show');
    Route::get('/courts/{citySlug}', [DiscoveryController::class, 'city'])
        ->name('marketplace.courts.city');
    Route::get('/venues/{venueSlug}', MarketplaceVenueController::class)
        ->name('marketplace.venues.show');
});
Route::post('/directory/{directoryListing}/report', VenueDirectoryReportController::class)
    ->middleware('throttle:directory-report')
    ->name('marketplace.directory.report');
Route::get('/sitemap.xml', SitemapController::class)
    ->middleware('throttle:marketplace')
    ->name('marketplace.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('marketplace.robots');
Route::get('/sales-referral/{referralCode}', SalesPartnerReferralController::class)
    ->middleware('throttle:marketplace')
    ->name('partner.referral');
Route::get('/sales-referral/{referralCode}/qr.svg', SalesPartnerQrController::class)
    ->middleware('throttle:marketplace')
    ->name('partner.referral.qr');
Route::get('/go/{visibilityLink:token}/qr.svg', VisibilityQrController::class)
    ->middleware('throttle:marketplace')
    ->name('visibility-links.qr');
Route::get('/go/{visibilityLink:token}', MarketplaceVisibilityLinkController::class)
    ->middleware('throttle:marketplace')
    ->name('visibility-links.visit');
Route::get('/venues/{venueSlug}/reserve', [PlayerBookingController::class, 'create'])
    ->middleware('throttle:marketplace')
    ->name('player.bookings.create');
Route::get('/booking/{reference}', [PlayerBookingController::class, 'share'])
    ->middleware(['signed', 'throttle:marketplace'])
    ->name('bookings.share');
Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:payment-webhook')
    ->name('webhooks.payments');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1');
    Route::get('/player/login', [PlayerAuthenticatedSessionController::class, 'create'])
        ->name('player.login');
    Route::post('/player/login', [PlayerAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
    Route::get('/player/register', [PlayerRegisteredUserController::class, 'create'])
        ->name('player.register');
    Route::post('/player/register', [PlayerRegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1');
    Route::get('/auth/{audience}/{provider}/redirect', [SocialAuthenticationController::class, 'redirect'])
        ->whereIn('audience', ['owner', 'player'])
        ->whereIn('provider', ['google', 'facebook', 'apple'])
        ->middleware('throttle:social-login')
        ->name('social.redirect');
    Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialAuthenticationController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook', 'apple'])
        ->middleware('throttle:social-login')
        ->name('social.callback');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/reset-password/{token}', [AccountPasswordResetController::class, 'create'])
    ->middleware('throttle:6,1')
    ->name('password.reset');
Route::post('/reset-password', [AccountPasswordResetController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('password.store');

Route::middleware('auth')->group(function () {
    Route::get('/owner/social/setup', [SocialOwnerSetupController::class, 'create'])
        ->middleware('verified')
        ->name('owner.social-setup.create');
    Route::post('/owner/social/setup', [SocialOwnerSetupController::class, 'store'])
        ->middleware(['verified', 'throttle:6,1'])
        ->name('owner.social-setup.store');
    Route::get('/email/verify', function (Request $request) {
        $isOwner = $request->user()->memberships()->exists();
        $accountRoute = $isOwner
            ? 'owner.account.edit'
            : 'player.account.edit';

        return view('auth.verify-email', [
            'accountSettingsUrl' => route($accountRoute),
            'isOwnerVerification' => $isOwner,
            'seo' => [
                'title' => 'Verify your account email',
                'description' => $isOwner
                    ? 'Verify your FinACourt account email before using the court-owner workspace.'
                    : 'Verify the email address associated with your FinACourt account.',
                'canonical' => route('verification.notice'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    })->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        $user = $request->user();
        $destination = $user->is_platform_admin
            ? route('platform.dashboard')
            : ($user->memberships()->exists() ? route('owner.dashboard') : route('player.bookings.index'));

        return redirect()->intended($destination)
            ->with('status', 'Your account email is verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::prefix('player')->name('player.')->middleware(['auth', 'throttle:authenticated'])->group(function () {
    Route::get('/account', [AccountSettingsController::class, 'playerEdit'])->name('account.edit');
    Route::patch('/account/profile', [AccountSettingsController::class, 'updateProfile'])
        ->middleware('throttle:6,1')
        ->name('account.profile.update');
    Route::put('/account/password', [AccountSettingsController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');
    Route::post('/account/password-link', [AccountPasswordResetController::class, 'send'])
        ->middleware('throttle:3,60')
        ->name('account.password-link.store');
    Route::get('/bookings', [PlayerBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{reference}', [PlayerBookingController::class, 'show'])
        ->name('bookings.show');
    Route::post('/bookings/{reference}/confirm', [PlayerBookingController::class, 'confirm'])
        ->middleware('throttle:player-booking')
        ->name('bookings.confirm');
    Route::post('/bookings/{reference}/checkout', [PlayerBookingController::class, 'checkout'])
        ->middleware('throttle:player-booking')
        ->name('bookings.checkout');
    Route::get('/bookings/{reference}/payment/return', [PlayerBookingController::class, 'paymentReturn'])
        ->name('bookings.payment.return');
    Route::patch('/bookings/{reference}/cancel', [PlayerBookingController::class, 'cancel'])
        ->middleware('throttle:player-booking')
        ->name('bookings.cancel');
    Route::post('/bookings/{reference}/review', [PlayerVenueReviewController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('bookings.review.store');
    Route::patch('/notifications/{notification}/read', [PlayerNotificationController::class, 'read'])
        ->name('notifications.read');
    Route::get('/preferences', [PlayerMarketingPreferenceController::class, 'edit'])
        ->name('preferences.edit');
    Route::put('/preferences', [PlayerMarketingPreferenceController::class, 'update'])
        ->name('preferences.update');
    Route::get('/reactivation/{clickToken}', ReactivationClickController::class)
        ->name('reactivation.click');
});

Route::post('/venues/{venueSlug}/holds', [PlayerBookingController::class, 'store'])
    ->middleware(['auth', 'throttle:player-booking'])
    ->name('player.bookings.store');

Route::prefix('owner')->name('owner.')->middleware(['auth', 'tenant', 'throttle:authenticated'])->group(function () {
    Route::get('/account', [AccountSettingsController::class, 'ownerEdit'])->name('account.edit');
    Route::patch('/account/profile', [AccountSettingsController::class, 'updateProfile'])
        ->middleware('throttle:6,1')
        ->name('account.profile.update');
    Route::put('/account/password', [AccountSettingsController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');
    Route::post('/account/password-link', [AccountPasswordResetController::class, 'send'])
        ->middleware('throttle:3,60')
        ->name('account.password-link.store');
});

Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'tenant', 'throttle:authenticated'])->group(function () {
    Route::get('/google-business-profile/callback', [GoogleBusinessProfileController::class, 'callback'])
        ->middleware('throttle:google-business-profile')
        ->name('google-business-profile.callback');
    Route::get('/dashboard', OwnerDashboardController::class)->name('dashboard');
    Route::get('/analytics', OwnerAnalyticsController::class)->name('analytics');
    Route::get('/growth', OwnerGrowthRecommendationController::class)->name('growth.index');
    Route::post('/growth/{recommendationKey}/state', [OwnerGrowthRecommendationStateController::class, 'store'])
        ->where('recommendationKey', '[a-f0-9]{64}')
        ->name('growth.state.store');
    Route::delete('/growth/{recommendationKey}/state', [OwnerGrowthRecommendationStateController::class, 'destroy'])
        ->where('recommendationKey', '[a-f0-9]{64}')
        ->name('growth.state.destroy');
    Route::get('/visibility', OwnerVisibilityController::class)->name('visibility.index');
    Route::get('/directory-claims', [VenueClaimController::class, 'index'])->name('directory-claims.index');
    Route::get('/venue-invitations/{invitationToken}', [VenueClaimController::class, 'create'])
        ->where('invitationToken', '[a-f0-9]{64}')
        ->middleware('verified')
        ->name('directory-claims.invitations.create');
    Route::post('/venue-invitations/{invitationToken}', [VenueClaimController::class, 'store'])
        ->where('invitationToken', '[a-f0-9]{64}')
        ->middleware(['verified', 'throttle:directory-claim'])
        ->name('directory-claims.invitations.store');
    Route::post('/directory-claims/{claim}/proof/email', [VenueClaimController::class, 'resendEmailCode'])
        ->middleware(['verified', 'throttle:directory-claim'])
        ->name('directory-claims.proof.email');
    Route::post('/directory-claims/{claim}/proof/verify', [VenueClaimController::class, 'verifyEmailCode'])
        ->middleware(['verified', 'throttle:directory-claim-proof'])
        ->name('directory-claims.proof.verify');
    Route::delete('/directory-claims/{claim}', [VenueClaimController::class, 'cancel'])
        ->name('directory-claims.cancel');
    Route::get('/location-options/cities', PsgcLocationController::class)
        ->name('location-options.cities');
    Route::get('/bookings/availability', BookingAvailabilityController::class)
        ->name('bookings.availability');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::patch('/bookings/{booking}/payment', [PaymentController::class, 'update'])
        ->name('bookings.payment.update');
    Route::get('/earnings', OwnerSettlementController::class)->name('settlements.index');
    Route::put('/earnings/payment-details', [OwnerPayoutProfileController::class, 'update'])
        ->name('settlements.profile.update');
    Route::post('/earnings/request', OwnerPayoutRequestController::class)
        ->name('settlements.request');
    Route::get('/earnings/payouts/{payout}/statement', OwnerPayoutStatementController::class)
        ->name('settlements.payouts.statement');
    Route::resource('promotions', PromotionController::class);
    Route::get('/reactivation', [ReactivationCampaignController::class, 'index'])
        ->name('reactivation.index');
    Route::get('/reactivation/create', [ReactivationCampaignController::class, 'create'])
        ->name('reactivation.create');
    Route::post('/reactivation', [ReactivationCampaignController::class, 'store'])
        ->name('reactivation.store');
    Route::get('/reactivation/{campaign}', [ReactivationCampaignController::class, 'show'])
        ->name('reactivation.show');
    Route::post('/reactivation/{campaign}/send', [ReactivationCampaignController::class, 'send'])
        ->name('reactivation.send');
    Route::patch('/reactivation/{campaign}/cancel', [ReactivationCampaignController::class, 'cancel'])
        ->name('reactivation.cancel');
    Route::post('/venues/{venue}/photos', [VenuePhotoController::class, 'store'])
        ->name('venues.photos.store');
    Route::post('/venues/{venue}/visibility-links', [OwnerVisibilityLinkController::class, 'store'])
        ->name('venues.visibility-links.store');
    Route::post('/venues/{venue}/google-place', [VenuePlaceController::class, 'store'])
        ->name('venues.google-place.store');
    Route::post('/venues/{venue}/google-business-profile/connect', [GoogleBusinessProfileController::class, 'connect'])
        ->middleware('throttle:google-business-profile')
        ->name('venues.google-business-profile.connect');
    Route::post('/venues/{venue}/google-business-profile/retry', [GoogleBusinessProfileController::class, 'retry'])
        ->middleware('throttle:google-business-profile')
        ->name('venues.google-business-profile.retry');
    Route::post('/venues/{venue}/google-business-profile/candidates/{candidateKey}', [GoogleBusinessProfileController::class, 'confirm'])
        ->where('candidateKey', '[a-f0-9]{64}')
        ->middleware('throttle:google-business-profile')
        ->name('venues.google-business-profile.confirm');
    Route::delete('/venues/{venue}/google-business-profile', [GoogleBusinessProfileController::class, 'disconnect'])
        ->middleware('throttle:google-business-profile')
        ->name('venues.google-business-profile.disconnect');
    Route::patch('/venues/{venue}/photos/{photo}', [VenuePhotoController::class, 'update'])
        ->name('venues.photos.update');
    Route::delete('/venues/{venue}/photos/{photo}', [VenuePhotoController::class, 'destroy'])
        ->name('venues.photos.destroy');
    Route::resource('venues', VenueController::class);
    Route::get('/venues/{venue}/hours', [OperatingHoursController::class, 'edit'])
        ->name('venues.hours.edit');
    Route::put('/venues/{venue}/hours', [OperatingHoursController::class, 'update'])
        ->name('venues.hours.update');
    Route::resource('venues.resources', CourtResourceController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy']);
});

Route::post('/owner/organizations/{organization}/activate', OrganizationContextController::class)
    ->middleware(['auth', 'verified'])
    ->name('owner.organizations.activate');

Route::prefix('partner')->name('partner.')->middleware(['auth', 'sales.partner', 'throttle:authenticated'])->group(function () {
    Route::get('/dashboard', PartnerDashboardController::class)->name('dashboard');
    Route::get('/leads', [PartnerLeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [PartnerLeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [PartnerLeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [PartnerLeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{lead}', [PartnerLeadController::class, 'update'])->name('leads.update');
    Route::patch('/leads/{lead}/status', [PartnerLeadController::class, 'transition'])->name('leads.transition');
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform.admin', 'throttle:authenticated'])->group(function () {
    Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
    Route::get('/analytics', PlatformAnalyticsController::class)->name('analytics');
    Route::get('/growth', PlatformGrowthRecommendationController::class)->name('growth.index');
    Route::get('/payments', [PlatformPaymentSettingsController::class, 'index'])->name('payments.index');
    Route::post('/payments/service-fees', [PlatformPaymentSettingsController::class, 'store'])->name('payments.service-fees.store');
    Route::patch('/payments/service-fees/{rule}', [PlatformPaymentSettingsController::class, 'update'])->name('payments.service-fees.update');
    Route::post('/payments/{payment}/record-refund', PlatformPaymentRefundController::class)->name('payments.refunds.store');
    Route::get('/owner-payouts', PlatformOwnerSettlementController::class)->name('owner-payouts.index');
    Route::post('/owner-payouts', [PlatformOwnerPayoutController::class, 'store'])->name('owner-payouts.store');
    Route::post('/owner-payouts/adjustments', [PlatformOwnerPayoutController::class, 'adjust'])->name('owner-payouts.adjustments.store');
    Route::post('/owner-payouts/{payout}/approve', [PlatformOwnerPayoutController::class, 'approve'])->name('owner-payouts.approve');
    Route::post('/owner-payouts/{payout}/process', [PlatformOwnerPayoutController::class, 'process'])->name('owner-payouts.process');
    Route::post('/owner-payouts/{payout}/send', [PlatformOwnerPayoutController::class, 'send'])->name('owner-payouts.send');
    Route::post('/owner-payouts/{payout}/fail', [PlatformOwnerPayoutController::class, 'fail'])->name('owner-payouts.fail');
    Route::post('/owner-payouts/{payout}/cancel', [PlatformOwnerPayoutController::class, 'cancel'])->name('owner-payouts.cancel');
    Route::post('/owner-payouts/{payout}/reverse', [PlatformOwnerPayoutController::class, 'reverse'])->name('owner-payouts.reverse');
    Route::get('/owner-payouts/{payout}/statement', PlatformOwnerPayoutStatementController::class)->name('owner-payouts.statement');
    Route::get('/reviews', [PlatformVenueReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}', [PlatformVenueReviewController::class, 'update'])
        ->name('reviews.update');
    Route::get('/location-options/cities', PsgcLocationController::class)
        ->name('location-options.cities');
    Route::get('/directory', [PlatformVenueDirectoryController::class, 'index'])->name('directory.index');
    Route::get('/directory/create', [PlatformVenueDirectoryController::class, 'create'])->name('directory.create');
    Route::post('/directory', [PlatformVenueDirectoryController::class, 'store'])->name('directory.store');
    Route::get('/directory/{directoryListing}/edit', [PlatformVenueDirectoryController::class, 'edit'])->name('directory.edit');
    Route::put('/directory/{directoryListing}', [PlatformVenueDirectoryController::class, 'update'])->name('directory.update');
    Route::post('/directory/{directoryListing}/verify', [PlatformVenueDirectoryController::class, 'verify'])->name('directory.verify');
    Route::post('/directory/{directoryListing}/publish', [PlatformVenueDirectoryController::class, 'publish'])->name('directory.publish');
    Route::post('/directory/{directoryListing}/close', [PlatformVenueDirectoryController::class, 'close'])->name('directory.close');
    Route::post('/directory/{directoryListing}/remove', [PlatformVenueDirectoryController::class, 'remove'])->name('directory.remove');
    Route::post('/directory/{directoryListing}/claim-invitations', [PlatformVenueDirectoryController::class, 'issueClaimInvitation'])->name('directory.claim-invitations.store');
    Route::delete('/directory/{directoryListing}/claim-invitations/{invitation}', [PlatformVenueDirectoryController::class, 'revokeClaimInvitation'])->name('directory.claim-invitations.destroy');
    Route::post('/directory/claims/{claim}/approve', [PlatformVenueDirectoryController::class, 'approveClaim'])->name('directory.claims.approve');
    Route::post('/directory/claims/{claim}/reject', [PlatformVenueDirectoryController::class, 'rejectClaim'])->name('directory.claims.reject');
    Route::post('/directory/claims/{claim}/verify-proof', [PlatformVenueDirectoryController::class, 'verifyClaimProof'])->name('directory.claims.verify-proof');
    Route::post('/directory/{directoryListing}/verify-claimed-venue', [PlatformVenueDirectoryController::class, 'verifyClaimedVenue'])->name('directory.claimed-venue.verify');
    Route::post('/directory/{directoryListing}/revoke-claimed-venue', [PlatformVenueDirectoryController::class, 'revokeClaimedVenue'])->name('directory.claimed-venue.revoke');
    Route::patch('/directory/reports/{report}', [PlatformVenueDirectoryController::class, 'reviewReport'])->name('directory.reports.review');
    Route::get('/sales', PlatformSalesController::class)->name('sales.index');
    Route::post('/sales/partners', [PlatformSalesPartnerController::class, 'store'])->name('sales.partners.store');
    Route::patch('/sales/partners/{partner}', [PlatformSalesPartnerController::class, 'update'])->name('sales.partners.update');
    Route::patch('/sales/leads/{lead}/status', [PlatformSalesLeadController::class, 'transition'])->name('sales.leads.transition');
    Route::post('/sales/leads/{lead}/activate', [PlatformSalesLeadController::class, 'activate'])->name('sales.leads.activate');
    Route::post('/sales/leads/{lead}/override', [PlatformSalesLeadController::class, 'override'])->name('sales.leads.override');
    Route::post('/sales/commission-rules', [PlatformCommissionRuleController::class, 'store'])->name('sales.rules.store');
    Route::patch('/sales/commission-rules/{rule}', [PlatformCommissionRuleController::class, 'update'])->name('sales.rules.update');
    Route::post('/sales/commissions/adjust', [PlatformCommissionEntryController::class, 'adjust'])->name('sales.commissions.adjust');
    Route::post('/sales/commissions/{entry}/approve', [PlatformCommissionEntryController::class, 'approve'])->name('sales.commissions.approve');
    Route::post('/sales/commissions/{entry}/reverse', [PlatformCommissionEntryController::class, 'reverse'])->name('sales.commissions.reverse');
    Route::post('/sales/payouts', [PlatformPartnerPayoutController::class, 'store'])->name('sales.payouts.store');
    Route::post('/sales/payouts/{payout}/approve', [PlatformPartnerPayoutController::class, 'approve'])->name('sales.payouts.approve');
    Route::post('/sales/payouts/{payout}/pay', [PlatformPartnerPayoutController::class, 'pay'])->name('sales.payouts.pay');
    Route::post('/sales/payouts/{payout}/cancel', [PlatformPartnerPayoutController::class, 'cancel'])->name('sales.payouts.cancel');
});

Route::get('/{sportSlug}/{citySlug}', [DiscoveryController::class, 'sportCity'])
    ->middleware('throttle:marketplace')
    ->where([
        'sportSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'citySlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->name('marketplace.courts.sport-city');
