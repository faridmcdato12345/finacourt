<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Payments\ApplyVerifiedPaymentEvent;
use App\Payments\Contracts\WebhookPaymentProvider;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\UnsupportedWebhookEvent;
use App\Payments\PaymentProviderRegistry;
use App\Payments\VerifiedRefundEvent;
use App\Refunds\ApplyVerifiedRefundEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentProviderRegistry $providers,
        ApplyVerifiedPaymentEvent $applyEvent,
        ApplyVerifiedRefundEvent $applyRefundEvent,
    ): JsonResponse {
        $adapter = $providers->find($provider);
        abort_unless($adapter instanceof WebhookPaymentProvider, 404);

        try {
            $event = $adapter->verifyWebhook($request);
        } catch (InvalidWebhookSignature) {
            Log::warning('Payment webhook signature rejected.', ['provider' => $provider]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        } catch (UnsupportedWebhookEvent $exception) {
            Log::info('Payment webhook ignored.', [
                'provider' => $provider,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['result' => 'ignored']);
        }

        $result = $event instanceof VerifiedRefundEvent
            ? $applyRefundEvent->handle($adapter->key(), $event)
            : $applyEvent->handle($adapter->key(), $event);

        if ($result === 'review') {
            Log::warning('Verified payment or refund webhook requires review.', [
                'provider' => $adapter->key(),
                'event_id' => $event->eventId,
            ]);
        }

        return response()->json(['result' => $result]);
    }
}
