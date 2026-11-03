<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OfficialDocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'issued_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    public function replacer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }

    public function hasValidIntegrity(): bool
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->storage_path)) {
            return false;
        }

        return hash_equals($this->sha256, hash('sha256', $disk->get($this->storage_path)));
    }
}
