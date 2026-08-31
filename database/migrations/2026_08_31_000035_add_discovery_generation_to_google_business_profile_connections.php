<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_business_profile_connections', function (Blueprint $table): void {
            $table->ulid('discovery_generation')->nullable()->after('candidates');
        });
    }

    public function down(): void
    {
        Schema::table('google_business_profile_connections', function (Blueprint $table): void {
            $table->dropColumn('discovery_generation');
        });
    }
};
