<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RkasDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sudah_diinput_arkas' => 'boolean',
    ];

    public function header()
    {
        return $this->belongsTo(RkasHeader::class, 'rkas_header_id');
    }

    public function rekening()
    {
        return $this->belongsTo(Rekening::class);
    }

    public function standarHarga()
    {
        return $this->belongsTo(StandarHarga::class, 'standar_harga_id');
    }

    public function rkt()
    {
        return $this->belongsTo(PerencanaanRkt::class, 'rkt_id');
    }

    /**
     * Peta volume 12 bulan (Januari=1 s/d Desember=12) ala Kertas Kerja ARKAS 4.
     * Bila `bulan_volumes` belum diisi (mis. RKAS lama / impor CSV), fallback otomatis:
     * - dari `periode_volume` tahap 1/2 bila tersedia, atau
     * - dibagi proporsional 50% Januari (Tahap 1) & 50% Juli (Tahap 2) atau ke Januari bila ganjil.
     *
     * @return array<int, float>
     */
    public function bulanMap(): array
    {
        $out = array_fill(1, 12, 0.0);
        if (! empty($this->bulan_volumes)) {
            $raw = is_array($this->bulan_volumes) ? $this->bulan_volumes : json_decode((string) $this->bulan_volumes, true);
            if (is_array($raw)) {
                for ($m = 1; $m <= 12; $m++) {
                    $out[$m] = max(0.0, (float) ($raw[$m] ?? $raw[(string) $m] ?? 0));
                }
                if (array_sum($out) > 0) {
                    return $out;
                }
            }
        }

        if (! empty($this->periode_volume)) {
            $pv = json_decode((string) $this->periode_volume, true);
            if (is_array($pv) && array_sum(array_map('floatval', $pv)) > 0) {
                $out[1] = (float) ($pv[1] ?? $pv['1'] ?? 0);
                $out[7] = (float) ($pv[2] ?? $pv['2'] ?? 0);
                for ($k = 3; $k <= 5; $k++) {
                    $out[min(12, $k * 2)] += (float) ($pv[$k] ?? $pv[(string) $k] ?? 0);
                }

                return $out;
            }
        }

        $out[1] = (float) max(0, (int) $this->banyak);

        return $out;
    }

    /** Nilai rupiah per bulan (1..12) berdasarkan `bulanMap()` dan `jumlah`. */
    public function nominalPerBulan(): array
    {
        $map = $this->bulanMap();
        $totVol = array_sum($map);
        $out = array_fill(1, 12, 0);
        if ($totVol <= 0) {
            return $out;
        }
        $sisa = (int) $this->jumlah;
        $lastNonZero = 1;
        for ($m = 1; $m <= 12; $m++) {
            if ($map[$m] > 0) {
                $lastNonZero = $m;
            }
        }
        for ($m = 1; $m <= 12; $m++) {
            if ($map[$m] <= 0) {
                continue;
            }
            if ($m === $lastNonZero) {
                $out[$m] = max(0, $sisa);
            } else {
                $n = (int) round((int) $this->jumlah * ($map[$m] / $totVol));
                $out[$m] = $n;
                $sisa -= $n;
            }
        }

        return $out;
    }

    public function nominalTahap1(): int
    {
        $nb = $this->nominalPerBulan();

        return array_sum(array_slice($nb, 0, 6));
    }

    public function nominalTahap2(): int
    {
        $nb = $this->nominalPerBulan();

        return array_sum(array_slice($nb, 6, 6));
    }

    /** Kelompok akun belanja ARKAS 4 untuk validasi Pergeseran vs Perubahan. */
    public static function kelompokAkunDariKode(string $kode): string
    {
        if (str_starts_with($kode, '5.2.05') || str_starts_with($kode, '5.2.5')) {
            return '5.2.05 Belanja Modal Aset Tetap Lainnya';
        }
        if (str_starts_with($kode, '5.2.')) {
            return '5.2.02 Belanja Modal Peralatan dan Mesin';
        }

        return '5.1.02 Belanja Operasional';
    }
}
