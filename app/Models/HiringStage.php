<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A step of the hiring pipeline (Sourced → Screening → … → Joined).
 */
class HiringStage extends Model
{
    use HasStatus;

    public const SOURCED = 'sourced';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_terminal',
        'status_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_terminal' => 'boolean',
        ];
    }

    public static function idFor(string $code): int
    {
        return static::query()->where('code', $code)->valueOrFail('id');
    }
}
