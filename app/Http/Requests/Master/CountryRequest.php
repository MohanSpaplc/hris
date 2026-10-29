<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CountryRequest',
    required: ['name', 'code'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'India'),
        new OA\Property(property: 'code', description: 'ISO 3166-1 alpha-2', type: 'string', maxLength: 2, example: 'IN'),
        new OA\Property(property: 'iso3_code', description: 'ISO 3166-1 alpha-3, used in job codes (IND-JAVA-00001)', type: 'string', maxLength: 3, nullable: true, example: 'IND'),
        new OA\Property(property: 'flag', type: 'string', nullable: true, example: '🇮🇳'),
        new OA\Property(property: 'currency_code', description: 'ISO 4217', type: 'string', maxLength: 3, nullable: true, example: 'INR'),
        new OA\Property(property: 'currency_name', type: 'string', nullable: true, example: 'Indian Rupee'),
        new OA\Property(property: 'currency_symbol', type: 'string', nullable: true, example: '₹'),
        new OA\Property(property: 'dial_code', type: 'string', nullable: true, example: '+91'),
        new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Asia/Kolkata'),
        new OA\Property(property: 'status_id', description: 'Status of module "general". Defaults to Active.', type: 'integer', nullable: true, example: 1),
    ],
)]
class CountryRequest extends MasterRequest
{
    protected function fieldRules(): array
    {
        $countryId = $this->route('country')?->id;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('countries', 'name')->ignore($countryId)],
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('countries', 'code')->ignore($countryId)],
            'iso3_code' => ['nullable', 'string', 'size:3', 'alpha', Rule::unique('countries', 'iso3_code')->ignore($countryId)],
            'flag' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'currency_name' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'dial_code' => ['nullable', 'string', 'max:10', 'regex:/^\+[0-9]{1,4}$/'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'code' => $this->has('code') ? strtoupper(trim((string) $this->input('code'))) : null,
            'iso3_code' => $this->filled('iso3_code') ? strtoupper(trim((string) $this->input('iso3_code'))) : null,
            'currency_code' => $this->filled('currency_code') ? strtoupper(trim((string) $this->input('currency_code'))) : null,
        ], fn ($value) => $value !== null));
    }
}
