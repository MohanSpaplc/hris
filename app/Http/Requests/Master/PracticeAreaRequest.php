<?php

namespace App\Http\Requests\Master;

use App\Models\Status;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PracticeAreaRequest',
    required: ['name', 'code'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Java & Microservices'),
        new OA\Property(property: 'code', description: 'Letters, numbers, dash, underscore', type: 'string', maxLength: 50, example: 'JAVA'),
        new OA\Property(property: 'head_id', description: 'Active user who heads this practice', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'status_id', description: 'Status of module "general". Defaults to Active.', type: 'integer', nullable: true, example: 1),
    ],
)]
class PracticeAreaRequest extends MasterRequest
{
    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('practice_areas', 'code')->ignore($this->route('practice_area')?->id),
            ],
            'head_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at')->where('status_id', Status::idFor(Status::MODULE_GENERAL, 'active')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
