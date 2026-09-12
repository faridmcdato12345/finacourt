<?php

use App\Enums\AcquisitionSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attributions', function (Blueprint $table): void {
            foreach (['first', 'last', 'attributed'] as $touch) {
                $table->string("{$touch}_evidence", 32)->nullable()->after("{$touch}_source");
                $table->string("{$touch}_content", 120)->nullable()->after("{$touch}_campaign");
                $table->string("{$touch}_term", 120)->nullable()->after("{$touch}_content");
                $table->char("{$touch}_click_id_hash", 64)->nullable()->after("{$touch}_term");
            }
        });

        Schema::table('visibility_links', function (Blueprint $table): void {
            $table->string('acquisition_source', 40)
                ->default(AcquisitionSource::QrCode->value)
                ->after('destination');
            $table->index(
                ['venue_id', 'acquisition_source', 'is_active'],
                'visibility_links_venue_source_active_idx',
            );
        });

    }

    public function down(): void
    {
        Schema::table('visibility_links', function (Blueprint $table): void {
            $table->dropIndex('visibility_links_venue_source_active_idx');
            $table->dropColumn('acquisition_source');
        });

        Schema::table('booking_attributions', function (Blueprint $table): void {
            $columns = [];

            foreach (['first', 'last', 'attributed'] as $touch) {
                $columns[] = "{$touch}_evidence";
                $columns[] = "{$touch}_content";
                $columns[] = "{$touch}_term";
                $columns[] = "{$touch}_click_id_hash";
            }

            $table->dropColumn($columns);
        });
    }
};
