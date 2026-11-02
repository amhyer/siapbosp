<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncSnapshot extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'snapshot_date' => 'date',
        'is_frozen' => 'boolean',
        'frozen_at' => 'datetime',
        'payload_summary' => 'array',
        'total_pendapatan' => 'integer',
        'total_belanja' => 'integer',
        'saldo' => 'integer',
        'total_transaksi' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lpjSubmission(): BelongsTo
    {
        return $this->belongsTo(LpjSubmission::class, 'frozen_by_lpj_id');
    }
}
