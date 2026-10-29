<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A candidate tagged to a job, moving through the hiring stages.
 */
class JobApplication extends Model
{
    public const STATUS_MODULE = 'application';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_id',
        'recruiter_id',
        'vendor_id',
        'source',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class)->withTrashed();
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class)->withTrashed();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(HiringStage::class, 'current_stage_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Tag a candidate to a job at the first stage (Sourced, active) and log it in the stage history.
     * Must run inside a DB transaction.
     */
    public static function tag(Candidate $candidate, Job $job, User $by): self
    {
        $application = new self([
            'job_id' => $job->id,
            'recruiter_id' => $by->id,
            'vendor_id' => $candidate->vendor_id,
            'source' => $candidate->resume_source,
        ]);
        $application->forceFill([
            'candidate_id' => $candidate->id,
            'current_stage_id' => HiringStage::idFor(HiringStage::SOURCED),
            'status_id' => Status::idFor(self::STATUS_MODULE, 'active'),
            'applied_at' => now(),
            'created_by' => $by->id,
        ])->save();

        DB::table('application_stage_history')->insert([
            'application_id' => $application->id,
            'from_stage_id' => null,
            'to_stage_id' => $application->current_stage_id,
            'from_status_id' => null,
            'to_status_id' => $application->status_id,
            'comments' => 'Tagged to job',
            'changed_by' => $by->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $application;
    }
}
