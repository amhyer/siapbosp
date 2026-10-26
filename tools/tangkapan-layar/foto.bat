@echo off
rem Tangkap layar semua menu SiapBOSP — klik 2x berkas ini.
rem Syarat: aplikasi sudah jalan (php artisan serve) dan Node.js terpasang.
setlocal
cd /d "%~dp0"

where node >nul 2>nul
if errorlevel 1 (
  echo [!] Node.js tidak ditemukan. Pasang dari https://nodejs.org lalu ulangi.
  pause
  exit /b 1
)

if not exist "node_modules\puppeteer-core" (
  echo [*] Memasang puppeteer-core sekali saja...
  call npm install --no-audit --no-fund
  if errorlevel 1 (
    echo [!] Gagal memasang puppeteer-core. Periksa koneksi internet.
    pause
    exit /b 1
  )
)

echo.
echo [*] Menangkap layar. Pastikan aplikasi sudah jalan di http://127.0.0.1:8000
echo     (kalau alamatnya lain: foto.bat --base http://alamat:port)
echo.
node foto.cjs %*

echo.
echo Selesai. Buka galerinya: docs\tangkapan-layar\index.html
pause
