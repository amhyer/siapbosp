<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed', 'is_active' => 'boolean'];

    public const ROLES = ['superadmin', 'dinas', 'operator', 'kepsek'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function isSchoolScoped(): bool
    {
        return ! empty($this->school_id);
    }

    public function isDinas(): bool
    {
        return in_array($this->role, ['superadmin', 'dinas']);
    }
}
