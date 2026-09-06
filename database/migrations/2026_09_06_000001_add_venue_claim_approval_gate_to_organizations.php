<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('requires_venue_claim_approval')
                ->default(false)
                ->after('timezone');
        });

        DB::table('organizations')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('venue_claim_requests')
                ->whereColumn('venue_claim_requests.organization_id', 'organizations.id'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('venues')
                ->whereColumn('venues.organization_id', 'organizations.id'))
            ->update(['requires_venue_claim_approval' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('requires_venue_claim_approval');
        });
    }
};
