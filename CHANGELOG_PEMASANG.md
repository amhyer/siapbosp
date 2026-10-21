# Catatan Pemasang SiapBOSP Lokal (v12) — P1 "Profesional"

> Berkas ini terpisah dari `CHANGELOG_SiapBOSP.md` karena berkas itu di workspace ini
> kini berisi catatan lineage lain (v23). Catatan di bawah khusus pekerjaan **pemasang
> laptop bendahara** (EXE/ZIP + launcher) yang tinggal di `tools/` dan `launcher-windows/`.

**Tanggal:** 21 Oktober 2026 · **Artefak:** `storage/app/paket/SiapBOSP-Setup-v12.exe` + `SiapBOSP-Portable-v12.zip`

## P1 — Pemasang bergaya aplikasi profesional

1. **Wizard GUI (WinForms) di EXE** — `tools/bootstrap.cs`
   - Halaman: Selamat datang → Data Sekolah → Akun Bendahara → Progres → Selesai.
   - Selamat datang mendeteksi **sudah terpasang** (registri + folder) → menandai "akan memperbarui; data pembukuan aman".
   - Data Sekolah: NPSN/nama sekolah, otomatis terisi dari `akun-sekolah.conf` di samping EXE (identitas tetap disalin ke `launcher-windows\`).
   - Akun Bendahara: sandi tersamar (`*`), validasi email + panjang sandi ≥ 6 + ulangi sandi.
   - Progres: bar (0–100 saat menyalin, marquee saat database/akun), log langsung; tombol **Buka Log**.
   - Selesai: lokasi pemasangan + opsi "Buka SiapBOSP sekarang".
   - Mode senyap tetap ada (tanpa `SIPBOS_DEST` env → GUI; dengan `SIPBOS_DEST`/`--senyap` → senyap; `--paksa-gui` memaksa wizard walau `SIPBOS_DEST` diisi, untuk uji).
2. **Metadata EXE** — Produk `SiapBOSP`, versi file 12.0.0.0, ProductVersion `12.0.0 (v12)`, Penerbit `Tim SiapBOSP`, Deskripsi `Pemasang SiapBOSP`; ikon + manifest (`asInvoker`, DPI aware) via `tools/app.manifest`.
3. **Pintasan Start Menu** — `launcher-windows/buat-pintasan.ps1` kini membuat folder `Start Menu\Programs\SiapBOSP` berisi 4 pintasan (buka, Tarik ARKAS, Sinkron, **Hapus**) di samping 3 pintasan Desktop; `-Hapus` membersihkan keduanya.
4. **Entri "Aplikasi & fitur" lengkap** — `launcher-windows/Pasang-SiapBOSP.bat`: DisplayName, **DisplayVersion 12.0.0**, **Publisher Tim SiapBOSP**, DisplayIcon, InstallLocation, UninstallString, **QuietUninstallString** (`Hapus-SiapBOSP.bat --senyap`), **EstimatedSize** (dihitung dari ukuran folder), NoModify/NoRepair.
5. **Log pemasangan** — `%LocalAppData%\SiapBOSP-Install.log` (header + ekstraksi + seluruh keluaran `PASANG-SIAPBOSP.bat` + footer); tombol **Buka Log** di wizard; dihapus saat uninstall.
6. **Penghapus aman** — `launcher-windows/Hapus-SiapBOSP.bat`:
   - Dialog **Ya/Tidak/Batal**: Ya = cadangkan dulu, Tidak = hapus semua, Batal = urungkan.
   - Cadangan: `Documents\SiapBOSP-Backup-<yyyyMMdd-HHmmss>.zip` berisi `database\`, `storage\app\`, `.env` (mode `--senyap` mencadangkan otomatis; `SIPBOS_BACKUP` bisa menimpa folder tujuan untuk uji).
   - Menutup aplikasi yang masih berjalan (php.exe/cmd.exe milik folder ini, tidak menutup proses lain).
   - Membersihkan pintasan Desktop + Start Menu, entri registri, dan log; skrip menyalin dirinya ke `%TEMP%` lebih dulu agar folder aplikasi bisa dihapus utuh, lalu penghapusan folder dijadwalkan lewat proses terpisah (coba ulang hingga 10 kali + pesan bila masih terkunci).
7. **Perbaikan penting saat uji** — `JalankanBat` kini menjalankan `PASANG-SIAPBOSP.bat` dengan **`< NUL`** (semua `pause`/`set /p` tidak pernah menggantung di konsol tersembunyi) + **batas waktu 20 menit** (taskkill `/t /f` bila lewat, lapor kode −2).
8. **Unduhan per akun di website** (sudah ada sebelumnya, dicatat di sini karena changelog utama berganti):
   - `/unduh-pemasang` → EXE generik; `/unduh-identitas` → `akun-sekolah.conf` (token API baru tiap unduh, token lama nonaktif); `/unduh-aplikasi` (ZIP ber-identitas) tetap ada.
   - Kartu "Opsi 0" di `impor-arkas/form.blade.php`; controller `UnduhAplikasiController`; uji `tests/Feature/UnduhAplikasiTest.php`.

## Berkas yang diubah untuk P1

- `tools/bootstrap.cs` (GUI + mode senyap + log + `< NUL` + timeout)
- `tools/app.manifest` (baru)
- `tools/buat-setup-exe.ps1` (`/target:winexe`, referensi WinForms/Drawing, manifest, `/codepage:65001`)
- `launcher-windows/buat-pintasan.ps1` (Start Menu)
- `launcher-windows/Hapus-SiapBOSP.bat` (penghapus aman baru)
- `launcher-windows/Pasang-SiapBOSP.bat` (registri lengkap + pesan penutup)
- `launcher-windows/BACA-DULU.txt` (panduan pasang/hapus baru)

## Uji otomatis

- `%TEMP%\opencode\uji-exe.ps1` — mode senyap dasar (sandbox).
- `%TEMP%\opencode\uji-exe2.ps1` — sidecar A/B (`akun-sekolah.conf` ada/tidak).
- `%TEMP%\opencode\uji-hapus.ps1` — penghapus `--senyap` + isi cadangan + registri/pintasan asli utuh.
- `%TEMP%\opencode\uji-gui.ps1` — wizard GUI (klik-otomatis + tangkapan layar `gui-01…05.png`).
