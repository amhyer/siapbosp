@echo off
REM ============================================================
REM  PEMASANG SiapBOSP untuk laptop bendahara (Tahap D).
REM  Klik 2x berkas ini SEKALI untuk memasang:
REM  - menyalin aplikasi ke %LocalAppData%\Programs\SiapBOSP
REM  - menyiapkan database + master rekening + tahap
REM  - membuat akun bendahara + token Tarik ARKAS otomatis
REM  - membuat 3 pintasan di Desktop
REM  Tidak perlu install PHP/Node — semuanya sudah dibundel.
REM ============================================================
setlocal EnableDelayedExpansion
for %%i in ("%~dp0..") do set "SRC=%%~fi"
cd /d "!SRC!"

REM --- folder sementara milik aplikasi (tak bergantung TMP/TEMP laptop) ---
if not exist "%~dp0..\temp" mkdir "%~dp0..\temp"
set "TMP=%~dp0..\temp"
set "TEMP=%~dp0..\temp"

REM --- jaminan folder wajib Laravel: tahan alat ekstrak yang membuang folder kosong ---
if not exist "bootstrap\cache" mkdir "bootstrap\cache"
if not exist "storage\framework\views" mkdir "storage\framework\views"
if not exist "storage\framework\cache" mkdir "storage\framework\cache"
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions"
if not exist "storage\logs" mkdir "storage\logs"

set "DEST=%LocalAppData%\Programs\SiapBOSP"
if defined SIPBOS_DEST set "DEST=!SIPBOS_DEST!"

echo ================================================
echo   PEMASANG APLIKASI SiapBOSP
echo ================================================
echo.

REM --- identitas sekolah dari paket unduhan ---
set "NPSN="
set "SEKOLAH="
if exist "launcher-windows\akun-sekolah.conf" (
  for /f "usebackq tokens=1* delims==" %%A in ("launcher-windows\akun-sekolah.conf") do (
    set "_k=%%A"
    set "_v=%%B"
    if /i "!_k!"=="NPSN" set "NPSN=!_v!"
    if /i "!_k!"=="SEKOLAH" set "SEKOLAH=!_v!"
  )
)
if defined SIPBOS_NPSN set "NPSN=!SIPBOS_NPSN!"
if defined SIPBOS_SEKOLAH set "SEKOLAH=!SIPBOS_SEKOLAH!"
if "!NPSN!"=="" (
  echo  Paket ini tidak memuat identitas sekolah. Isi manual dulu:
  set /p "NPSN=NPSN sekolah  : "
)
if "!SEKOLAH!"=="" set /p "SEKOLAH=Nama sekolah : "
echo  Sekolah  : !SEKOLAH! ^(!NPSN!^)
echo  Tujuan   : !DEST!
echo.

if not exist "php\php.exe" (
  if exist "artisan" if exist "composer.json" (
    echo  [SALAH FOLDER] Ini folder KODE SUMBER, bukan paket pasang.
    echo  Pakai paket yang benar: ekstrak SiapBOSP-Portable-v12.zip lalu klik
    echo  PASANG-SIAPBOSP.bat di folder hasil ekstrak ^(folder SiapBOSP^),
    echo  atau jalankan SiapBOSP-Setup-v12.exe ^(satu berkas, langsung pasang^).
  ) else (
    echo  [GAGAL] Folder "php" tidak ada di paket ini.
    echo  Unduh paket terbaru dari website SiapBOSP, atau pakai
    echo  SiapBOSP-Setup-v12.exe ^(satu berkas, langsung pasang^).
  )
  echo.
  pause
  exit /b 1
)

REM --- data login bendahara ---
set "NAMA=!SIPBOS_NAMA!"
if "!NAMA!"=="" set /p "NAMA=Nama bendahara     [Bendahara Sekolah]: "
if "!NAMA!"=="" set "NAMA=Bendahara Sekolah"
set "EMAIL=!SIPBOS_EMAIL!"
if "!EMAIL!"=="" set /p "EMAIL=Email untuk login   [!NPSN!@siapbosp.lokal]: "
if "!EMAIL!"=="" set "EMAIL=!NPSN!@siapbosp.lokal"
set "SANDI=!SIPBOS_SANDI!"
if "!SANDI!"=="" (
  REM Kata sandi diselipkan (tidak terlihat saat mengetik) lewat Read-Host tersembunyi; hasil ditulis ke berkas sementara lalu dihapus.
  powershell -NoProfile -Command "$k=Read-Host 'Kata sandi min 6' -AsSecureString; $b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($k); $s=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b); [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b); [IO.File]::WriteAllText((Join-Path $env:TEMP 'siapbosp-sandi.txt'), $s)" >nul 2>nul
  if exist "%TEMP%\siapbosp-sandi.txt" (
    set /p "SANDI=" <"%TEMP%\siapbosp-sandi.txt"
    del /q "%TEMP%\siapbosp-sandi.txt" >nul 2>nul
  )
  REM Cadangan bila mekanisme tersembunyi gagal: prompt biasa.
  if "!SANDI!"=="" set /p "SANDI=Kata sandi tidak tersembunyi, ketik di sini min 6 : "
)
if "!SANDI!"=="" (
  echo  [GAGAL] Kata sandi wajib diisi.
  pause
  exit /b 1
)
echo.

REM --- salin aplikasi ---
if /i "!SRC!"=="!DEST!" (
  echo  Aplikasi sudah berada di folder tujuan - lewati penyalinan.
) else (
  echo  Menyalin aplikasi ke tujuan... ^(1-3 menit, mohon tunggu^)
  robocopy "!SRC!" "!DEST!" /E /NFL /NDL /NJH /NJS /R:1 /W:1 >nul
  if !errorlevel! GEQ 8 (
    echo  [GAGAL] Penyalinan berkas gagal. Jalankan pemasang sekali lagi.
    pause
    exit /b 1
  )
)

REM --- bebaskan blokir unduhan (mark-of-the-web) ---
echo  Menyiapkan izin berkas...
powershell -NoProfile -Command "Get-ChildItem -LiteralPath '!DEST!' -Recurse -File -ErrorAction SilentlyContinue | Unblock-File -ErrorAction SilentlyContinue" >nul 2>nul

REM --- pasang: .env, kunci, database, master, akun, token ---
echo.
cd /d "!DEST!"
"!DEST!\php\php.exe" "!DEST!\artisan" siapbos:pasang-lokal --nama="!NAMA!" --email="!EMAIL!" --sandi="!SANDI!" --npsn="!NPSN!" --sekolah="!SEKOLAH!"
if errorlevel 1 (
  echo.
  echo  [GAGAL] Pemasangan belum berhasil - baca pesan di atas.
  pause
  exit /b 1
)

REM --- pintasan desktop ---
if defined SIPBOS_TANPA_PINTASAN (
  echo  Lewati pembuatan pintasan Desktop ^(mode otomatis^).
) else (
  set "IKON=!DEST!\launcher-windows\SiapBOSP.ico"
  if not exist "!IKON!" set "IKON=!DEST!\php\php.exe"
  powershell -NoProfile -ExecutionPolicy Bypass -File "!DEST!\launcher-windows\buat-pintasan.ps1" -Dest "!DEST!" -Ikon "!IKON!"
)

REM --- daftar di "Aplikasi & fitur" (agar bisa dihapus seperti aplikasi biasa) ---
if not defined SIPBOS_DEST (
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v DisplayName /d "SiapBOSP - Aplikasi Bendahara Sekolah" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v DisplayVersion /d "12.0.0" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v Publisher /d "Tim SiapBOSP" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v DisplayIcon /d "!DEST!\launcher-windows\SiapBOSP.ico" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v InstallLocation /d "!DEST!" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v UninstallString /d "\"!DEST!\launcher-windows\Hapus-SiapBOSP.bat\"" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v QuietUninstallString /d "\"!DEST!\launcher-windows\Hapus-SiapBOSP.bat\" --senyap" /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v NoModify /t REG_DWORD /d 1 /f >nul 2>nul
  reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP" /v NoRepair /t REG_DWORD /d 1 /f >nul 2>nul
  powershell -NoProfile -Command "try { $kb=[long]((Get-ChildItem -LiteralPath '!DEST!' -Recurse -File -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum / 1KB); if ($kb -lt 1) { $kb = 1 }; Set-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP' -Name EstimatedSize -Type DWord -Value $kb -ErrorAction SilentlyContinue } catch { }" >nul 2>nul
)

echo.
echo  SELESAI! Pintasan siap dipakai:
echo    - Desktop    : 3 pintasan (SiapBOSP, Tarik Data ARKAS, Sinkron)
echo    - Start Menu : menu "SiapBOSP" (termasuk Hapus SiapBOSP)
echo.
echo  CATAT kata sandi login Anda baik-baik.
if /i not "!SRC!"=="!DEST!" echo  Folder hasil ekstrak ini boleh dihapus setelah pemasangan.
echo.
set "BUKA=!SIPBOS_BUKA!"
if "!BUKA!"=="" set /p "BUKA=Buka SiapBOSP sekarang? [Y/N]: "
if /i "!BUKA!"=="Y" start "" "!DEST!\launcher-windows\SiapBOSP.bat"
echo.
pause
endlocal
