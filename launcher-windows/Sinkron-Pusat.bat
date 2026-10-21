@echo off
REM ============================================================
REM  Sinkron ke Pusat satu-klik (Tahap C2/D)
REM  Mengirim transaksi baru/berubah di laptop ini ke website
REM  SiapBOSP pusat (idempoten: kirim ulang aman, tak duplikat).
REM  Syarat: launcher-windows\akun-sekolah.conf ada
REM  (otomatis terisi saat mengunduh aplikasi dari website).
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0.."

REM --- folder sementara milik aplikasi (tak bergantung TMP/TEMP laptop) ---
if not exist "%~dp0..\temp" mkdir "%~dp0..\temp"
set "TMP=%~dp0..\temp"
set "TEMP=%~dp0..\temp"

set PHPBIN=
if exist "php\php.exe" set PHPBIN="php\php.exe"
if not defined PHPBIN (
  where php >nul 2>nul
  if not errorlevel 1 set PHPBIN=php
)
if not defined PHPBIN (
  for /d %%D in ("%LocalAppData%\Microsoft\WinGet\Packages\PHP.PHP.8.*") do (
    if exist "%%D\php.exe" set PHPBIN="%%D\php.exe"
  )
)
if not defined PHPBIN (
  echo  [GAGAL] PHP tidak ditemukan ^(folder "php" bundel juga tidak ada^).
  echo  Unduh paket aplikasi terbaru dari website SiapBOSP.
  pause
  exit /b 1
)

echo  Sinkron ke Pusat ...
echo.
%PHPBIN% artisan siapbos:sync-push
if errorlevel 1 (
  echo.
  echo  Sinkronisasi BELUM berhasil - baca pesan di atas.
  pause
  exit /b 1
)
echo.
echo  Selesai. Data lokal kini tercatat di website pusat.
pause
endlocal
