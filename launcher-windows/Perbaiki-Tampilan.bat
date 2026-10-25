@echo off
REM ============================================================
REM  Perbaiki Tampilan SiapBOSP
REM
REM  Klik 2x berkas ini bila aplikasi terbuka tetapi tampil
REM  "polos" (tanpa warna, tombol jelek, tulisan besar semua).
REM
REM  Yang dilakukan (aman, tidak menyentuh data):
REM    1. menghapus penanda dev server public\hot yang basi
REM    2. membersihkan cache tampilan/konfigurasi Laravel
REM    3. memeriksa kelengkapan aset CSS (public\build)
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0.."

echo  ============================================
echo   Perbaiki Tampilan SiapBOSP
echo  ============================================
echo  Folder aplikasi: %CD%
echo.

if not exist "artisan" (
  echo  [GAGAL] Berkas "artisan" tidak ditemukan di folder ini.
  echo  Letakkan berkas ini di dalam folder "launcher-windows"
  echo  milik aplikasi SiapBOSP, lalu klik 2x lagi.
  pause
  exit /b 1
)

REM --- cari PHP: 1) bundel "php" 2) server.conf 3) PATH 4) lokasi umum ---
set PHPBIN=
if exist "php\php.exe" set PHPBIN="php\php.exe"
if not defined PHPBIN if exist "launcher-windows\server.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\server.conf") do (
    if /i "%%A"=="PHP_PATH" if exist "%%B" set PHPBIN="%%B"
  )
)
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
  for %%F in (C:\laragon\bin\php\php-8.3*\php.exe) do (
    if exist "%%F" set PHPBIN="%%F"
  )
)
if not defined PHPBIN if exist "C:\xampp\php\php.exe" set PHPBIN="C:\xampp\php\php.exe"
if not defined PHPBIN if exist "C:\php\php.exe" set PHPBIN="C:\php\php.exe"
if not defined PHPBIN (
  echo  [GAGAL] PHP tidak ditemukan.
  echo  Isi PHP_PATH di launcher-windows\server.conf, atau jalankan
  echo  perintah di bawah ini secara manual dengan PHP Anda:
  echo      php artisan optimize:clear
  echo      php artisan siapbos:cek-aset
  pause
  exit /b 1
)
echo  PHP : !PHPBIN!
echo.

REM --- 1) penanda dev server yang basi ---
if exist "public\hot" (
  echo  [1/3] Menghapus public\hot ^(sisa "npm run dev" yang sudah mati^) ...
  del /f /q "public\hot" >nul 2>nul
  if exist "public\hot" (
    echo        GAGAL menghapus. Tutup dulu jendela server, lalu ulangi.
  ) else (
    echo        Selesai.
  )
) else (
  echo  [1/3] public\hot tidak ada ^(bagus^).
)

REM --- 2) cache tampilan ---
echo  [2/3] Membersihkan cache tampilan ...
!PHPBIN! artisan optimize:clear >nul 2>nul
echo        Selesai.

REM --- 3) aset CSS ---
echo  [3/3] Memeriksa aset CSS ...
echo.
if not exist "public\build\manifest.json" (
  echo  [PERINGATAN] public\build\manifest.json TIDAK ADA.
  echo.
  echo  Berarti paket ini terkirim tanpa CSS hasil build. Pilihan:
  echo    a^) Pasang ulang paket SiapBOSP versi terbaru ^(disarankan^), atau
  echo    b^) Di komputer yang ada Node.js, jalankan di folder ini:
  echo         npm ci
  echo         npm run build
  echo       lalu salin folder public\build ke laptop ini.
  echo.
) else (
  echo  public\build\manifest.json ditemukan.
)
!PHPBIN! artisan siapbos:cek-aset
set CEK=%ERRORLEVEL%
echo.
if "%CEK%"=="0" (
  echo  Aset CSS siap. Buka aplikasi lalu tekan Ctrl+F5 di browser.
) else (
  echo  Aset CSS BELUM siap ^(lihat keterangan di atas^).
  echo  Ikuti langkah yang disarankan, lalu jalankan berkas ini lagi.
)
echo.
pause
endlocal
