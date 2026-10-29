<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/login',
        summary: 'Login and get an API token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'hr.admin@hris.local'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret#Pass123'),
                new OA\Property(property: 'device_name', type: 'string', nullable: true, example: 'chrome-web'),
            ],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged in', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'token', type: 'string', example: '1|Xb3kQ9...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['users.view', 'users.create']),
            ])),
            new OA\Response(response: 403, description: 'Account inactive', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Your account is inactive. Contact HR.'])),
            new OA\Response(response: 422, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError', example: ['message' => 'These credentials do not match our records.', 'errors' => ['email' => ['These credentials do not match our records.']]])),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with(['role.permissions', 'status'])->where('email', $request->validated('email'))->first();

        // Hash a dummy value when the user is missing so response time does not reveal which emails exist.
        $passwordIsValid = Hash::check(
            $request->validated('password'),
            $user?->password ?? Cache::rememberForever('auth:dummy_hash', fn () => Hash::make(Str::random(40))),
        );

        if (! $user || ! $passwordIsValid) {
            Log::warning('Failed login attempt', ['email' => $request->validated('email'), 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account is inactive. Contact HR.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $token = $user->createToken(
            $request->validated('device_name') ?? 'api',
            ['*'],
            now()->addMinutes(config('sanctum.expiration')),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'user' => new UserResource($user),
            'permissions' => $user->role->permissions->pluck('name'),
        ]);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Current logged-in user with permissions',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['users.view']),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role.permissions', 'status']);

        return response()->json([
            'user' => new UserResource($user),
            'permissions' => $user->role->permissions->pluck('name'),
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout (revoke the current token)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Logged out successfully.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    #[OA\Post(
        path: '/auth/logout-all',
        summary: 'Logout from all devices (revoke every token)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out everywhere', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Logged out from all devices.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    #[OA\Post(
        path: '/auth/change-password',
        summary: 'Change own password (other devices are logged out)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Secret#Pass123'),
                new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Min 10 chars with upper and lower case, number and symbol', example: 'N3w-Secure#Pass'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'N3w-Secure#Pass'),
            ],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Password changed', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Password changed successfully.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'password_changed_at' => now(),
        ])->save();

        // Keep this session, sign out every other device.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
