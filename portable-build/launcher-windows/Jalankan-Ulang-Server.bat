@echo off
REM ============================================================
REM  Jalankan Ulang SiapBOSP (memperbaiki tampilan polos)
REM
REM  Klik 2x berkas ini bila aplikasi terbuka tetapi tampil
REM  "polos" (tanpa warna, tombol jelek, tulisan besar semua).
REM
REM  Yang dilakukan (aman, TIDAK menyentuh data):
REM    1. mematikan server yang masih menahan port 8000
REM       (server lama = kode lama, walaupun berkas sudah diperbarui)
REM    2. menghapus penanda dev server public\hot yang basi
REM    3. membersihkan cache tampilan/konfigurasi Laravel
REM    4. memeriksa aset CSS, lalu menyalakan server baru
REM    5. membuka aplikasi di browser
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0.."

set PORT=8000
set SERVER_URL=http://localhost:%PORT%

if exist "launcher-windows\server.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\server.conf") do (
    if /i "%%A"=="PORT" set PORT=%%B
    if /i "%%A"=="SERVER_URL" set SERVER_URL=%%B
  )
)

echo  ============================================
echo   Jalankan Ulang SiapBOSP
echo  ============================================
echo  Folder aplikasi: %CD%
echo  Alamat         : %SERVER_URL%
echo.

if not exist "artisan" (
  echo  [GAGAL] "artisan" tidak ditemukan. Berkas ini harus berada di
  echo  folder "launcher-windows" milik aplikasi SiapBOSP.
  pause
  exit /b 1
)

REM --- cari PHP ---
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
if not defined PHPBIN if exist "C:\xampp\php\php.exe" set PHPBIN="C:\xampp\php\php.exe"
if not defined PHPBIN if exist "C:\laragon\bin\php" (
  for /d %%D in ("C:\laragon\bin\php\php-8.3*") do if exist "%%D\php.exe" set PHPBIN="%%D\php.exe"
)
if not defined PHPBIN (
  echo  [GAGAL] PHP tidak ditemukan.
  pause
  exit /b 1
)
echo  PHP : !PHPBIN!
echo.

REM --- 1) matikan server yang menahan port ---
echo  [1/5] Memeriksa server yang menahan port %PORT% ...
set ADA=0
for /f "tokens=5" %%P in ('netstat -ano ^| findstr /r /c:":%PORT% .*LISTENING"') do (
  if not "%%P"=="0" (
    echo        Mematikan proses PID %%P ...
    taskkill /f /pid %%P >nul 2>nul
    set ADA=1
  )
)
if "!ADA!"=="0" echo        Tidak ada server lama.

REM --- 2) penanda dev server basi ---
echo  [2/5] Memeriksa public\hot ...
if exist "public\hot" (
  del /f /q "public\hot" >nul 2>nul
  echo        public\hot dihapus.
) else (
  echo        Tidak ada public\hot.
)

REM --- 3) cache Laravel ---
echo  [3/5] Membersihkan cache tampilan ...
!PHPBIN! artisan optimize:clear >nul 2>nul
if errorlevel 1 (
  echo        Peringatan: cache tidak sepenuhnya terhapus.
) else (
  echo        Selesai.
)

REM --- 4) aset CSS ---
echo  [4/5] Memeriksa aset CSS ...
if exist "public\build\assets" (
  dir /b "public\build\assets\*.css" >nul 2>nul
  if errorlevel 1 (
    echo        [PERINGATAN] Folder public\build\assets kosong.
  ) else (
    echo        Berkas CSS ditemukan:
    for %%F in ("public\build\assets\*.css") do echo          - %%~nxF
  )
) else (
  echo        [PERINGATAN] public\build\assets TIDAK ADA.
  echo        Halaman akan memakai mode darurat ^(tetap berwarna,
  echo        tetapi lebih lambat^). Sebaiknya pakai paket versi terbaru
  echo        atau jalankan "npm ci" + "npm run build" di komputer
  echo        yang ada Node.js lalu salin folder public\build.
)

REM --- 5) nyalakan server baru ---
echo  [5/5] Menyalakan server baru di port %PORT% ...
start "SiapBOSP-Server" /min !PHPBIN! artisan serve --host=127.0.0.1 --port=%PORT%
for /l %%i in (1,1,30) do (
  powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }" >nul
  if not errorlevel 1 goto siap
  timeout /t 1 /nobreak >nul
)
echo  [GAGAL] Server tidak merespons dalam 30 detik. Jalankan lagi, atau
echo  periksa jendela "SiapBOSP-Server".
pause
exit /b 1

:siap
echo        Server siap.
echo.

echo  ============================================
echo   PENTING: tekan Ctrl+Shift+R di browser
echo   ^(muat ulang paksa^) supaya CSS baru dipakai.
echo  ============================================
echo.

REM --- buka browser ---
set BROWSER=
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set BROWSER="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set BROWSER="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" set BROWSER="%LocalAppData%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set BROWSER="%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not defined BROWSER if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set BROWSER="%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if defined BROWSER (
  start "" %BROWSER% --app=%SERVER_URL%
) else (
  start "" "%SERVER_URL%"
)

echo  Bila tampilan MASIH polos, buka alamat ini di browser:
echo      %SERVER_URL%/_aset
echo  lalu kirimkan isinya. Halaman itu menunjukkan sumber CSS yang
echo  dipakai dan versi kode yang sedang berjalan.
echo.
echo  Jendela ini boleh ditutup. Jendela "SiapBOSP-Server"
echo  jangan ditutup selama memakai aplikasi.
echo.
pause
endlocal
