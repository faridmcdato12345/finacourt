<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psgc_locations', function (Blueprint $table): void {
            $table->char('geographic_parent_code', 10)
                ->nullable()
                ->after('parent_code')
                ->index();
            $table->foreign('geographic_parent_code')
                ->references('code')
                ->on('psgc_locations')
                ->nullOnDelete();
        });

        foreach ($this->geographicParents() as $cityCode => $provinceCode) {
            DB::table('psgc_locations')
                ->where('code', $cityCode)
                ->update(['geographic_parent_code' => $provinceCode]);
        }
    }

    public function down(): void
    {
        Schema::table('psgc_locations', function (Blueprint $table): void {
            $table->dropForeign(['geographic_parent_code']);
            $table->dropIndex(['geographic_parent_code']);
            $table->dropColumn('geographic_parent_code');
        });
    }

    /** @return array<string, string> */
    private function geographicParents(): array
    {
        return [
            '0330100000' => '0305400000', // Angeles City — Pampanga
            '1830200000' => '1804500000', // Bacolod City — Negros Occidental
            '1430300000' => '1401100000', // Baguio City — Benguet
            '1630400000' => '1600200000', // Butuan City — Agusan del Norte
            '1030500000' => '1004300000', // Cagayan de Oro City — Misamis Oriental
            '0730600000' => '0702200000', // Cebu City — Cebu
            '1130700000' => '1102400000', // Davao City — Davao del Sur
            '1230800000' => '1206300000', // General Santos City — South Cotabato
            '1030900000' => '1003500000', // Iligan City — Lanao del Norte
            '0631000000' => '0603000000', // Iloilo City — Iloilo
            '0990100000' => '1900700000', // Isabela City — Basilan
            '0731100000' => '0702200000', // Lapu-Lapu City — Cebu
            '0431200000' => '0405600000', // Lucena City — Quezon
            '0731300000' => '0702200000', // Mandaue City — Cebu
            '0331400000' => '0307100000', // Olongapo City — Zambales
            '1731500000' => '1705300000', // Puerto Princesa City — Palawan
            '0831600000' => '0803700000', // Tacloban City — Leyte
            '0931700000' => '0907300000', // Zamboanga City — Zamboanga del Sur
        ];
    }
};
