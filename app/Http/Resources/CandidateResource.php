<?php

namespace App\Http\Resources;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Candidate
 */
#[OA\Schema(
    schema: 'Candidate',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'candidate_code', type: 'string', example: 'CAN-00001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'Anita'),
        new OA\Property(property: 'last_name', type: 'string', nullable: true, example: 'Sharma'),
        new OA\Property(property: 'full_name', type: 'string', example: 'Anita Sharma'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'anita.sharma@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 9876543210'),
        new OA\Property(property: 'gender', type: 'string', nullable: true, example: 'female'),
        new OA\Property(property: 'current_designation', type: 'string', nullable: true, example: 'Java Developer'),
        new OA\Property(property: 'current_company', type: 'string', nullable: true, example: 'Acme Corp'),
        new OA\Property(property: 'total_experience', type: 'string', nullable: true, example: '6.0'),
        new OA\Property(property: 'relevant_experience', type: 'string', nullable: true, example: '4.0'),
        new OA\Property(property: 'current_ctc', type: 'string', nullable: true, example: '1200000.00'),
        new OA\Property(property: 'expected_ctc', type: 'string', nullable: true, example: '1600000.00'),
        new OA\Property(property: 'ctc_currency_code', type: 'string', nullable: true, example: 'INR'),
        new OA\Property(property: 'notice_period_days', type: 'integer', nullable: true, example: 30),
        new OA\Property(property: 'resume_source', type: 'string', nullable: true, example: 'vendor'),
        new OA\Property(property: 'vendor', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object', nullable: true),
        new OA\Property(property: 'referral_name', type: 'string', nullable: true),
        new OA\Property(property: 'preferred_location', type: 'string', nullable: true, example: 'Bengaluru'),
        new OA\Property(property: 'current_location', type: 'string', nullable: true, example: 'Hyderabad'),
        new OA\Property(property: 'education_details', type: 'string', nullable: true, example: 'MCA'),
        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string', example: 'Java'),
            new OA\Property(property: 'skill_type', type: 'string', example: 'primary'),
            new OA\Property(property: 'experience_months', type: 'integer', nullable: true, example: 48),
        ], type: 'object')),
        new OA\Property(property: 'educations', description: 'Details only', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'employments', description: 'Details only', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'applications', description: 'Jobs the candidate is tagged to (details only)', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'job', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'job_code', type: 'string'), new OA\Property(property: 'job_title', type: 'string')], type: 'object'),
            new OA\Property(property: 'stage', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'code', type: 'string', example: 'sourced'), new OA\Property(property: 'name', type: 'string', example: 'Sourced')], type: 'object'),
            new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
            new OA\Property(property: 'applied_at', type: 'string', format: 'date-time'),
        ], type: 'object')),
        new OA\Property(property: 'resume', ref: '#/components/schemas/CandidateDocument', description: 'Current resume, download with GET /candidates/{id}/documents/{document}', nullable: true),
        new OA\Property(property: 'status', ref: '#/components/schemas/Status'),
        new OA\Property(property: 'created_by', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')], type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class CandidateResource extends JsonResource
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
            'candidate_code' => $this->candidate_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'current_designation' => $this->current_designation,
            'current_company' => $this->current_company,
            'total_experience' => $this->total_experience,
            'relevant_experience' => $this->relevant_experience,
            'current_ctc' => $this->current_ctc,
            'expected_ctc' => $this->expected_ctc,
            'ctc_currency_code' => $this->ctc_currency_code,
            'notice_period_days' => $this->notice_period_days,
            'resume_source' => $this->resume_source,
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor?->only(['id', 'name'])),
            'referral_name' => $this->referral_name,
            'preferred_location' => $this->preferred_location,
            'current_location' => $this->current_location,
            'education_details' => $this->education_details,
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'skill_type' => $skill->pivot->skill_type,
                'experience_months' => $skill->pivot->experience_months,
            ])->values()),
            'educations' => $this->whenLoaded('educations', fn () => $this->educations->map(fn ($education) => [
                'id' => $education->id,
                ...$education->only(['qualification', 'institution', 'specialization', 'year_of_passing', 'percentage_or_cgpa']),
            ])->values()),
            'employments' => $this->whenLoaded('employments', fn () => $this->employments->map(fn ($employment) => [
                'id' => $employment->id,
                ...$employment->only(['company_name', 'designation', 'is_current', 'salary', 'currency_code', 'responsibilities']),
                'start_date' => $employment->start_date?->toDateString(),
                'end_date' => $employment->end_date?->toDateString(),
            ])->values()),
            'applications' => $this->whenLoaded('applications', fn () => $this->applications->map(fn ($application) => [
                'id' => $application->id,
                'job' => $application->job?->only(['id', 'job_code', 'job_title']),
                'stage' => $application->stage?->only(['id', 'code', 'name']),
                'status' => new StatusResource($application->status),
                'applied_at' => $application->applied_at,
            ])->values()),
            'resume' => $this->whenLoaded('currentResume', fn () => $this->currentResume ? new CandidateDocumentResource($this->currentResume) : null),
            'status' => new StatusResource($this->whenLoaded('status')),
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => trim("{$this->creator->first_name} {$this->creator->last_name}")]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
