<?php

namespace App\Rules;

use App\Support\Sandi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan validasi sandi kuat (R-01). Pesan kesalahan sudah berbahasa Indonesia
 * dan menjelaskan apa yang harus diperbaiki.
 */
class SandiKuat implements ValidationRule
{
    public function __construct(
        private ?string $email = null,
        private ?string $nama = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $masalah = Sandi::masalah(is_string($value) ? $value : null, $this->email, $this->nama);

        if ($masalah !== null) {
            $fail($masalah);
        }
    }
}
