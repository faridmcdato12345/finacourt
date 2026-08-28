<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_unread_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('confirmation_notified_at')->nullable()->after('cancellation_reason');
            $table->dateTime('payment_notified_at')->nullable()->after('confirmation_notified_at');
            $table->dateTime('reminder_notified_at')->nullable()->after('payment_notified_at');
            $table->index(['status', 'reminder_notified_at', 'start_at'], 'bookings_reminder_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_reminder_due_idx');
            $table->dropColumn([
                'confirmation_notified_at',
                'payment_notified_at',
                'reminder_notified_at',
            ]);
        });

        Schema::dropIfExists('notifications');
    }
};
