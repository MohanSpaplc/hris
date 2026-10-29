<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Info(
    version: '1.0.0',
    title: 'HRIS Hiring API',
    description: "Login with `POST /auth/login`, copy the `token`, click **Authorize** and paste it.\n\nAll responses are JSON. Validation errors return 422, missing/expired token 401, no permission 403, rate limit 429.",
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'API server')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', scheme: 'bearer', bearerFormat: 'Sanctum token', description: 'Paste the token returned by /auth/login')]
#[OA\Tag(name: 'Auth', description: 'Login, logout, password management')]
#[OA\Tag(name: 'Users', description: 'User management (HR)')]
#[OA\Tag(name: 'Roles', description: 'Role master')]
#[OA\Tag(name: 'Statuses', description: 'Status master (for dropdowns)')]
#[OA\Tag(name: 'Countries', description: 'Country master')]
#[OA\Tag(name: 'Practice Areas', description: 'Practice area master')]
#[OA\Tag(name: 'Clients', description: 'Client master')]
#[OA\Tag(name: 'Client Managers', description: 'Client manager master')]
#[OA\Tag(name: 'Vendors', description: 'Vendor master')]
#[OA\Parameter(parameter: 'Search', name: 'search', description: 'Search by name (and code / email where available)', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 100))]
#[OA\Parameter(parameter: 'StatusFilter', name: 'status_id', description: 'Filter by status id (module "general")', in: 'query', schema: new OA\Schema(type: 'integer', example: 1))]
#[OA\Parameter(parameter: 'PerPage', name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1))]
#[OA\Parameter(parameter: 'Sort', name: 'sort', description: '`latest` (default, newest first) or `name` (A–Z)', in: 'query', schema: new OA\Schema(type: 'string', default: 'latest', enum: ['latest', 'name']))]
#[OA\Parameter(parameter: 'Page', name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 5),
    ],
    type: 'object',
)]
#[OA\Response(response: 'ServerError', description: 'Unexpected server error (details are logged, not returned)', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Could not save the job. Please try again.']))]
#[OA\Tag(name: 'Jobs', description: 'Job openings / requirements')]
#[OA\Tag(name: 'Skills', description: 'Skill master (autocomplete)')]
#[OA\Tag(name: 'Candidates', description: 'Candidates / resume master and their documents')]
#[OA\Response(response: 'InUse', description: 'Record is still used by other records', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'This record is in use. Mark it inactive instead.']))]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'first_name', type: 'string', example: 'Ravi'),
        new OA\Property(property: 'last_name', type: 'string', nullable: true, example: 'Kumar'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ravi.kumar@company.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 9876543210'),
        new OA\Property(property: 'role', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'code', type: 'string', enum: ['hr', 'tag_manager', 'tag_team'], example: 'tag_team'),
            new OA\Property(property: 'name', type: 'string', example: 'TAG Team'),
        ], type: 'object'),
        new OA\Property(property: 'status', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'code', type: 'string', example: 'active'),
            new OA\Property(property: 'name', type: 'string', example: 'Active'),
            new OA\Property(property: 'color', type: 'string', example: 'success'),
        ], type: 'object'),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Message',
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Operation successful.')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email field is required.']]),
    ],
    type: 'object',
)]
#[OA\Response(response: 'Unauthenticated', description: 'Missing, invalid or expired token', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Unauthenticated.']))]
#[OA\Response(response: 'Forbidden', description: 'No permission or inactive account', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'This action is unauthorized.']))]
#[OA\Response(response: 'NotFound', description: 'Record not found', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Record not found.']))]
#[OA\Response(response: 'ValidationFailed', description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
#[OA\Response(response: 'TooManyRequests', description: 'Rate limit exceeded', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Too Many Attempts.']))]
// swagger-php keeps only one #[OA\Schema] per class, so shared schemas live together in OA\Components.
#[OA\Components(
    schemas: [

    ],
)]
abstract class Controller
{
    /**
     * Common list filters: search, status_id, per_page.
     *
     * @return array{search?: string, status_id?: int, per_page?: int}
     */
    protected function validateListFilters(Request $request, array $extraRules = []): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'in:latest,name'],
            ...$extraRules,
        ]);
    }

    /**
     * Default: newest first, so a record you just added shows at the top.
     * `sort=name` gives A–Z (for dropdowns).
     */
    protected function applySort(Builder $query, array $filters): void
    {
        ($filters['sort'] ?? 'latest') === 'name'
            ? $query->orderBy('name')
            : $query->latest('id');
    }

    /**
     * Log an unexpected error (with request context) and return a safe 500 JSON message.
     * The real exception is never sent to the client.
     */
    protected function serverError(Throwable $exception, string $message): JsonResponse
    {
        report($exception);

        return response()->json(['message' => $message], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}
