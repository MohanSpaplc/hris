<?php

namespace App\Http\Requests\User;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSelf = $this->user()->is($this->route('user'));

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-]{7,30}$/'],
            // A user can never change their own role or status (no self-escalation / self-lockout).
            'role_id' => [
                Rule::prohibitedIf($isSelf), 'sometimes', 'integer',
                Rule::exists('roles', 'id')->where('status_id', Status::idFor(Status::MODULE_GENERAL, 'active')),
            ],
            'status_id' => [
                Rule::prohibitedIf($isSelf), 'sometimes', 'integer',
                Rule::exists('statuses', 'id')->where('module', Status::MODULE_GENERAL),
            ],
        ];
    }
}
