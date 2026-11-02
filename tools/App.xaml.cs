// Titik masuk Pemasang SiapBOSP (WPF).
// - Mode GUI   : StartupUri -> MainWindow (wizard 5 halaman).
// - Mode senyap: --senyap / /s / /senyap, env SIPBOS_SENYAP, atau env SIPBOS_DEST tanpa
//                --paksa-gui -> Installer.JalurSenyap() di konsol, lalu Shutdown(kode).
// App.xaml dikompilasi sebagai Page (bukan ApplicationDefinition) agar Main() di bawah
// bisa mengembalikan kode keluar ke sistem operasi (dipakai uji otomatis).
using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Windows;
using Microsoft.Win32;

namespace SiapBOSP.Setup
{
    public partial class App : Application
    {
        /// <summary>Release minimum .NET Framework 4.8 (registri NDP\v4\Full).</summary>
        private const int RELEASE_NET48 = 528040;

#if NDP48_BUNDLED
        /// <summary>Nama sumber tertanam installer offline .NET 4.8 (hanya bila dibundel).</summary>
        private const string NAMA_REDIST = "ndp48-x86-x64-allos-enu.exe";
#endif

        [STAThread]
        public static int Main()
        {
            App app = new App();
            app.InitializeComponent();
            return app.Run();
        }

        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            bool gui = Installer.Siapkan(e.Args);
            if (!gui)
            {
                SiapkanKonsol();
            }

            if (!PastikanNet48(!gui))
            {
                Installer.KodeKeluar = 1;
                Shutdown(1);
                return;
            }

            if (!gui)
            {
                int kode = Installer.JalurSenyap();
                Installer.KodeKeluar = kode;
                Shutdown(kode);
            }
            else
            {
                MainWindow window = new MainWindow();
                MainWindow = window;
                window.Show();
            }
        }

        protected override void OnExit(ExitEventArgs e)
        {
            e.ApplicationExitCode = Installer.KodeKeluar;
            base.OnExit(e);
        }

        // ============================================================
        //  Konsol untuk mode senyap (EXE ini bertipe WinExe)
        // ============================================================

        private const int ATTACH_PARENT_PROCESS = -1;
        private const int STD_OUTPUT_HANDLE = -11;
        private const int FILE_TYPE_UNKNOWN = 0;

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AllocConsole();

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AttachConsole(int dwProcessId);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr GetStdHandle(int nStdHandle);

        [DllImport("kernel32.dll")]
        private static extern int GetFileType(IntPtr hFile);

        /// <summary>
        /// Siapkan tempat keluaran Console:
        /// 1. stdout sudah dialihkan (berkas/pipa/NUL, mis. uji otomatis) -> dibiarkan, perilaku sama persis versi lama;
        /// 2. dijalankan dari cmd/PowerShell -> menumpang konsol induk (AttachConsole);
        /// 3. selain itu -> jendela konsol baru (AllocConsole).
        /// </summary>
        private static void SiapkanKonsol()
        {
            try
            {
                IntPtr h = GetStdHandle(STD_OUTPUT_HANDLE);
                if (h != IntPtr.Zero && h != new IntPtr(-1) && GetFileType(h) != FILE_TYPE_UNKNOWN)
                {
                    return;
                }
                if (AttachConsole(ATTACH_PARENT_PROCESS))
                {
                    Installer.KonsolPinjaman = true;
                    Installer.TulisKonsol(Environment.NewLine);
                    return;
                }
                if (AllocConsole())
                {
                    Installer.KonsolBaru = true;
                    try
                    {
                        Console.Title = "Pemasang SiapBOSP v12 - mode senyap";
                    }
                    catch
                    {
                    }
                }
            }
            catch
            {
            }
        }

        // ============================================================
        //  Prasyarat .NET Framework 4.8 (lihat tools/Cek-Net48.ps1)
        // ============================================================

        private static bool Net48Terpasang()
        {
            try
            {
                using (RegistryKey k = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full"))
                {
                    if (k == null)
                    {
                        return false;
                    }
                    object rilis = k.GetValue("Release");
                    return rilis is int && (int)rilis >= RELEASE_NET48;
                }
            }
            catch
            {
                return false;
            }
        }

        /// <summary>
        /// Pemeriksaan cepat di registri; hanya bila .NET 4.8 belum ada, Cek-Net48.ps1 (tertanam)
        /// dijalankan untuk menawarkan pemasangan (dari redist tertanam bila dibundel). Di
        /// Windows 10/11 pemeriksaan ini lolos seketika tanpa dialog apa pun.
        /// </summary>
        private static bool PastikanNet48(bool senyap)
        {
            if (Net48Terpasang())
            {
                return true;
            }

            string pesan = ".NET Framework 4.8 belum terpasang di laptop ini.";
            int kode = -1;
            try
            {
                string temp = Path.Combine(Path.GetTempPath(), "SiapBOSP-Net48");
                Directory.CreateDirectory(temp);
                string skrip = Path.Combine(temp, "Cek-Net48.ps1");
                if (!SalinSumber("Cek-Net48.ps1", skrip))
                {
                    throw new FileNotFoundException("Cek-Net48.ps1 tidak tertanam di EXE ini.");
                }

                string argumen = "-NoProfile -ExecutionPolicy Bypass -File \"" + skrip + "\"";
#if NDP48_BUNDLED
                string redist = Path.Combine(temp, NAMA_REDIST);
                if (SalinSumber(NAMA_REDIST, redist))
                {
                    argumen += " -Redist \"" + redist + "\"";
                }
#endif
                if (senyap)
                {
                    argumen += " -Senyap";
                }

                ProcessStartInfo pi = new ProcessStartInfo("powershell.exe", argumen);
                pi.UseShellExecute = false;
                pi.CreateNoWindow = true;
                pi.RedirectStandardOutput = senyap;
                using (Process p = Process.Start(pi))
                {
                    if (senyap)
                    {
                        Installer.TulisKonsol(p.StandardOutput.ReadToEnd());
                    }
                    p.WaitForExit();
                    kode = p.ExitCode;
                }
            }
            catch (Exception ex)
            {
                pesan += " (" + ex.Message + ")";
            }

            if (kode == 0)
            {
                return true;
            }

            if (kode == -1)
            {
                // PowerShell/skrip tidak bisa dijalankan sama sekali - beri tahu langsung.
                string teks = pesan + Environment.NewLine + "Unduh dan pasang dari: https://go.microsoft.com/fwlink/?linkid=2088631";
                if (senyap)
                {
                    Installer.TulisKonsol("  [GAGAL] " + teks + Environment.NewLine);
                }
                else
                {
                    MessageBox.Show(teks, "Pemasang SiapBOSP", MessageBoxButton.OK, MessageBoxImage.Warning);
                }
            }
            Installer.CatuLog("[GAGAL] .NET Framework 4.8 belum terpasang - pemasangan SiapBOSP dibatalkan (kode " + kode + ").");
            return false;
        }

        private static bool SalinSumber(string nama, string tujuan)
        {
            Assembly asm = Assembly.GetExecutingAssembly();
            foreach (string n in asm.GetManifestResourceNames())
            {
                if (n.EndsWith(nama, StringComparison.OrdinalIgnoreCase))
                {
                    using (Stream masuk = asm.GetManifestResourceStream(n))
                    using (FileStream keluar = new FileStream(tujuan, FileMode.Create, FileAccess.Write, FileShare.None))
                    {
                        masuk.CopyTo(keluar);
                    }

                    return true;
                }
            }

            return false;
        }
    }
}
