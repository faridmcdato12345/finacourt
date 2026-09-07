<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_preferences', function (Blueprint $table) {
            $table->boolean('email_marketing_enabled')
                ->default(false)
                ->after('in_app_marketing_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_preferences', function (Blueprint $table) {
            $table->dropColumn('email_marketing_enabled');
        });
    }
};
