<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasNota extends Model
{
    protected $guarded = [];

    protected $casts = ['tanggal_nota' => 'date'];
}
