<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the job list as it grows: every list query filters soft-deleted rows,
     * usually by status / client / practice area, newest first.
     * (Foreign keys already have their own single-column indexes.)
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['deleted_at', 'id'], 'jobs_deleted_at_id_index');                       // default list: newest first
            $table->index(['status_id', 'deleted_at', 'id'], 'jobs_status_deleted_id_index');      // filter by status
            $table->index(['client_id', 'deleted_at', 'id'], 'jobs_client_deleted_id_index');      // filter by client / internal
            $table->index(['practice_area_id', 'deleted_at', 'id'], 'jobs_practice_deleted_id_index');
            $table->index(['priority', 'deleted_at'], 'jobs_priority_deleted_index');
        });

        // "which jobs is this recruiter on?" (the pivot's primary key starts with job_id)
        Schema::table('job_recruiters', function (Blueprint $table) {
            $table->index(['user_id', 'job_id'], 'job_recruiters_user_job_index');
        });

        // Dropdowns: active records sorted A–Z.
        foreach (['countries', 'practice_areas', 'clients', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index(['status_id', 'name'], "{$tableName}_status_name_index");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_deleted_at_id_index');
            $table->dropIndex('jobs_status_deleted_id_index');
            $table->dropIndex('jobs_client_deleted_id_index');
            $table->dropIndex('jobs_practice_deleted_id_index');
            $table->dropIndex('jobs_priority_deleted_index');
        });

        Schema::table('job_recruiters', function (Blueprint $table) {
            $table->dropIndex('job_recruiters_user_job_index');
        });

        foreach (['countries', 'practice_areas', 'clients', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_status_name_index");
            });
        }
    }
};
