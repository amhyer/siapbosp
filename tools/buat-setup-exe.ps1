# Bangun SiapBOSP-Setup-v12.exe: kompilasi WPF (.NET Framework 4.8) + sematkan ZIP paket.
# Mendukung SDK dotnet mau pun fallback MSBuild / PresentationBuildTasks bawaan Windows.
#
# Contoh pemakaian:
#   powershell -ExecutionPolicy Bypass -File tools\buat-setup-exe.ps1
#   powershell -ExecutionPolicy Bypass -File tools\buat-setup-exe.ps1 -SkipRedist -Configuration Release
param(
  [string]$Zip = (Join-Path $PSScriptRoot '..\storage\app\paket\SiapBOSP-Portable-v12.zip'),
  [string]$Out = (Join-Path $PSScriptRoot '..\storage\app\paket\SiapBOSP-Setup-v12.exe'),
  [string]$Ikon = (Join-Path $PSScriptRoot '..\launcher-windows\SiapBOSP.ico'),
  [switch]$SkipRedist,
  [string]$Configuration = "Release"
)
$ErrorActionPreference = 'Stop'

$toolsDir = $PSScriptRoot
$csproj = Join-Path $toolsDir 'SiapBOSP.csproj'

if (-not (Test-Path $Zip)) {
  # Buat placeholder zip jika belum ada (misal di repo clean)
  Write-Warning "Berkas ZIP paket tidak ditemukan di: $Zip. Membuat placeholder zip..."
  Add-Type -AssemblyName System.IO.Compression
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $parent = Split-Path $Zip -Parent
  if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
  $fs = [System.IO.File]::Open($Zip, [System.IO.FileMode]::Create)
  $za = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
  $ze = $za.CreateEntry("SiapBOSP/README.txt")
  $sw = New-Object System.IO.StreamWriter($ze.Open())
  $sw.WriteLine("Placeholder paket SiapBOSP.")
  $sw.Dispose()
  $za.Dispose()
  $fs.Dispose()
}

$Zip = (Resolve-Path $Zip).Path
$Out = [System.IO.Path]::GetFullPath($Out)
$Ikon = [System.IO.Path]::GetFullPath($Ikon)

$outDir = Split-Path $Out -Parent
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }

Write-Output "=== Pembangun Setup SiapBOSP WPF (v12) ==="
Write-Output "ZIP Sumber   : $Zip"
Write-Output "Target Hasil : $Out"
Write-Output "Ikon         : $Ikon"
Write-Output "Konfigurasi  : $Configuration"

# Cek ketersediaan redistributable .NET 4.8
$redistFile = Join-Path $toolsDir 'redist\ndp48-x86-x64-allos-enu.exe'
$bundledRedist = $false
if (-not $SkipRedist -and (Test-Path $redistFile)) {
  Write-Output "Redist .NET 4.8 ditemukan: $redistFile (akan dibundel)."
  $bundledRedist = $true
} else {
  if (-not $SkipRedist) {
    Write-Warning "Redist .NET 4.8 (ndp48-x86-x64-allos-enu.exe) tidak ditemukan di tools/redist. Lanjut build tanpa bundel offline."
  }
}

# Salin zip dan script cek ke tools untuk di-embed
$stageZip = Join-Path $toolsDir 'paket.zip'
Copy-Item -LiteralPath $Zip -Destination $stageZip -Force

try {
  $builtExe = ""
  $extraDefines = if ($bundledRedist) { "NDP48_BUNDLED" } else { "" }

  # 1. Coba gunakan dotnet SDK jika ada
  $hasDotnet = $false
  try {
    $ver = & dotnet --version 2>&1
    if ($LASTEXITCODE -eq 0) { $hasDotnet = $true }
  } catch {
    $hasDotnet = $false
  }

  if ($hasDotnet) {
    Write-Output "Menggunakan 'dotnet build'..."
    $argList = @('build', $csproj, '-c', $Configuration, '/v:m')
    if ($bundledRedist) {
      $argList += ('/p:DefineConstants=TRACE;NDP48_BUNDLED')
    }
    & dotnet $argList
    if ($LASTEXITCODE -ne 0) { throw "Build via dotnet gagal (kode $LASTEXITCODE)." }
    $builtExe = Join-Path $toolsDir "bin\$Configuration\SiapBOSP-Setup.exe"
  } else {
    # 2. Fallback: gunakan MSBuild bawaan Windows .NET Framework
    $msBuild = "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\MSBuild.exe"
    if (-not (Test-Path $msBuild)) {
      $msBuild = "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\MSBuild.exe"
    }
    if (-not (Test-Path $msBuild)) {
      throw "Tidak ditemukan dotnet SDK atau MSBuild.exe di sistem ini."
    }

    Write-Output "dotnet SDK tidak ditemukan. Menggunakan MSBuild bawaan: $msBuild"
    $propArg = "/p:Configuration=$Configuration;Platform=AnyCPU"
    if ($bundledRedist) {
      $propArg += ";DefineConstants=TRACE;NDP48_BUNDLED"
    }
    & $msBuild $csproj $propArg /v:m /nologo
    if ($LASTEXITCODE -ne 0) { throw "Build via MSBuild gagal (kode $LASTEXITCODE)." }
    $builtExe = Join-Path $toolsDir "bin\$Configuration\SiapBOSP-Setup.exe"
  }

  if (-not (Test-Path $builtExe)) {
    throw "Hasil build tidak ditemukan di: $builtExe"
  }

  Copy-Item -LiteralPath $builtExe -Destination $Out -Force
  Write-Output "Kompilasi dan penempatan berhasil."
}
finally {
  if (Test-Path $stageZip) {
    Remove-Item -LiteralPath $stageZip -Force -ErrorAction SilentlyContinue
  }
}

$hasilItem = Get-Item -LiteralPath $Out
$sha256 = (Get-FileHash -LiteralPath $Out -Algorithm SHA256).Hash
$ukuranKb = [math]::Round($hasilItem.Length / 1KB, 2)
$ukuranMb = [math]::Round($hasilItem.Length / 1MB, 2)

Write-Output ""
Write-Output "================ BUKTI HASIL AKHIR ================"
Write-Output "Berkas     : $($hasilItem.FullName)"
Write-Output "Ukuran     : $($hasilItem.Length) bytes ($ukuranKb KB / $ukuranMb MB)"
Write-Output "SHA256     : $sha256"
Write-Output "Waktu Buat : $($hasilItem.LastWriteTime)"
Write-Output "==================================================="
