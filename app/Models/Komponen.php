<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Komponen extends Model
{
    protected $guarded = [];

    public function rekenings(): HasMany
    {
        return $this->hasMany(Rekening::class);
    }
}
