<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StatusResource;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class StatusController extends Controller
{
    #[OA\Get(
        path: '/statuses',
        summary: 'Active statuses of one module (for status dropdowns)',
        security: [['sanctum' => []]],
        tags: ['Statuses'],
        parameters: [new OA\Parameter(name: 'module', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'general'))],
        responses: [
            new OA\Response(response: 200, description: 'Statuses', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Status')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['module' => ['required', 'string', 'max:50']]);

        return StatusResource::collection(
            Status::query()->where('module', $validated['module'])->where('is_active', true)->orderBy('sort_order')->get(),
        );
    }
}
