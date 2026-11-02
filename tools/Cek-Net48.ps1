<#
.SYNOPSIS
    Memeriksa ketersediaan .NET Framework 4.8 di sistem Windows.
    Bila belum ada, menawarkan pemasangan offline (bila redist tersedia) atau panduan unduh.
#>
param(
    [string]$Redist = "",
    [switch]$Senyap
)

$RELEASE_NET48 = 528040
$regPath = "HKLM:\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full"

$terpasang = $false
try {
    if (Test-Path $regPath) {
        $rel = (Get-ItemProperty -Path $regPath -Name "Release" -ErrorAction SilentlyContinue).Release
        if ($rel -ge $RELEASE_NET48) {
            $terpasang = $true
        }
    }
} catch {
    $terpasang = $false
}

if ($terpasang) {
    if (-not $Senyap) {
        Write-Output ".NET Framework 4.8 sudah terpasang."
    }
    exit 0
}

# .NET 4.8 belum terpasang
$pesan = "Aplikasi SiapBOSP membutuhkan Microsoft .NET Framework 4.8 agar dapat berjalan di Windows ini."

if ($Redist -ne "" -and (Test-Path $Redist)) {
    if ($Senyap) {
        Write-Output "Memasang .NET Framework 4.8 secara otomatis..."
        $proc = Start-Process -FilePath $Redist -ArgumentList "/q /norestart" -Wait -PassThru
        if ($proc.ExitCode -eq 0 -or $proc.ExitCode -eq 3010) {
            exit 0
        } else {
            Write-Error "Gagal memasang .NET Framework 4.8 (kode $($proc.ExitCode))."
            exit 1
        }
    } else {
        Add-Type -AssemblyName System.Windows.Forms
        $tanya = [System.Windows.Forms.MessageBox]::Show(
            "$pesan`n`nPaket instalasi offline tersedia. Apakah Anda ingin memasangnya sekarang?",
            "Prasyarat .NET Framework 4.8 - SiapBOSP",
            [System.Windows.Forms.MessageBoxButtons]::YesNo,
            [System.Windows.Forms.MessageBoxIcon]::Question
        )

        if ($tanya -eq [System.Windows.Forms.DialogResult]::Yes) {
            $proc = Start-Process -FilePath $Redist -ArgumentList "/showfinalerror /passive /norestart" -Wait -PassThru
            if ($proc.ExitCode -eq 0 -or $proc.ExitCode -eq 3010) {
                exit 0
            } else {
                [System.Windows.Forms.MessageBox]::Show(
                    "Pemasangan .NET Framework 4.8 tidak selesai (kode $($proc.ExitCode)).",
                    "Pemasang SiapBOSP",
                    [System.Windows.Forms.MessageBoxButtons]::OK,
                    [System.Windows.Forms.MessageBoxIcon]::Warning
                )
                exit 1
            }
        } else {
            exit 1
        }
    }
} else {
    $url = "https://go.microsoft.com/fwlink/?linkid=2088631"
    if ($Senyap) {
        Write-Output "$pesan Silakan unduh dari $url"
        exit 1
    } else {
        Add-Type -AssemblyName System.Windows.Forms
        $tanya = [System.Windows.Forms.MessageBox]::Show(
            "$pesan`n`nKlik 'OK' untuk membuka halaman unduhan resmi Microsoft .NET Framework 4.8 Runtime.",
            "Prasyarat .NET Framework 4.8 - SiapBOSP",
            [System.Windows.Forms.MessageBoxButtons]::OKCancel,
            [System.Windows.Forms.MessageBoxIcon]::Information
        )
        if ($tanya -eq [System.Windows.Forms.DialogResult]::OK) {
            try {
                [System.Diagnostics.Process]::Start($url)
            } catch {}
        }
        exit 1
    }
}
