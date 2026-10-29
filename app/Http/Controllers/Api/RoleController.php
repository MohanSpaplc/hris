<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/roles',
        summary: 'List roles (for user form dropdown)',
        security: [['sanctum' => []]],
        tags: ['Roles'],
        responses: [
            new OA\Response(response: 200, description: 'Roles', content: new OA\JsonContent(example: ['data' => [
                ['id' => 1, 'name' => 'HR', 'code' => 'hr', 'status_id' => 1, 'status' => ['id' => 1, 'code' => 'active', 'name' => 'Active']],
                ['id' => 2, 'name' => 'TAG Manager', 'code' => 'tag_manager', 'status_id' => 1, 'status' => ['id' => 1, 'code' => 'active', 'name' => 'Active']],
                ['id' => 3, 'name' => 'TAG Team', 'code' => 'tag_team', 'status_id' => 1, 'status' => ['id' => 1, 'code' => 'active', 'name' => 'Active']],
            ]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('roles.view'), JsonResponse::HTTP_FORBIDDEN);

        return response()->json([
            'data' => Role::query()->with('status:id,code,name')->orderBy('name')->get(['id', 'name', 'code', 'status_id']),
        ]);
    }
}
