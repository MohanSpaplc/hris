<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * candidate_personal_details and candidate_family_members (restricted, collected after offer) come with the offer stage.
     */
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code', 50)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('gender', 30)->nullable();
            $table->string('current_designation', 200)->nullable();
            $table->string('current_company', 200)->nullable();
            $table->decimal('total_experience', 4, 1)->nullable();
            $table->decimal('relevant_experience', 4, 1)->nullable();
            $table->decimal('current_ctc', 12, 2)->nullable();
            $table->decimal('expected_ctc', 12, 2)->nullable();
            $table->char('ctc_currency_code', 3)->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->string('resume_source', 50)->nullable();
            $table->string('referral_name', 200)->nullable();
            $table->string('preferred_location', 200)->nullable();
            $table->string('current_location', 200)->nullable();
            $table->longText('resume_text')->nullable();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status_id', 'created_at']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['first_name', 'last_name', 'current_designation', 'resume_text'], 'candidates_search_fulltext');
            }
        });

        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('qualification', 150);
            $table->string('institution')->nullable();
            $table->string('specialization', 200)->nullable();
            $table->unsignedSmallInteger('year_of_passing')->nullable();
            $table->decimal('percentage_or_cgpa', 6, 2)->nullable();
            $table->string('document_path', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('designation', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->decimal('salary', 12, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('responsibilities')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('original_filename');
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'document_type', 'is_current']);
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->restrictOnDelete();
            $table->string('skill_type', 20); // primary, secondary, other
            $table->unsignedSmallInteger('experience_months')->nullable();
            $table->timestamps();
            $table->primary(['candidate_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_skills');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidate_employments');
        Schema::dropIfExists('candidate_educations');
        Schema::dropIfExists('candidates');
    }
};
