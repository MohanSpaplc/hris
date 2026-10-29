<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ISO 3166-1 alpha-3 codes for the countries seeded so far.
     */
    private const ISO3 = ['IN' => 'IND', 'US' => 'USA', 'GB' => 'GBR', 'AE' => 'ARE', 'SG' => 'SGP'];

    /**
     * Run the migrations.
     * Job codes become {COUNTRY ISO3}-{PRACTICE CODE}-{00001}, e.g. IND-JAVA-00001.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->char('iso3_code', 3)->nullable()->unique()->after('code');
        });

        // Room for a 50-character practice code in the job code.
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('job_code', 80)->change();
        });

        foreach (self::ISO3 as $code => $iso3) {
            DB::table('countries')->where('code', $code)->update(['iso3_code' => $iso3]);
        }

        // Re-number existing jobs in the new format, oldest first, per country + practice.
        $jobs = DB::table('jobs')
            ->join('countries', 'countries.id', '=', 'jobs.country_id')
            ->join('practice_areas', 'practice_areas.id', '=', 'jobs.practice_area_id')
            ->orderBy('jobs.id')
            ->get(['jobs.id', 'countries.code', 'countries.iso3_code', 'practice_areas.code as practice_code']);

        $counters = [];
        foreach ($jobs as $job) {
            $prefix = ($job->iso3_code ?? $job->code).'-'.$job->practice_code;
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
            DB::table('jobs')->where('id', $job->id)->update(['job_code' => sprintf('%s-%05d', $prefix, $counters[$prefix])]);
        }
    }

    /**
     * Reverse the migrations. (Old JOB-YYYY-xxxxx codes are not restored.)
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique(['iso3_code']);
            $table->dropColumn('iso3_code');
        });
    }
};
