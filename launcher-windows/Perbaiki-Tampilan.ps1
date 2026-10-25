# ============================================================================
#  Perbaiki-Tampilan.ps1 — membuat SiapBOSP kembali berwarna.
#
#  Dipanggil oleh Perbaiki-Tampilan.bat (klik 2x). Alurnya:
#    1. cari folder aplikasi yang SEDANG dilayani di port 8000
#       (server lama = kode/aset lama; ini penyebab tampilan "polos")
#    2. pastikan folder itu punya public\build lengkap (manifest + CSS + JS);
#       bila tidak, pakai aset dari folder ini, atau unduh dari GitHub
#    3. hapus penanda dev server public\hot yang basi
#    4. bersihkan cache tampilan Laravel
#    5. nyalakan ulang server dari folder yang dilayani
#    6. VERIFIKASI CSS benar-benar terunduh (HTTP 200 + ukuran wajar),
#       lalu buka browser
#
#  Nama berkas aset dibaca dari manifest.json, bukan ditulis di sini, supaya
#  tetap benar setelah CSS dibangun ulang (nama berkas ber-hash bisa berubah).
#
#  Tidak menyentuh database maupun data sekolah. Aman dijalankan berulang.
# ============================================================================

$ErrorActionPreference = 'Stop'

$Source  = Split-Path -Parent $PSScriptRoot          # folder induk launcher-windows
$Port    = 8000
$RepoRaw = 'https://raw.githubusercontent.com/amhyer/siapbosp/main'

function Judul($teks) { Write-Host ''; Write-Host "  $teks" -ForegroundColor Cyan }
function Ok($teks)    { Write-Host "  [OK] $teks" -ForegroundColor Green }
function Warn($teks)  { Write-Host "  [!]  $teks" -ForegroundColor Yellow }
function Gagal($teks) { Write-Host "  [X]  $teks" -ForegroundColor Red }

Write-Host ''
Write-Host '  ============================================' -ForegroundColor White
Write-Host '   Perbaiki Tampilan SiapBOSP' -ForegroundColor White
Write-Host '  ============================================' -ForegroundColor White
Write-Host "  Folder aplikasi ini : $Source"
Write-Host "  Port yang diperiksa : $Port"

if (-not (Test-Path (Join-Path $Source 'artisan'))) {
    Gagal '"artisan" tidak ditemukan. Jalankan berkas ini dari folder "launcher-windows" milik aplikasi.'
    Read-Host 'Tekan Enter untuk menutup'
    exit 1
}

# ---------------------------------------------------------------------------
# Pembantu: baca manifest, unduh aset yang dirujuknya
# ---------------------------------------------------------------------------

# Kembalikan daftar berkas aset (relatif terhadap build/) dari manifest.
function BacaManifest($manifestPath) {
    if (-not (Test-Path $manifestPath)) { return @() }
    try {
        $json = Get-Content -Raw -Path $manifestPath | ConvertFrom-Json
    } catch {
        return @()
    }

    $daftar = @()
    foreach ($entri in $json.PSObject.Properties) {
        $nilai = $entri.Value
        if ($nilai -and $nilai.file) { $daftar += [string]$nilai.file }
    }
    return $daftar
}

# Lengkapi public\build di $folder. True bila lengkap.
function LengkapiAset($folder) {
    $buildDir = Join-Path $folder 'build'
    $manifest = Join-Path $buildDir 'manifest.json'
    $asetDir  = Join-Path $buildDir 'assets'
    New-Item -ItemType Directory -Force -Path $asetDir | Out-Null

    $rujukan = BacaManifest $manifest
    $kurang = ($rujukan.Count -eq 0)
    if (-not $kurang) {
        foreach ($rel in $rujukan) {
            $path = Join-Path $buildDir ($rel -replace '/', '\')
            if (-not (Test-Path $path)) { $kurang = $true }
        }
    }
    if (-not $kurang) { return $true }

    Write-Host "        Aset belum lengkap di: $folder"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Write-Host '        Mengunduh manifest + aset dari GitHub ...'
        Invoke-WebRequest -UseBasicParsing -Uri "$RepoRaw/public/build/manifest.json" -OutFile $manifest

        $rujukan = BacaManifest $manifest
        if ($rujukan.Count -eq 0) { throw 'manifest tidak berisi entri aset' }

        foreach ($rel in $rujukan) {
            $relUrl  = $rel -replace '\\', '/'
            $relDisk = $rel -replace '/', '\'
            $tujuan  = Join-Path $buildDir $relDisk
            New-Item -ItemType Directory -Force -Path (Split-Path -Parent $tujuan) | Out-Null
            Invoke-WebRequest -UseBasicParsing -Uri "$RepoRaw/public/build/$relUrl" -OutFile $tujuan
            Write-Host "          - $relUrl"
        }
        Ok 'Aset diunduh dari GitHub.'
        return $true
    } catch {
        Gagal "Gagal mengunduh aset: $($_.Exception.Message)"
        Write-Host '        Alternatif — di komputer yang ada Node.js, jalankan pada folder ini:'
        Write-Host '            npm ci'
        Write-Host '            npm run build'
        Write-Host '        lalu salin folder public\build ke folder aplikasi yang dilayani.'
        return $false
    }
}

# ---------------------------------------------------------------------------
# 1. Cari folder yang sedang melayani port
# ---------------------------------------------------------------------------
Judul '[1/6] Mencari server yang sedang berjalan di port ini ...'

$pidServer = $null
try {
    $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
            Select-Object -First 1
    if ($conn) { $pidServer = [int]$conn.OwningProcess }
} catch {
    $baris = netstat -ano | Select-String ":$Port " | Select-Object -First 1
    if ($baris) {
        $bagian = ($baris.ToString() -split '\s+') | Where-Object { $_ -ne '' }
        if ($bagian.Count -ge 5) { $pidServer = [int]$bagian[-1] }
    }
}

$Disajikan = $Source
if ($pidServer) {
    try {
        $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$pidServer"
        $exe  = $proc.ExecutablePath
        if ($exe -and (Test-Path $exe)) {
            $folderExe = Split-Path -Parent $exe
            if ((Split-Path -Leaf $folderExe) -ieq 'php') { $Disajikan = Split-Path -Parent $folderExe }
        }
        Ok "Server berjalan (PID $pidServer)."
        Write-Host "        Folder yang dilayani: $Disajikan"
    } catch {
        Warn "Server berjalan (PID $pidServer), folder tidak bisa dipastikan."
    }
} else {
    Warn 'Tidak ada server yang berjalan di port ini.'
}

$samaFolder = ((Resolve-Path $Disajikan).Path -eq (Resolve-Path $Source).Path)
if ($samaFolder) {
    Ok 'Folder yang dilayani sama dengan folder ini.'
} else {
    Warn 'Folder yang dilayani BERBEDA dengan folder ini — aset akan disalin ke sana.'
}

# ---------------------------------------------------------------------------
# 2. Pastikan aset ada di folder ini
# ---------------------------------------------------------------------------
Judul '[2/6] Memeriksa aset CSS/JS hasil build ...'
LengkapiAset (Join-Path $Source 'public') | Out-Null

# ---------------------------------------------------------------------------
# 3. Salin ke folder yang dilayani (bila berbeda)
# ---------------------------------------------------------------------------
Judul '[3/6] Menyiapkan aset di folder yang dilayani ...'
$publikDisajikan = Join-Path $Disajikan 'public'

if ($samaFolder) {
    Ok 'Tidak perlu menyalin (folder sama).'
} else {
    $sumberBuild = Join-Path $Source 'public\build'
    if (Test-Path $sumberBuild) {
        New-Item -ItemType Directory -Force -Path (Join-Path $publikDisajikan 'build\assets') | Out-Null
        Copy-Item -Force -Recurse -Path (Join-Path $sumberBuild '*') -Destination (Join-Path $publikDisajikan 'build')
        Ok 'public\build disalin ke folder yang dilayani.'
    } else {
        Warn 'Folder ini belum punya public\build; mengunduh langsung ke folder yang dilayani.'
        LengkapiAset $publikDisajikan | Out-Null
    }
}

# ---------------------------------------------------------------------------
# 4. Bersihkan penanda dev server + cache
# ---------------------------------------------------------------------------
Judul '[4/6] Membersihkan penanda dev server dan cache ...'

$hot = Join-Path $publikDisajikan 'hot'
if (Test-Path $hot) {
    Remove-Item -Force $hot -ErrorAction SilentlyContinue
    Ok 'public\hot (penanda "npm run dev") dihapus.'
} else {
    Ok 'Tidak ada public\hot.'
}

$php = $null
$phpBundel = Join-Path $Disajikan 'php\php.exe'
if (Test-Path $phpBundel) {
    $php = $phpBundel
} else {
    $cari = Get-Command php -ErrorAction SilentlyContinue
    if ($cari) { $php = $cari.Source }
}

if ($php) {
    Ok "PHP: $php"
    & $php (Join-Path $Disajikan 'artisan') optimize:clear 2>&1 | Out-Null
    Ok 'Cache tampilan/konfigurasi dibersihkan.'
} else {
    Warn 'php.exe tidak ditemukan — cache tidak dibersihkan (biasanya tetap aman).'
}

# ---------------------------------------------------------------------------
# 5. Nyalakan ulang server
# ---------------------------------------------------------------------------
Judul '[5/6] Menyalakan ulang server ...'

if ($pidServer) {
    try {
        Stop-Process -Id $pidServer -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        Ok "Server lama (PID $pidServer) dihentikan."
    } catch {
        Warn "Tidak bisa menghentikan PID $pidServer : $($_.Exception.Message)"
    }
}

if ($php) {
    $argumen = @('artisan', 'serve', '--host=127.0.0.1', "--port=$Port")
    Start-Process -FilePath $php -ArgumentList $argumen -WorkingDirectory $Disajikan -WindowStyle Minimized
    Ok 'Server baru dijalankan dari folder yang dilayani.'

    $siap = $false
    for ($i = 1; $i -le 30; $i++) {
        try {
            $tcp = New-Object Net.Sockets.TcpClient
            $tcp.Connect('127.0.0.1', $Port)
            $tcp.Close()
            $siap = $true
            break
        } catch {
            Start-Sleep -Seconds 1
        }
    }
    if ($siap) { Ok 'Server merespons.' } else { Gagal 'Server tidak merespons dalam 30 detik.' }
} else {
    Gagal 'php.exe tidak ditemukan; jalankan SiapBOSP.bat untuk menyalakan server.'
}

# ---------------------------------------------------------------------------
# 6. Verifikasi CSS benar-benar terkirim
# ---------------------------------------------------------------------------
Judul '[6/6] Memeriksa apakah CSS benar-benar terkirim ke browser ...'

$manifestDisajikan = Join-Path $publikDisajikan 'build\manifest.json'
$rujukan = BacaManifest $manifestDisajikan
$cssRel = $null
foreach ($rel in $rujukan) {
    if ($rel -like '*.css') { $cssRel = $rel; break }
}

$cssOk = $false
if (-not $cssRel) {
    Gagal "Tidak ada rujukan CSS di $manifestDisajikan"
} else {
    $url = "http://127.0.0.1:$Port/build/" + ($cssRel -replace '\\', '/')
    for ($i = 1; $i -le 10; $i++) {
        try {
            $jawab = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 5
            if ($jawab.StatusCode -eq 200 -and $jawab.RawContentLength -gt 10000) {
                $cssOk = $true
                break
            }
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    Write-Host ''
    if ($cssOk) {
        Ok "CSS terunduh normal ($url)"
        Ok 'Tampilan seharusnya sudah berwarna.'
    } else {
        Gagal "CSS TIDAK bisa diunduh dari $url"
        Write-Host '        Kirimkan seluruh isi jendela ini kepada pengembang.'
    }
}

$alamat = "http://127.0.0.1:$Port/login?v=$(Get-Date -Format 'yyyyMMddHHmmss')"
Start-Process $alamat
Write-Host ''
Write-Host '  Bila browser masih menampilkan halaman lama, tekan Ctrl+Shift+R.' -ForegroundColor White
Write-Host '  Diagnosa lengkap (bukti untuk pengembang): ' -NoNewline
Write-Host "http://127.0.0.1:$Port/_aset" -ForegroundColor White
Write-Host ''
Read-Host 'Tekan Enter untuk menutup jendela ini'

if ($cssOk) { exit 0 } else { exit 2 }
