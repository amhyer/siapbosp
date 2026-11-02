# Paket Redistributable Microsoft .NET Framework 4.8 (Offline Installer)

Folder ini disediakan untuk menampung berkas installer offline resmi Microsoft .NET Framework 4.8:
`ndp48-x86-x64-allos-enu.exe`

### Tujuan
Jika berkas `ndp48-x86-x64-allos-enu.exe` diletakkan di folder ini saat menjalankan skrip pembangunan `tools/buat-setup-exe.ps1`, berkas tersebut akan otomatis disematkan (embedded resource) ke dalam EXE pemasang (`SiapBOSP-Setup-v12.exe`).

Pemasang di Windows 7 SP1 yang belum memiliki .NET 4.8 akan dapat langsung memasang prasyarat tersebut tanpa koneksi internet.

### Catatan Git
> **PENTING:** Jangan meng-commit berkas `ndp48-x86-x64-allos-enu.exe` (~115 MB) ke repositori Git. Berkas tersebut sudah dikecualikan oleh aturan `.gitignore`.

### Unduhan Resmi
Unduh berkas resmi dari portal Microsoft:
- **Tautan Langsung:** [Microsoft .NET Framework 4.8 Offline Installer](https://go.microsoft.com/fwlink/?linkid=2088631)
- Nama berkas yang diharapkan: `ndp48-x86-x64-allos-enu.exe`
