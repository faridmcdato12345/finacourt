<?php

namespace App\Payments;

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
}
