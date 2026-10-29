<?php

namespace App\Http\Requests\Candidate;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CandidateRequest',
    required: ['first_name'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Anita'),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, nullable: true, example: 'Sharma'),
        new OA\Property(property: 'email', description: 'Must not match another candidate', type: 'string', format: 'email', nullable: true, example: 'anita.sharma@example.com'),
        new OA\Property(property: 'phone', description: 'Must not match another candidate', type: 'string', maxLength: 30, nullable: true, example: '+91 9876543210'),
        new OA\Property(property: 'gender', type: 'string', enum: Candidate::GENDERS, nullable: true, example: 'female'),
        new OA\Property(property: 'current_designation', type: 'string', nullable: true, example: 'Java Developer'),
        new OA\Property(property: 'current_company', type: 'string', nullable: true, example: 'Acme Corp'),
        new OA\Property(property: 'total_experience', description: 'Years, 0–60', type: 'number', nullable: true, example: 6),
        new OA\Property(property: 'relevant_experience', description: 'Years, ≤ total_experience', type: 'number', nullable: true, example: 4),
        new OA\Property(property: 'current_ctc', type: 'number', nullable: true, example: 1200000),
        new OA\Property(property: 'expected_ctc', type: 'number', nullable: true, example: 1600000),
        new OA\Property(property: 'ctc_currency_code', description: 'Optional: with a CTC it defaults to the job\'s country currency, else INR', type: 'string', maxLength: 3, nullable: true, example: 'INR'),
        new OA\Property(property: 'notice_period_days', type: 'integer', maximum: 365, nullable: true, example: 30),
        new OA\Property(property: 'resume_source', type: 'string', enum: Candidate::RESUME_SOURCES, nullable: true, example: 'vendor'),
        new OA\Property(property: 'vendor_id', description: 'Active vendor; required when resume_source is vendor', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'referral_name', description: 'Required when resume_source is referral', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'preferred_location', type: 'string', nullable: true, example: 'Bengaluru'),
        new OA\Property(property: 'current_location', type: 'string', nullable: true, example: 'Hyderabad'),
        new OA\Property(property: 'education_details', description: 'Highest qualification, free text', type: 'string', maxLength: 200, nullable: true, example: 'MCA'),
        new OA\Property(property: 'status_id', description: 'Status of module "candidate". Defaults to Active.', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'job_id', description: 'Optional open / on-hold job: tags the candidate to it at stage Sourced (skipped if already tagged)', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'skills', description: 'Replaces the current list when sent. New names are added to the skill master.', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Java'),
            new OA\Property(property: 'skill_type', type: 'string', enum: Candidate::SKILL_TYPES, example: 'primary'),
            new OA\Property(property: 'experience_months', type: 'integer', nullable: true, example: 48),
        ], type: 'object')),
        new OA\Property(property: 'educations', description: 'Replaces the current list when sent', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'qualification', type: 'string', example: 'B.Tech'),
            new OA\Property(property: 'institution', type: 'string', nullable: true, example: 'JNTU Hyderabad'),
            new OA\Property(property: 'specialization', type: 'string', nullable: true, example: 'Computer Science'),
            new OA\Property(property: 'year_of_passing', type: 'integer', nullable: true, example: 2018),
            new OA\Property(property: 'percentage_or_cgpa', type: 'number', nullable: true, example: 8.2),
        ], type: 'object')),
        new OA\Property(property: 'employments', description: 'Replaces the current list when sent', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'company_name', type: 'string', example: 'Acme Corp'),
            new OA\Property(property: 'designation', type: 'string', nullable: true, example: 'Java Developer'),
            new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true, example: '2021-06-01'),
            new OA\Property(property: 'end_date', description: 'Empty when is_current', type: 'string', format: 'date', nullable: true, example: null),
            new OA\Property(property: 'is_current', type: 'boolean', example: true),
            new OA\Property(property: 'salary', type: 'number', nullable: true, example: 1200000),
            new OA\Property(property: 'currency_code', type: 'string', nullable: true, example: 'INR'),
            new OA\Property(property: 'responsibilities', type: 'string', nullable: true),
        ], type: 'object')),
    ],
)]
class CandidateRequest extends FormRequest
{
    private const DEFAULT_CURRENCY = 'INR';

    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isUpdate() ? 'candidates.update' : 'candidates.create');
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
        $year = (int) date('Y');

        return [
            'first_name' => [$required, 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'gender' => ['nullable', Rule::in(Candidate::GENDERS)],
            'current_designation' => ['nullable', 'string', 'max:200'],
            'current_company' => ['nullable', 'string', 'max:200'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'current_ctc' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'ctc_currency_code' => ['nullable', 'string', 'size:3', 'alpha'], // filled in prepareForValidation() when a CTC is sent without it
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'resume_source' => ['nullable', Rule::in(Candidate::RESUME_SOURCES)],
            'vendor_id' => ['nullable', 'integer', 'required_if:resume_source,vendor', Rule::exists('vendors', 'id')->whereNull('deleted_at')->where('status_id', $active)],
            'referral_name' => ['nullable', 'string', 'max:200', 'required_if:resume_source,referral'],
            'preferred_location' => ['nullable', 'string', 'max:200'],
            'current_location' => ['nullable', 'string', 'max:200'],
            'education_details' => ['nullable', 'string', 'max:200'],
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('module', Candidate::STATUS_MODULE)],
            'job_id' => ['nullable', 'integer', Rule::exists('jobs', 'id')->whereNull('deleted_at')->whereIn('status_id', [
                Status::idFor(Job::STATUS_MODULE, 'open'),
                Status::idFor(Job::STATUS_MODULE, 'on_hold'),
            ])],

            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*.name' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'skills.*.skill_type' => ['required', Rule::in(Candidate::SKILL_TYPES)],
            'skills.*.experience_months' => ['nullable', 'integer', 'min:0', 'max:720'],

            'educations' => ['sometimes', 'array', 'max:20'],
            'educations.*.qualification' => ['required', 'string', 'max:150'],
            'educations.*.institution' => ['nullable', 'string', 'max:255'],
            'educations.*.specialization' => ['nullable', 'string', 'max:200'],
            'educations.*.year_of_passing' => ['nullable', 'integer', 'min:1950', 'max:'.($year + 6)],
            'educations.*.percentage_or_cgpa' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'employments' => ['sometimes', 'array', 'max:30'],
            'employments.*.company_name' => ['required', 'string', 'max:255'],
            'employments.*.designation' => ['nullable', 'string', 'max:200'],
            'employments.*.start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'employments.*.end_date' => ['nullable', 'date', 'after_or_equal:employments.*.start_date'],
            'employments.*.is_current' => ['sometimes', 'boolean'],
            'employments.*.salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'employments.*.currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'employments.*.responsibilities' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, spaces, + ( ) - only).',
            'skills.*.name.distinct' => 'This skill is listed twice.',
            'job_id.exists' => 'Select an open or on-hold job.',
        ];
    }

    /**
     * Rules that compare fields (using the saved values for fields not sent on update) and the duplicate check.
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

                $candidate = $this->route('candidate');
                $value = fn (string $field) => $this->has($field) ? $this->input($field) : $candidate?->{$field};

                $total = $value('total_experience');
                $relevant = $value('relevant_experience');
                if ($total !== null && $relevant !== null && (float) $relevant > (float) $total) {
                    $validator->errors()->add('relevant_experience', 'Relevant experience cannot be more than total experience.');
                }

                $employments = collect($this->input('employments', []));
                if ($employments->filter(fn ($employment) => filter_var($employment['is_current'] ?? false, FILTER_VALIDATE_BOOLEAN))->count() > 1) {
                    $validator->errors()->add('employments', 'Only one employment can be marked as current.');
                }
                foreach ($employments as $index => $employment) {
                    if (filter_var($employment['is_current'] ?? false, FILTER_VALIDATE_BOOLEAN) && filled($employment['end_date'] ?? null)) {
                        $validator->errors()->add("employments.{$index}.end_date", 'A current job cannot have an end date.');
                    }
                }

                foreach (['email', 'phone'] as $field) {
                    if (! $this->filled($field)) {
                        continue;
                    }

                    $duplicate = Candidate::query()
                        ->whereRaw("LOWER({$field}) = ?", [mb_strtolower($this->input($field))])
                        ->when($candidate, fn ($query) => $query->whereKeyNot($candidate->id))
                        ->first(['id', 'candidate_code']);

                    if ($duplicate) {
                        $validator->errors()->add($field, "A candidate with this {$field} already exists ({$duplicate->candidate_code}).");
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('email')) {
            $normalized['email'] = mb_strtolower(trim((string) $this->input('email')));
        }
        if ($this->filled('phone')) {
            $normalized['phone'] = preg_replace('/\s+/', ' ', trim((string) $this->input('phone')));
        }
        if ($this->filled('ctc_currency_code')) {
            $normalized['ctc_currency_code'] = strtoupper((string) $this->input('ctc_currency_code'));
        } elseif ($this->filled('current_ctc') || $this->filled('expected_ctc')) {
            // The form has no currency field: use the selected job's country currency, else the saved one, else INR.
            $jobCurrency = is_numeric($this->input('job_id'))
                ? Job::query()->whereKey($this->input('job_id'))->join('countries', 'countries.id', '=', 'jobs.country_id')->value('countries.currency_code')
                : null;
            $normalized['ctc_currency_code'] = $jobCurrency ?? $this->route('candidate')?->ctc_currency_code ?? self::DEFAULT_CURRENCY;
        }
        if (is_array($this->input('skills'))) {
            $normalized['skills'] = array_values(array_filter(
                array_map(fn ($skill) => is_array($skill) && is_string($skill['name'] ?? null) ? [...$skill, 'name' => trim($skill['name'])] : $skill, $this->input('skills')),
                fn ($skill) => ! is_array($skill) || ($skill['name'] ?? '') !== '',
            ));
        }
        if (is_array($this->input('employments'))) {
            $normalized['employments'] = array_map(
                fn ($employment) => is_array($employment) && filled($employment['currency_code'] ?? null) ? [...$employment, 'currency_code' => strtoupper((string) $employment['currency_code'])] : $employment,
                $this->input('employments'),
            );
        }

        $this->merge($normalized);
    }

    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }
}
