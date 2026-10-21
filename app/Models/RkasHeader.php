<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class RkasHeader extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['dikirim_dinas_at' => 'datetime', 'ditinjau_at' => 'datetime'];

    public const LABEL_STATUS = [
        'draft' => 'Draft', 'diajukan' => 'Diajukan ke Kepsek', 'disetujui' => 'Disetujui Kepsek',
        'dikirim_dinas' => 'Menunggu Tinjauan Dinas', 'revisi' => 'Perlu Revisi (Dinas)',
        'disahkan' => 'Disahkan Dinas', 'dikunci' => 'Dikunci',
    ];

    public function details()
    {
        return $this->hasMany(RkasDetail::class);
    }

    public function total(): int
    {
        return (int) $this->details->sum('jumlah');
    }
}
