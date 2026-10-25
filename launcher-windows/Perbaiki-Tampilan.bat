@echo off
REM ============================================================
REM  Perbaiki Tampilan SiapBOSP
REM
REM  Klik 2x berkas ini bila aplikasi terbuka tetapi tampil
REM  "polos" (tanpa warna, tombol jelek, tulisan besar semua).
REM
REM  Berkas ini menjalankan Perbaiki-Tampilan.ps1:
REM   - mencari folder yang SEDANG dilayani di port 8000
REM     (server lama = kode/aset lama: penyebab tampilan polos)
REM   - memastikan folder itu punya public\build (CSS+JS)
REM   - menghapus penanda dev server public\hot yang basi
REM   - membersihkan cache, menyalakan ulang server
REM   - memeriksa CSS benar-benar terunduh, lalu membuka browser
REM
REM  Tidak menyentuh data. Aman dijalankan berulang kali.
REM ============================================================
setlocal
cd /d "%~dp0"

if not exist "%~dp0Perbaiki-Tampilan.ps1" (
  echo  [GAGAL] Berkas Perbaiki-Tampilan.ps1 tidak ditemukan di folder ini.
  pause
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Perbaiki-Tampilan.ps1"
set HASIL=%ERRORLEVEL%

echo.
if not "%HASIL%"=="0" (
  echo  Selesai dengan peringatan ^(kode %HASIL%^). Bila tampilan masih polos,
  echo  kirimkan isi jendela ini kepada pengembang.
)
endlocal
