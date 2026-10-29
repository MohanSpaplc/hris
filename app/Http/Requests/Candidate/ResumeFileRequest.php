<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\Job\JdFileRequest;

/**
 * A resume to read: same file checks as a JD file (type from the content, max 5 MB).
 */
class ResumeFileRequest extends JdFileRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('candidates.create') || $this->user()->hasPermission('candidates.update');
    }
}
