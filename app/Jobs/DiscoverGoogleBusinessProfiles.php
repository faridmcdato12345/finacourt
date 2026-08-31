<?php

namespace App\Jobs;

use App\Google\BusinessProfile\GoogleBusinessProfileConnectionManager;
use App\Google\BusinessProfile\GoogleBusinessProfileException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DiscoverGoogleBusinessProfiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 75;

    public function __construct(
        public readonly int $connectionId,
        public readonly int $organizationId,
        public readonly string $generation,
    ) {
        $this->onQueue('default')->afterCommit();
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("google-business-profile:{$this->connectionId}"))
                ->releaseAfter(30)
                ->expireAfter(120),
        ];
    }

    public function handle(GoogleBusinessProfileConnectionManager $connections): void
    {
        try {
            $connections->discover($this->connectionId, $this->organizationId, $this->generation);
        } catch (GoogleBusinessProfileException $exception) {
            if ($exception->retryable() && $this->attempts() < $this->tries) {
                $connections->recordRetry(
                    $this->connectionId,
                    $this->organizationId,
                    $this->generation,
                    $exception,
                    $this->attempts(),
                );

                throw $exception;
            }

            $connections->failDiscovery(
                $this->connectionId,
                $this->organizationId,
                $this->generation,
                $exception,
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $safeFailure = $exception instanceof GoogleBusinessProfileException
            ? $exception
            : new GoogleBusinessProfileException(
                'discovery_job_failed',
                'FinACourt could not finish checking Google. Try again from this venue later.',
            );

        app(GoogleBusinessProfileConnectionManager::class)->failDiscovery(
            $this->connectionId,
            $this->organizationId,
            $this->generation,
            $safeFailure,
        );
    }
}
