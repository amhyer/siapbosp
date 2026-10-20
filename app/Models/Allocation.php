<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    public function bosType()
    {
        return $this->belongsTo(BosType::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}
