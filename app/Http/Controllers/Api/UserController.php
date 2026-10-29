<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'UserId', name: 'user', description: 'User id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 2))]
class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        summary: 'List users (paginated)',
        description: 'Permission: `users.view` (HR, TAG Manager).',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'search', description: 'Name or email', in: 'query', schema: new OA\Schema(type: 'string', example: 'ravi')),
            new OA\Parameter(name: 'role_id', in: 'query', schema: new OA\Schema(type: 'integer', example: 3)),
            new OA\Parameter(name: 'status_id', in: 'query', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated users', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', type: 'object', example: ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 3]),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', 'integer'],
            'status_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with(['role', 'status'])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['role_id'] ?? null, fn ($query, int $roleId) => $query->where('role_id', $roleId))
            ->when($validated['status_id'] ?? null, fn ($query, int $statusId) => $query->where('status_id', $statusId))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return UserResource::collection($users);
    }

    /**
     * HR creates the account without a password; the user sets their own via the emailed link.
     */
    #[OA\Post(
        path: '/users',
        summary: 'Create a user (HR only)',
        description: 'Permission: `users.create`. No password is sent — the user receives an email link to set it. New users are always Active.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['first_name', 'email', 'role_id'],
            properties: [
                new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Ravi'),
                new OA\Property(property: 'last_name', type: 'string', maxLength: 100, nullable: true, example: 'Kumar'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ravi.kumar@company.com'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 9876543210'),
                new OA\Property(property: 'role_id', description: 'From GET /roles', type: 'integer', example: 3),
            ],
        )),
        tags: ['Users'],
        responses: [
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                new OA\Property(property: 'message', type: 'string', example: 'User created. A link to set the password has been emailed.'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = new User($request->safe()->except('role_id'));
            $user->password = Str::password(32);
            $user->role_id = $request->validated('role_id');
            $user->status_id = Status::idFor(Status::MODULE_GENERAL, 'active');
            $user->created_by = $request->user()->id;
            $user->save();

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return (new UserResource($user->load(['role', 'status'])))
            ->additional(['message' => 'User created. A link to set the password has been emailed.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/users/{user}',
        summary: 'User details',
        description: 'Permission: `users.view`, or any user viewing their own profile.',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 200, description: 'User', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load(['role', 'status']));
    }

    #[OA\Put(
        path: '/users/{user}',
        summary: 'Update a user (HR only)',
        description: 'Permission: `users.update`. Send only the fields to change. You cannot change your own role or status. Changing role/status logs the user out of all devices.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Ravi'),
                new OA\Property(property: 'last_name', type: 'string', maxLength: 100, nullable: true, example: 'Kumar'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 9876543210'),
                new OA\Property(property: 'role_id', description: 'From GET /roles', type: 'integer', example: 2),
                new OA\Property(property: 'status_id', description: 'A status of module "general" (active / inactive)', type: 'integer', example: 2),
            ],
        )),
        tags: ['Users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 200, description: 'Updated user', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->fill($request->safe()->only(['first_name', 'last_name', 'phone']));
        $user->forceFill($request->safe()->only(['role_id', 'status_id']));
        $user->save();

        // Role or status change: force re-login so old permissions are not kept in active sessions.
        if ($user->wasChanged(['role_id', 'status_id'])) {
            $user->tokens()->delete();
        }

        return new UserResource($user->load(['role', 'status']));
    }

    #[OA\Delete(
        path: '/users/{user}',
        summary: 'Delete a user (HR only)',
        description: 'Permission: `users.delete`. Soft delete: user is set inactive and all tokens are revoked. You cannot delete yourself.',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'User deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->forceFill(['status_id' => Status::idFor(Status::MODULE_GENERAL, 'inactive')])->save();
            $user->delete();
        });

        return response()->json(['message' => 'User deleted.']);
    }
}
