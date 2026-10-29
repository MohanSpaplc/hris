<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SkillController extends Controller
{
    #[OA\Get(
        path: '/skills',
        summary: 'Active skills for autocomplete (max 50)',
        security: [['sanctum' => []]],
        tags: ['Skills'],
        parameters: [new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', example: 'ja'))],
        responses: [
            new OA\Response(response: 200, description: 'Skills', content: new OA\JsonContent(example: ['data' => [['id' => 1, 'name' => 'Java'], ['id' => 7, 'name' => 'JavaScript']]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $skills = Skill::query()
            ->active()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);

        return response()->json(['data' => $skills]);
    }
}
