<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, HasStatus, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * created_by and status_id are set explicitly, never from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'country_id',
        'name',
        'email',
        'phone',
        'address',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function managers(): HasMany
    {
        return $this->hasMany(ClientManager::class);
    }
}
