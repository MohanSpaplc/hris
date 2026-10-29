<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\JdFileRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Support\JdParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class JobDescriptionController extends Controller
{
    private const DISK = 'local'; // private: storage/app/private, never web-accessible

    #[OA\Post(
        path: '/jobs/parse-jd',
        summary: 'Read a JD file and suggest job form values',
        description: 'multipart/form-data with `file` (PDF, DOCX, XLSX or TXT, max 5 MB). Nothing is saved. Returns the description as safe HTML and the fields that could be matched.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]),
        )),
        tags: ['Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Suggestions', content: new OA\JsonContent(example: ['data' => [
                'job_description' => '<h3>Responsibilities</h3><ul><li>Build Spring Boot services</li></ul>',
                'fields' => [
                    'job_title' => 'Senior Java Developer', 'total_experience' => 5, 'relevant_experience' => 3,
                    'work_location' => 'Bengaluru', 'work_mode' => 'hybrid', 'employment_type' => 'full_time', 'no_of_positions' => 2,
                    'budget_min' => 1800000, 'budget_max' => 2400000,
                    'primary_skills' => ['Java', 'Spring Boot'], 'secondary_skills' => ['Kafka'], 'country_id' => 1, 'practice_area_id' => 1,
                ],
            ]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Wrong type, too large, or no readable text', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function parse(JdFileRequest $request, JdParser $parser): JsonResponse
    {
        $file = $request->file('file');

        try {
            $text = $parser->extractText($file->getRealPath(), $request->documentType()); // type from the file content, not its name
        } catch (RuntimeException $exception) {
            return $this->unreadable($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->unreadable('This file could not be read. Try saving it again as PDF or DOCX.');
        }

        if (mb_strlen(trim($text)) < 20) {
            return $this->unreadable('No text found in this file. If it is a scanned PDF, upload a text-based PDF or Word file.');
        }

        try {
            return response()->json(['data' => $parser->parse($text)]);
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not read the job description. Please fill the form manually.');
        }
    }

    #[OA\Post(
        path: '/jobs/{job}/jd-file',
        summary: 'Attach (or replace) the JD file of a job',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]),
        )),
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Saved', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Job')])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function upload(JdFileRequest $request, Job $job): JsonResponse
    {
        Gate::authorize('update', $job);
        $oldPath = $job->jd_file_path;

        try {
            $file = $request->file('file');
            $job->forceFill([
                'jd_file_path' => $file->store('job-descriptions', self::DISK), // random name; the original name is only stored as text
                'jd_file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            ])->save();

            if ($oldPath) {
                Storage::disk(self::DISK)->delete($oldPath);
            }
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not save the JD file. Please try again.');
        }

        return (new JobResource($job->load(['status', 'country', 'client', 'clientManager', 'practiceArea', 'skills', 'recruiters', 'vendors', 'creator'])))->response();
    }

    #[OA\Get(
        path: '/jobs/{job}/jd-file',
        summary: 'Download the JD file',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The file'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function download(Job $job): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $job);

        if (! $job->jd_file_path || ! Storage::disk(self::DISK)->exists($job->jd_file_path)) {
            return response()->json(['message' => 'This job has no JD file.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return Storage::disk(self::DISK)->download($job->jd_file_path, $job->jd_file_name ?? 'job-description');
    }

    #[OA\Delete(
        path: '/jobs/{job}/jd-file',
        summary: 'Remove the JD file',
        security: [['sanctum' => []]],
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Removed', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'JD file removed.'])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function destroy(Job $job): JsonResponse
    {
        Gate::authorize('update', $job);

        if ($job->jd_file_path) {
            Storage::disk(self::DISK)->delete($job->jd_file_path);
            $job->forceFill(['jd_file_path' => null, 'jd_file_name' => null])->save();
        }

        return response()->json(['message' => 'JD file removed.']);
    }

    private function unreadable(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
