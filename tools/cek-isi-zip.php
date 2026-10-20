<?php
// Periksa integritas isi ZIP paket portable: bandingkan setiap entri berkas
// dengan sumbernya, byte-for-byte (ukuran + md5).
// - Berkas aplikasi/vendor -> dibandingkan dengan folder repo.
// - *.bat                  -> dibandingkan setelah normalisasi CRLF (pengepak menormalkan .bat).
// - storage/*              -> rakitan pengepak (dilewati, dihitung).
// - php/*                  -> dibandingkan dengan folder PHP yang dipakai pengepak.
// - agen/*                 -> dibandingkan dengan isi ZIP agen sumber (TarikArkas).
//
// Pakai: php tools/cek-isi-zip.php storage/app/paket/SiapBOSP-Portable-v12.zip . [agen.zip]
//        (jalankan dari akar repo; argumen ke-3 opsional, default storage/app/agen/TarikArkas-v1.0.0.zip)
//
// Berguna setelah `php artisan siapbos:paket-portable` untuk memastikan tidak ada
// berkas yang tertangkap setengah tertulis (mis. repo sedang disunting bersamaan).
$zipPath = $argv[1] ?? '';
$rootRepo = rtrim($argv[2] ?? '', "\\/");
$agenZip = $argv[3] ?? ($rootRepo.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'agen'.DIRECTORY_SEPARATOR.'TarikArkas-v1.0.0.zip');

if (! is_file($zipPath) || $rootRepo === '') {
    fwrite(STDERR, "Pakai: php cek-isi-zip.php <paket.zip> <folder repo> [agen.zip]\n");
    exit(1);
}

$kecuali = ['.env.portable', 'PASANG-SIAPBOSP.bat', 'php/php.ini'];

$z = new ZipArchive;
if ($z->open($zipPath) !== true) {
    fwrite(STDERR, "Tidak bisa membuka ZIP: $zipPath\n");
    exit(1);
}

$agen = null;
if (is_file($agenZip)) {
    $agen = new ZipArchive;
    if ($agen->open($agenZip) !== true) {
        $agen = null;
    }
}

// Peta nama entri agen sumber (nama di ZIP agen memakai backslash).
$agenMap = null;
if ($agen !== null) {
    $agenMap = [];
    for ($i = 0; $i < $agen->numFiles; $i++) {
        $nn = $agen->statIndex($i)['name'];
        $agenMap[str_replace('\\', '/', $nn)] = $nn;
    }
}

$phpSrc = rtrim(dirname(PHP_BINARY), "\\/");
echo "Sumber PHP  : $phpSrc\n";
echo 'Sumber agen : '.(is_file($agenZip) ? $agenZip : 'TIDAK ADA')."\n\n";

$diperiksa = 0;
$beda = 0;
$dilewati = 0;
$info = 0;

function md5Entri(ZipArchive $zip, string $nama): string
{
    $aliran = $zip->getStream($nama);

    return md5(stream_get_contents($aliran));
}

for ($i = 0; $i < $z->numFiles; $i++) {
    $st = $z->statIndex($i);
    $name = $st['name'];
    if (substr($name, -1) === '/') {
        continue; // folder
    }
    if (strpos($name, 'SiapBOSP/') !== 0) {
        echo "[LUAR-PAKET] $name\n";

        continue;
    }
    $rel = str_replace('\\', '/', substr($name, strlen('SiapBOSP/')));

    if (in_array($rel, $kecuali, true)) {
        $dilewati++;

        continue;
    }

    $ukuranPaket = (int) $st['size'];
    $hashPaket = md5Entri($z, $name);

    // --- php/*: bandingkan dengan folder PHP sumber ---
    if (strpos($rel, 'php/') === 0) {
        $sumber = $phpSrc.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($rel, 4));
        if (! is_file($sumber)) {
            echo "[PHP-TANPA-SUMBER] $rel\n";
            $info++;

            continue;
        }
        $diperiksa++;
        if (filesize($sumber) !== $ukuranPaket || md5_file($sumber) !== $hashPaket) {
            echo '[BEDA-PHP] '.$rel.' : sumber='.filesize($sumber)." paket=$ukuranPaket\n";
            $beda++;
        }

        continue;
    }

    // --- agen/*: bandingkan dengan isi ZIP agen sumber ---
    if (strpos($rel, 'agen/') === 0) {
        if ($agenMap === null) {
            $info++;

            continue;
        }
        $dalamAgen = str_replace('\\', '/', substr($rel, 5));
        $src = $agenMap[$dalamAgen] ?? null;
        if ($src === null) {
            echo "[AGEN-TANPA-SUMBER] $rel\n";
            $info++;

            continue;
        }
        $sa = $agen->statIndex($agen->locateName($src));
        $diperiksa++;
        if ((int) $sa['size'] !== $ukuranPaket || md5Entri($agen, $src) !== $hashPaket) {
            echo '[BEDA-AGEN] '.$rel.' : sumber='.$sa['size']." paket=$ukuranPaket\n";
            $beda++;
        }

        continue;
    }

    // --- storage/*: rakitan pengepak ---
    if (strpos($rel, 'storage/') === 0) {
        $dilewati++;

        continue;
    }

    // --- sisanya: bandingkan dengan repo (bat dinormalkan CRLF) ---
    $disk = $rootRepo.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (! is_file($disk)) {
        echo "[TANPA-PASANGAN-REPO] $rel\n";
        $info++;

        continue;
    }

    $diperiksa++;
    if (strtolower(substr($rel, -4)) === '.bat') {
        $isi = (string) file_get_contents($disk);
        $isi = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $isi));
        if (md5($isi) !== $hashPaket) {
            echo "[BEDA-BAT]     $rel\n";
            $beda++;
        }

        continue;
    }

    if (filesize($disk) !== $ukuranPaket) {
        echo '[BEDA-UKURAN]  '.$rel.' : repo='.filesize($disk)." paket=$ukuranPaket\n";
        $beda++;

        continue;
    }
    if (md5_file($disk) !== $hashPaket) {
        echo "[BEDA-ISI]     $rel : ukuran sama, isi beda\n";
        $beda++;
    }
}

$z->close();
if ($agen !== null) {
    $agen->close();
}

echo "\nDiperiksa (ukuran+hash) : $diperiksa\n";
echo "Dilewati (rakitan)      : $dilewati\n";
echo "Info tanpa sumber       : $info\n";
echo "Beda                    : $beda\n";
echo $beda === 0 ? "SEMUA ISI COCOK\n" : "ADA ISI TIDAK COCOK\n";
exit($beda === 0 ? 0 : 1);
