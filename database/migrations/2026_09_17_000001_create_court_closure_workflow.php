<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('scope', 32);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone', 64);
            $table->string('reason', 500);
            $table->string('status', 32);
            $table->string('refund_status', 32);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'starts_at'], 'court_closures_tenant_status_idx');
            $table->index(['refund_status', 'created_at'], 'court_closures_refund_status_idx');
        });

        Schema::create('court_closure_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_closure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['court_closure_id', 'resource_id'], 'closure_resources_unique');
            $table->index(['resource_id', 'court_closure_id'], 'closure_resources_resource_idx');
        });

        Schema::create('court_closure_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_closure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 40);
            $table->boolean('refund_required')->default(false);
            $table->string('payment_mode', 32)->nullable();
            $table->string('payment_status_at_closure', 32)->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('PHP');
            $table->string('failure_message', 500)->nullable();
            $table->dateTime('cancelled_at');
            $table->dateTime('notification_queued_at')->nullable();
            $table->timestamps();

            $table->index(['court_closure_id', 'status'], 'closure_bookings_status_idx');
        });

        Schema::table('court_availability_blocks', function (Blueprint $table) {
            $table->foreignId('court_closure_id')
                ->nullable()
                ->after('resource_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->dateTime('ends_at')->nullable()->change();
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('court_closure_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['court_closure_id', 'status'], 'refund_requests_closure_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropForeign(['court_closure_id']);
            $table->dropIndex('refund_requests_closure_status_idx');
            $table->dropColumn('court_closure_id');
        });

        DB::table('court_availability_blocks')->whereNotNull('court_closure_id')->delete();

        Schema::table('court_availability_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('court_closure_id');
        });

        Schema::table('court_availability_blocks', function (Blueprint $table) {
            $table->dateTime('ends_at')->nullable(false)->change();
        });

        Schema::dropIfExists('court_closure_bookings');
        Schema::dropIfExists('court_closure_resources');
        Schema::dropIfExists('court_closures');
    }
};
