# Buat/hapus pintasan SiapBOSP (Desktop + Start Menu) - dipakai pemasang.
# Contoh: powershell -NoProfile -ExecutionPolicy Bypass -File buat-pintasan.ps1 -Dest "C:\...\SiapBOSP" -Ikon "C:\...\SiapBOSP.ico"
param(
  [Parameter(Mandatory = $true)][string]$Dest,
  [string]$Ikon = "",
  [switch]$Hapus
)

$desk = [Environment]::GetFolderPath('Desktop')
$mulai = Join-Path ([Environment]::GetFolderPath('ApplicationData')) 'Microsoft\Windows\Start Menu\Programs\SiapBOSP'

$namaDesk = @(
  'SiapBOSP',
  'Tarik Data ARKAS - SiapBOSP',
  'Sinkron ke Pusat - SiapBOSP'
)
$targetDesk = @(
  (Join-Path $Dest 'launcher-windows\SiapBOSP.bat'),
  (Join-Path $Dest 'launcher-windows\Tarik-ARKAS.bat'),
  (Join-Path $Dest 'launcher-windows\Sinkron-Pusat.bat')
)

$namaMulai = @(
  'SiapBOSP',
  'Tarik Data ARKAS',
  'Sinkron ke Pusat',
  'Hapus SiapBOSP'
)
$targetMulai = @(
  (Join-Path $Dest 'launcher-windows\SiapBOSP.bat'),
  (Join-Path $Dest 'launcher-windows\Tarik-ARKAS.bat'),
  (Join-Path $Dest 'launcher-windows\Sinkron-Pusat.bat'),
  (Join-Path $Dest 'launcher-windows\Hapus-SiapBOSP.bat')
)

if ($Hapus) {
  foreach ($n in $namaDesk) {
    Remove-Item (Join-Path $desk ($n + '.lnk')) -Force -ErrorAction SilentlyContinue
  }
  Remove-Item $mulai -Recurse -Force -ErrorAction SilentlyContinue
  Write-Output "Pintasan Desktop + Start Menu dihapus."
  exit 0
}

$shell = New-Object -ComObject WScript.Shell
for ($i = 0; $i -lt $namaDesk.Count; $i++) {
  $lnk = Join-Path $desk ($namaDesk[$i] + '.lnk')
  $k = $shell.CreateShortcut($lnk)
  $k.TargetPath = $targetDesk[$i]
  $k.WorkingDirectory = $Dest
  if ($Ikon -and (Test-Path $Ikon)) {
    $k.IconLocation = $Ikon
  }
  $k.Save()
}

if (-not (Test-Path $mulai)) {
  New-Item -ItemType Directory -Path $mulai -Force | Out-Null
}
for ($i = 0; $i -lt $namaMulai.Count; $i++) {
  $lnk = Join-Path $mulai ($namaMulai[$i] + '.lnk')
  $k = $shell.CreateShortcut($lnk)
  $k.TargetPath = $targetMulai[$i]
  $k.WorkingDirectory = $Dest
  if ($Ikon -and (Test-Path $Ikon)) {
    $k.IconLocation = $Ikon
  }
  $k.Save()
}

Write-Output "Pintasan dibuat: 3 Desktop + 4 Start Menu (folder SiapBOSP)."
