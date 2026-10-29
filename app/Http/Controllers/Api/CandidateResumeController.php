<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ResumeFileRequest;
use App\Support\ResumeParser;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use RuntimeException;
use Throwable;

class CandidateResumeController extends Controller
{
    #[OA\Post(
        path: '/candidates/parse-resume',
        summary: 'Read a resume and suggest candidate form values',
        description: 'multipart/form-data with `file` (PDF, DOCX or TXT, max 5 MB). Nothing is saved. Returns only the fields that could be found.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]),
        )),
        tags: ['Candidates'],
        responses: [
            new OA\Response(response: 200, description: 'Suggestions', content: new OA\JsonContent(example: ['data' => ['fields' => [
                'first_name' => 'Anita', 'last_name' => 'Sharma', 'email' => 'anita.sharma@example.com', 'phone' => '+91 98765 43210',
                'gender' => 'female', 'current_location' => 'Hyderabad', 'current_designation' => 'Java Developer', 'current_company' => 'Acme Corp',
                'preferred_location' => 'Bengaluru', 'total_experience' => 6, 'relevant_experience' => 4,
                'current_ctc' => 1200000, 'expected_ctc' => 1600000, 'notice_period_days' => 30,
                'primary_skills' => ['Java', 'Spring Boot'], 'secondary_skills' => ['Docker'],
                'educations' => [['qualification' => 'B.Tech', 'institution' => 'JNTU Hyderabad', 'year_of_passing' => 2018, 'percentage_or_cgpa' => 8.2]],
            ]]])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Wrong type, too large, or no readable text', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function parse(ResumeFileRequest $request, ResumeParser $parser): JsonResponse
    {
        $type = $request->documentType(); // from the file content, not its name
        if (! in_array($type, ['pdf', 'docx', 'txt'], true)) {
            return $this->unreadable('Upload the resume as PDF, Word (.docx) or text.');
        }

        try {
            $text = $parser->extractText($request->file('file')->getRealPath(), $type);
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
            return $this->serverError($exception, 'Could not read the resume. Please fill the form manually.');
        }
    }

    private function unreadable(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
