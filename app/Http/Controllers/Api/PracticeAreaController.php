<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\PracticeAreaRequest;
use App\Http\Resources\PracticeAreaResource;
use App\Models\PracticeArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class PracticeAreaController extends Controller
{
    #[OA\Get(
        path: '/practice-areas',
        summary: 'List practice areas',
        security: [['sanctum' => []]],
        tags: ['Practice Areas'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Search'),
            new OA\Parameter(ref: '#/components/parameters/StatusFilter'),
            new OA\Parameter(ref: '#/components/parameters/Sort'),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated practice areas', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PracticeArea')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PracticeArea::class);
        $filters = $this->validateListFilters($request);

        $practiceAreas = PracticeArea::query()
            ->with(['status', 'head'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->tap(fn ($query) => $this->applySort($query, $filters))
            ->paginate($filters['per_page'] ?? 15);

        return PracticeAreaResource::collection($practiceAreas);
    }

    #[OA\Post(
        path: '/practice-areas',
        summary: 'Create a practice area',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PracticeAreaRequest')),
        tags: ['Practice Areas'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PracticeArea')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(PracticeAreaRequest $request): JsonResponse
    {
        Gate::authorize('create', PracticeArea::class);

        $practiceArea = new PracticeArea($request->safe()->except('status_id'));
        $practiceArea->status_id = $request->validated('status_id');
        $practiceArea->save();

        return (new PracticeAreaResource($practiceArea->load(['status', 'head'])))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/practice-areas/{practice_area}',
        summary: 'Practice area details',
        security: [['sanctum' => []]],
        tags: ['Practice Areas'],
        parameters: [new OA\Parameter(name: 'practice_area', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Practice area', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PracticeArea')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(PracticeArea $practiceArea): PracticeAreaResource
    {
        Gate::authorize('view', $practiceArea);

        return new PracticeAreaResource($practiceArea->load(['status', 'head']));
    }

    #[OA\Put(
        path: '/practice-areas/{practice_area}',
        summary: 'Update a practice area (send only changed fields)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PracticeAreaRequest')),
        tags: ['Practice Areas'],
        parameters: [new OA\Parameter(name: 'practice_area', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PracticeArea')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(PracticeAreaRequest $request, PracticeArea $practiceArea): PracticeAreaResource
    {
        Gate::authorize('update', $practiceArea);

        $practiceArea->fill($request->safe()->except('status_id'));
        $practiceArea->forceFill($request->safe()->only('status_id'));
        $practiceArea->save();

        return new PracticeAreaResource($practiceArea->load(['status', 'head']));
    }

    #[OA\Delete(
        path: '/practice-areas/{practice_area}',
        summary: 'Delete a practice area',
        security: [['sanctum' => []]],
        tags: ['Practice Areas'],
        parameters: [new OA\Parameter(name: 'practice_area', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Practice area deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(PracticeArea $practiceArea): JsonResponse
    {
        Gate::authorize('delete', $practiceArea);

        $practiceArea->delete();

        return response()->json(['message' => 'Practice area deleted.']);
    }
}
