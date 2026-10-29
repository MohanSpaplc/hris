<?php

namespace App\Http\Requests\Job;

use App\Models\ClientManager;
use App\Models\Country;
use App\Models\Job;
use App\Models\Status;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'JobRequest',
    required: ['country_id', 'practice_area_id', 'job_title'],
    properties: [
        new OA\Property(property: 'job_title', type: 'string', maxLength: 200, example: 'Senior Java Developer'),
        new OA\Property(property: 'country_id', description: 'Active country', type: 'integer', example: 1),
        new OA\Property(property: 'client_id', description: 'Active client. null = internal job', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'client_manager_id', description: 'Must belong to client_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'practice_area_id', description: 'Active practice area', type: 'integer', example: 1),
        new OA\Property(property: 'total_experience', description: 'Years, 0–60', type: 'number', nullable: true, example: 6),
        new OA\Property(property: 'relevant_experience', description: 'Years, ≤ total_experience', type: 'number', nullable: true, example: 4),
        new OA\Property(property: 'budget_min', type: 'number', nullable: true, example: 1800000),
        new OA\Property(property: 'budget_max', description: '≥ budget_min', type: 'number', nullable: true, example: 2400000),
        new OA\Property(property: 'budget_currency_code', description: 'Optional: defaults to the country\'s currency when a budget is given', type: 'string', maxLength: 3, nullable: true, example: 'INR'),
        new OA\Property(property: 'requirement_date', type: 'string', format: 'date', nullable: true, example: '2026-11-15'),
        new OA\Property(property: 'contract_duration', type: 'string', nullable: true, example: '12 months'),
        new OA\Property(property: 'work_mode', type: 'string', enum: Job::WORK_MODES, nullable: true, example: 'hybrid'),
        new OA\Property(property: 'work_location', type: 'string', nullable: true, example: 'Bengaluru'),
        new OA\Property(property: 'employment_type', type: 'string', enum: Job::EMPLOYMENT_TYPES, nullable: true, example: 'full_time'),
        new OA\Property(property: 'no_of_positions', type: 'integer', minimum: 1, maximum: 1000, example: 2),
        new OA\Property(property: 'priority', type: 'string', enum: Job::PRIORITIES, example: 'high'),
        new OA\Property(property: 'notice_period_days', type: 'integer', maximum: 365, nullable: true, example: 30),
        new OA\Property(property: 'job_description', description: 'Rich text HTML (CKEditor). Unsafe tags/attributes are removed.', type: 'string', maxLength: 60000, nullable: true, example: '<p>Build and maintain <strong>Spring Boot</strong> microservices.</p>'),
        new OA\Property(property: 'primary_skills', description: 'Skill names; new ones are added to the skill master', type: 'array', items: new OA\Items(type: 'string'), example: ['Java', 'Spring Boot']),
        new OA\Property(property: 'secondary_skills', type: 'array', items: new OA\Items(type: 'string'), example: ['Kafka', 'Docker']),
        new OA\Property(property: 'recruiter_ids', description: 'Active users', type: 'array', items: new OA\Items(type: 'integer'), example: [1]),
        new OA\Property(property: 'primary_recruiter_id', description: 'One of recruiter_ids', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'vendor_ids', description: 'Active vendors', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
        new OA\Property(property: 'status_id', description: 'Status of module "job". Defaults to Draft.', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'closed_reason', type: 'string', maxLength: 255, nullable: true, example: null),
    ],
)]
class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isUpdate() ? 'jobs.update' : 'jobs.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $active = Status::idFor(Status::MODULE_GENERAL, 'active');
        $required = $this->isUpdate() ? 'sometimes' : 'required';

        return [
            'job_title' => [$required, 'string', 'max:200'],
            'country_id' => [$required, 'integer', Rule::exists('countries', 'id')->where('status_id', $active)],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')->where('status_id', $active)],
            'client_manager_id' => ['nullable', 'integer', Rule::exists('client_managers', 'id')->whereNull('deleted_at')->where('status_id', $active)],
            'practice_area_id' => [$required, 'integer', Rule::exists('practice_areas', 'id')->where('status_id', $active)],

            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'budget_currency_code' => ['nullable', 'string', 'size:3', 'alpha', 'required_with:budget_min,budget_max'], // filled from the country in prepareForValidation()
            'requirement_date' => ['nullable', 'date'],
            'contract_duration' => ['nullable', 'string', 'max:100'],
            'work_mode' => ['nullable', Rule::in(Job::WORK_MODES)],
            'work_location' => ['nullable', 'string', 'max:200'],
            'employment_type' => ['nullable', Rule::in(Job::EMPLOYMENT_TYPES)],
            'no_of_positions' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'priority' => ['sometimes', Rule::in(Job::PRIORITIES)],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'job_description' => ['nullable', 'string', 'max:60000'], // HTML from the editor; cleaned in passedValidation()
            'closed_reason' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('module', Job::STATUS_MODULE)],

            'primary_skills' => ['sometimes', 'array', 'max:30'],
            'primary_skills.*' => ['string', 'distinct:ignore_case', 'max:150'],
            'secondary_skills' => ['sometimes', 'array', 'max:30'],
            'secondary_skills.*' => ['string', 'distinct:ignore_case', 'max:150'],

            'recruiter_ids' => ['sometimes', 'array', 'max:20'],
            'recruiter_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status_id', $active)],
            'primary_recruiter_id' => ['nullable', 'integer'],
            'vendor_ids' => ['sometimes', 'array', 'max:50'],
            'vendor_ids.*' => ['integer', 'distinct', Rule::exists('vendors', 'id')->whereNull('deleted_at')->where('status_id', $active)],
        ];
    }

    /**
     * Rules that compare fields, using the saved job's values for fields not sent on update.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $job = $this->route('job');
                $value = fn (string $field) => $this->has($field) ? $this->input($field) : $job?->{$field};

                $total = $value('total_experience');
                $relevant = $value('relevant_experience');
                if ($total !== null && $relevant !== null && (float) $relevant > (float) $total) {
                    $validator->errors()->add('relevant_experience', 'Relevant experience cannot be more than total experience.');
                }

                $min = $value('budget_min');
                $max = $value('budget_max');
                if ($min !== null && $max !== null && (float) $max < (float) $min) {
                    $validator->errors()->add('budget_max', 'Maximum budget must be greater than or equal to the minimum.');
                }

                $clientId = $value('client_id');
                $managerId = $value('client_manager_id');
                if ($managerId && ! $clientId) {
                    $validator->errors()->add('client_manager_id', 'An internal job cannot have a client manager.');
                } elseif ($managerId && ClientManager::whereKey($managerId)->value('client_id') !== (int) $clientId) {
                    $validator->errors()->add('client_manager_id', 'This manager does not belong to the selected client.');
                }

                if ($job && $this->has('no_of_positions') && (int) $this->input('no_of_positions') < $job->positions_filled) {
                    $validator->errors()->add('no_of_positions', "Positions cannot be less than the {$job->positions_filled} already filled.");
                }

                $primaryRecruiter = $this->input('primary_recruiter_id');
                if ($primaryRecruiter && ! in_array((int) $primaryRecruiter, array_map('intval', (array) $this->input('recruiter_ids', [])), true)) {
                    $validator->errors()->add('primary_recruiter_id', 'The primary recruiter must be one of the selected recruiters.');
                }

                $overlap = array_intersect(
                    array_map('mb_strtolower', (array) $this->input('primary_skills', [])),
                    array_map('mb_strtolower', (array) $this->input('secondary_skills', [])),
                );
                if ($overlap) {
                    $validator->errors()->add('secondary_skills', 'A skill cannot be both primary and secondary: '.implode(', ', $overlap).'.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('budget_currency_code')) {
            $normalized['budget_currency_code'] = strtoupper((string) $this->input('budget_currency_code'));
        } elseif ($this->filled('budget_min') || $this->filled('budget_max')) {
            // No currency sent with a budget: use the country's currency (the form has no currency field).
            $countryId = $this->input('country_id', $this->route('job')?->country_id);
            $countryCurrency = is_numeric($countryId) ? Country::whereKey($countryId)->value('currency_code') : null;
            if ($countryCurrency) {
                $normalized['budget_currency_code'] = $countryCurrency;
            }
        }

        // Trim skill names and drop empty entries.
        foreach (['primary_skills', 'secondary_skills'] as $field) {
            if (is_array($this->input($field))) {
                $normalized[$field] = array_values(array_filter(array_map(fn ($skill) => is_string($skill) ? trim($skill) : $skill, $this->input($field)), fn ($skill) => $skill !== '' && $skill !== null));
            }
        }

        $this->merge($normalized);
    }

    /**
     * After validation: clean the description HTML.
     */
    protected function passedValidation(): void
    {
        $extra = [];

        if ($this->has('job_description')) {
            $extra['job_description'] = HtmlSanitizer::clean($this->input('job_description'));
        }

        if ($extra) {
            $this->merge($extra);
            // validated() is cached from the request data before merge(); rebuild it so safe()/validated() see the changes.
            $this->validator->setData($this->all());
        }
    }

    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }
}
