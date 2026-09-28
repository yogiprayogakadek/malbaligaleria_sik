<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table) {
            $table->string('applicant_token', 64)->nullable()->unique()->after('public_token');
            $table->boolean('deposit_required')->nullable()->after('security_deposit');
            $table->decimal('deposit_amount', 15, 2)->nullable()->after('deposit_required');
            $table->text('deposit_notes')->nullable()->after('deposit_amount');
            $table->foreignId('deposit_set_by')->nullable()->after('deposit_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('deposit_set_at')->nullable()->after('deposit_set_by');
            $table->string('payment_proof_path')->nullable()->after('deposit_set_at');
            $table->string('payment_proof_type', 20)->nullable()->after('payment_proof_path');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_type');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_verified_at')->nullable()->after('payment_verified_by');
            $table->text('finance_notes')->nullable()->after('payment_verified_at');
            $table->foreignId('completed_by')->nullable()->after('finance_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
            $table->decimal('refund_amount', 15, 2)->nullable()->after('completed_at');
            $table->string('refund_proof_path')->nullable()->after('refund_amount');
            $table->string('refund_proof_type', 20)->nullable()->after('refund_proof_path');
            $table->text('refund_notes')->nullable()->after('refund_proof_type');
            $table->foreignId('refund_processed_by')->nullable()->after('refund_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('refund_processed_at')->nullable()->after('refund_processed_by');
            $table->text('review_notes')->nullable()->after('refund_processed_at');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        DB::table('work_permits')->orderBy('id')->eachById(function (object $permit): void {
            DB::table('work_permits')->where('id', $permit->id)->update([
                'applicant_token' => Str::random(64),
                'status' => $permit->status === 'pending' ? 'mep_review' : $permit->status,
                'deposit_required' => null,
            ]);
        });

        Schema::create('work_permit_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('action', 60);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['work_permit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_permit_status_logs');

        Schema::table('work_permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_set_by');
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('refund_processed_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'applicant_token',
                'deposit_required',
                'deposit_amount',
                'deposit_notes',
                'deposit_set_at',
                'payment_proof_path',
                'payment_proof_type',
                'payment_submitted_at',
                'payment_verified_at',
                'finance_notes',
                'completed_at',
                'refund_amount',
                'refund_proof_path',
                'refund_proof_type',
                'refund_notes',
                'refund_processed_at',
                'review_notes',
                'reviewed_at',
            ]);
        });
    }
};
