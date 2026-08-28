<?php

use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('city_slug')->nullable()->after('city');
            $table->string('province_slug')->nullable()->after('province');
            $table->index(['city_slug', 'is_published']);
            $table->index(['province_slug', 'city_slug']);
        });

        Venue::query()->select(['id', 'city', 'province'])->chunkById(200, function ($venues): void {
            foreach ($venues as $venue) {
                $venue->updateQuietly([
                    'city_slug' => Str::slug($venue->city),
                    'province_slug' => Str::slug($venue->province),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['city_slug', 'is_published']);
            $table->dropIndex(['province_slug', 'city_slug']);
            $table->dropColumn(['city_slug', 'province_slug']);
        });
    }
};
