<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class PerencanaanRkm extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    public function pbd()
    {
        return $this->belongsTo(PerencanaanPbd::class, 'pbd_id');
    }

    public function rkts()
    {
        return $this->hasMany(PerencanaanRkt::class, 'rkm_id');
    }
}
