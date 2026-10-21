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
        'draft' => 'Draft',
        'diajukan' => 'Diajukan ke Kepsek',
        'disetujui' => 'Disetujui Kepsek',
        'dikirim_dinas' => 'Menunggu Tinjauan Dinas',
        'revisi' => 'Perlu Revisi (Dinas)',
        'disahkan' => 'Disahkan Dinas',
        'dikunci' => 'Dikunci',
    ];

    public const LABEL_JENIS = [
        'murni' => 'RKAS Murni',
        'pergeseran' => 'Pergeseran RKAS',
        'perubahan' => 'Perubahan RKAS',
    ];

    public const SUMBER_DANA_ARKAS = [
        'BOS Reguler',
        'BOS Kinerja',
        'SiLPA BOSP Kinerja',
        'BOSP Afirmasi',
        'BOSP Daerah',
        'SILPA',
        'Sumber Dana Lainnya',
    ];

    public function details()
    {
        return $this->hasMany(RkasDetail::class);
    }

    public function acuan()
    {
        return $this->belongsTo(self::class, 'acuan_header_id');
    }

    public function total(): int
    {
        return (int) $this->details->sum('jumlah');
    }

    public function labelJenis(): string
    {
        return self::LABEL_JENIS[$this->jenis_rkas ?? 'murni'] ?? ucfirst((string) $this->jenis_rkas);
    }
}
