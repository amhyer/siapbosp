<?php

namespace App\Models\Concerns;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $q) {
            $u = auth()->user();
            if ($u && $u->school_id) {
                $q->where($q->getModel()->getTable().'.school_id', $u->school_id);
            }
        });
        static::creating(function ($m) {
            $u = auth()->user();
            if ($u && $u->school_id && ! $m->school_id) {
                $m->school_id = $u->school_id;
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
