@echo off
rem ============================================================
rem SiapBOSP Portable Package Builder
rem Membuat paket ZIP untuk distribusi ke banyak leptop
rem ============================================================
setlocal EnableDelayedExpansion

rem ============= SETUP =============
set "PROJECT_DIR=%~dp0"
set "BUILD_DIR=%PROJECT_DIR%portable-build"
set "OUTPUT_ZIP=%PROJECT_DIR%SiapBOSP-Portable-v12.zip"

echo.
echo ===============================================
echo  SiapBOSP Portable Package Builder
echo ===============================================
echo.

rem Cek folder project
if not exist "%PROJECT_DIR%composer.json" (
    echo [GAGAL] Bukan folder project SiapBOSP (tidak ada composer.json)
    pause
    exit /b 1
)

echo [INFO] Folder project: %PROJECT_DIR%

rem ============= BUAT FOLDER BUILD =============
if exist "%BUILD_DIR%" rmdir /s /q "%BUILD_DIR%"
mkdir "%BUILD_DIR%"

echo [MEMULAHI] Menyalin file-file wajib...

rem Salin file-file wajib
if exist "artisan" copy "artisan" "%BUILD_DIR%\artisan" /y && echo [OK] artisan
if exist "composer.json" copy "composer.json" "%BUILD_DIR%\composer.json" /y && echo [OK] composer.json
if exist ".env.example" copy ".env.example" "%BUILD_DIR%\."env.example" /y && echo [OK] .env.example
if exist "package.json" copy "package.json" "%BUILD_DIR%\package.json" /y && echo [OK] package.json
if exist "vite.config.js" copy "vite.config.js" "%BUILD_DIR%\vite.config.js" /y && echo [OK] vite.config.js

rem Salin folder
if exist "resources" xcopy "resources" "%BUILD_DIR%\resources" /e /y /i >nul && echo [OK] resources/
if exist "routes" xcopy "routes" "%BUILD_DIR%\routes" /e /y /i >nul && echo [OK] routes/
if exist "app" xcopy "app" "%BUILD_DIR%\app" /e /y /i >nul && echo [OK] app/
if exist "config" xcopy "config" "%BUILD_DIR%\config" /e /y /i >nul && echo [OK] config/

rem PHP bundle (jika ada)
if exist "php\php.exe" (
    xcopy "php" "%BUILD_DIR%\php" /e /y /i >nul && echo [OK] php/
)

rem ============= BUAT AKUN SEKOLAH.CONF =============
echo.
echo [MEMULAHI] Membuat identitas sekolah...

set "NPSN="
set "SEKOLAH="

if defined 1_NPSN set "NPSN=%1"
if defined 1_SEKOLAH set "SEKOLAH=%2"

if defined NPSN (
    set "CLEAN_NPSN=!NPSN:=[^A-Za-z0-9]=!"
    set "CLEAN_SEKOLAH=!SEKOLAH:=[^A-Za-z0-9]=!"
    
    set "CONF_PATH=%BUILD_DIR%\launcher-windows\akun-sekolah.conf"
    mkdir "%BUILD_DIR%\launcher-windows" 2>nul
    
    echo # Identitas sekolah SiapBOSP > "%CONF_PATH%"
    echo NPSN=!CLEAN_NPSN! >> "%CONF_PATH%"
    echo SEKOLAH=!CLEAN_SEKOLAH! >> "%CONF_PATH%"
    echo [OK] akun-sekolah.conf dibuat
) else (
    echo [CATAT] Tidak ada NPSN/SEKOLAH - isi manual di tiap laptop di launcher-windows\akun-sekolah.conf
)

rem ============= BUAT README PENGAJARAN =============
echo.
echo [MEMULAHI] Membuat file instruksi...

set "README=%BUILD_DIR%\PENGAJARAN.txt"
echo === INSTALASI SiapBOSP di Laptop Target === > "%README%"
echo. >> "%README%"
echo 1. Ekstrak berkas ZIP ini ke folder tujuan (mis. D:\SiapBOSP atau C:\Users\%USER%\AppData\Local\Programs\SiapBOSP) >> "%README%"
echo. >> "%README%"
echo 2. Pastikan di laptop ada folder php\php.exe (jika tidak ada, pakai PHP dari XAMPP/Laragon) >> "%README%"
echo. >> "%README%"
echo 3. Jalankan: double-klik berkas launcher-windows\Pasang-SiapBOSP.bat >> "%README%"
echo. >> "%README%"
echo 4. Ikuti petunjuk: nama bendahara, email, kata sandi >> "%README%"
echo. >> "%README%"
echo 5. Setelah selesai, buka SiapBOSP dari Desktop/Start Menu >> "%README%"
echo. >> "%README%"
echo. >> "%README%"
echo === AKUN DEMO === >> "%README%"
echo - dinas@siapbosp.id / password (role: dinas) >> "%README%"
echo - superadmin@siapbosp.id / password (role: superadmin) >> "%README%"
echo - operator@sekolah.demo / password (role: operator) >> "%README%"
echo - kepsek@sekolah.demo / password (role: kepsek) >> "%README%"
echo. >> "%README%"
echo === TROUBLESHOOTING === >> "%README%"
echo - Jika error PHP: pastikan ada folder php\php.exe >> "%README%"
echo - Jika error database: pastikan folder tujuan bisa ditulis >> "%README%"
echo. >> "%README%"
echo Selamat mencoba! >> "%README%"

rem ============= BUAT ZIP =============
echo.
echo [MEMULAHI] Membuat paket ZIP...

if exist "%OUTPUT_ZIP%" del /f /q "%OUTPUT_ZIP%"

rem Pakai maket kompresi
powershell -Command "Compress-Archive -Path '%BUILD_DIR%\*' -DestinationPath '%OUTPUT_ZIP%' -Force" >nul

if errorlevel 0 (
    echo [OK] SiapBOSP-Portable-v12.zip berhasil dibuat
    echo [INFO] Lokasi: %OUTPUT_ZIP%
    echo.
    echo Langkah selanjutnya:
    echo 1. Kirim file ZIP ini ke laptop-target
    echo 2. Di laptop-target: ekstrak ZIP ke folder tujuan
    echo 3. Klik kanan -> Extract All, atau gunakan built-in Windows
    echo 4. Double-klik launcher-windows\Pasang-SiapBOSP.bat
    echo 5. Ikuti wizard pemasangan
) else (
    echo [GAGAL] Gagal membuat ZIP
)

rem ============= BERSIHKAN =============
echo.
echo [SELESAI] Tekan Enter untuk keluar...
pause >nul