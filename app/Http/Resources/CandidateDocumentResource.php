<?php

namespace App\Http\Resources;

use App\Models\CandidateDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CandidateDocument
 */
#[OA\Schema(
    schema: 'CandidateDocument',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'document_type', type: 'string', enum: CandidateDocument::TYPES, example: 'resume'),
        new OA\Property(property: 'original_filename', type: 'string', example: 'Anita-Sharma-Resume.pdf'),
        new OA\Property(property: 'mime_type', type: 'string', nullable: true, example: 'application/pdf'),
        new OA\Property(property: 'file_size_bytes', type: 'integer', nullable: true, example: 184320),
        new OA\Property(property: 'version', type: 'integer', example: 2),
        new OA\Property(property: 'is_current', type: 'boolean', example: true),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'uploaded_by', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object'),
        new OA\Property(property: 'verified_by', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object', nullable: true),
        new OA\Property(property: 'verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class CandidateDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = fn ($user) => $user ? ['id' => $user->id, 'name' => trim("{$user->first_name} {$user->last_name}")] : null;

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'version' => $this->version,
            'is_current' => $this->is_current,
            'status' => new StatusResource($this->whenLoaded('status')),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $user($this->uploader)),
            'verified_by' => $this->whenLoaded('verifier', fn () => $user($this->verifier)),
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
