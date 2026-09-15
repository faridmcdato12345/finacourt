<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_payment_reference')->nullable()->after('provider_reference');
            $table->index(['provider', 'provider_payment_reference'], 'payments_provider_payment_idx');
        });

        DB::table('payment_transitions')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(200, function ($transitions): void {
                foreach ($transitions as $transition) {
                    $metadata = json_decode((string) $transition->metadata, true);
                    $providerPaymentReference = $metadata['paymongo_payment_id'] ?? null;

                    if (is_string($providerPaymentReference) && $providerPaymentReference !== '') {
                        DB::table('payments')
                            ->where('id', $transition->payment_id)
                            ->whereNull('provider_payment_reference')
                            ->update(['provider_payment_reference' => $providerPaymentReference]);
                    }
                }
            });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 32);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_note', 500)->nullable();
            $table->string('provider', 40);
            $table->string('provider_payment_reference')->nullable();
            $table->string('provider_refund_reference')->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('requires_review')->default(false);
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'requested_at'], 'refund_requests_tenant_status_idx');
            $table->index(['provider', 'provider_payment_reference'], 'refund_requests_provider_payment_idx');
            $table->unique(['provider', 'provider_refund_reference'], 'refund_requests_provider_refund_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_provider_payment_idx');
            $table->dropColumn('provider_payment_reference');
        });
    }
};
