<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psgc_locations', function (Blueprint $table) {
            $table->char('code', 10)->primary();
            $table->char('parent_code', 10)->nullable()->index();
            $table->string('name', 160);
            $table->string('level', 24)->index();
            $table->string('type', 48)->nullable();
            $table->string('source_version', 32);
            $table->timestamps();

            $table->foreign('parent_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->index(['level', 'name']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->char('psgc_region_code', 10)->nullable()->after('province_slug');
            $table->char('psgc_province_code', 10)->nullable()->after('psgc_region_code');
            $table->char('psgc_city_municipality_code', 10)->nullable()->after('psgc_province_code');

            $table->foreign('psgc_region_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->foreign('psgc_province_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->foreign('psgc_city_municipality_code')->references('code')->on('psgc_locations')->nullOnDelete();
            $table->index('psgc_city_municipality_code');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['psgc_region_code']);
            $table->dropForeign(['psgc_province_code']);
            $table->dropForeign(['psgc_city_municipality_code']);
            $table->dropIndex(['psgc_city_municipality_code']);
            $table->dropColumn([
                'psgc_region_code',
                'psgc_province_code',
                'psgc_city_municipality_code',
            ]);
        });

        Schema::dropIfExists('psgc_locations');
    }
};
