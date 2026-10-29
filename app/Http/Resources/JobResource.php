<?php

namespace App\Http\Resources;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * @mixin Job
 */
#[OA\Schema(
    schema: 'Job',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'job_code', type: 'string', example: 'JOB-2026-00001'),
        new OA\Property(property: 'job_title', type: 'string', example: 'Senior Java Developer'),
        new OA\Property(property: 'is_internal', type: 'boolean', example: false),
        new OA\Property(property: 'country', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'code', type: 'string')], type: 'object'),
        new OA\Property(property: 'client', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'logo_url', type: 'string', nullable: true)], type: 'object', nullable: true),
        new OA\Property(property: 'client_manager', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'email', type: 'string', nullable: true)], type: 'object', nullable: true),
        new OA\Property(property: 'practice_area', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'code', type: 'string')], type: 'object'),
        new OA\Property(property: 'total_experience', type: 'string', nullable: true, example: '6.0'),
        new OA\Property(property: 'relevant_experience', type: 'string', nullable: true, example: '4.0'),
        new OA\Property(property: 'budget_min', type: 'string', nullable: true, example: '1800000.00'),
        new OA\Property(property: 'budget_max', type: 'string', nullable: true, example: '2400000.00'),
        new OA\Property(property: 'budget_currency_code', type: 'string', nullable: true, example: 'INR'),
        new OA\Property(property: 'requirement_date', type: 'string', format: 'date', nullable: true, example: '2026-11-15'),
        new OA\Property(property: 'contract_duration', type: 'string', nullable: true),
        new OA\Property(property: 'work_mode', type: 'string', nullable: true, example: 'hybrid'),
        new OA\Property(property: 'work_location', type: 'string', nullable: true, example: 'Bengaluru'),
        new OA\Property(property: 'employment_type', type: 'string', nullable: true, example: 'full_time'),
        new OA\Property(property: 'no_of_positions', type: 'integer', example: 2),
        new OA\Property(property: 'positions_filled', type: 'integer', example: 0),
        new OA\Property(property: 'applications_count', description: 'Candidates (resumes) tagged to the job. In lists only.', type: 'integer', example: 3),
        new OA\Property(property: 'priority', type: 'string', example: 'high'),
        new OA\Property(property: 'notice_period_days', type: 'integer', nullable: true, example: 30),
        new OA\Property(property: 'job_description', description: 'Sanitized HTML', type: 'string', nullable: true),
        new OA\Property(property: 'jd_file', description: 'Download with GET /jobs/{id}/jd-file', properties: [new OA\Property(property: 'name', type: 'string', example: 'Java-Developer-JD.pdf')], type: 'object', nullable: true),
        new OA\Property(property: 'primary_skills', type: 'array', items: new OA\Items(type: 'string'), example: ['Java', 'Spring Boot']),
        new OA\Property(property: 'secondary_skills', type: 'array', items: new OA\Items(type: 'string'), example: ['Kafka']),
        new OA\Property(property: 'recruiters', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'is_primary', type: 'boolean'),
            new OA\Property(property: 'assigned_at', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object')),
        new OA\Property(property: 'vendors', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object')),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'closed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'closed_reason', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class JobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = fn ($user) => trim("{$user->first_name} {$user->last_name}");

        return [
            'id' => $this->id,
            'job_code' => $this->job_code,
            'job_title' => $this->job_title,
            'is_internal' => $this->isInternal(),
            'country' => $this->whenLoaded('country', fn () => $this->country?->only(['id', 'name', 'code'])),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'logo_url' => $this->client->logo_path ? Storage::disk('public')->url($this->client->logo_path) : null,
            ] : null),
            'client_manager' => $this->whenLoaded('clientManager', fn () => $this->clientManager?->only(['id', 'name', 'email'])),
            'practice_area' => $this->whenLoaded('practiceArea', fn () => $this->practiceArea?->only(['id', 'name', 'code'])),
            'total_experience' => $this->total_experience,
            'relevant_experience' => $this->relevant_experience,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'budget_currency_code' => $this->budget_currency_code,
            'requirement_date' => $this->requirement_date?->toDateString(),
            'contract_duration' => $this->contract_duration,
            'work_mode' => $this->work_mode,
            'work_location' => $this->work_location,
            'employment_type' => $this->employment_type,
            'no_of_positions' => $this->no_of_positions,
            'positions_filled' => $this->positions_filled,
            'applications_count' => $this->whenCounted('applications'),
            'priority' => $this->priority,
            'notice_period_days' => $this->notice_period_days,
            'job_description' => $this->job_description,
            'jd_file' => $this->jd_file_path ? ['name' => $this->jd_file_name] : null,
            'primary_skills' => $this->whenLoaded('skills', fn () => $this->skills->where('pivot.skill_type', 'primary')->pluck('name')->values()),
            'secondary_skills' => $this->whenLoaded('skills', fn () => $this->skills->where('pivot.skill_type', 'secondary')->pluck('name')->values()),
            'recruiters' => $this->whenLoaded('recruiters', fn () => $this->recruiters->map(fn ($user) => [
                'id' => $user->id,
                'name' => $fullName($user),
                'is_primary' => (bool) $user->pivot->is_primary,
                'assigned_at' => $user->pivot->assigned_at,
            ])->values()),
            'vendors' => $this->whenLoaded('vendors', fn () => $this->vendors->map->only(['id', 'name'])->values()),
            'status' => new StatusResource($this->whenLoaded('status')),
            'closed_at' => $this->closed_at,
            'closed_reason' => $this->closed_reason,
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $fullName($this->creator)]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
