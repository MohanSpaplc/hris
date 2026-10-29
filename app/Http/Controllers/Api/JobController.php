<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\JobRequest;
use App\Http\Resources\JobResource;
use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Skill;
use App\Models\Status;
use App\Models\User;
use App\Notifications\JobAssignedNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Throwable;

class JobController extends Controller
{
    /**
     * Relations shown in lists and details.
     *
     * @var list<string>
     */
    private const RELATIONS = ['status', 'country', 'client', 'clientManager', 'practiceArea', 'skills', 'recruiters', 'vendors', 'creator'];

    #[OA\Get(
        path: '/jobs',
        summary: 'List jobs',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        parameters: [
            new OA\Parameter(name: 'search', description: 'Job title or job code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status_id', description: 'Status of module "job"', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'client_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'country_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'practice_area_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'recruiter_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'posted_from', description: 'Created on or after (Y-m-d)', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'posted_to', description: 'Created on or before (Y-m-d)', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'assigned_to_me', description: 'Only jobs the logged-in user is assigned to as recruiter (newest assignment first)', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string', enum: Job::PRIORITIES)),
            new OA\Parameter(name: 'job_type', description: 'internal or client', in: 'query', schema: new OA\Schema(type: 'string', enum: ['internal', 'client'])),
            new OA\Parameter(name: 'sort', description: '`latest` (default) or `title`', in: 'query', schema: new OA\Schema(type: 'string', enum: ['latest', 'title'])),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated jobs', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Job')),
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
        Gate::authorize('viewAny', Job::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'country_id' => ['nullable', 'integer'],
            'practice_area_id' => ['nullable', 'integer'],
            'recruiter_id' => ['nullable', 'integer'],
            'assigned_to_me' => ['nullable', 'boolean'],
            'posted_from' => ['nullable', 'date'],
            'posted_to' => ['nullable', 'date', 'after_or_equal:posted_from'],
            'priority' => ['nullable', 'in:'.implode(',', Job::PRIORITIES)],
            'job_type' => ['nullable', 'in:internal,client'],
            'sort' => ['nullable', 'in:latest,title'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $jobs = Job::query()
            ->with(self::RELATIONS)
            ->withCount('applications')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('job_title', 'like', "%{$search}%")->orWhere('job_code', 'like', "%{$search}%"));
            })
            ->when($filters['status_id'] ?? null, fn ($query, int $id) => $query->where('status_id', $id))
            ->when($filters['client_id'] ?? null, fn ($query, int $id) => $query->where('client_id', $id))
            ->when($filters['country_id'] ?? null, fn ($query, int $id) => $query->where('country_id', $id))
            ->when($filters['practice_area_id'] ?? null, fn ($query, int $id) => $query->where('practice_area_id', $id))
            ->when($filters['recruiter_id'] ?? null, fn ($query, int $id) => $query->whereRelation('recruiters', 'users.id', $id))
            ->when($request->boolean('assigned_to_me'), fn ($query) => $query->whereRelation('recruiters', 'users.id', $request->user()->id))
            ->when($filters['posted_from'] ?? null, fn ($query, string $date) => $query->where('jobs.created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['posted_to'] ?? null, fn ($query, string $date) => $query->where('jobs.created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['job_type'] ?? null, fn ($query, string $type) => $type === 'internal' ? $query->whereNull('client_id') : $query->whereNotNull('client_id'))
            ->when(($filters['sort'] ?? 'latest') === 'title', fn ($query) => $query->orderBy('job_title'), fn ($query) => $query->latest('id'))
            ->paginate($filters['per_page'] ?? 15);

        return JobResource::collection($jobs);
    }

    #[OA\Post(
        path: '/jobs',
        summary: 'Create a job',
        description: 'job_code is generated as {country ISO3}-{practice code}-{00001}, e.g. IND-JAVA-00001, numbered per country + practice. It does not change if the job is later edited. Status defaults to Draft. New skill names are added to the skill master.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/JobRequest')),
        tags: ['Jobs'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Job')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(JobRequest $request): JsonResponse
    {
        try {
            // Two users can still race for the very first code of a new prefix (nothing to lock yet);
            // the unique index rejects the loser, so retry with the next number.
            for ($attempt = 1; ; $attempt++) {
                try {
                    $job = DB::transaction(function () use ($request) {
                        $job = new Job;
                        $job->created_by = $request->user()->id;
                        $job->job_code = Job::nextCode(
                            Country::findOrFail($request->validated('country_id')),
                            PracticeArea::findOrFail($request->validated('practice_area_id')),
                        );

                        return $this->saveJob($job, $request);
                    });
                    break;
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt >= 3) {
                        throw $exception;
                    }
                }
            }
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not create the job. Please try again.');
        }

        return (new JobResource($job->load(self::RELATIONS)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/jobs/{job}',
        summary: 'Job details',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Job', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Job')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Job $job): JobResource
    {
        Gate::authorize('view', $job);

        return new JobResource($job->load(self::RELATIONS));
    }

    #[OA\Put(
        path: '/jobs/{job}',
        summary: 'Update a job (send only changed fields)',
        description: 'Arrays (skills, recruiter_ids, vendor_ids) replace the current list when sent. Setting a final status (closed / filled / cancelled) sets closed_at.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/JobRequest')),
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Job')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function update(JobRequest $request, Job $job): JsonResponse
    {
        Gate::authorize('update', $job);

        try {
            DB::transaction(fn () => $this->saveJob($job, $request));
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not update the job. Please try again.');
        }

        return (new JobResource($job->load(self::RELATIONS)))->response();
    }

    #[OA\Delete(
        path: '/jobs/{job}',
        summary: 'Delete a job (soft delete)',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Job deleted.'])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Job $job): JsonResponse
    {
        Gate::authorize('delete', $job);

        try {
            $job->delete();
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not delete the job. Please try again.');
        }

        return response()->json(['message' => 'Job deleted.']);
    }

    /**
     * Fill the job from the validated request and sync skills, recruiters and vendors.
     * Must run inside a DB transaction.
     */
    private function saveJob(Job $job, JobRequest $request): Job
    {
        $job->fill($request->safe()->only($job->getFillable()));

        $this->applyStatus($job, $request->validated('status_id'));
        $job->save();

        if ($request->has('primary_skills') || $request->has('secondary_skills')) {
            $this->syncSkills($job, $request);
        }

        if ($request->has('recruiter_ids')) {
            $this->syncRecruiters($job, $request->validated('recruiter_ids'), $request->validated('primary_recruiter_id'), $request->user());
        }

        if ($request->has('vendor_ids')) {
            $existing = $job->vendors()->pluck('job_vendors.assigned_at', 'vendors.id');
            $job->vendors()->sync(collect($request->validated('vendor_ids'))->mapWithKeys(fn ($id) => [
                $id => ['assigned_at' => $existing[$id] ?? now()],
            ]));
        }

        return $job;
    }

    /**
     * New jobs start as Draft. Final statuses (closed / filled / cancelled) stamp closed_at.
     */
    private function applyStatus(Job $job, ?int $statusId): void
    {
        if ($statusId === null && $job->exists) {
            return;
        }

        $status = Status::find($statusId ?? Status::idFor(Job::STATUS_MODULE, 'draft'));
        $job->status_id = $status->id;

        if ($status->is_final) {
            $job->closed_at ??= now();
        } else {
            $job->closed_at = null;
            $job->closed_reason = null;
        }
    }

    private function syncSkills(Job $job, JobRequest $request): void
    {
        $current = $job->skills()->get();
        $types = [
            'primary' => $request->has('primary_skills') ? $request->validated('primary_skills', []) : $current->where('pivot.skill_type', 'primary')->pluck('name')->all(),
            'secondary' => $request->has('secondary_skills') ? $request->validated('secondary_skills', []) : $current->where('pivot.skill_type', 'secondary')->pluck('name')->all(),
        ];

        $sync = [];
        foreach ($types as $type => $names) {
            foreach ($names as $name) {
                // Case-insensitive match on any database, so "java" reuses "Java".
                $skill = Skill::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? Skill::create(['name' => $name]);
                $sync[$skill->id] = ['skill_type' => $type];
            }
        }

        $job->skills()->sync($sync);
    }

    /**
     * Save the job assignments and email recruiters who were newly assigned (after the transaction commits).
     *
     * @param  list<int>  $recruiterIds
     */
    private function syncRecruiters(Job $job, array $recruiterIds, ?int $primaryRecruiterId, User $assignedBy): void
    {
        $existing = $job->recruiters()->get()->keyBy('id');
        $primaryRecruiterId ??= $recruiterIds[0] ?? null;

        $job->recruiters()->sync(collect($recruiterIds)->mapWithKeys(fn ($id) => [
            $id => [
                'is_primary' => $id === $primaryRecruiterId,
                'assigned_at' => $existing[$id]?->pivot->assigned_at ?? now(),
                'assigned_by' => $existing[$id]?->pivot->assigned_by ?? $assignedBy->id,
            ],
        ]));

        User::query()
            ->whereKey(array_diff($recruiterIds, $existing->keys()->all()))
            ->get()
            ->each(fn (User $recruiter) => $recruiter->notify(new JobAssignedNotification($job, $assignedBy, $recruiter->id === $primaryRecruiterId)));
    }
}
