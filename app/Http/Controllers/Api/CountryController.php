<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CountryRequest;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class CountryController extends Controller
{
    #[OA\Get(
        path: '/countries',
        summary: 'List countries',
        security: [['sanctum' => []]],
        tags: ['Countries'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Search'),
            new OA\Parameter(ref: '#/components/parameters/StatusFilter'),
            new OA\Parameter(ref: '#/components/parameters/Sort'),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated countries', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Country')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Country::class);
        $filters = $this->validateListFilters($request);

        $countries = Country::query()
            ->with('status')
            ->withCount('clients')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', $search));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->tap(fn ($query) => $this->applySort($query, $filters))
            ->paginate($filters['per_page'] ?? 15);

        return CountryResource::collection($countries);
    }

    #[OA\Post(
        path: '/countries',
        summary: 'Create a country',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CountryRequest')),
        tags: ['Countries'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Country')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(CountryRequest $request): JsonResponse
    {
        Gate::authorize('create', Country::class);

        $country = new Country($request->safe()->except('status_id'));
        $country->status_id = $request->validated('status_id');
        $country->save();

        return (new CountryResource($country->load('status')))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/countries/{country}',
        summary: 'Country details',
        security: [['sanctum' => []]],
        tags: ['Countries'],
        parameters: [new OA\Parameter(name: 'country', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Country', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Country')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Country $country): CountryResource
    {
        Gate::authorize('view', $country);

        return new CountryResource($country->load('status')->loadCount('clients'));
    }

    #[OA\Put(
        path: '/countries/{country}',
        summary: 'Update a country (send only changed fields)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CountryRequest')),
        tags: ['Countries'],
        parameters: [new OA\Parameter(name: 'country', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Country')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(CountryRequest $request, Country $country): CountryResource
    {
        Gate::authorize('update', $country);

        $country->fill($request->safe()->except('status_id'));
        $country->forceFill($request->safe()->only('status_id'));
        $country->save();

        return new CountryResource($country->load('status'));
    }

    #[OA\Delete(
        path: '/countries/{country}',
        summary: 'Delete a country (only if no client uses it)',
        security: [['sanctum' => []]],
        tags: ['Countries'],
        parameters: [new OA\Parameter(name: 'country', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Country deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/InUse'),
        ],
    )]
    public function destroy(Country $country): JsonResponse
    {
        Gate::authorize('delete', $country);

        if ($country->clients()->withTrashed()->exists()) {
            return response()->json(['message' => 'This country is used by clients. Mark it inactive instead.'], JsonResponse::HTTP_CONFLICT);
        }

        $country->delete();

        return response()->json(['message' => 'Country deleted.']);
    }
}
