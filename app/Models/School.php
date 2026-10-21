<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function alamatLengkap(): string
    {
        return collect([$this->desa, $this->kecamatan, $this->kab_kota, $this->provinsi])->filter()->implode(', ');
    }
}
