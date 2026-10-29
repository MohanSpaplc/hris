<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A candidate (resume master record).
 */
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_MODULE = 'candidate';

    public const GENDERS = ['male', 'female', 'other', 'prefer_not_to_say'];

    public const RESUME_SOURCES = ['portal', 'referral', 'vendor', 'direct', 'linkedin'];

    public const SKILL_TYPES = ['primary', 'secondary', 'other'];

    /**
     * The attributes that are mass assignable.
     * candidate_code, created_by, status_id and resume_text are set by the server.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vendor_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'current_designation',
        'current_company',
        'total_experience',
        'relevant_experience',
        'current_ctc',
        'expected_ctc',
        'ctc_currency_code',
        'notice_period_days',
        'resume_source',
        'referral_name',
        'preferred_location',
        'current_location',
        'education_details',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_experience' => 'decimal:1',
            'relevant_experience' => 'decimal:1',
            'current_ctc' => 'decimal:2',
            'expected_ctc' => 'decimal:2',
            'notice_period_days' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class)->orderByDesc('year_of_passing');
    }

    public function employments(): HasMany
    {
        return $this->hasMany(CandidateEmployment::class)->orderByDesc('is_current')->orderByDesc('start_date');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function currentResume(): HasOne
    {
        return $this->hasOne(CandidateDocument::class)
            ->where('document_type', CandidateDocument::TYPE_RESUME)
            ->where('is_current', true)
            ->latestOfMany('version');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills')->withPivot(['skill_type', 'experience_months'])->withTimestamps();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Next candidate code: CAN-00001, CAN-00002, ... Includes deleted candidates, so a number is never reused.
     * Call inside a transaction: matching rows are locked so concurrent saves can't take the same number.
     */
    public static function nextCode(): string
    {
        $lastNumber = static::withTrashed()
            ->where('candidate_code', 'like', 'CAN-%')
            ->lockForUpdate()
            ->pluck('candidate_code')
            ->map(fn (string $code) => preg_match('/^CAN-(\d+)$/', $code, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return sprintf('CAN-%05d', $lastNumber + 1);
    }
}
