<?php

namespace App\Http\Requests\Master;

use App\Models\Status;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ClientManagerRequest',
    required: ['client_id', 'name'],
    properties: [
        new OA\Property(property: 'client_id', description: 'Active client', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', maxLength: 200, example: 'Meera Joshi'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'meera.joshi@northwind.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 98450 11111'),
        new OA\Property(property: 'status_id', description: 'Status of module "general". Defaults to Active.', type: 'integer', nullable: true, example: 1),
    ],
)]
class ClientManagerRequest extends MasterRequest
{
    protected function fieldRules(): array
    {
        return [
            'client_id' => [
                'required', 'integer',
                Rule::exists('clients', 'id')->whereNull('deleted_at')->where('status_id', Status::idFor(Status::MODULE_GENERAL, 'active')),
            ],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
        ];
    }
}
