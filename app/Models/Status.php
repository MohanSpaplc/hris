<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Status extends Model
{
    public const MODULE_GENERAL = 'general';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'code',
        'name',
        'color',
        'sort_order',
        'is_default',
        'is_final',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_final' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve a status id by module and code. Never hard-code status ids.
     */
    public static function idFor(string $module, string $code): int
    {
        return Cache::rememberForever(
            "status_id:{$module}:{$code}",
            fn () => static::query()->where('module', $module)->where('code', $code)->valueOrFail('id'),
        );
    }

    protected static function booted(): void
    {
        static::saved(fn (Status $status) => Cache::forget("status_id:{$status->module}:{$status->code}"));
    }
}
