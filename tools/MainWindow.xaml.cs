using System;
using System.ComponentModel;
using System.Threading;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Shapes;

namespace SiapBOSP.Setup
{
    public partial class MainWindow : Window
    {
        private Grid HalamanAktif;

        public MainWindow()
        {
            if (string.IsNullOrEmpty(Installer.Dest))
            {
                Installer.Dest = Installer.TentukanDest();
            }
            if (Installer.LogPath == null)
            {
                try
                {
                    Installer.LogPath = System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SiapBOSP-Install.log");
                }
                catch
                {
                    Installer.LogPath = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "SiapBOSP-Install.log");
                }
            }

            InitializeComponent();

            // Setup installer log callback to UI
            Installer.TeksLogUi = delegate(string teks)
            {
                Dispatcher.BeginInvoke(new Action(delegate()
                {
                    if (TxtLog.Text.Length > 400000)
                    {
                        TxtLog.Clear();
                    }
                    TxtLog.AppendText(teks);
                    TxtLog.ScrollToEnd();
                }));
            };

            InisialisasiData();
            Tampilkan(PageWelcome);
        }

        private void InisialisasiData()
        {
            LblLokasi.Text = "Lokasi pemasangan: " + Installer.Dest;
            LblLokasiSelesai.Text = "Lokasi: " + Installer.Dest;

            string versiLama;
            if (Installer.SudahTerpasang(out versiLama))
            {
                TxtBannerPerbarui.Text = "SiapBOSP sudah terpasang di laptop ini" +
                    (versiLama.Length > 0 ? " (versi " + versiLama + ")" : "") +
                    ". Melanjutkan akan MEMPERBARUI aplikasi - data pembukuan lama tetap aman; akun bendahara disetel ulang sesuai isian di halaman berikut.";
                BannerPerbarui.Visibility = Visibility.Visible;
            }
            else
            {
                BannerPerbarui.Visibility = Visibility.Collapsed;
            }

            if (Installer.ConfSidecar.Count > 0)
            {
                LblConfInfo.Visibility = Visibility.Visible;
            }
            else
            {
                LblConfInfo.Visibility = Visibility.Collapsed;
            }

            TxtNpsn.Text = Installer.Ambil("SIPBOS_NPSN", Installer.ConfGet("NPSN"));
            TxtSekolah.Text = Installer.Ambil("SIPBOS_SEKOLAH", Installer.ConfGet("SEKOLAH"));
            TxtNama.Text = Installer.Ambil("SIPBOS_NAMA", "Bendahara Sekolah");
            TxtEmail.Text = Installer.Ambil("SIPBOS_EMAIL", "");
            string sandiDefault = Installer.Ambil("SIPBOS_SANDI", "");
            TxtSandi.Password = sandiDefault;
            TxtSandi2.Password = sandiDefault;

            ChkBuka.IsChecked = !string.Equals(Environment.GetEnvironmentVariable("SIPBOS_BUKA"), "N", StringComparison.OrdinalIgnoreCase);
        }

        public void Tampilkan(Grid p)
        {
            HalamanAktif = p;
            PageWelcome.Visibility = (p == PageWelcome) ? Visibility.Visible : Visibility.Collapsed;
            PageDataSekolah.Visibility = (p == PageDataSekolah) ? Visibility.Visible : Visibility.Collapsed;
            PageAkun.Visibility = (p == PageAkun) ? Visibility.Visible : Visibility.Collapsed;
            PageProses.Visibility = (p == PageProses) ? Visibility.Visible : Visibility.Collapsed;
            PageSelesai.Visibility = (p == PageSelesai) ? Visibility.Visible : Visibility.Collapsed;

            PerbaruiIndikator();

            if (p == PageWelcome)
            {
                BtnSekunder.Visibility = Visibility.Visible;
                BtnSekunder.Content = "Batal";
                BtnSekunder.IsEnabled = true;

                BtnPrimer.Visibility = Visibility.Visible;
                BtnPrimer.Content = "Mulai >";
                BtnPrimer.IsEnabled = true;
                BtnPrimer.IsDefault = true;
                BtnPrimer.Focus();
            }
            else if (p == PageDataSekolah)
            {
                BtnSekunder.Visibility = Visibility.Visible;
                BtnSekunder.Content = "< Kembali";
                BtnSekunder.IsEnabled = true;

                BtnPrimer.Visibility = Visibility.Visible;
                BtnPrimer.Content = "Lanjut >";
                BtnPrimer.IsEnabled = true;
                BtnPrimer.IsDefault = true;
                TxtNpsn.Focus();
            }
            else if (p == PageAkun)
            {
                if (string.IsNullOrEmpty(TxtEmail.Text) && TxtNpsn.Text.Trim().Length > 0)
                {
                    TxtEmail.Text = TxtNpsn.Text.Trim() + "@siapbosp.lokal";
                }

                BtnSekunder.Visibility = Visibility.Visible;
                BtnSekunder.Content = "< Kembali";
                BtnSekunder.IsEnabled = true;

                BtnPrimer.Visibility = Visibility.Visible;
                BtnPrimer.Content = "Pasang Sekarang";
                BtnPrimer.IsEnabled = true;
                BtnPrimer.IsDefault = true;
                TxtNama.Focus();
            }
            else if (p == PageProses)
            {
                BtnSekunder.Visibility = Visibility.Collapsed;
                BtnPrimer.Visibility = Visibility.Collapsed;
            }
            else if (p == PageSelesai)
            {
                BtnSekunder.Visibility = Visibility.Collapsed;

                BtnPrimer.Visibility = Visibility.Visible;
                BtnPrimer.Content = "Selesai";
                BtnPrimer.IsEnabled = true;
                BtnPrimer.IsDefault = true;
                BtnPrimer.Focus();
            }
        }

        private void PerbaruiIndikator()
        {
            SolidColorBrush aktif = (SolidColorBrush)FindResource("BrushHijau");
            SolidColorBrush nonaktif = (SolidColorBrush)FindResource("BrushAbuNonaktif");

            Titik1.Fill = (HalamanAktif == PageWelcome) ? aktif : nonaktif;
            Titik2.Fill = (HalamanAktif == PageDataSekolah) ? aktif : nonaktif;
            Titik3.Fill = (HalamanAktif == PageAkun) ? aktif : nonaktif;
            Titik4.Fill = (HalamanAktif == PageProses) ? aktif : nonaktif;
            Titik5.Fill = (HalamanAktif == PageSelesai) ? aktif : nonaktif;

            if (HalamanAktif == PageWelcome) LblLangkah.Text = "Langkah 1 dari 5";
            else if (HalamanAktif == PageDataSekolah) LblLangkah.Text = "Langkah 2 dari 5";
            else if (HalamanAktif == PageAkun) LblLangkah.Text = "Langkah 3 dari 5";
            else if (HalamanAktif == PageProses) LblLangkah.Text = "Langkah 4 dari 5";
            else if (HalamanAktif == PageSelesai) LblLangkah.Text = "Langkah 5 dari 5";
        }

        private void BtnPrimer_Click(object sender, RoutedEventArgs e)
        {
            if (HalamanAktif == PageWelcome)
            {
                Tampilkan(PageDataSekolah);
            }
            else if (HalamanAktif == PageDataSekolah)
            {
                LblGalatData.Visibility = Visibility.Collapsed;
                if (TxtNpsn.Text.Trim().Length == 0)
                {
                    LblGalatData.Text = "Isi NPSN sekolah dulu.";
                    LblGalatData.Visibility = Visibility.Visible;
                    MessageBox.Show(this, "Isi NPSN sekolah dulu.", "Data Sekolah", MessageBoxButton.OK, MessageBoxImage.Information);
                    TxtNpsn.Focus();
                    return;
                }
                Tampilkan(PageAkun);
            }
            else if (HalamanAktif == PageAkun)
            {
                LblGalatAkun.Visibility = Visibility.Collapsed;
                string nama = TxtNama.Text.Trim();
                if (nama.Length == 0)
                {
                    nama = "Bendahara Sekolah";
                }
                string email = TxtEmail.Text.Trim();
                if (email.Length == 0 || email.IndexOf('@') < 1 || email.IndexOf('.') < 0)
                {
                    LblGalatAkun.Text = "Periksa alamat email login (contoh: nama@sekolah.id).";
                    LblGalatAkun.Visibility = Visibility.Visible;
                    MessageBox.Show(this, "Periksa alamat email login (contoh: nama@sekolah.id).", "Akun Bendahara", MessageBoxButton.OK, MessageBoxImage.Information);
                    TxtEmail.Focus();
                    return;
                }
                if (TxtSandi.Password.Length < 6)
                {
                    LblGalatAkun.Text = "Kata sandi minimal 6 karakter.";
                    LblGalatAkun.Visibility = Visibility.Visible;
                    MessageBox.Show(this, "Kata sandi minimal 6 karakter.", "Akun Bendahara", MessageBoxButton.OK, MessageBoxImage.Information);
                    TxtSandi.Focus();
                    return;
                }
                if (TxtSandi.Password != TxtSandi2.Password)
                {
                    LblGalatAkun.Text = "Ulangi kata sandi belum sama - periksa kembali.";
                    LblGalatAkun.Visibility = Visibility.Visible;
                    MessageBox.Show(this, "Ulangi kata sandi belum sama - periksa kembali.", "Akun Bendahara", MessageBoxButton.OK, MessageBoxImage.Information);
                    TxtSandi2.Focus();
                    return;
                }

                Installer.fNpsn = TxtNpsn.Text.Trim();
                Installer.fSekolah = TxtSekolah.Text.Trim();
                Installer.fNama = nama;
                Installer.fEmail = email;
                Installer.fSandi = TxtSandi.Password;

                MulaiPasang();
            }
            else if (HalamanAktif == PageProses)
            {
                // Bila gagal dan klik "Coba Lagi"
                MulaiPasang();
            }
            else if (HalamanAktif == PageSelesai)
            {
                if (ChkBuka.IsChecked == true)
                {
                    Installer.BukaAplikasi();
                }
                Installer.KodeKeluar = 0;
                Application.Current.Shutdown(0);
            }
        }

        private void BtnSekunder_Click(object sender, RoutedEventArgs e)
        {
            if (HalamanAktif == PageWelcome)
            {
                Installer.KodeKeluar = 0;
                Application.Current.Shutdown(0);
            }
            else if (HalamanAktif == PageDataSekolah)
            {
                Tampilkan(PageWelcome);
            }
            else if (HalamanAktif == PageAkun)
            {
                Tampilkan(PageDataSekolah);
            }
            else if (HalamanAktif == PageProses)
            {
                // Bila gagal dan klik "Tutup"
                Installer.KodeKeluar = 1;
                Application.Current.Shutdown(1);
            }
        }

        public void MulaiPasang()
        {
            if (Installer.SedangPasang)
            {
                return;
            }
            Installer.SedangPasang = true;
            LblGalat.Visibility = Visibility.Collapsed;
            LblGalat.Text = "";

            Bar.IsIndeterminate = false;
            Bar.Minimum = 0;
            Bar.Maximum = 100;
            Bar.Value = 0;
            LblPersen.Text = "0%";
            TxtLog.Clear();
            LblStatus.Text = "Menyiapkan...";
            LblJudulProses.Text = "Memasang SiapBOSP...";

            Tampilkan(PageProses);

            Thread t = new Thread(new ThreadStart(PekerjaanPasang));
            t.IsBackground = true;
            t.Start();
        }

        public void LaporkanProgress(int jumlah, int semua)
        {
            Dispatcher.BeginInvoke(new Action(delegate()
            {
                if (Bar.Maximum != semua)
                {
                    Bar.Maximum = semua;
                }
                if (jumlah <= Bar.Maximum)
                {
                    Bar.Value = jumlah;
                }
                int persen = (semua > 0) ? (int)((double)jumlah / semua * 100.0) : 0;
                LblPersen.Text = persen + "%";
                LblStatus.Text = "Menyalin berkas aplikasi... " + jumlah + "/" + semua;
            }));
        }

        public void PekerjaanPasang()
        {
            bool sukses = false;
            int kode = -1;
            try
            {
                Installer.Catat("");
                Installer.Catat("=== Pemasangan SiapBOSP " + Installer.VERSI + " (" + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + ") ===");
                Installer.Catat("Tujuan: " + Installer.Dest);
                Installer.Catat("");

                Dispatcher.BeginInvoke(new Action(delegate()
                {
                    LblStatus.Text = "Menyalin berkas aplikasi... (mohon tunggu)";
                }));

                int total = Installer.EkstrakPaket(new Action<int, int>(LaporkanProgress));
                Installer.Catat("Menyalin selesai: " + total + " berkas.");

                Installer.SalinSidecarKeDest();

                Dispatcher.BeginInvoke(new Action(delegate()
                {
                    LblStatus.Text = "Menyiapkan database, akun, dan pintasan...";
                    LblPersen.Text = "100%";
                    Bar.IsIndeterminate = true;
                }));

                kode = Installer.JalankanBat(true);
                sukses = (kode == 0);
            }
            catch (Exception ex)
            {
                Installer.Catat("[GAGAL] " + ex.GetType().Name + ": " + ex.Message);
                kode = -1;
            }

            Installer.SedangPasang = false;
            if (sukses)
            {
                Installer.Catat("");
                Installer.Catat("Pemasangan selesai tanpa galat.");
                Dispatcher.BeginInvoke(new Action(delegate()
                {
                    TampilkanSelesai();
                }));
            }
            else
            {
                Installer.Catat("Pemasangan BELUM selesai (kode " + kode + "). Periksa log, lalu coba lagi.");
                Dispatcher.BeginInvoke(new Action(delegate()
                {
                    TampilkanGalat("Pemasangan belum selesai (kode " + kode + "). Data lama tidak dihapus. Klik \"Buka Log\" untuk melihat penyebabnya, lalu \"Coba Lagi\".");
                }));
            }
        }

        public void TampilkanSelesai()
        {
            Bar.IsIndeterminate = false;
            Bar.Maximum = 100;
            Bar.Value = 100;
            LblPersen.Text = "100%";
            LblStatus.Text = "Selesai.";
            Tampilkan(PageSelesai);
        }

        public void TampilkanGalat(string pesan)
        {
            Bar.IsIndeterminate = false;
            Bar.Maximum = 100;
            Bar.Value = 0;
            LblPersen.Text = "0%";
            LblStatus.Text = "Pemasangan belum selesai.";
            LblJudulProses.Text = "Pemasangan Terhenti";
            LblGalat.Text = pesan;
            LblGalat.Visibility = Visibility.Visible;

            BtnSekunder.Visibility = Visibility.Visible;
            BtnSekunder.Content = "Tutup";
            BtnSekunder.IsEnabled = true;

            BtnPrimer.Visibility = Visibility.Visible;
            BtnPrimer.Content = "Coba Lagi";
            BtnPrimer.IsEnabled = true;
            BtnPrimer.IsDefault = true;
            BtnPrimer.Focus();
        }

        private void BtnBukaLog_Click(object sender, RoutedEventArgs e)
        {
            Installer.BukaLog();
        }

        private void Window_Closing(object sender, CancelEventArgs e)
        {
            if (Installer.SedangPasang)
            {
                e.Cancel = true;
                MessageBox.Show(this, "Pemasangan sedang berjalan - mohon tunggu sampai selesai.", "Pemasang SiapBOSP", MessageBoxButton.OK, MessageBoxImage.Information);
            }
        }
    }
}
