<?php

namespace App\Models;

use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A job opening (requirement). Not to be confused with queued jobs (table queue_jobs).
 */
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_MODULE = 'job';

    public const WORK_MODES = ['onsite', 'remote', 'hybrid'];

    public const EMPLOYMENT_TYPES = ['full_time', 'contract', 'c2h', 'intern'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * The attributes that are mass assignable.
     * job_code, created_by, status_id, positions_filled and closed_at are set by the server.
     *
     * @var list<string>
     */
    protected $fillable = [
        'country_id',
        'client_id',
        'client_manager_id',
        'practice_area_id',
        'job_title',
        'total_experience',
        'relevant_experience',
        'budget_min',
        'budget_max',
        'budget_currency_code',
        'requirement_date',
        'contract_duration',
        'work_mode',
        'work_location',
        'employment_type',
        'no_of_positions',
        'priority',
        'notice_period_days',
        'job_description',
        'closed_reason',
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
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'requirement_date' => 'date',
            'closed_at' => 'datetime',
            'no_of_positions' => 'integer',
            'positions_filled' => 'integer',
            'notice_period_days' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function clientManager(): BelongsTo
    {
        return $this->belongsTo(ClientManager::class)->withTrashed();
    }

    public function practiceArea(): BelongsTo
    {
        return $this->belongsTo(PracticeArea::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_skills')->withPivot('skill_type')->withTimestamps();
    }

    public function recruiters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_recruiters')->withPivot(['is_primary', 'assigned_at', 'assigned_by'])->withTimestamps();
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'job_vendors')->withPivot('assigned_at')->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function isInternal(): bool
    {
        return $this->client_id === null;
    }

    /**
     * Next job code for a country + practice area: {ISO3}-{PRACTICE}-{00001}, e.g. IND-JAVA-00001.
     * Numbering is per prefix and includes deleted jobs, so a number is never reused.
     * Call inside a transaction: matching rows are locked so concurrent saves can't take the same number.
     */
    public static function nextCode(Country $country, PracticeArea $practiceArea): string
    {
        $prefix = strtoupper(($country->iso3_code ?: $country->code).'-'.$practiceArea->code);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);

        $lastNumber = static::withTrashed()
            ->whereRaw("job_code LIKE ? ESCAPE '!'", ["{$escaped}-%"])
            ->lockForUpdate()
            ->pluck('job_code')
            ->map(fn (string $code) => preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $code, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%05d', $prefix, $lastNumber + 1);
    }
}
