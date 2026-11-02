<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArkasImportRun extends Model
{
    public const JENIS_IMPOR = 'impor';

    public const JENIS_INTEGRITAS = 'integritas';

    public const JENIS_REKONSILIASI = 'rekonsiliasi';

    public const JENIS_RKAS_CSV = 'rkas-csv';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
