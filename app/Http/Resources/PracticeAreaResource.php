<?php

namespace App\Http\Resources;

use App\Models\PracticeArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin PracticeArea
 */
#[OA\Schema(
    schema: 'PracticeArea',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Java & Microservices'),
        new OA\Property(property: 'code', type: 'string', example: 'JAVA'),
        new OA\Property(property: 'head', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'HR Admin'),
            new OA\Property(property: 'email', type: 'string', example: 'hr.admin@hris.local'),
        ], type: 'object', nullable: true),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class PracticeAreaResource extends JsonResource
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
            'head' => $this->whenLoaded('head', fn () => $this->head ? [
                'id' => $this->head->id,
                'name' => trim("{$this->head->first_name} {$this->head->last_name}"),
                'email' => $this->head->email,
            ] : null),
            'status' => new StatusResource($this->whenLoaded('status')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
