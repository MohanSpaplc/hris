<?php

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Country
 */
#[OA\Schema(
    schema: 'Country',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'India'),
        new OA\Property(property: 'code', type: 'string', example: 'IN'),
        new OA\Property(property: 'iso3_code', type: 'string', nullable: true, example: 'IND'),
        new OA\Property(property: 'flag', type: 'string', nullable: true, example: '🇮🇳'),
        new OA\Property(property: 'currency_code', type: 'string', nullable: true, example: 'INR'),
        new OA\Property(property: 'currency_name', type: 'string', nullable: true, example: 'Indian Rupee'),
        new OA\Property(property: 'currency_symbol', type: 'string', nullable: true, example: '₹'),
        new OA\Property(property: 'dial_code', type: 'string', nullable: true, example: '+91'),
        new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Asia/Kolkata'),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'clients_count', type: 'integer', example: 2),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class CountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'iso3_code' => $this->iso3_code,
            'flag' => $this->flag,
            'currency_code' => $this->currency_code,
            'currency_name' => $this->currency_name,
            'currency_symbol' => $this->currency_symbol,
            'dial_code' => $this->dial_code,
            'timezone' => $this->timezone,
            'status' => new StatusResource($this->whenLoaded('status')),
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
