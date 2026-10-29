<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ClientController extends Controller
{
    #[OA\Get(
        path: '/clients',
        summary: 'List clients',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Search'),
            new OA\Parameter(ref: '#/components/parameters/StatusFilter'),
            new OA\Parameter(name: 'country_id', in: 'query', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(ref: '#/components/parameters/Sort'),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated clients', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Client')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);
        $filters = $this->validateListFilters($request, ['country_id' => ['nullable', 'integer']]);

        $clients = Client::query()
            ->with(['status', 'country', 'creator'])
            ->withCount('managers')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->when($filters['country_id'] ?? null, fn ($query, int $countryId) => $query->where('country_id', $countryId))
            ->tap(fn ($query) => $this->applySort($query, $filters))
            ->paginate($filters['per_page'] ?? 15);

        return ClientResource::collection($clients);
    }

    #[OA\Post(
        path: '/clients',
        summary: 'Create a client',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClientRequest')),
        tags: ['Clients'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(ClientRequest $request): JsonResponse
    {
        Gate::authorize('create', Client::class);

        $client = new Client($request->safe()->except('status_id'));
        $client->status_id = $request->validated('status_id');
        $client->created_by = $request->user()->id;
        $client->save();

        return (new ClientResource($client->load(['status', 'country', 'creator'])))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/clients/{client}',
        summary: 'Client details',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Client', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return new ClientResource($client->load(['status', 'country', 'creator'])->loadCount('managers'));
    }

    #[OA\Put(
        path: '/clients/{client}',
        summary: 'Update a client (send only changed fields)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClientRequest')),
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(ClientRequest $request, Client $client): ClientResource
    {
        Gate::authorize('update', $client);

        $client->fill($request->safe()->except('status_id'));
        $client->forceFill($request->safe()->only('status_id'));
        $client->save();

        return new ClientResource($client->load(['status', 'country', 'creator']));
    }

    #[OA\Delete(
        path: '/clients/{client}',
        summary: 'Delete a client and its managers (soft delete)',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Client deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Client $client): JsonResponse
    {
        Gate::authorize('delete', $client);

        DB::transaction(function () use ($client) {
            $client->managers()->each(fn ($manager) => $manager->delete());
            $client->delete();
        });

        return response()->json(['message' => 'Client deleted.']);
    }
}
