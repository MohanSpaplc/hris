<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Note: Laravel's queue table was renamed to queue_jobs, so "jobs" is free for job openings.
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 50)->unique();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete(); // null = internal job
            $table->foreignId('client_manager_id')->nullable()->constrained('client_managers')->nullOnDelete();
            $table->foreignId('practice_area_id')->constrained('practice_areas')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('job_title', 200);
            $table->decimal('total_experience', 4, 1)->nullable();
            $table->decimal('relevant_experience', 4, 1)->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->char('budget_currency_code', 3)->nullable();
            $table->date('requirement_date')->nullable();
            $table->string('contract_duration', 100)->nullable();
            $table->string('work_mode', 30)->nullable();
            $table->string('work_location', 200)->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->unsignedInteger('no_of_positions')->default(1);
            $table->unsignedInteger('positions_filled')->default(0);
            $table->string('priority', 20)->default('medium');
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->longText('job_description')->nullable();
            $table->string('jd_file_path', 500)->nullable();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status_id', 'priority']);
            $table->index('job_title');
        });

        Schema::create('job_skills', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->restrictOnDelete();
            $table->string('skill_type', 20); // primary, secondary
            $table->timestamps();
            $table->primary(['job_id', 'skill_id']);
        });

        Schema::create('job_recruiters', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->primary(['job_id', 'user_id']);
        });

        Schema::create('job_vendors', function (Blueprint $table) {
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->primary(['job_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_vendors');
        Schema::dropIfExists('job_recruiters');
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('skills');
    }
};
