# Bangun SiapBOSP-Setup-v12.exe: bungkus ZIP paket + bootstrap C# menjadi satu EXE.
# Memakai csc.exe bawaan Windows (.NET Framework) — tanpa alat tambahan.
# Contoh: powershell -ExecutionPolicy Bypass -File tools\buat-setup-exe.ps1
param(
  [string]$Zip = (Join-Path $PSScriptRoot '..\storage\app\paket\SiapBOSP-Portable-v12.zip'),
  [string]$Out = (Join-Path $PSScriptRoot '..\storage\app\paket\SiapBOSP-Setup-v12.exe'),
  [string]$Ikon = (Join-Path $PSScriptRoot '..\launcher-windows\SiapBOSP.ico')
)
$ErrorActionPreference = 'Stop'

$csc = 'C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (-not (Test-Path $csc)) { $csc = 'C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe' }
if (-not (Test-Path $csc)) { throw 'csc.exe (kompiler C# bawaan Windows) tidak ditemukan.' }
$fw = Split-Path $csc -Parent

$Zip = (Resolve-Path $Zip).Path
$Out = [System.IO.Path]::GetFullPath($Out)
$Ikon = [System.IO.Path]::GetFullPath($Ikon)
$sumber = Join-Path $PSScriptRoot 'bootstrap.cs'

Write-Output "ZIP    : $Zip"
Write-Output "Hasil  : $Out"

$stage = Join-Path $env:TEMP ('siapbosp-setup-' + [Guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $stage | Out-Null
try {
  Copy-Item $Zip (Join-Path $stage 'paket.zip') -Force
  $manifest = Join-Path $PSScriptRoot 'app.manifest'
  & $csc /nologo /target:winexe /optimize+ /codepage:65001 "/out:$Out" "/win32icon:$Ikon" "/win32manifest:$manifest" "/resource:$stage\paket.zip,paket.zip" "/reference:$fw\System.IO.Compression.dll" "/reference:$fw\System.Windows.Forms.dll" "/reference:$fw\System.Drawing.dll" "$sumber"
  if ($LASTEXITCODE -ne 0) { throw "Kompilasi gagal (kode $LASTEXITCODE)." }
} finally {
  Remove-Item $stage -Recurse -Force -ErrorAction SilentlyContinue
}

Get-Item $Out | Select-Object FullName, Length, LastWriteTime | Format-List
Write-Output 'Selesai.'
