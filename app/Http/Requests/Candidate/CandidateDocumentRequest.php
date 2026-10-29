<?php

namespace App\Http\Requests\Candidate;

use App\Models\CandidateDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CandidateDocumentRequest extends FormRequest
{
    /**
     * Allowed extensions per document type; other types accept OTHER_EXTENSIONS.
     *
     * @var array<string, list<string>>
     */
    public const EXTENSIONS = [
        'resume' => ['pdf', 'docx', 'txt'],
        'photo' => ['jpg', 'jpeg', 'png', 'webp'],
    ];

    /**
     * @var list<string>
     */
    public const OTHER_EXTENSIONS = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];

    public function authorize(): bool
    {
        return $this->user()->hasPermission('candidates.create') || $this->user()->hasPermission('candidates.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = self::EXTENSIONS[$this->input('document_type')] ?? self::OTHER_EXTENSIONS;

        return [
            'document_type' => ['required', Rule::in(CandidateDocument::TYPES)],
            // mimes checks the real content type, not just the name.
            'file' => ['required', 'file', 'max:5120', 'mimes:'.implode(',', $extensions)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $extensions = self::EXTENSIONS[$this->input('document_type')] ?? self::OTHER_EXTENSIONS;

        return [
            'file.max' => 'The file must be 5 MB or smaller.',
            'file.mimes' => 'Allowed file types: '.strtoupper(implode(', ', $extensions)).'.',
        ];
    }
}
