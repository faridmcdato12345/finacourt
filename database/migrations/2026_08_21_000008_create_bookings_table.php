<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->restrictOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('status', 32);
            $table->string('source', 32);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at')->nullable();
            $table->string('timezone', 64);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->char('currency', 3);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'start_at']);
            $table->index(['venue_id', 'start_at']);
            $table->index(['resource_id', 'start_at', 'end_at']);
            $table->index(['resource_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
