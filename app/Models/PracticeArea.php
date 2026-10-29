<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Database\Factories\PracticeAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeArea extends Model
{
    /** @use HasFactory<PracticeAreaFactory> */
    use HasFactory, HasStatus;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'head_id',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }
}
