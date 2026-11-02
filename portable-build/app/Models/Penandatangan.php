<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Penandatangan extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $table = 'penandatangans';
}
