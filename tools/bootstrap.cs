// Pemasang SiapBOSP (SFX) - membungkus SiapBOSP-Portable-v12.zip menjadi satu EXE.
// Mode wizard GUI (WinForms) untuk pemakaian biasa; mode senyap (env SIPBOS_*) untuk
// uji otomatis / pemasangan massal.
// Kompilasi: tools\buat-setup-exe.ps1 (pakai csc.exe bawaan Windows).
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.IO.Compression;
using System.Reflection;
using System.Text;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("Pemasang SiapBOSP")]
[assembly: AssemblyProduct("SiapBOSP")]
[assembly: AssemblyCompany("Tim SiapBOSP")]
[assembly: AssemblyDescription("Pemasang aplikasi SiapBOSP untuk laptop bendahara sekolah")]
[assembly: AssemblyCopyright("(c) 2026 Tim SiapBOSP")]
[assembly: AssemblyVersion("12.0.0.0")]
[assembly: AssemblyFileVersion("12.0.0.0")]
[assembly: AssemblyInformationalVersion("12.0.0 (v12)")]

internal static class Bootstrap
{
    private const string VERSI = "12.0.0";

    // --- keadaan umum ---
    private static string Dest;
    private static string LogPath;
    private static string SidecarPath;
    private static bool GuiAktif;
    private static volatile bool SedangPasang;
    private static int KodeKeluar;
    private static Dictionary<string, string> ConfSidecar = new Dictionary<string, string>();

    // data terpilih (dari GUI atau env)
    private static string fNpsn = "";
    private static string fSekolah = "";
    private static string fNama = "";
    private static string fEmail = "";
    private static string fSandi = "";

    // --- kontrol GUI ---
    private static Form F;
    private static Panel PSelamat, PData, PAkun, PProses, PSelesai;
    private static Label LblSdhAda, LblConfInfo, LblStatus, LblGalat;
    private static TextBox TxtNpsn, TxtSekolah, TxtNama, TxtEmail, TxtSandi, TxtSandi2, TxtLog;
    private static ProgressBar Bar;
    private static Button BtnMulai, BtnLanjutData, BtnPasang, BtnUlangi, BtnTutupGalat, BtnSelesai;
    private static CheckBox ChkBuka;

    [STAThread]
    private static int Main(string[] args)
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
        if (GuiAktif)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            F = BangunForm();
            Application.Run(F);
            return KodeKeluar;
        }
        return JalurSenyap();
    }

    private static bool PunyaArg(string[] args, string cari)
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

    private static string TentukanDest()
    {
        string dest = Environment.GetEnvironmentVariable("SIPBOS_DEST");
        if (string.IsNullOrEmpty(dest))
        {
            dest = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Programs\\SiapBOSP");
        }

        return Path.GetFullPath(dest);
    }

    private static void BacaConfSidecar()
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

    private static string ConfGet(string kunci)
    {
        string v;
        return ConfSidecar.TryGetValue(kunci, out v) ? v : "";
    }

    private static string Ambil(string env, string cadangan)
    {
        string v = Environment.GetEnvironmentVariable(env);
        return string.IsNullOrEmpty(v) ? cadangan : v;
    }

    // --- catatan/log (Console + berkas install.log) ---
    private static void TulisKonsol(string s)
    {
        try
        {
            Console.Write(s);
        }
        catch
        {
        }
    }

    private static void Catat(string baris)
    {
        if (!GuiAktif)
        {
            TulisKonsol(baris + Environment.NewLine);
        }
        CatuLog(baris);
        if (GuiAktif && TxtLog != null)
        {
            TeksLog(baris + "\r\n");
        }
    }

    private static void CatuLog(string baris)
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

    // --- jembatan ke UI (thread pekerja -> form) ---
    private static void Ui(Action aksi)
    {
        try
        {
            F.BeginInvoke(aksi);
        }
        catch
        {
        }
    }

    private static void TeksLog(string teks)
    {
        Ui(delegate()
        {
            if (TxtLog.TextLength > 400000)
            {
                TxtLog.Clear();
            }
            TxtLog.AppendText(teks);
        });
    }

    private static void StatusP(string teks)
    {
        Ui(delegate()
        {
            LblStatus.Text = teks;
        });
    }

    // --- identitas sekolah di sebelah pemasang ---
    private static void SalinSidecarKeDest()
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
    private static int JalankanBat(bool denganTail)
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

    private static void Hentikan(Process proc)
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

    private static void BuntutiLog(ref long offset)
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
    private static int EkstrakPaket(Action<int, int> lapor)
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

    private static void Tahan()
    {
        try
        {
            if (Console.IsOutputRedirected)
            {
                return;
            }
            TulisKonsol("  Tekan tombol apa saja untuk menutup jendela ini..." + Environment.NewLine);
            Console.ReadKey(true);
        }
        catch
        {
        }
    }

    // --- mode senyap: dipakai uji otomatis & pemasangan massal ---
    private static int JalurSenyap()
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

    // ============================================================
    //  BAGIAN GUI (wizard pemasangan)
    // ============================================================

    private static Panel BuatPanel()
    {
        Panel p = new Panel();
        p.Dock = DockStyle.Fill;
        p.Visible = false;
        F.Controls.Add(p);
        return p;
    }

    private static Label AddLabel(Panel induk, string teks, int x, int y, int w, int h, float ukuran, bool tebal, Color warna)
    {
        Label l = new Label();
        l.Text = teks;
        l.Location = new Point(x, y);
        l.Size = new Size(w, h);
        l.Font = new Font("Segoe UI", ukuran, tebal ? FontStyle.Bold : FontStyle.Regular);
        l.ForeColor = warna;
        l.AutoSize = false;
        induk.Controls.Add(l);
        return l;
    }

    private static Button AddTombol(Panel induk, string teks, int x, int y, int w, int h)
    {
        Button b = new Button();
        b.Text = teks;
        b.Location = new Point(x, y);
        b.Size = new Size(w, h);
        b.Font = new Font("Segoe UI", 9.5f);
        b.UseVisualStyleBackColor = true;
        induk.Controls.Add(b);
        return b;
    }

    private static TextBox AddText(Panel induk, string isi, int x, int y, int w, bool sandi)
    {
        TextBox t = new TextBox();
        t.Text = isi;
        t.Location = new Point(x, y);
        t.Size = new Size(w, 24);
        t.Font = new Font("Segoe UI", 9.5f);
        if (sandi)
        {
            t.PasswordChar = '*';
        }
        induk.Controls.Add(t);
        return t;
    }

    private static Form BangunForm()
    {
        F = new Form();
        F.Text = "Pemasang SiapBOSP v12";
        F.ClientSize = new Size(640, 440);
        F.StartPosition = FormStartPosition.CenterScreen;
        F.FormBorderStyle = FormBorderStyle.FixedDialog;
        F.MaximizeBox = false;
        F.MinimizeBox = false;
        F.Font = new Font("Segoe UI", 9f);
        F.FormClosing += F_FormClosing;
        try
        {
            F.Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
        }
        catch
        {
        }

        Color warnaJudul = Color.FromArgb(21, 94, 60);
        Color warnaTeks = Color.FromArgb(51, 65, 85);
        Color warnaAmber = Color.FromArgb(180, 95, 6);
        Color warnaRed = Color.FromArgb(185, 28, 28);

        // ---------- Panel: Selamat datang ----------
        PSelamat = BuatPanel();
        AddLabel(PSelamat, "Selamat datang di Pemasang SiapBOSP", 24, 22, 592, 32, 16f, true, warnaJudul);
        AddLabel(PSelamat, "Aplikasi bendahara sekolah - lokal di laptop ini, tersambung ke website pusat.", 24, 58, 592, 20, 9.5f, false, warnaTeks);
        AddLabel(PSelamat,
            "Pemasang akan:\r\n   1. menyalin aplikasi SiapBOSP ke laptop ini (tidak perlu internet);\r\n   2. menyiapkan database dan data awal sekolah;\r\n   3. membuat akun bendahara untuk login;\r\n   4. membuat pintasan di Desktop dan Start Menu.\r\n\r\nTidak perlu memasang PHP, Node, atau aplikasi lain - semuanya sudah dibundel.",
            24, 88, 592, 150, 9f, false, warnaTeks);
        AddLabel(PSelamat, "Lokasi pemasangan: " + Dest, 24, 246, 592, 20, 8.5f, false, Color.DimGray);
        LblSdhAda = AddLabel(PSelamat, "", 24, 272, 592, 64, 9f, false, warnaAmber);
        LblSdhAda.Visible = false;
        string versiLama;
        if (SudahTerpasang(out versiLama))
        {
            LblSdhAda.Text = "SiapBOSP sudah terpasang di laptop ini" + (versiLama.Length > 0 ? " (versi " + versiLama + ")" : "") +
                ".\r\nMelanjutkan akan MEMPERBARUI aplikasi - data pembukuan lama tetap aman;\r\nakun bendahara disetel ulang sesuai isian di halaman berikut.";
            LblSdhAda.Visible = true;
        }
        Button bBatal = AddTombol(PSelamat, "Batal", 440, 395, 90, 30);
        bBatal.Click += delegate(object s, EventArgs e) { KodeKeluar = 0; Application.Exit(); };
        BtnMulai = AddTombol(PSelamat, "Mulai >", 535, 395, 90, 30);
        BtnMulai.Click += delegate(object s, EventArgs e) { Tampilkan(PData); };

        // ---------- Panel: Data sekolah ----------
        PData = BuatPanel();
        AddLabel(PData, "Data Sekolah", 24, 22, 592, 32, 16f, true, warnaJudul);
        LblConfInfo = AddLabel(PData, "", 24, 58, 592, 20, 9f, false, Color.FromArgb(21, 128, 61));
        LblConfInfo.Visible = false;
        if (ConfSidecar.Count > 0)
        {
            LblConfInfo.Text = "Identitas terbaca dari akun-sekolah.conf (unduhan website) - sinkron ke pusat siap dipakai.";
            LblConfInfo.Visible = true;
        }
        AddLabel(PData, "NPSN sekolah:", 24, 100, 120, 20, 9f, false, warnaTeks);
        TxtNpsn = AddText(PData, Ambil("SIPBOS_NPSN", ConfGet("NPSN")), 150, 97, 200, false);
        AddLabel(PData, "Nama sekolah:", 24, 136, 120, 20, 9f, false, warnaTeks);
        TxtSekolah = AddText(PData, Ambil("SIPBOS_SEKOLAH", ConfGet("SEKOLAH")), 150, 133, 440, false);
        AddLabel(PData, "NPSN dipakai untuk mengaitkan data dengan sekolah di website pusat.\r\nPeriksa ejaan nama sekolah - tampil juga di laporan.", 24, 172, 592, 40, 8.5f, false, Color.DimGray);
        Button bKembaliData = AddTombol(PData, "< Kembali", 340, 395, 90, 30);
        bKembaliData.Click += delegate(object s, EventArgs e) { Tampilkan(PSelamat); };
        BtnLanjutData = AddTombol(PData, "Lanjut >", 435, 395, 190, 30);
        BtnLanjutData.Click += delegate(object s, EventArgs e)
        {
            if (TxtNpsn.Text.Trim().Length == 0)
            {
                MessageBox.Show(F, "Isi NPSN sekolah dulu.", "Data Sekolah", MessageBoxButtons.OK, MessageBoxIcon.Information);
                TxtNpsn.Focus();
                return;
            }
            Tampilkan(PAkun);
        };

        // ---------- Panel: Akun bendahara ----------
        PAkun = BuatPanel();
        AddLabel(PAkun, "Akun Bendahara", 24, 22, 592, 32, 16f, true, warnaJudul);
        AddLabel(PAkun, "Nama bendahara:", 24, 90, 120, 20, 9f, false, warnaTeks);
        TxtNama = AddText(PAkun, Ambil("SIPBOS_NAMA", "Bendahara Sekolah"), 150, 87, 440, false);
        AddLabel(PAkun, "Email untuk login:", 24, 126, 120, 20, 9f, false, warnaTeks);
        TxtEmail = AddText(PAkun, Ambil("SIPBOS_EMAIL", ""), 150, 123, 440, false);
        AddLabel(PAkun, "Kata sandi (min 6):", 24, 162, 120, 20, 9f, false, warnaTeks);
        TxtSandi = AddText(PAkun, Ambil("SIPBOS_SANDI", ""), 150, 159, 220, true);
        AddLabel(PAkun, "Ulangi kata sandi:", 24, 198, 130, 20, 9f, false, warnaTeks);
        TxtSandi2 = AddText(PAkun, TxtSandi.Text, 150, 195, 220, true);
        AddLabel(PAkun, "CATAT email dan kata sandi ini - dipakai untuk login aplikasi setiap hari\r\n(bisa diganti kapan saja lewat menu Profil di aplikasi).", 24, 236, 592, 44, 8.5f, false, warnaAmber);
        Button bKembaliAkun = AddTombol(PAkun, "< Kembali", 340, 395, 90, 30);
        bKembaliAkun.Click += delegate(object s, EventArgs e) { Tampilkan(PData); };
        BtnPasang = AddTombol(PAkun, "Pasang Sekarang", 435, 395, 190, 30);
        BtnPasang.Font = new Font(BtnPasang.Font, FontStyle.Bold);
        BtnPasang.Click += delegate(object s, EventArgs e)
        {
            string nama = TxtNama.Text.Trim();
            if (nama.Length == 0)
            {
                nama = "Bendahara Sekolah";
            }
            string email = TxtEmail.Text.Trim();
            if (email.Length == 0 || email.IndexOf('@') < 1 || email.IndexOf('.') < 0)
            {
                MessageBox.Show(F, "Periksa alamat email login (contoh: nama@sekolah.id).", "Akun Bendahara", MessageBoxButtons.OK, MessageBoxIcon.Information);
                TxtEmail.Focus();
                return;
            }
            if (TxtSandi.Text.Length < 6)
            {
                MessageBox.Show(F, "Kata sandi minimal 6 karakter.", "Akun Bendahara", MessageBoxButtons.OK, MessageBoxIcon.Information);
                TxtSandi.Focus();
                return;
            }
            if (TxtSandi.Text != TxtSandi2.Text)
            {
                MessageBox.Show(F, "Ulangi kata sandi belum sama - periksa kembali.", "Akun Bendahara", MessageBoxButtons.OK, MessageBoxIcon.Information);
                TxtSandi2.Focus();
                return;
            }
            fNpsn = TxtNpsn.Text.Trim();
            fSekolah = TxtSekolah.Text.Trim();
            fNama = nama;
            fEmail = email;
            fSandi = TxtSandi.Text;
            MulaiPasang();
        };

        // ---------- Panel: Progres ----------
        PProses = BuatPanel();
        AddLabel(PProses, "Memasang SiapBOSP...", 24, 22, 592, 32, 16f, true, warnaJudul);
        LblStatus = AddLabel(PProses, "Menyiapkan...", 24, 60, 592, 24, 10f, true, warnaTeks);
        Bar = new ProgressBar();
        Bar.Location = new Point(24, 92);
        Bar.Size = new Size(592, 20);
        Bar.Minimum = 0;
        Bar.Maximum = 100;
        PProses.Controls.Add(Bar);
        TxtLog = new TextBox();
        TxtLog.Location = new Point(24, 124);
        TxtLog.Size = new Size(592, 232);
        TxtLog.Multiline = true;
        TxtLog.ReadOnly = true;
        TxtLog.ScrollBars = ScrollBars.Vertical;
        TxtLog.WordWrap = false;
        TxtLog.BackColor = Color.White;
        TxtLog.Font = new Font("Consolas", 8.25f);
        PProses.Controls.Add(TxtLog);
        LblGalat = AddLabel(PProses, "", 24, 360, 592, 32, 9f, false, warnaRed);
        LblGalat.Visible = false;
        Button bBukaLog1 = AddTombol(PProses, "Buka Log", 24, 395, 110, 30);
        bBukaLog1.Click += delegate(object s, EventArgs e) { BukaLog(); };
        BtnUlangi = AddTombol(PProses, "Coba Lagi", 425, 395, 100, 30);
        BtnUlangi.Visible = false;
        BtnUlangi.Click += delegate(object s, EventArgs e) { MulaiPasang(); };
        BtnTutupGalat = AddTombol(PProses, "Tutup", 530, 395, 95, 30);
        BtnTutupGalat.Visible = false;
        BtnTutupGalat.Click += delegate(object s, EventArgs e) { KodeKeluar = 1; Application.Exit(); };

        // ---------- Panel: Selesai ----------
        PSelesai = BuatPanel();
        AddLabel(PSelesai, "SiapBOSP berhasil terpasang!", 24, 22, 592, 32, 16f, true, Color.FromArgb(21, 128, 61));
        AddLabel(PSelesai, "Lokasi: " + Dest, 24, 66, 592, 20, 9f, false, warnaTeks);
        AddLabel(PSelesai, "Pintasan dibuat di Desktop dan Start Menu (menu SiapBOSP):\r\nbuka aplikasi, Tarik Data ARKAS, Sinkron ke Pusat, dan Hapus.", 24, 92, 592, 44, 9f, false, warnaTeks);
        AddLabel(PSelesai, "Simpan email dan kata sandi login Anda baik-baik.\r\nGanti kata sandi kapan saja lewat menu Profil di aplikasi.", 24, 146, 592, 44, 8.5f, false, warnaAmber);
        ChkBuka = new CheckBox();
        ChkBuka.Text = "Buka SiapBOSP sekarang";
        ChkBuka.Location = new Point(24, 204);
        ChkBuka.Size = new Size(280, 24);
        ChkBuka.Font = new Font("Segoe UI", 9.5f);
        ChkBuka.Checked = !string.Equals(Environment.GetEnvironmentVariable("SIPBOS_BUKA"), "N", StringComparison.OrdinalIgnoreCase);
        PSelesai.Controls.Add(ChkBuka);
        Button bBukaLog2 = AddTombol(PSelesai, "Buka Log", 24, 395, 110, 30);
        bBukaLog2.Click += delegate(object s, EventArgs e) { BukaLog(); };
        BtnSelesai = AddTombol(PSelesai, "Selesai", 530, 395, 95, 30);
        BtnSelesai.Click += delegate(object s, EventArgs e)
        {
            if (ChkBuka.Checked)
            {
                BukaAplikasi();
            }
            Application.Exit();
        };

        Tampilkan(PSelamat);
        return F;
    }

    // ============================================================
    //  LOGIKA WIZARD
    // ============================================================

    private static void Tampilkan(Panel p)
    {
        PSelamat.Visible = p == PSelamat;
        PData.Visible = p == PData;
        PAkun.Visible = p == PAkun;
        PProses.Visible = p == PProses;
        PSelesai.Visible = p == PSelesai;
        if (p == PSelamat)
        {
            F.AcceptButton = BtnMulai;
            BtnMulai.Focus();
        }
        else if (p == PData)
        {
            F.AcceptButton = BtnLanjutData;
            TxtNpsn.Focus();
        }
        else if (p == PAkun)
        {
            F.AcceptButton = BtnPasang;
            if (TxtEmail.Text.Length == 0 && TxtNpsn.Text.Trim().Length > 0)
            {
                TxtEmail.Text = TxtNpsn.Text.Trim() + "@siapbosp.lokal";
            }
            TxtNama.Focus();
        }
        else if (p == PProses)
        {
            F.AcceptButton = null;
        }
        else
        {
            F.AcceptButton = BtnSelesai;
            BtnSelesai.Focus();
        }
    }

    private static void MulaiPasang()
    {
        if (SedangPasang)
        {
            return;
        }
        SedangPasang = true;
        BtnUlangi.Visible = false;
        BtnTutupGalat.Visible = false;
        LblGalat.Visible = false;
        Bar.Style = ProgressBarStyle.Continuous;
        Bar.Maximum = 100;
        Bar.Value = 0;
        TxtLog.Clear();
        LblStatus.Text = "Menyiapkan...";
        Tampilkan(PProses);
        Thread t = new Thread(new ThreadStart(PekerjaanPasang));
        t.IsBackground = true;
        t.Start();
    }

    private static void PekerjaanPasang()
    {
        bool sukses = false;
        int kode = -1;
        try
        {
            Catat("");
            Catat("=== Pemasangan SiapBOSP " + VERSI + " (" + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + ") ===");
            Catat("Tujuan: " + Dest);
            Catat("");

            StatusP("Menyalin berkas aplikasi... (mohon tunggu)");
            int total = EkstrakPaket(delegate(int jumlah, int semua)
            {
                Ui(delegate()
                {
                    if (Bar.Maximum != semua)
                    {
                        Bar.Maximum = semua;
                    }
                    if (jumlah <= Bar.Maximum)
                    {
                        Bar.Value = jumlah;
                    }
                    LblStatus.Text = "Menyalin berkas aplikasi... " + jumlah + "/" + semua;
                });
            });
            Catat("Menyalin selesai: " + total + " berkas.");

            SalinSidecarKeDest();

            StatusP("Menyiapkan database, akun, dan pintasan...");
            Ui(delegate()
            {
                Bar.Style = ProgressBarStyle.Marquee;
            });
            kode = JalankanBat(true);
            sukses = kode == 0;
        }
        catch (Exception ex)
        {
            Catat("[GAGAL] " + ex.GetType().Name + ": " + ex.Message);
            kode = -1;
        }

        SedangPasang = false;
        if (sukses)
        {
            Catat("");
            Catat("Pemasangan selesai tanpa galat.");
            Ui(delegate()
            {
                Bar.Style = ProgressBarStyle.Continuous;
                Bar.Maximum = 100;
                Bar.Value = 100;
                LblStatus.Text = "Selesai.";
                Tampilkan(PSelesai);
            });
        }
        else
        {
            int kk = kode;
            Catat("Pemasangan BELUM selesai (kode " + kode + "). Periksa log, lalu coba lagi.");
            Ui(delegate()
            {
                Bar.Style = ProgressBarStyle.Continuous;
                Bar.Maximum = 100;
                Bar.Value = 0;
                LblStatus.Text = "Pemasangan belum selesai.";
                LblGalat.Text = "Pemasangan belum selesai (kode " + kk + "). Data lama tidak dihapus. Klik \"Buka Log\" untuk melihat penyebabnya, lalu \"Coba Lagi\".";
                LblGalat.Visible = true;
                BtnUlangi.Visible = true;
                BtnTutupGalat.Visible = true;
            });
        }
    }

    private static void F_FormClosing(object s, FormClosingEventArgs e)
    {
        if (SedangPasang)
        {
            e.Cancel = true;
            MessageBox.Show(F, "Pemasangan sedang berjalan - mohon tunggu sampai selesai.", "Pemasang SiapBOSP", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
    }

    private static void BukaLog()
    {
        try
        {
            Process.Start(new ProcessStartInfo("notepad.exe", "\"" + LogPath + "\""));
        }
        catch
        {
        }
    }

    private static void BukaAplikasi()
    {
        try
        {
            ProcessStartInfo pi = new ProcessStartInfo(Path.Combine(Dest, "launcher-windows", "SiapBOSP.bat"));
            pi.UseShellExecute = true;
            pi.WorkingDirectory = Dest;
            Process.Start(pi);
        }
        catch
        {
        }
    }

    private static bool SudahTerpasang(out string versiLama)
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
