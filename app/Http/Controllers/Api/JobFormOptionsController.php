<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class JobFormOptionsController extends Controller
{
    #[OA\Get(
        path: '/jobs/form-options',
        summary: 'All dropdown data for job screens in one request',
        description: 'Active countries, practice areas, clients, vendors (A–Z), job statuses, and active users when the caller may view users. Replaces 7 separate list calls.',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Options', content: new OA\JsonContent(example: ['data' => [
                'job_statuses' => [['id' => 4, 'code' => 'draft', 'name' => 'Draft', 'color' => 'secondary', 'is_final' => false]],
                'countries' => [['id' => 1, 'name' => 'India', 'code' => 'IN', 'currency_code' => 'INR']],
                'practice_areas' => [['id' => 1, 'name' => 'Java & Microservices', 'code' => 'JAVA']],
                'clients' => [['id' => 1, 'name' => 'Northwind Technologies Pvt Ltd', 'country' => ['id' => 1, 'name' => 'India', 'code' => 'IN']]],
                'vendors' => [['id' => 1, 'name' => 'TalentBridge Staffing Solutions', 'contact_person' => 'Rohit Desai']],
                'users' => [['id' => 1, 'first_name' => 'HR', 'last_name' => 'Admin', 'role' => ['name' => 'HR']]],
            ]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Job::class);

        $active = Status::idFor(Status::MODULE_GENERAL, 'active');

        return response()->json(['data' => [
            'job_statuses' => Status::query()
                ->where('module', Job::STATUS_MODULE)->where('is_active', true)->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'color', 'is_final']),
            'countries' => Country::query()->where('status_id', $active)->orderBy('name')
                ->get(['id', 'name', 'code', 'iso3_code', 'currency_code']),
            'practice_areas' => PracticeArea::query()->where('status_id', $active)->orderBy('name')
                ->get(['id', 'name', 'code']),
            'clients' => Client::query()->where('status_id', $active)->with('country:id,name,code')->orderBy('name')
                ->get(['id', 'name', 'country_id', 'logo_path'])
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'logo_url' => $client->logo_path ? Storage::disk('public')->url($client->logo_path) : null,
                    'country' => $client->country?->only(['id', 'name', 'code']),
                ]),
            'vendors' => Vendor::query()->where('status_id', $active)->orderBy('name')
                ->get(['id', 'name', 'contact_person']),
            'users' => $request->user()->hasPermission('users.view')
                ? User::query()->where('status_id', $active)->with('role:id,name')->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'role_id'])
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'role' => $user->role?->only(['name']),
                    ])
                : [],
        ]]);
    }
}
