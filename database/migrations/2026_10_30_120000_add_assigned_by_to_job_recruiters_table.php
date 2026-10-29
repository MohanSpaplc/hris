<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * job_recruiters is the job assignment record: who was assigned, by whom and when.
     */
    public function up(): void
    {
        Schema::table('job_recruiters', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->index(['user_id', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_recruiters', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'assigned_at']);
            $table->dropConstrainedForeignId('assigned_by');
        });
    }
};
