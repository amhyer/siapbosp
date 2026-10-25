@echo off
REM ============================================================
REM  SiapBOSP Launcher — klik 2x untuk membuka aplikasi.
REM  Paket ini membawa PHP sendiri (folder "php") sehingga
REM  TIDAK perlu install apa pun di laptop ini.
REM  Pasang dulu sekali: PASANG-SIAPBOSP.bat
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0.."

REM --- folder sementara milik aplikasi (tak bergantung TMP/TEMP laptop) ---
if not exist "%~dp0..\temp" mkdir "%~dp0..\temp"
set "TMP=%~dp0..\temp"
set "TEMP=%~dp0..\temp"

set SERVER_URL=http://127.0.0.1:8000
set PORT=8000
set PHP_PATH=
if exist "launcher-windows\server.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\server.conf") do (
    set _k=%%A
    set _v=%%B
    if /i "!_k!"=="SERVER_URL" set SERVER_URL=!_v!
    if /i "!_k!"=="PORT" set PORT=!_v!
    if /i "!_k!"=="PHP_PATH" set PHP_PATH=!_v!
  )
)

echo  SiapBOSP Launcher
echo  Server : %SERVER_URL%
echo.

echo %SERVER_URL% | findstr /i "127.0.0.1 localhost" >nul
if errorlevel 1 goto buka_browser

REM --- mode lokal: pastikan server jalan ---
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }"
if not errorlevel 1 (
  echo  Server lokal sudah jalan.
  goto buka_browser
)

echo  Menyalakan server lokal di port %PORT% ...

REM --- cari PHP: 1) bundel "php" 2) PHP_PATH 3) PATH 4) lokasi umum ---
set PHPBIN=
if exist "php\php.exe" set PHPBIN="php\php.exe"
if not defined PHPBIN if defined PHP_PATH if exist "%PHP_PATH%" set PHPBIN="%PHP_PATH%"
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
  echo.
  echo  [GAGAL] PHP tidak ditemukan dan folder "php" bundel tidak ada.
  echo  Unduh ulang paket aplikasi terbaru dari website SiapBOSP,
  echo  atau isi PHP_PATH di launcher-windows\server.conf.
  pause
  exit /b 1
)
echo  PHP: !PHPBIN!

REM --- konfigurasi awal + cek kelengkapan pemasangan ---
if not exist ".env" if exist ".env.portable" (
  copy /y ".env.portable" ".env" >nul
  echo  Konfigurasi .env dibuat.
)
!PHPBIN! artisan siapbos:pasang-lokal --status >nul 2>nul
if errorlevel 1 (
  echo.
  echo  Aplikasi BELUM dipasang lengkap.
  echo  Klik 2x "PASANG-SIAPBOSP.bat" di folder ini dulu
  echo  ^(sekali saja^), lalu klik lagi SiapBOSP.bat.
  pause
  exit /b 1
)

start "SiapBOSP-Server" /min !PHPBIN! artisan serve --host=127.0.0.1 --port=%PORT%
echo  Menunggu server siap ...
for /l %%i in (1,1,30) do (
  powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }" >nul
  if not errorlevel 1 goto server_ok
  timeout /t 1 /nobreak >nul
)
echo  [GAGAL] Server tidak merespons dalam 30 detik.
pause
exit /b 1
:server_ok
echo  Server OK.

REM --- cek aset tampilan (CSS/JS) supaya tidak muncul halaman polos ---
!PHPBIN! artisan siapbos:cek-aset >nul 2>nul
if errorlevel 1 (
  echo.
  echo  [PERINGATAN] Aset tampilan belum siap - halaman bisa tampil polos.
  echo  Pada laptop ini (tanpa Node.js) sebaiknya pasang ulang paket
  echo  aplikasi versi terbaru. Pada komputer pengembang jalankan:
  echo      npm ci ^&^& npm run build
  echo.
)

goto buka_browser

:buka_browser
REM --- buka jendela aplikasi ---
set BROWSER=
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set BROWSER="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set BROWSER="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" set BROWSER="%LocalAppData%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set BROWSER="%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not defined BROWSER if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set BROWSER="%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if defined BROWSER (
  echo  Membuka jendela aplikasi ...
  start "" %BROWSER% --app=%SERVER_URL% --user-data-dir="%LocalAppData%\SiapBOSP-App"
) else (
  echo  Chrome/Edge tidak ketemu, membuka browser default ...
  start "" "%SERVER_URL%"
)

echo.
echo  Jangan tutup jendela "SiapBOSP-Server" selama memakai aplikasi.
echo  Login memakai email + kata sandi yang dibuat saat pemasangan.
echo.
echo  === Pemantau server: jendela ini mengecek tiap 15 detik. ===
:monitor
timeout /t 15 /nobreak >nul
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }" >nul
if not errorlevel 1 goto monitor
echo.
echo  [PERINGATAN] Server di port %PORT% tidak merespons!
echo  Kemungkinan jendela "SiapBOSP-Server" tertutup. Jalankan
echo  lagi SiapBOSP.bat, lalu refresh jendela aplikasi (Ctrl+R^).
pause
goto monitor
endlocal
