<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TutupBuku extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public static function terkunci(int $schoolId, int $tahun, ?int $stageId): bool
    {
        if (! $stageId) {
            return false;
        }

        return static::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('tahun', $tahun)->where('stage_id', $stageId)->exists();
    }
}
