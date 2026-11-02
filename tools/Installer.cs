// Logika non-UI Pemasang SiapBOSP - port 1:1 dari tools/bootstrap.cs (WinForms) lama.
// Dipakai bersama oleh wizard WPF (MainWindow) dan mode senyap (App -> JalurSenyap).
// Sengaja ditulis kompatibel C# 5 agar tetap bisa dikompilasi csc.exe bawaan Windows
// (jalur cadangan tools/buat-setup-exe.ps1 bila .NET SDK tidak tersedia).
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Reflection;
using System.Text;
using Microsoft.Win32;

namespace SiapBOSP.Setup
{
    internal static class Installer
    {
        internal const string VERSI = "12.0.0";

        // --- keadaan umum ---
        internal static string Dest;
        internal static string LogPath;
        internal static string SidecarPath;
        internal static bool GuiAktif;
        internal static volatile bool SedangPasang;
        internal static int KodeKeluar;
        internal static Dictionary<string, string> ConfSidecar = new Dictionary<string, string>();

        // data terpilih (dari GUI atau env)
        internal static string fNpsn = "";
        internal static string fSekolah = "";
        internal static string fNama = "";
        internal static string fEmail = "";
        internal static string fSandi = "";

        /// <summary>
        /// Penerima teks log untuk wizard (diisi MainWindow). Dipanggil dari thread pekerja;
        /// penerima wajib melempar pembaruan UI lewat Dispatcher.
        /// </summary>
        internal static Action<string> TeksLogUi;

        /// <summary>
        /// Konsol milik sendiri (AllocConsole) - Tahan() menunggu tombol (dengan batas waktu).
        /// </summary>
        internal static bool KonsolBaru;

        /// <summary>
        /// Konsol pinjaman induk (AttachConsole) - Tahan() tidak membaca tombol, karena
        /// masukan papan ketik milik shell induk.
        /// </summary>
        internal static bool KonsolPinjaman;

        /// <summary>
        /// Bagian awal Main() lama: tentukan berkas log, mode senyap/GUI, folder tujuan,
        /// dan baca akun-sekolah.conf di samping pemasang. Mengembalikan true bila GUI aktif.
        /// </summary>
        internal static bool Siapkan(string[] args)
        {
            try
            {
                LogPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SiapBOSP-Install.log");
            }
            catch
            {
                LogPath = Path.Combine(Path.GetTempPath(), "SiapBOSP-Install.log");
            }

            bool senyap = PunyaArg(args, "--senyap") || PunyaArg(args, "/s") || PunyaArg(args, "/senyap");
            if (!string.IsNullOrEmpty(Environment.GetEnvironmentVariable("SIPBOS_SENYAP")))
            {
                senyap = true;
            }
            bool paksaGui = PunyaArg(args, "--paksa-gui");
            bool adaDest = !string.IsNullOrEmpty(Environment.GetEnvironmentVariable("SIPBOS_DEST"));

            Dest = TentukanDest();
            BacaConfSidecar();

            GuiAktif = !senyap && (!adaDest || paksaGui);
            return GuiAktif;
        }

        internal static bool PunyaArg(string[] args, string cari)
        {
            foreach (string a in args)
            {
                if (a != null && a.Trim().ToLowerInvariant() == cari.ToLowerInvariant())
                {
                    return true;
                }
            }

            return false;
        }

        internal static string TentukanDest()
        {
            string dest = Environment.GetEnvironmentVariable("SIPBOS_DEST");
            if (string.IsNullOrEmpty(dest))
            {
                dest = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Programs\\SiapBOSP");
            }

            return Path.GetFullPath(dest);
        }

        internal static void BacaConfSidecar()
        {
            try
            {
                string dir = Path.GetDirectoryName(Process.GetCurrentProcess().MainModule.FileName);
                SidecarPath = Path.Combine(dir, "akun-sekolah.conf");
                if (File.Exists(SidecarPath))
                {
                    foreach (string baris in File.ReadAllLines(SidecarPath))
                    {
                        string b = baris.Trim();
                        if (b.Length == 0 || b.StartsWith("#"))
                        {
                            continue;
                        }
                        int p = b.IndexOf('=');
                        if (p <= 0)
                        {
                            continue;
                        }
                        ConfSidecar[b.Substring(0, p).Trim()] = b.Substring(p + 1).Trim();
                    }
                }
            }
            catch
            {
                ConfSidecar = new Dictionary<string, string>();
            }
        }

        internal static string ConfGet(string kunci)
        {
            string v;
            return ConfSidecar.TryGetValue(kunci, out v) ? v : "";
        }

        internal static string Ambil(string env, string cadangan)
        {
            string v = Environment.GetEnvironmentVariable(env);
            return string.IsNullOrEmpty(v) ? cadangan : v;
        }

        // --- catatan/log (Console + berkas install.log) ---
        internal static void TulisKonsol(string s)
        {
            try
            {
                Console.Write(s);
            }
            catch
            {
            }
        }

        internal static void Catat(string baris)
        {
            if (!GuiAktif)
            {
                TulisKonsol(baris + Environment.NewLine);
            }
            CatuLog(baris);
            if (GuiAktif && TeksLogUi != null)
            {
                TeksLog(baris + "\r\n");
            }
        }

        internal static void CatuLog(string baris)
        {
            try
            {
                using (StreamWriter sw = new StreamWriter(LogPath, true, new UTF8Encoding(false)))
                {
                    sw.WriteLine(baris);
                }
            }
            catch
            {
            }
        }

        private static void TeksLog(string teks)
        {
            Action<string> penerima = TeksLogUi;
            if (penerima == null)
            {
                return;
            }
            try
            {
                penerima(teks);
            }
            catch
            {
            }
        }

        // --- identitas sekolah di sebelah pemasang ---
        internal static void SalinSidecarKeDest()
        {
            try
            {
                string confTujuan = Path.Combine(Dest, "launcher-windows", "akun-sekolah.conf");
                if (File.Exists(SidecarPath))
                {
                    Directory.CreateDirectory(Path.GetDirectoryName(confTujuan));
                    File.Copy(SidecarPath, confTujuan, true);
                    string npsn = ConfGet("NPSN");
                    Catat("  Identitas sekolah: akun-sekolah.conf" + (npsn.Length > 0 ? " (NPSN " + npsn + ")" : "") + " - disalin.");
                    Catat("  (Sinkron ke pusat siap dipakai - tidak perlu isi NPSN manual.)");
                }
                else
                {
                    Catat("  Identitas sekolah (akun-sekolah.conf) tidak ada di folder pemasang ini.");
                    Catat("  (NPSN akan ditanyakan saat pemasangan; sinkron ke pusat bisa menyusul.)");
                }
            }
            catch (Exception ex)
            {
                Catat("  Catatan: gagal menyalin akun-sekolah.conf (" + ex.Message + ") - lanjut tanpa identitas.");
            }
        }

        // --- jalankan PASANG-SIAPBOSP.bat; keluarannya menuju berkas log ---
        internal static int JalankanBat(bool denganTail)
        {
            long offset = 0;
            try
            {
                offset = new FileInfo(LogPath).Length;
            }
            catch
            {
            }

            ProcessStartInfo info = new ProcessStartInfo();
            info.FileName = "cmd.exe";
            info.Arguments = "/d /s /c \"\"PASANG-SIAPBOSP.bat\" < NUL >> \"" + LogPath + "\" 2>&1\"";
            info.WorkingDirectory = Dest;
            info.UseShellExecute = false;
            info.CreateNoWindow = true;
            info.EnvironmentVariables["SIPBOS_NPSN"] = fNpsn;
            info.EnvironmentVariables["SIPBOS_SEKOLAH"] = fSekolah;
            info.EnvironmentVariables["SIPBOS_NAMA"] = fNama;
            info.EnvironmentVariables["SIPBOS_EMAIL"] = fEmail;
            info.EnvironmentVariables["SIPBOS_SANDI"] = fSandi;
            info.EnvironmentVariables["SIPBOS_BUKA"] = "N";

            Process proc = Process.Start(info);
            DateTime batas = DateTime.UtcNow.AddMinutes(20);
            if (denganTail)
            {
                while (!proc.WaitForExit(200))
                {
                    BuntutiLog(ref offset);
                    if (DateTime.UtcNow > batas)
                    {
                        Hentikan(proc);
                        return -2;
                    }
                }
                proc.WaitForExit();
                BuntutiLog(ref offset);
            }
            else
            {
                int sisa = (int)Math.Max(1000, (batas - DateTime.UtcNow).TotalMilliseconds);
                if (!proc.WaitForExit(sisa))
                {
                    Hentikan(proc);
                    return -2;
                }
                try
                {
                    using (FileStream fs = new FileStream(LogPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                    {
                        if (fs.Length > offset)
                        {
                            fs.Seek(offset, SeekOrigin.Begin);
                            byte[] buf = new byte[fs.Length - offset];
                            int n = fs.Read(buf, 0, buf.Length);
                            TulisKonsol(Encoding.UTF8.GetString(buf, 0, n));
                        }
                    }
                }
                catch
                {
                }
            }

            return proc.ExitCode;
        }

        internal static void Hentikan(Process proc)
        {
            Catat("[GAGAL] Pemasangan melebihi batas waktu (20 menit) - proses dihentikan.");
            try
            {
                ProcessStartInfo pi = new ProcessStartInfo("taskkill.exe", "/pid " + proc.Id + " /t /f");
                pi.UseShellExecute = false;
                pi.CreateNoWindow = true;
                Process.Start(pi).WaitForExit(10000);
            }
            catch
            {
            }
        }

        internal static void BuntutiLog(ref long offset)
        {
            try
            {
                using (FileStream fs = new FileStream(LogPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                {
                    if (fs.Length <= offset)
                    {
                        return;
                    }
                    fs.Seek(offset, SeekOrigin.Begin);
                    byte[] buf = new byte[fs.Length - offset];
                    int n = fs.Read(buf, 0, buf.Length);
                    offset += n;
                    TeksLog(Encoding.UTF8.GetString(buf, 0, n));
                }
            }
            catch
            {
            }
        }

        // --- ekstraksi paket tertanam (langsung ke folder tujuan) ---
        internal static int EkstrakPaket(Action<int, int> lapor)
        {
            Assembly asm = Assembly.GetExecutingAssembly();
            string namaSumber = null;
            string[] daftar = asm.GetManifestResourceNames();
            for (int i = 0; i < daftar.Length; i++)
            {
                if (daftar[i].EndsWith("paket.zip", StringComparison.OrdinalIgnoreCase))
                {
                    namaSumber = daftar[i];
                    break;
                }
            }
            if (namaSumber == null)
            {
                throw new Exception("Paket tertanam tidak ditemukan di EXE ini.");
            }

            if (!Directory.Exists(Dest))
            {
                Directory.CreateDirectory(Dest);
            }

            int jumlah = 0;
            int total = 0;
            using (Stream aliran = asm.GetManifestResourceStream(namaSumber))
            using (ZipArchive zip = new ZipArchive(aliran, ZipArchiveMode.Read))
            {
                total = zip.Entries.Count;
                foreach (ZipArchiveEntry entri in zip.Entries)
                {
                    string nama = entri.FullName.Replace('\\', '/');
                    int pisah = nama.IndexOf('/');
                    if (pisah < 0)
                    {
                        continue;
                    }
                    string relatif = nama.Substring(pisah + 1);
                    if (relatif.Length == 0)
                    {
                        continue;
                    }

                    string tujuan = Path.Combine(Dest, relatif.Replace('/', Path.DirectorySeparatorChar));
                    if (relatif.EndsWith("/"))
                    {
                        if (!Directory.Exists(tujuan))
                        {
                            Directory.CreateDirectory(tujuan);
                        }
                    }
                    else
                    {
                        string folder = Path.GetDirectoryName(tujuan);
                        if (!string.IsNullOrEmpty(folder) && !Directory.Exists(folder))
                        {
                            Directory.CreateDirectory(folder);
                        }
                        using (Stream masuk = entri.Open())
                        using (FileStream keluar = new FileStream(tujuan, FileMode.Create, FileAccess.Write, FileShare.None))
                        {
                            masuk.CopyTo(keluar);
                        }
                    }

                    jumlah++;
                    if (lapor != null && (jumlah % 100 == 0 || jumlah == total))
                    {
                        lapor(jumlah, total);
                    }
                }
            }

            return jumlah;
        }

        internal static void Tahan()
        {
            if (KonsolPinjaman)
            {
                // Konsol milik shell induk: shell sudah kembali ke prompt-nya sendiri.
                return;
            }
            try
            {
                if (Console.IsOutputRedirected)
                {
                    return;
                }
                TulisKonsol("  Tekan tombol apa saja untuk menutup jendela ini..." + Environment.NewLine);
                if (KonsolBaru)
                {
                    // Jendela konsol baru: jangan sampai menggantung pemasangan otomatis -
                    // tutup sendiri setelah 60 detik bila tidak ada tombol ditekan.
                    DateTime batas = DateTime.UtcNow.AddSeconds(60);
                    while (DateTime.UtcNow < batas)
                    {
                        if (Console.KeyAvailable)
                        {
                            Console.ReadKey(true);
                            return;
                        }
                        System.Threading.Thread.Sleep(100);
                    }
                    return;
                }
                Console.ReadKey(true);
            }
            catch
            {
            }
        }

        // --- mode senyap: dipakai uji otomatis & pemasangan massal ---
        internal static int JalurSenyap()
        {
            Catat("================================================");
            Catat("  PEMASANG SiapBOSP (Setup.exe) - mode senyap");
            Catat("================================================");
            Catat("");
            Catat("  Tujuan : " + Dest);
            Catat("");
            Catat("  Menyalin aplikasi ke folder tujuan... (mohon tunggu)");

            int total = 0;
            try
            {
                total = EkstrakPaket(delegate(int jumlah, int semua)
                {
                    if (jumlah % 500 == 0)
                    {
                        TulisKonsol("\r  " + jumlah + "/" + semua + " berkas...        ");
                    }
                });
            }
            catch (Exception ex)
            {
                Catat("  [GAGAL] " + ex.GetType().Name + ": " + ex.Message);
                Tahan();
                return 1;
            }
            TulisKonsol("\r  " + total + "/" + total + " berkas selesai.        " + Environment.NewLine);
            Catat("  " + total + " berkas selesai disalin.");
            Catat("");

            SalinSidecarKeDest();
            Catat("");

            fNpsn = Ambil("SIPBOS_NPSN", ConfGet("NPSN"));
            fSekolah = Ambil("SIPBOS_SEKOLAH", ConfGet("SEKOLAH"));
            fNama = Ambil("SIPBOS_NAMA", "Bendahara Sekolah");
            fEmail = Ambil("SIPBOS_EMAIL", fNpsn.Length > 0 ? fNpsn + "@siapbosp.lokal" : "");
            fSandi = Ambil("SIPBOS_SANDI", "");

            if (fNpsn.Length == 0 || fSandi.Length == 0)
            {
                Catat("  [GAGAL] Mode senyap butuh SIPBOS_NPSN dan SIPBOS_SANDI");
                Catat("  (NPSN juga bisa dibaca dari akun-sekolah.conf di samping pemasang).");
                Tahan();
                return 1;
            }

            Catat("  Menjalankan pemasangan...");
            Catat("");

            int kode;
            try
            {
                kode = JalankanBat(false);
            }
            catch (Exception ex)
            {
                Catat("  [GAGAL] " + ex.GetType().Name + ": " + ex.Message);
                Tahan();
                return 1;
            }

            Catat("");
            if (kode == 0)
            {
                Catat("================================================");
                Catat("  SELESAI. SiapBOSP terpasang di:");
                Catat("  " + Dest);
                Catat("  Buka lewat pintasan Desktop \"SiapBOSP\".");
                Catat("================================================");
                Tahan();
                return 0;
            }

            Catat("================================================");
            Catat("  Pemasangan BELUM selesai (kode " + kode + ").");
            Catat("  Pastikan aplikasi SiapBOSP sedang TIDAK berjalan,");
            Catat("  lalu jalankan lagi SiapBOSP-Setup-v12.exe (aman diulang).");
            Catat("================================================");
            Tahan();
            return kode;
        }

        // --- aksi pendukung wizard ---
        internal static void BukaLog()
        {
            try
            {
                Process.Start(new ProcessStartInfo("notepad.exe", "\"" + LogPath + "\""));
            }
            catch
            {
            }
        }

        internal static void BukaAplikasi()
        {
            try
            {
                string vbs = Path.Combine(Dest, "launcher-windows", "Buka-SiapBOSP.vbs");
                if (File.Exists(vbs))
                {
                    ProcessStartInfo piVbs = new ProcessStartInfo("wscript.exe", "\"" + vbs + "\"");
                    piVbs.UseShellExecute = true;
                    piVbs.WorkingDirectory = Dest;
                    Process.Start(piVbs);
                    return;
                }

                ProcessStartInfo pi = new ProcessStartInfo(Path.Combine(Dest, "launcher-windows", "SiapBOSP.bat"));
                pi.UseShellExecute = true;
                pi.WorkingDirectory = Dest;
                Process.Start(pi);
            }
            catch
            {
            }
        }

        internal static bool SudahTerpasang(out string versiLama)
        {
            versiLama = "";
            try
            {
                using (RegistryKey k = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Uninstall\SiapBOSP"))
                {
                    if (k != null)
                    {
                        object loc = k.GetValue("InstallLocation");
                        string sl = loc == null ? "" : loc.ToString();
                        if (sl.Length == 0 || string.Equals(sl.TrimEnd('\\'), Dest.TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                        {
                            object v = k.GetValue("DisplayVersion");
                            versiLama = v == null ? "" : v.ToString();
                            return true;
                        }
                    }
                }
            }
            catch
            {
            }

            return File.Exists(Path.Combine(Dest, "launcher-windows", "SiapBOSP.bat"));
        }
    }
}
