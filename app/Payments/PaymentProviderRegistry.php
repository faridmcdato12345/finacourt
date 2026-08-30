<?php

namespace App\Payments;

use App\Enums\PaymentMode;
use App\Enums\PlayerPaymentOption;
use App\Payments\Contracts\PaymentProvider;

class PaymentProviderRegistry
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    /** @param iterable<PaymentProvider> $providers */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function find(string $key): ?PaymentProvider
    {
        return $this->providers[$key] ?? null;
    }

    public function default(): PaymentProvider
    {
        $key = (string) config('payments.default', 'manual');

        return $this->find($key)
            ?? throw new \LogicException("Payment provider [{$key}] is not registered.");
    }

    public function forPlayerOption(PlayerPaymentOption $option): ?PaymentProvider
    {
        $key = match ($option) {
            PlayerPaymentOption::Online => (string) config('payments.online_provider', 'paymongo'),
            PlayerPaymentOption::PayAtVenue => 'manual',
        };
        $provider = $this->find($key);
        $expectedMode = match ($option) {
            PlayerPaymentOption::Online => PaymentMode::HostedCheckout,
            PlayerPaymentOption::PayAtVenue => PaymentMode::PayAtVenue,
        };

        return $provider?->mode() === $expectedMode ? $provider : null;
    }

    public function online(): ?PaymentProvider
    {
        return $this->forPlayerOption(PlayerPaymentOption::Online);
    }
}
