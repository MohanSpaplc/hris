<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file of a candidate. Files are on the private disk; only the path is stored.
 */
class CandidateDocument extends Model
{
    public const STATUS_MODULE = 'document';

    public const TYPE_RESUME = 'resume';

    public const TYPES = ['resume', 'photo', 'certificate', 'id_proof', 'relieving_letter', 'payslip', 'offer_letter', 'other'];

    /**
     * Types a candidate has only one current file of: a new upload becomes the next version.
     *
     * @var list<string>
     */
    public const SINGLE_CURRENT_TYPES = ['resume', 'photo'];

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_current' => 'boolean',
            'file_size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
