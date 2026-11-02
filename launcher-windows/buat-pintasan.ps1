# Buat/hapus pintasan SiapBOSP (Desktop + Start Menu) - dipakai pemasang.
# Contoh: powershell -NoProfile -ExecutionPolicy Bypass -File buat-pintasan.ps1 -Dest "C:\...\SiapBOSP" -Ikon "C:\...\SiapBOSP.ico"
param(
  [Parameter(Mandatory = $true)][string]$Dest,
  [string]$Ikon = "",
  [switch]$Hapus
)

$desk = [Environment]::GetFolderPath('Desktop')
$mulai = Join-Path ([Environment]::GetFolderPath('ApplicationData')) 'Microsoft\Windows\Start Menu\Programs\SiapBOSP'

# Desktop dan Start Menu cukup satu aplikasi utama (SiapBOSP) + Hapus SiapBOSP.
# Seluruh proses tarik data ARKAS dan sinkronisasi ke pusat dilakukan langsung
# di dalam aplikasi SiapBOSP (melalui menu/sidebar).
$namaDesk = @(
  'SiapBOSP'
)
$targetDesk = @(
  (Join-Path $Dest 'launcher-windows\Buka-SiapBOSP.vbs')
)
$pintasanDeskLama = @(
  'Tarik Data ARKAS - SiapBOSP',
  'Sinkron ke Pusat - SiapBOSP',
  'Tarik Data ARKAS',
  'Sinkron ke Pusat'
)

$namaMulai = @(
  'SiapBOSP',
  'Hapus SiapBOSP'
)
$targetMulai = @(
  (Join-Path $Dest 'launcher-windows\Buka-SiapBOSP.vbs'),
  (Join-Path $Dest 'launcher-windows\Hapus-SiapBOSP.bat')
)
$pintasanMulaiLama = @(
  'Tarik Data ARKAS',
  'Sinkron ke Pusat',
  'Tarik Data ARKAS - SiapBOSP',
  'Sinkron ke Pusat - SiapBOSP'
)

if ($Hapus) {
  foreach ($n in ($namaDesk + $pintasanDeskLama)) {
    Remove-Item (Join-Path $desk ($n + '.lnk')) -Force -ErrorAction SilentlyContinue
  }
  Remove-Item $mulai -Recurse -Force -ErrorAction SilentlyContinue
  Write-Output "Pintasan Desktop + Start Menu dihapus."
  exit 0
}

$shell = New-Object -ComObject WScript.Shell
$iconLoc = ""
if ($Ikon) {
  $rawIkon = $Ikon -replace ',\d+$', ''
  if (Test-Path $rawIkon) {
    $iconLoc = if ($Ikon -match ',\d+$') { $Ikon } else { "$rawIkon,0" }
  }
}

# Hapus pintasan Desktop versi lama (dulu 3 ikon) agar tidak meninggalkan duplikat.
foreach ($n in $pintasanDeskLama) {
  Remove-Item (Join-Path $desk ($n + '.lnk')) -Force -ErrorAction SilentlyContinue
}

for ($i = 0; $i -lt $namaDesk.Count; $i++) {
  $lnk = Join-Path $desk ($namaDesk[$i] + '.lnk')
  $k = $shell.CreateShortcut($lnk)
  $k.TargetPath = $targetDesk[$i]
  $k.WorkingDirectory = $Dest
  if ($iconLoc) {
    $k.IconLocation = $iconLoc
  }
  $k.Save()
}

if (-not (Test-Path $mulai)) {
  New-Item -ItemType Directory -Path $mulai -Force | Out-Null
}
foreach ($n in $pintasanMulaiLama) {
  Remove-Item (Join-Path $mulai ($n + '.lnk')) -Force -ErrorAction SilentlyContinue
}
for ($i = 0; $i -lt $namaMulai.Count; $i++) {
  $lnk = Join-Path $mulai ($namaMulai[$i] + '.lnk')
  $k = $shell.CreateShortcut($lnk)
  $k.TargetPath = $targetMulai[$i]
  $k.WorkingDirectory = $Dest
  if ($iconLoc) {
    $k.IconLocation = $iconLoc
  }
  $k.Save()
}

Write-Output "Pintasan dibuat: 1 Desktop (SiapBOSP) + Start Menu (folder SiapBOSP)."
