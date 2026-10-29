<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory, HasStatus;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'iso3_code',
        'flag',
        'currency_code',
        'currency_name',
        'currency_symbol',
        'dial_code',
        'timezone',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
