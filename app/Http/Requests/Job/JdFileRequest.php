<?php

namespace App\Http\Requests\Job;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class JdFileRequest extends FormRequest
{
    public const EXTENSIONS = ['pdf', 'docx', 'xlsx', 'txt'];

    private const TYPE_ERROR = 'Upload a PDF, Word (.docx), Excel (.xlsx) or text file. Old .doc / .xls files: save as .docx / .xlsx first.';

    public function authorize(): bool
    {
        return $this->user()->hasPermission('jobs.create') || $this->user()->hasPermission('jobs.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                function (string $attribute, mixed $file, Closure $fail) {
                    if ($file instanceof UploadedFile && $this->detectType($file) === null) {
                        $fail(self::TYPE_ERROR);
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['file.max' => 'The file must be 5 MB or smaller.'];
    }

    /**
     * The real document type from the file content (the name alone is never trusted).
     * Office files are zips: some tools produce ones that detect only as "application/zip",
     * so we look inside for the part that makes it a Word / Excel file.
     */
    public function documentType(): ?string
    {
        return $this->detectType($this->file('file'));
    }

    private function detectType(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        return match ($extension) {
            'pdf' => $mime === 'application/pdf' ? 'pdf' : null,
            'txt' => str_starts_with($mime, 'text/') ? 'txt' : null,
            'docx' => $this->zipContains($file, 'word/document.xml') ? 'docx' : null,
            'xlsx' => $this->zipContains($file, 'xl/workbook.xml') ? 'xlsx' : null,
            default => null,
        };
    }

    private function zipContains(UploadedFile $file, string $entry): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            return false;
        }
        $found = $zip->locateName($entry) !== false;
        $zip->close();

        return $found;
    }
}
