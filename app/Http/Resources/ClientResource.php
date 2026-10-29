<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * @mixin Client
 */
#[OA\Schema(
    schema: 'Client',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Northwind Technologies Pvt Ltd'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'contact@northwind.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 80 4000 1000'),
        new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Whitefield, Bengaluru'),
        new OA\Property(property: 'logo_url', type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/storage/client-logos/abc123.png'),
        new OA\Property(property: 'country', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'India'),
            new OA\Property(property: 'code', type: 'string', example: 'IN'),
        ], type: 'object'),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'managers_count', type: 'integer', example: 1),
        new OA\Property(property: 'created_by', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'HR Admin'),
        ], type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class ClientResource extends JsonResource
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
            'address' => $this->address,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'country' => $this->whenLoaded('country', fn () => $this->country->only(['id', 'name', 'code'])),
            'status' => new StatusResource($this->whenLoaded('status')),
            'managers_count' => $this->whenCounted('managers'),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => trim("{$this->creator->first_name} {$this->creator->last_name}"),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
