<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\CandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Skill;
use App\Models\Status;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Throwable;

class CandidateController extends Controller
{
    /**
     * Relations shown in lists.
     *
     * @var list<string>
     */
    private const LIST_RELATIONS = ['status', 'vendor', 'skills', 'currentResume', 'creator'];

    /**
     * Relations shown in details.
     *
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [...self::LIST_RELATIONS, 'educations', 'employments', 'currentResume.status', 'currentResume.uploader', 'applications.job', 'applications.stage', 'applications.status'];

    #[OA\Get(
        path: '/candidates',
        summary: 'List candidates',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [
            new OA\Parameter(name: 'search', description: 'Name, code, email, phone, designation or company', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'keyword', description: 'Search inside resume text', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status_id', description: 'Status of module "candidate"', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'vendor_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'job_id', description: 'Candidates tagged to this job', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'resume_source', in: 'query', schema: new OA\Schema(type: 'string', enum: Candidate::RESUME_SOURCES)),
            new OA\Parameter(name: 'skill', description: 'Has this skill (exact name, any type)', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'min_experience', description: 'Total experience ≥ (years)', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_experience', description: 'Total experience ≤ (years)', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_notice_period', description: 'Notice period ≤ (days)', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', description: '`latest` (default) or `name`', in: 'query', schema: new OA\Schema(type: 'string', enum: ['latest', 'name'])),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated candidates', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Candidate')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Candidate::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'integer'],
            'resume_source' => ['nullable', 'in:'.implode(',', Candidate::RESUME_SOURCES)],
            'skill' => ['nullable', 'string', 'max:150'],
            'min_experience' => ['nullable', 'numeric', 'min:0'],
            'max_experience' => ['nullable', 'numeric', 'min:0'],
            'max_notice_period' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:latest,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $candidates = Candidate::query()
            ->with(self::LIST_RELATIONS)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                // Every word must match some column, so "anita sharma" finds first + last name.
                foreach (preg_split('/\s+/', trim($search)) as $word) {
                    $query->whereAny(['first_name', 'last_name', 'candidate_code', 'email', 'phone', 'current_designation', 'current_company'], 'like', "%{$word}%");
                }
            })
            ->when($filters['keyword'] ?? null, function ($query, string $keyword) {
                DB::connection()->getDriverName() === 'mysql'
                    ? $query->whereFullText(['first_name', 'last_name', 'current_designation', 'resume_text'], $keyword)
                    : $query->where('resume_text', 'like', "%{$keyword}%");
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $id) => $query->where('status_id', $id))
            ->when($filters['vendor_id'] ?? null, fn ($query, int $id) => $query->where('vendor_id', $id))
            ->when($filters['job_id'] ?? null, fn ($query, int $id) => $query->whereRelation('applications', 'job_id', $id))
            ->when($filters['resume_source'] ?? null, fn ($query, string $source) => $query->where('resume_source', $source))
            ->when($filters['skill'] ?? null, fn ($query, string $skill) => $query->whereRelation('skills', 'name', $skill))
            ->when(isset($filters['min_experience']), fn ($query) => $query->where('total_experience', '>=', $filters['min_experience']))
            ->when(isset($filters['max_experience']), fn ($query) => $query->where('total_experience', '<=', $filters['max_experience']))
            ->when(isset($filters['max_notice_period']), fn ($query) => $query->where('notice_period_days', '<=', $filters['max_notice_period']))
            ->when(($filters['sort'] ?? 'latest') === 'name', fn ($query) => $query->orderBy('first_name')->orderBy('last_name'), fn ($query) => $query->latest('id'))
            ->paginate($filters['per_page'] ?? 15);

        return CandidateResource::collection($candidates);
    }

    #[OA\Post(
        path: '/candidates',
        summary: 'Add a candidate',
        description: 'candidate_code is generated as CAN-00001. Status defaults to Active. An email or phone already used by another candidate is rejected (422). Upload the resume afterwards with POST /candidates/{id}/documents.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CandidateRequest')),
        tags: ['Candidates'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Candidate')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(CandidateRequest $request): JsonResponse
    {
        try {
            // Two users can race for the same code; the unique index rejects the loser, so retry with the next number.
            for ($attempt = 1; ; $attempt++) {
                try {
                    $candidate = DB::transaction(function () use ($request) {
                        $candidate = new Candidate;
                        $candidate->created_by = $request->user()->id;
                        $candidate->candidate_code = Candidate::nextCode();

                        return $this->saveCandidate($candidate, $request);
                    });
                    break;
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt >= 3) {
                        throw $exception;
                    }
                }
            }
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not add the candidate. Please try again.');
        }

        return (new CandidateResource($candidate->load(self::DETAIL_RELATIONS)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/candidates/{candidate}',
        summary: 'Candidate details',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Candidate', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Candidate')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Candidate $candidate): CandidateResource
    {
        Gate::authorize('view', $candidate);

        return new CandidateResource($candidate->load(self::DETAIL_RELATIONS));
    }

    #[OA\Put(
        path: '/candidates/{candidate}',
        summary: 'Update a candidate (send only changed fields)',
        description: 'Arrays (skills, educations, employments) replace the current list when sent.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CandidateRequest')),
        tags: ['Candidates'],
        parameters: [new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Candidate')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function update(CandidateRequest $request, Candidate $candidate): JsonResponse
    {
        Gate::authorize('update', $candidate);

        try {
            DB::transaction(fn () => $this->saveCandidate($candidate, $request));
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not update the candidate. Please try again.');
        }

        return (new CandidateResource($candidate->load(self::DETAIL_RELATIONS)))->response();
    }

    #[OA\Delete(
        path: '/candidates/{candidate}',
        summary: 'Delete a candidate (soft delete)',
        description: 'Documents are kept for audit.',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Candidate deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Candidate $candidate): JsonResponse
    {
        Gate::authorize('delete', $candidate);

        try {
            $candidate->delete();
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not delete the candidate. Please try again.');
        }

        return response()->json(['message' => 'Candidate deleted.']);
    }

    /**
     * Fill the candidate from the validated request and replace skills, educations and employments when sent.
     * Must run inside a DB transaction.
     */
    private function saveCandidate(Candidate $candidate, CandidateRequest $request): Candidate
    {
        $candidate->fill($request->safe()->only($candidate->getFillable()));

        if ($request->validated('status_id') !== null || ! $candidate->exists) {
            $candidate->status_id = $request->validated('status_id') ?? Status::idFor(Candidate::STATUS_MODULE, 'active');
        }

        // A vendor is only kept for vendor-sourced resumes.
        if ($candidate->resume_source !== 'vendor') {
            $candidate->vendor_id = null;
        }
        if ($candidate->resume_source !== 'referral') {
            $candidate->referral_name = null;
        }

        $candidate->save();

        if ($request->has('skills')) {
            $sync = [];
            foreach ($request->validated('skills', []) as $skill) {
                // Case-insensitive match on any database, so "java" reuses "Java".
                $model = Skill::whereRaw('LOWER(name) = ?', [mb_strtolower($skill['name'])])->first() ?? Skill::create(['name' => $skill['name']]);
                $sync[$model->id] = ['skill_type' => $skill['skill_type'], 'experience_months' => $skill['experience_months'] ?? null];
            }
            $candidate->skills()->sync($sync);
        }

        if ($request->has('educations')) {
            $candidate->educations()->delete();
            $candidate->educations()->createMany($request->validated('educations', []));
        }

        if ($request->has('employments')) {
            $candidate->employments()->delete();
            $candidate->employments()->createMany($request->validated('employments', []));
        }

        $jobId = $request->validated('job_id');
        if ($jobId && ! $candidate->applications()->where('job_id', $jobId)->exists()) {
            JobApplication::tag($candidate, Job::findOrFail($jobId), $request->user());
        }

        return $candidate;
    }
}
