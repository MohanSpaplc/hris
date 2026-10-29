<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Vendor
 */
#[OA\Schema(
    schema: 'Vendor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'TalentBridge Staffing Solutions'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'accounts@talentbridge.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 22 6100 2000'),
        new OA\Property(property: 'contact_person', type: 'string', nullable: true, example: 'Rohit Desai'),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class VendorResource extends JsonResource
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
            'contact_person' => $this->contact_person,
            'status' => new StatusResource($this->whenLoaded('status')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
