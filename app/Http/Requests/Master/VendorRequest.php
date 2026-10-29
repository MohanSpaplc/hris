<?php

namespace App\Http\Requests\Master;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VendorRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 200, example: 'TalentBridge Staffing Solutions'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'accounts@talentbridge.example'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 22 6100 2000'),
        new OA\Property(property: 'contact_person', type: 'string', maxLength: 200, nullable: true, example: 'Rohit Desai'),
        new OA\Property(property: 'status_id', description: 'Status of module "general". Defaults to Active.', type: 'integer', nullable: true, example: 1),
    ],
)]
class VendorRequest extends MasterRequest
{
    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'contact_person' => ['nullable', 'string', 'max:200'],
        ];
    }
}
