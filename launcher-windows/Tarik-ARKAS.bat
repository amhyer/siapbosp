@echo off
REM ============================================================
REM  Tarik-ARKAS satu-klik (Tahap B/D)
REM  Mengirim data ARKAS laptop ini ke aplikasi SiapBOSP lokal.
REM  Agen + runtime Node sudah dibundel di folder "agen".
REM  Syarat: aplikasi SiapBOSP sedang jalan (SiapBOSP.bat).
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0.."

REM --- folder sementara milik aplikasi (tak bergantung TMP/TEMP laptop) ---
if not exist "%~dp0..\temp" mkdir "%~dp0..\temp"
set "TMP=%~dp0..\temp"
set "TEMP=%~dp0..\temp"

set SERVER=http://127.0.0.1:8000
set TOKEN=
set TAHUN=
if exist "launcher-windows\agen.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\agen.conf") do (
    set _k=%%A
    set _v=%%B
    if /i "!_k!"=="SERVER" set SERVER=!_v!
    if /i "!_k!"=="TOKEN" set TOKEN=!_v!
    if /i "!_k!"=="TAHUN" set TAHUN=!_v!
  )
)
set "PORT=8000"
if exist "launcher-windows\server.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\server.conf") do (
    if /i "%%A"=="PORT" set "PORT=%%B"
  )
)

echo  Tarik-ARKAS satu-klik
echo  Server : %SERVER%
echo.

if "%TOKEN%"=="" (
  echo  [GAGAL] Token belum terpasang.
  echo  Jalankan PASANG-SIAPBOSP.bat sekali ^(di folder aplikasi^),
  echo  lalu klik lagi file ini.
  pause
  exit /b 1
)

REM --- pastikan aplikasi lokal menyala (bila SERVER menunjuk laptop ini) ---
echo %SERVER% | findstr /i "127.0.0.1 localhost" >nul
if not errorlevel 1 (
  powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient('127.0.0.1', %PORT%)).Close(); exit 0 } catch { exit 1 }"
  if errorlevel 1 (
    echo  [GAGAL] Aplikasi SiapBOSP belum menyala di laptop ini.
    echo  Klik 2x SiapBOSP.bat dulu, biarkan jendelanya terbuka,
    echo  lalu klik lagi Tarik-ARKAS.bat.
    pause
    exit /b 1
  )
)

REM --- runtime agen: 1) bundel "agen" 2) agen manual + Node di PATH ---
set ARUN=
set AARG=
if exist "agen\TarikArkas\tarik.cjs" (
  if exist "agen\TarikArkas\node.exe" (
    set ARUN="agen\TarikArkas\node.exe"
    set AARG="agen\TarikArkas\tarik.cjs"
  )
)
if not defined ARUN if exist "agen\tarik-arkas.js" (
  where node >nul 2>nul
  if not errorlevel 1 (
    set ARUN=node
    set AARG="agen\tarik-arkas.js"
  )
)
if not defined ARUN (
  echo  [GAGAL] Agen TarikArkas tidak ditemukan di folder "agen".
  echo  Unduh paket aplikasi terbaru dari website SiapBOSP.
  pause
  exit /b 1
)

set TARG=
if not "%TAHUN%"=="" set TARG=--tahun %TAHUN%
echo  Mengirim data ARKAS ke %SERVER% ...
REM "setup" = validasi token + simpan pengaturan + LANGSUNG kirim (aman diulang).
%ARUN% %AARG% setup --server %SERVER% --token %TOKEN% %TARG%
if errorlevel 1 (
  echo.
  echo  [GAGAL] Pengiriman gagal - baca pesan di atas.
  echo  Umum: aplikasi ARKAS sedang terbuka ^(tutup dulu^), token salah,
  echo  atau NPSN token beda dengan ARKAS laptop ini.
  pause
  exit /b 1
)
echo.
echo  Selesai. Cek hasilnya di aplikasi SiapBOSP - Pembukuan - BKU.
pause
endlocal
