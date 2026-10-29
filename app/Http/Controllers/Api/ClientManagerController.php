<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClientManagerRequest;
use App\Http\Resources\ClientManagerResource;
use App\Models\ClientManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ClientManagerController extends Controller
{
    #[OA\Get(
        path: '/client-managers',
        summary: 'List client managers',
        security: [['sanctum' => []]],
        tags: ['Client Managers'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Search'),
            new OA\Parameter(ref: '#/components/parameters/StatusFilter'),
            new OA\Parameter(name: 'client_id', description: 'Managers of one client (for dropdowns)', in: 'query', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(ref: '#/components/parameters/Sort'),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated client managers', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ClientManager')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ClientManager::class);
        $filters = $this->validateListFilters($request, ['client_id' => ['nullable', 'integer']]);

        $managers = ClientManager::query()
            ->with(['status', 'client'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->when($filters['client_id'] ?? null, fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->tap(fn ($query) => $this->applySort($query, $filters))
            ->paginate($filters['per_page'] ?? 15);

        return ClientManagerResource::collection($managers);
    }

    #[OA\Post(
        path: '/client-managers',
        summary: 'Create a client manager',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClientManagerRequest')),
        tags: ['Client Managers'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ClientManager')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(ClientManagerRequest $request): JsonResponse
    {
        Gate::authorize('create', ClientManager::class);

        $manager = new ClientManager($request->safe()->except('status_id'));
        $manager->status_id = $request->validated('status_id');
        $manager->created_by = $request->user()->id;
        $manager->save();

        return (new ClientManagerResource($manager->load(['status', 'client'])))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/client-managers/{client_manager}',
        summary: 'Client manager details',
        security: [['sanctum' => []]],
        tags: ['Client Managers'],
        parameters: [new OA\Parameter(name: 'client_manager', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Client manager', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ClientManager')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(ClientManager $clientManager): ClientManagerResource
    {
        Gate::authorize('view', $clientManager);

        return new ClientManagerResource($clientManager->load(['status', 'client']));
    }

    #[OA\Put(
        path: '/client-managers/{client_manager}',
        summary: 'Update a client manager (send only changed fields)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClientManagerRequest')),
        tags: ['Client Managers'],
        parameters: [new OA\Parameter(name: 'client_manager', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ClientManager')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(ClientManagerRequest $request, ClientManager $clientManager): ClientManagerResource
    {
        Gate::authorize('update', $clientManager);

        $clientManager->fill($request->safe()->except('status_id'));
        $clientManager->forceFill($request->safe()->only('status_id'));
        $clientManager->save();

        return new ClientManagerResource($clientManager->load(['status', 'client']));
    }

    #[OA\Delete(
        path: '/client-managers/{client_manager}',
        summary: 'Delete a client manager (soft delete)',
        security: [['sanctum' => []]],
        tags: ['Client Managers'],
        parameters: [new OA\Parameter(name: 'client_manager', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Client manager deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(ClientManager $clientManager): JsonResponse
    {
        Gate::authorize('delete', $clientManager);

        $clientManager->delete();

        return response()->json(['message' => 'Client manager deleted.']);
    }
}
