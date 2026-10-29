<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Send a password reset link by email',
        description: 'Always returns the same message, whether or not the email exists.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ravi.kumar@company.com')],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Request accepted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'If an account exists for this email, a password reset link has been sent.'])),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email exists, so accounts cannot be enumerated.
        return response()->json([
            'message' => 'If an account exists for this email, a password reset link has been sent.',
        ]);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Set a new password using the token from the email link',
        description: 'The reset link opens `FRONTEND_URL/reset-password?token=...&email=...`. Token is valid for 30 minutes. All devices are logged out.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: '5f2b8c1e9a...'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ravi.kumar@company.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Min 10 chars with upper and lower case, number and symbol', example: 'N3w-Secure#Pass'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'N3w-Secure#Pass'),
            ],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Password reset', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Your password has been reset.'])),
            new OA\Response(response: 422, description: 'Invalid/expired token or weak password', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError', example: ['message' => 'This password reset token is invalid.', 'errors' => ['email' => ['This password reset token is invalid.']]])),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                    'remember_token' => null,
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
