<?php

namespace App\Models\Concerns;

use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models whose status_id points to a status of the "general" module (active / inactive).
 */
trait HasStatus
{
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status_id'), Status::idFor(Status::MODULE_GENERAL, 'active'));
    }

    protected static function bootHasStatus(): void
    {
        static::creating(function ($model) {
            $model->status_id ??= Status::idFor(Status::MODULE_GENERAL, 'active');
        });
    }
}
