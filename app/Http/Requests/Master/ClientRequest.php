<?php

namespace App\Http\Requests\Master;

use App\Models\Status;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ClientRequest',
    required: ['country_id', 'name'],
    properties: [
        new OA\Property(property: 'country_id', description: 'Active country', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', maxLength: 200, example: 'Northwind Technologies Pvt Ltd'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'contact@northwind.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 80 4000 1000'),
        new OA\Property(property: 'address', type: 'string', maxLength: 1000, nullable: true, example: 'Whitefield, Bengaluru, Karnataka 560066'),
        new OA\Property(property: 'status_id', description: 'Status of module "general". Defaults to Active.', type: 'integer', nullable: true, example: 1),
    ],
)]
class ClientRequest extends MasterRequest
{
    protected function fieldRules(): array
    {
        return [
            'country_id' => [
                'required', 'integer',
                Rule::exists('countries', 'id')->where('status_id', Status::idFor(Status::MODULE_GENERAL, 'active')),
            ],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
