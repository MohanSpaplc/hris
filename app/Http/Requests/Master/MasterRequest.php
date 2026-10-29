<?php

namespace App\Http\Requests\Master;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared base for master-data create/update requests.
 * On update (PUT/PATCH) every field becomes "sometimes", so partial updates work.
 */
abstract class MasterRequest extends FormRequest
{
    /**
     * Field rules for creating a record.
     *
     * @return array<string, mixed>
     */
    abstract protected function fieldRules(): array;

    /**
     * Check permission before validating, so users without access get 403 (not 422).
     * The policy is the same for every master model, so the model class is not needed here.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->isUpdate() ? 'masters.update' : 'masters.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->fieldRules() + [
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('module', Status::MODULE_GENERAL)],
        ];

        if ($this->isUpdate()) {
            $rules = array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);
        }

        return $rules;
    }

    protected function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    /**
     * Normalise email to lower case (strings are already trimmed by Laravel's global middleware).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
