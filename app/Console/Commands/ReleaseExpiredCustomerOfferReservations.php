<?php

namespace App\Console\Commands;

use App\CustomerOffers\CustomerOfferService;
use Illuminate\Console\Command;

class ReleaseExpiredCustomerOfferReservations extends Command
{
    protected $signature = 'customer-offers:release-expired';
    protected $description = 'Release customer offers held by expired reservation checkouts.';

    public function handle(CustomerOfferService $offers): int
    {
        $released = $offers->sweepExpiredOffers();
        $this->info("Released {$released} expired customer offer reservations.");

        return self::SUCCESS;
    }
}
