<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\CandidateDocumentRequest;
use App\Http\Resources\CandidateDocumentResource;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Status;
use App\Support\JdParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CandidateDocumentController extends Controller
{
    private const DISK = 'local'; // private: storage/app/private, never web-accessible

    private const RELATIONS = ['status', 'uploader', 'verifier'];

    #[OA\Get(
        path: '/candidates/{candidate}/documents',
        summary: 'List documents of a candidate',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [
            new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'document_type', in: 'query', schema: new OA\Schema(type: 'string', enum: CandidateDocument::TYPES)),
            new OA\Parameter(name: 'current_only', description: 'Hide older versions', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Documents, newest first', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CandidateDocument')),
            ])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function index(Request $request, Candidate $candidate): AnonymousResourceCollection
    {
        Gate::authorize('view', $candidate);

        $filters = $request->validate([
            'document_type' => ['nullable', Rule::in(CandidateDocument::TYPES)],
            'current_only' => ['nullable', 'boolean'],
        ]);

        $documents = $candidate->documents()
            ->with(self::RELATIONS)
            ->when($filters['document_type'] ?? null, fn ($query, string $type) => $query->where('document_type', $type))
            ->when($request->boolean('current_only'), fn ($query) => $query->where('is_current', true))
            ->latest('id')
            ->get();

        return CandidateDocumentResource::collection($documents);
    }

    #[OA\Post(
        path: '/candidates/{candidate}/documents',
        summary: 'Upload a document',
        description: "multipart/form-data with `document_type` and `file` (max 5 MB).\n\n- resume: PDF, DOCX or TXT. The text is read for keyword search.\n- photo: JPG, PNG or WEBP.\n- others: PDF, DOCX, JPG or PNG.\n\nA new resume or photo becomes the next version and the previous one is kept as not current. Starts as Pending Verification.",
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['document_type', 'file'], properties: [
                new OA\Property(property: 'document_type', type: 'string', enum: CandidateDocument::TYPES),
                new OA\Property(property: 'file', type: 'string', format: 'binary'),
            ]),
        )),
        tags: ['Candidates'],
        parameters: [new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Uploaded', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CandidateDocument')])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(CandidateDocumentRequest $request, Candidate $candidate, JdParser $parser): JsonResponse
    {
        Gate::authorize('update', $candidate);

        $file = $request->file('file');
        $type = $request->validated('document_type');
        $path = null;

        try {
            $path = $file->store("candidates/{$candidate->id}", self::DISK); // random name; the original name is only stored as text

            $document = DB::transaction(function () use ($request, $candidate, $file, $type, $path) {
                $version = 1;
                if (in_array($type, CandidateDocument::SINGLE_CURRENT_TYPES, true)) {
                    $previous = $candidate->documents()->where('document_type', $type)->lockForUpdate();
                    $version = ((clone $previous)->max('version') ?? 0) + 1;
                    $previous->update(['is_current' => false]);
                }

                $document = new CandidateDocument;
                $document->forceFill([
                    'candidate_id' => $candidate->id,
                    'uploaded_by' => $request->user()->id,
                    'document_type' => $type,
                    'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'version' => $version,
                    'is_current' => true,
                    'status_id' => Status::idFor(CandidateDocument::STATUS_MODULE, 'pending_verification'),
                ])->save();

                return $document;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }

            return $this->serverError($exception, 'Could not upload the document. Please try again.');
        }

        if ($type === CandidateDocument::TYPE_RESUME) {
            $this->storeResumeText($candidate, $file, $parser);
        }

        return (new CandidateDocumentResource($document->load(self::RELATIONS)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/candidates/{candidate}/documents/{document}',
        summary: 'Download a document',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [
            new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'document', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The file'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Candidate $candidate, CandidateDocument $document): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $candidate);

        if (! Storage::disk(self::DISK)->exists($document->file_path)) {
            return response()->json(['message' => 'The file is missing.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return Storage::disk(self::DISK)->download($document->file_path, $document->original_filename);
    }

    #[OA\Patch(
        path: '/candidates/{candidate}/documents/{document}',
        summary: 'Verify or reject a document',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status_id'], properties: [
            new OA\Property(property: 'status_id', description: 'Status of module "document"', type: 'integer', example: 12),
        ])),
        tags: ['Candidates'],
        parameters: [
            new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'document', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CandidateDocument')])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(Request $request, Candidate $candidate, CandidateDocument $document): CandidateDocumentResource
    {
        Gate::authorize('update', $candidate);

        $validated = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->where('module', CandidateDocument::STATUS_MODULE)],
        ]);

        $isPending = (int) $validated['status_id'] === Status::idFor(CandidateDocument::STATUS_MODULE, 'pending_verification');
        $document->forceFill([
            'status_id' => $validated['status_id'],
            'verified_by' => $isPending ? null : $request->user()->id,
            'verified_at' => $isPending ? null : now(),
        ])->save();

        return new CandidateDocumentResource($document->load(self::RELATIONS));
    }

    #[OA\Delete(
        path: '/candidates/{candidate}/documents/{document}',
        summary: 'Delete a document',
        description: 'Deleting the current resume or photo makes the previous version current again.',
        security: [['sanctum' => []]],
        tags: ['Candidates'],
        parameters: [
            new OA\Parameter(name: 'candidate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'document', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message', example: ['message' => 'Document deleted.'])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Candidate $candidate, CandidateDocument $document): JsonResponse
    {
        Gate::authorize('update', $candidate);

        try {
            DB::transaction(function () use ($candidate, $document) {
                $document->delete();

                if ($document->is_current && in_array($document->document_type, CandidateDocument::SINGLE_CURRENT_TYPES, true)) {
                    $candidate->documents()->where('document_type', $document->document_type)->orderByDesc('version')->first()?->forceFill(['is_current' => true])->save();
                }
            });
            Storage::disk(self::DISK)->delete($document->file_path);
        } catch (Throwable $exception) {
            return $this->serverError($exception, 'Could not delete the document. Please try again.');
        }

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * Keep the resume text for keyword search. A file we can't read (e.g. scanned PDF) is still saved.
     */
    private function storeResumeText(Candidate $candidate, UploadedFile $file, JdParser $parser): void
    {
        try {
            $text = $parser->extractText($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
            $candidate->forceFill(['resume_text' => trim($text) !== '' ? $text : null])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
