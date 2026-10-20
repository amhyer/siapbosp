@echo off
REM ============================================================
REM  HAPUS SiapBOSP dari laptop ini (aplikasi + data lokal).
REM  - Muncul dialog pilihan: cadangkan data dulu / hapus tanpa
REM    cadangan / batal.
REM  - Aplikasi yang masih berjalan ditutup otomatis.
REM  - Pintasan Desktop + Start Menu dan entri "Aplikasi & fitur"
REM    ikut dibersihkan.
REM  - Data yang sudah tersinkron ke website pusat tetap aman.
REM  Mode senyap (tanpa dialog, cadangkan otomatis ke Documents):
REM    Hapus-SiapBOSP.bat --senyap
REM ============================================================
setlocal EnableDelayedExpansion

set "DEST=%LocalAppData%\Programs\SiapBOSP"
if defined SIPBOS_DEST set "DEST=!SIPBOS_DEST!"
set "SENYAP="
if /i "%~1"=="--senyap" set "SENYAP=1"
if defined SIPBOS_SENYAP set "SENYAP=1"
set "BACKUP="
if defined SENYAP set "BACKUP=1"

echo ================================================
echo   HAPUS SiapBOSP
echo   Folder : !DEST!
echo ================================================
echo.

if not exist "!DEST!" (
  echo  Folder pemasangan tidak ditemukan - mungkin sudah dihapus sebelumnya.
  echo  Membersihkan sisa pintasan dan entri daftar aplikasi...
  goto bersihkan_sisa
)

if not defined SENYAP (
  powershell -NoProfile -Command "Add-Type -AssemblyName System.Windows.Forms; $t='Hapus SiapBOSP dari laptop ini?' + [char]10 + [char]10 + 'YA    = cadangkan data dulu (database pembukuan + lampiran) ke Documents, lalu hapus.' + [char]10 + 'TIDAK = hapus semua TANPA cadangan.' + [char]10 + 'BATAL = urungkan.'; $j=[System.Windows.Forms.MessageBox]::Show($t,'Hapus SiapBOSP',[System.Windows.Forms.MessageBoxButtons]::YesNoCancel,[System.Windows.Forms.MessageBoxIcon]::Warning); if ($j -eq 'Yes') { exit 0 } elseif ($j -eq 'No') { exit 1 } else { exit 2 }"
  set "KODE=!errorlevel!"
  set "AKSI="
  if "!KODE!"=="0" set "AKSI=CADANG"
  if "!KODE!"=="1" set "AKSI=HAPUS"
  if not defined AKSI (
    echo  Dibatalkan - tidak ada yang dihapus.
    if not defined SENYAP pause
    exit /b 0
  )
  if "!AKSI!"=="CADANG" set "BACKUP=1"
)

REM --- hentikan aplikasi yang masih berjalan di folder ini ---
echo  Menutup aplikasi SiapBOSP yang masih berjalan...
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { (($_.Name -eq 'php.exe') -and $_.ExecutablePath -and ($_.ExecutablePath -like '*!DEST!*')) -or (($_.Name -eq 'cmd.exe') -and $_.CommandLine -and ($_.CommandLine -like '*!DEST!*') -and ($_.CommandLine -notlike '*Hapus-SiapBOSP*')) } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" >nul 2>nul

REM --- cadangkan data bila diminta ---
set "CAP="
if defined BACKUP (
  echo  Mencadangkan data (database + lampiran)...
  set "TUJUAN_CADANGAN=%USERPROFILE%\Documents"
  if defined SIPBOS_BACKUP set "TUJUAN_CADANGAN=!SIPBOS_BACKUP!"
  for /f %%a in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "STAMP=%%a"
  set "CAP=!TUJUAN_CADANGAN!\SiapBOSP-Backup-!STAMP!.zip"
  powershell -NoProfile -Command "try { $p=@(); if (Test-Path -LiteralPath '!DEST!\database') { $p += Join-Path '!DEST!' 'database' }; if (Test-Path -LiteralPath '!DEST!\storage\app') { $p += Join-Path '!DEST!' 'storage\app' }; if (Test-Path -LiteralPath '!DEST!\.env') { $p += Join-Path '!DEST!' '.env' }; if ($p.Count -eq 0) { Write-Output 'TIDAK-ADA-DATA'; exit 0 }; if (-not (Test-Path -LiteralPath '!TUJUAN_CADANGAN!')) { New-Item -ItemType Directory -Path '!TUJUAN_CADANGAN!' -Force | Out-Null }; Compress-Archive -Path $p -DestinationPath '!CAP!' -Force; exit 0 } catch { Write-Output ('CADANGAN-GAGAL: ' + $_.Exception.Message); exit 1 }"
  if errorlevel 1 (
    set "CAP="
    echo.
    echo  [GAGAL] Pencadangan gagal - penghapusan DIBATALKAN supaya data tidak hilang.
    echo  Muat ulang lalu pilih "TIDAK" untuk menghapus tanpa cadangan,
    echo  atau perbaiki dulu penyebab gagalnya.
    if not defined SENYAP pause
    exit /b 1
  )
  if exist "!CAP!" (
    echo  Cadangan aman: !CAP!
  ) else (
    echo  Tidak ada data lokal yang perlu dicadangkan.
  )
)

:bersihkan_sisa
REM --- bersihkan pintasan Desktop + Start Menu ---
if not defined SIPBOS_DEST (
  if exist "!DEST!\launcher-windows\buat-pintasan.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "!DEST!\launcher-windows\buat-pintasan.ps1" -Dest "!DEST!" -Hapus
  ) else (
    powershell -NoProfile -Command "Remove-Item (Join-Path ([Environment]::GetFolderPath('Desktop')) 'SiapBOSP.lnk') -Force -ErrorAction SilentlyContinue; Remove-Item (Join-Path ([Environment]::GetFolderPath('Desktop')) 'Tarik Data ARKAS - SiapBOSP.lnk') -Force -ErrorAction SilentlyContinue; Remove-Item (Join-Path ([Environment]::GetFolderPath('Desktop')) 'Sinkron ke Pusat - SiapBOSP.lnk') -Force -ErrorAction SilentlyContinue; Remove-Item (Join-Path ([Environment]::GetFolderPath('ApplicationData')) 'Microsoft\Windows\Start Menu\Programs\SiapBOSP') -Recurse -Force -ErrorAction SilentlyContinue"
  )
) else (
  echo  (mode uji: pintasan nyata tidak disentuh)
)

REM --- hapus entri "Aplikasi & fitur" + log pemasangan ---
if not defined SIPBOS_DEST (
  reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /f >nul 2>nul
  if exist "%LocalAppData%\SiapBOSP-Install.log" del /q "%LocalAppData%\SiapBOSP-Install.log" >nul 2>nul
)

REM --- hapus folder aplikasi ---
if exist "!DEST!" rmdir /s /q "!DEST!" 2>nul
if exist "!DEST!" (
  echo  Sebagian berkas masih terkunci - menunggu 3 detik lalu mencoba lagi...
  powershell -NoProfile -Command "Start-Sleep -Seconds 3" >nul 2>nul
  rmdir /s /q "!DEST!" 2>nul
)

echo.
if exist "!DEST!" (
  echo  [CATATAN] Sebagian berkas belum terhapus - kemungkinan masih terbuka.
  echo  Tutup semua jendela SiapBOSP lalu jalankan lagi Hapus-SiapBOSP.bat.
) else (
  echo  Selesai. SiapBOSP sudah dihapus dari laptop ini.
)
if defined CAP if exist "!CAP!" echo  Cadangan data Anda: !CAP!
if not defined SENYAP pause
endlocal
