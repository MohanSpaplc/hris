<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hiring_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->restrictOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('current_stage_id')->constrained('hiring_stages')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->string('source', 50)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['candidate_id', 'job_id']);
            $table->index(['job_id', 'current_stage_id', 'status_id']);
        });

        // Insert-only audit log: one row per stage / status change.
        Schema::create('application_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('job_applications')->restrictOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('hiring_stages')->restrictOnDelete();
            $table->foreignId('to_stage_id')->constrained('hiring_stages')->restrictOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->restrictOnDelete();
            $table->text('comments')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_stage_history');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('hiring_stages');
    }
};
