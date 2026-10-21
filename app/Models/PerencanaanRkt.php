<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class PerencanaanRkt extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['butuh_anggaran' => 'boolean'];

    public function rkm()
    {
        return $this->belongsTo(PerencanaanRkm::class, 'rkm_id');
    }

    public function rekening()
    {
        return $this->belongsTo(Rekening::class);
    }
}
