<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_directory_listings', function (Blueprint $table): void {
            $table->char('psgc_region_code', 10)->nullable()->after('province_slug');
            $table->char('psgc_province_code', 10)->nullable()->after('psgc_region_code');
            $table->char('psgc_city_municipality_code', 10)->nullable()->after('psgc_province_code');

            $table->foreign('psgc_region_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->foreign('psgc_province_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->foreign('psgc_city_municipality_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->index('psgc_city_municipality_code', 'directory_psgc_city_idx');
        });
    }

    public function down(): void
    {
        Schema::table('venue_directory_listings', function (Blueprint $table): void {
            $table->dropForeign(['psgc_region_code']);
            $table->dropForeign(['psgc_province_code']);
            $table->dropForeign(['psgc_city_municipality_code']);
            $table->dropIndex('directory_psgc_city_idx');
            $table->dropColumn([
                'psgc_region_code',
                'psgc_province_code',
                'psgc_city_municipality_code',
            ]);
        });
    }
};
