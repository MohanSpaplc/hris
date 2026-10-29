<?php

namespace App\Http\Resources;

use App\Models\ClientManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin ClientManager
 */
#[OA\Schema(
    schema: 'ClientManager',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Meera Joshi'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'meera.joshi@northwind.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 98450 11111'),
        new OA\Property(property: 'client', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Northwind Technologies Pvt Ltd'),
        ], type: 'object'),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class ClientManagerResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'client' => $this->whenLoaded('client', fn () => $this->client?->only(['id', 'name'])),
            'status' => new StatusResource($this->whenLoaded('status')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
