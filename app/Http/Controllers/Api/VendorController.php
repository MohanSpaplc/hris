<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\VendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class VendorController extends Controller
{
    #[OA\Get(
        path: '/vendors',
        summary: 'List vendors',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Search'),
            new OA\Parameter(ref: '#/components/parameters/StatusFilter'),
            new OA\Parameter(ref: '#/components/parameters/Sort'),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated vendors', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Vendor')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Vendor::class);
        $filters = $this->validateListFilters($request);

        $vendors = Vendor::query()
            ->with('status')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%"));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->tap(fn ($query) => $this->applySort($query, $filters))
            ->paginate($filters['per_page'] ?? 15);

        return VendorResource::collection($vendors);
    }

    #[OA\Post(
        path: '/vendors',
        summary: 'Create a vendor',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VendorRequest')),
        tags: ['Vendors'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Vendor')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(VendorRequest $request): JsonResponse
    {
        Gate::authorize('create', Vendor::class);

        $vendor = new Vendor($request->safe()->except('status_id'));
        $vendor->status_id = $request->validated('status_id');
        $vendor->created_by = $request->user()->id;
        $vendor->save();

        return (new VendorResource($vendor->load('status')))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/vendors/{vendor}',
        summary: 'Vendor details',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        parameters: [new OA\Parameter(name: 'vendor', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Vendor', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Vendor')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Vendor $vendor): VendorResource
    {
        Gate::authorize('view', $vendor);

        return new VendorResource($vendor->load('status'));
    }

    #[OA\Put(
        path: '/vendors/{vendor}',
        summary: 'Update a vendor (send only changed fields)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VendorRequest')),
        tags: ['Vendors'],
        parameters: [new OA\Parameter(name: 'vendor', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Vendor')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(VendorRequest $request, Vendor $vendor): VendorResource
    {
        Gate::authorize('update', $vendor);

        $vendor->fill($request->safe()->except('status_id'));
        $vendor->forceFill($request->safe()->only('status_id'));
        $vendor->save();

        return new VendorResource($vendor->load('status'));
    }

    #[OA\Delete(
        path: '/vendors/{vendor}',
        summary: 'Delete a vendor (soft delete)',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        parameters: [new OA\Parameter(name: 'vendor', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Vendor deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Vendor $vendor): JsonResponse
    {
        Gate::authorize('delete', $vendor);

        $vendor->delete();

        return response()->json(['message' => 'Vendor deleted.']);
    }
}
