<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class KasTransaction extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['tanggal' => 'date'];

    public const TYPES = [
        'pendapatan' => 'Pendapatan', 'ambil_simpanan' => 'Ambil Simpanan',
        'mutasi_tunai_ke_bank' => 'Mutasi Tunai ke Bank', 'belanja' => 'Belanja',
        'setor_pajak' => 'Setor Pajak', 'setor_utang_pajak' => 'Setor Utang Pajak',
    ];

    public function details()
    {
        return $this->hasMany(KasDetail::class);
    }

    public function taxes()
    {
        return $this->hasMany(KasTax::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'kas_transaction_id');
    }

    public function bosType()
    {
        return $this->belongsTo(BosType::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function belanja()
    {
        return $this->belongsTo(KasTransaction::class, 'belanja_transaction_id');
    }

    public function labelType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
