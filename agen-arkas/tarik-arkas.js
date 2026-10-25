#!/usr/bin/env node
// TarikArkas — agen satu-klik SiapBOSP.
// Dibaca: %AppData%\Roaming\Arkas\arkas.db (read-only, tak diubah; bisa
// dialihkan lewat variabel lingkungan ARKAS_DB — berguna untuk uji).
// Dikirim: file Excel ke {server}/api/v1/arkas-push (Bearer token sekolah).
// Pakai (cukup klik 2x — tanpa argumen pun dipandu otomatis):
//        TarikArkas.exe            (setup bila perlu, lalu kirim tahun berjalan)
//        TarikArkas.exe kirim --tahun 2025
//        TarikArkas.exe setup --server https://... --token XXX
//        TarikArkas.exe cek        (tes koneksi, tanpa kirim)
//        TarikArkas.exe lokal --keluar out.xlsx --tahun 2025   (mode JSON:
//        hanya bangun Excel dari ARKAS laptop ini, tanpa server/token —
//        dipakai tombol "Tarik Data ARKAS" di dalam aplikasi SiapBOSP)
const fs = require('fs');
const os = require('os');
const path = require('path');
const readline = require('node:readline/promises');
const ExcelJS = require('exceljs');

// better-sqlite dimuat dinamis: di samping exe (hasil bundel SEA) atau node_modules (mode node).
function muatDb() {
  try {
    return require(path.join(path.dirname(process.execPath), 'lib', 'better-sqlite3-multiple-ciphers'));
  } catch (e) {
    return require('better-sqlite3-multiple-ciphers');
  }
}

// Versi agen — dilaporkan pada `info --json` supaya aplikasi/diagnosa bisa
// membedakan agen lama (tanpa sheet RAPBS_Periode) dari agen terbaru.
const AGEN_VERSI = '1.0.1';
const DB_KEY = 'K3md1kbudRIS3n4yan';
const arg = (n) => { const i = process.argv.indexOf(n); return i > -1 ? process.argv[i + 1] : null; };
const adaFlag = (n) => process.argv.includes(n);
const JSON_MODE = process.argv.includes('--json');
const cetak = (...a) => { if (!JSON_MODE) console.log(...a); };
// Urutan prioritas: argumen --db (dari aplikasi SiapBOSP) > env ARKAS_DB > lokasi standar ARKAS.
const DB_PATH = arg('--db') || process.env.ARKAS_DB
  || path.join(os.homedir(), 'AppData', 'Roaming', 'Arkas', 'arkas.db');
const CFG_DIR = path.join(process.env.APPDATA || path.join(os.homedir(), 'AppData', 'Roaming'), 'SiapBOS-Agen');
const CFG_PATH = path.join(CFG_DIR, 'config.json');

const bacaKonfig = () => (fs.existsSync(CFG_PATH) ? JSON.parse(fs.readFileSync(CFG_PATH, 'utf8')) : {});
const simpanKonfig = (k) => { fs.mkdirSync(CFG_DIR, { recursive: true }); fs.writeFileSync(CFG_PATH, JSON.stringify(k, null, 2)); };

const Q_KAS = (thn) => 'SELECT k.id_kas_umum AS id_kas_umum, k.id_kas_nota AS id_kas_nota, '
  + 'k.id_rapbs_periode AS id_rapbs_periode, k.soft_delete AS soft_delete, '
  + 'k.tanggal_transaksi, k.no_bukti, k.uraian, k.kode_rekening, b.bku AS jenis_bku, b.kode_bku, '
  + 'k.saldo, k.status_bku, k.volume, k.is_ppn, k.is_pph_21, k.is_pph_22, k.is_pph_23, k.is_pph_4, '
  + 'k.is_sspd, k.is_lock, a.tahun_anggaran, '
  + '(SELECT nama_sumber_dana FROM ref_sumber_dana s WHERE s.id_ref_sumber_dana = a.id_ref_sumber_dana) AS sumber_dana, '
  + 'k.id_anggaran FROM kas_umum k LEFT JOIN ref_bku b ON b.id_ref_bku = k.id_ref_bku '
  + 'LEFT JOIN anggaran a ON a.id_anggaran = k.id_anggaran '
  + `WHERE a.tahun_anggaran = ${thn} OR a.tahun_anggaran IS NULL ORDER BY k.tanggal_transaksi LIMIT 20000`;

async function cek(server) {
  const r = await fetch(server.replace(/\/$/, '') + '/api/v1/ping');
  const j = await r.json();
  cetak('Server: ' + (j.app || '?') + ' ' + (j.versi || '') + ' — OK');
}

function bangunExcel(db, tahun) {
  const q = (sql) => db.prepare(sql).all();
  // Tabel yang tidak ada di versi ARKAS tertentu tidak boleh menggagalkan
  // seluruh ekspor (mis. rapbs_periode pada ARKAS lama).
  const qOpsional = (sql) => {
    try { return q(sql); } catch (e) { return []; }
  };
  const wb = new ExcelJS.Workbook();
  wb.creator = 'TarikArkas';
  const isi = (nama, baris) => {
    const s = wb.addWorksheet(nama);
    if (!baris.length) return;
    s.columns = Object.keys(baris[0]).map((h) => ({ header: h, key: h }));
    baris.forEach((r) => s.addRow(r));
  };
  isi('Sekolah', q('SELECT * FROM mst_sekolah'));
  isi('Anggaran', q(`SELECT a.*, (SELECT nama_sumber_dana FROM ref_sumber_dana s WHERE s.id_ref_sumber_dana = a.id_ref_sumber_dana) AS sumber_dana FROM anggaran a WHERE a.tahun_anggaran = ${tahun} LIMIT 5000`));
  isi('RAPBS', q(`SELECT r.*, a.tahun_anggaran, (SELECT nama_sumber_dana FROM ref_sumber_dana s WHERE s.id_ref_sumber_dana = a.id_ref_sumber_dana) AS sumber_dana FROM rapbs r LEFT JOIN anggaran a ON a.id_anggaran = r.id_anggaran WHERE a.tahun_anggaran = ${tahun} ORDER BY r.kode_rekening LIMIT 20000`));
  isi('KAS_Umum', q(Q_KAS(tahun)));
  // WAJIB: ArkasImportService memakai sheet ini untuk memetakan
  // KAS_Umum.id_rapbs_periode → kode kegiatan, dan untuk periode_volume RKAS.
  // Tanpa sheet ini transaksi masuk tanpa kode_kegiatan dan RKAS tanpa
  // periode_volume (laporan per kegiatan/tahap jadi kosong).
  isi('RAPBS_Periode', qOpsional('SELECT * FROM rapbs_periode LIMIT 20000'));
  isi('Nota', q('SELECT * FROM kas_umum_nota LIMIT 10000'));
  isi('Pajak_NTPN', q('SELECT * FROM kas_umum_nota_pajak'));
  // Master kode kegiatan (Ref_Kode) + master rekening (Ref_Rekening): sumber
  // kode kegiatan resmi (05.08.x, 07.12.x, 05.02.x, 06.33.x dst) untuk cek
  // Juknis 8/2026 dan label rekening.
  isi('Ref_Kode', q('SELECT * FROM ref_kode LIMIT 20000'));
  isi('Ref_Rekening', q('SELECT * FROM ref_rekening LIMIT 5000'));
  isi('Ref_Sumber_Dana', q('SELECT * FROM ref_sumber_dana'));
  isi('Ref_Satuan', q('SELECT * FROM ref_satuan LIMIT 5000'));
  isi('PTK', qOpsional('SELECT * FROM ptk WHERE CAST(IFNULL(soft_delete, \'0\') AS TEXT) <> \'1\' LIMIT 20000'));
  return wb;
}

async function tanya(rl, teks, wajib) {
  for (;;) {
    const j = (await rl.question(teks)).trim();
    if (j || !wajib) return j;
    cetak('  (tidak boleh kosong — diisi dulu ya)');
  }
}

async function profilServer(server, token) {
  const r = await fetch(server.replace(/\/$/, '') + '/api/v1/profil', {
    headers: { Authorization: 'Bearer ' + token },
  });
  if (!r.ok) return null;
  return r.json();
}

function bacaNpsnLokal() {
  try {
    if (!fs.existsSync(DB_PATH)) return null;
    const Database = muatDb();
    const db = new Database(DB_PATH, { readonly: true });
    db.pragma("cipher='sqlcipher'"); db.pragma('legacy=4'); db.pragma(`key='${DB_KEY}'`);
    const row = db.prepare('SELECT npsn FROM mst_sekolah LIMIT 1').get();
    db.close();
    return row ? String(row.npsn) : null;
  } catch (e) { return null; }
}

// Deteksi versi aplikasi ARKAS dari `app_config.version_app` (mis. 4.3.0),
// plus data operasional berguna: tahun aktif, tenggat penganggaran/penatausahaan,
// batas pergeseran/perubahan, dan whitelist sumber dana bebas juknis.
function bacaVersiArkas() {
  const hasil = {
    nama: null, version_app: null, tahun_aktif: null, app_config: 0,
    ref_kode: 0, ref_rekening: 0, ptk: 0,
    tenggat_penganggaran: null, tenggat_penatausahaan: null,
    allow_pergeseran: null, allow_perubahan: null, whitelist_juknis: null,
  };
  try {
    if (!fs.existsSync(DB_PATH)) return hasil;
    const Database = muatDb();
    const db = new Database(DB_PATH, { readonly: true });
    db.pragma("cipher='sqlcipher'"); db.pragma('legacy=4'); db.pragma(`key='${DB_KEY}'`);
    try {
      const a = db.prepare('SELECT nama FROM app LIMIT 1').get();
      if (a && a.nama) hasil.nama = String(a.nama);
    } catch (e) { /* tabel app tidak ada */ }
    const hitung = (sql) => { try { return db.prepare(sql).get().c || 0; } catch (e) { return 0; } };
    hasil.app_config = hitung('SELECT COUNT(*) c FROM app_config');
    hasil.ref_kode = hitung('SELECT COUNT(*) c FROM ref_kode');
    hasil.ref_rekening = hitung('SELECT COUNT(*) c FROM ref_rekening');
    hasil.ptk = hitung('SELECT COUNT(*) c FROM ptk');
    const cfg = {};
    try {
      db.prepare('SELECT varname, varvalue FROM app_config').all().forEach((r) => { cfg[r.varname] = r.varvalue; });
    } catch (e) { /* tidak ada app_config */ }
    if (cfg.version_app) hasil.version_app = String(cfg.version_app);
    if (cfg.tahun_aktif) hasil.tahun_aktif = String(cfg.tahun_aktif);
    // Tenggat: ambil hanya untuk tahun aktif & sumber dana BOS Reguler (id 1).
    const tenggat = (raw, kunci) => {
      try {
        const list = JSON.parse(raw || '[]');
        const th = parseInt(cfg.tahun_aktif || '0', 10);
        const cocok = list.filter((x) => parseInt(x.tahun, 10) === th && parseInt(x.id_ref_sumber_dana, 10) === 1);
        return cocok.length ? String(cocok[0][kunci] || '') : null;
      } catch (e) { return null; }
    };
    hasil.tenggat_penganggaran = tenggat(cfg.tenggat_penganggaran, 'tenggat_penganggaran');
    hasil.tenggat_penatausahaan = tenggat(cfg.tenggat_penatausahaan, 'tenggat_penatausahaan');
    if (cfg.count_allow_pergeseran) hasil.allow_pergeseran = String(cfg.count_allow_pergeseran);
    if (cfg.count_allow_perubahan) {
      try {
        hasil.allow_perubahan = String((JSON.parse(cfg.count_allow_perubahan))[cfg.tahun_aktif] ?? '');
      } catch (e) { hasil.allow_perubahan = String(cfg.count_allow_perubahan); }
    }
    if (cfg.whitelist_juknis) {
      try {
        const wl = JSON.parse(cfg.whitelist_juknis).find((x) => String(x.tahun) === String(cfg.tahun_aktif));
        hasil.whitelist_juknis = wl ? JSON.stringify(wl.sumber_dana_id) : null;
      } catch (e) { hasil.whitelist_juknis = null; }
    }
    db.close();
  } catch (e) { /* db tidak bisa dibuka */ }
  return hasil;
}

// Setup interaktif: tanpa argumen pun bisa — tinggal jawab pertanyaan.
async function setupInteraktif(rl, serverArg, tokenArg) {
  cetak('SELAMAT DATANG di TarikArkas — pengiriman data ARKAS ke SiapBOSP.');
  cetak('Cukup jawab pertanyaan berikut (cukup SEKALI, tersimpan otomatis).\n');
  let server = serverArg || '';
  let token = tokenArg || '';
  if (!server) {
    cetak('1) Alamat server SiapBOSP (tanya Dinas bila belum tahu).');
    cetak('   Contoh: https://siapbosp.dinas.go.id  atau  http://192.168.1.10:18081');
    server = await tanya(rl, '   Server: ', true);
  }
  if (!token) {
    cetak('\n2) Token sekolah (dari SiapBOSP → Utility → Profil → Token API).');
    token = await tanya(rl, '   Token: ', true);
  }
  server = server.replace(/\/$/, '');
  if (!/^https?:\/\//i.test(server)) server = 'https://' + server;
  await cek(server);

  // Verifikasi silang: pemilik token vs NPSN ARKAS laptop ini.
  const prof = await profilServer(server, token);
  const npsnToken = prof && prof.sekolah ? String(prof.sekolah.npsn || '') : '';
  const npsnFile = bacaNpsnLokal();
  if (prof) cetak('Token milik: ' + (prof.pengguna ? prof.pengguna.nama : '?') + (npsnToken ? ' (NPSN ' + npsnToken + ')' : ''));
  if (npsnFile) cetak('ARKAS laptop ini: NPSN ' + npsnFile);
  if (npsnToken && npsnFile && npsnToken !== npsnFile) {
    cetak('\nPERINGATAN: token milik NPSN ' + npsnToken + ' tetapi ARKAS ini NPSN ' + npsnFile + '.');
    cetak('Pakai token operator dari sekolah NPSN ' + npsnFile + ' agar tidak tertolak.');
    const lanjut = await tanya(rl, 'Tetap simpan dan lanjut? (y/n): ', false);
    if (lanjut.toLowerCase() !== 'y' && lanjut.toLowerCase() !== 'ya') {
      throw new Error('Dibatalkan. Ulangi setup dengan token yang benar.');
    }
  }
  simpanKonfig({ server, token, npsn: npsnFile || npsnToken || null });
  cetak('Konfigurasi tersimpan. Mulai sekarang klik 2x saja untuk mengirim.\n');
  return { server, token };
}

// Mode lokal (dipakai tombol "Tarik Data ARKAS" di dalam aplikasi SiapBOSP
// portabel): bangun Excel ARKAS ke berkas — tanpa server, tanpa token.
// Impor ke SiapBOSP dilakukan aplikasi sendiri setelah berkas jadi.
async function bangunLokal(keluar, tahun) {
  if (!fs.existsSync(DB_PATH)) throw new Error('Database ARKAS tidak ditemukan: ' + DB_PATH + '\nPastikan ARKAS sudah pernah dibuka di laptop ini (dan tutup ARKAS bila database terkunci).');
  const Database = muatDb();
  const db = new Database(DB_PATH, { readonly: true });
  try {
    db.pragma("cipher='sqlcipher'"); db.pragma('legacy=4'); db.pragma(`key='${DB_KEY}'`);
    const npsn = db.prepare('SELECT npsn FROM mst_sekolah LIMIT 1').get();
    const wb = bangunExcel(db, tahun);
    const buf = await wb.xlsx.writeBuffer();
    fs.writeFileSync(keluar, buf);
    return { ok: true, agen: AGEN_VERSI, npsn: npsn ? String(npsn.npsn) : null, tahun, berkas: keluar, kb: Math.round(buf.byteLength / 1024), versi: bacaVersiArkas() };
  } finally {
    try { db.close(); } catch (_) {}
  }
}

async function kirim(server, token, tahun, npsnHarapan) {
  if (!fs.existsSync(DB_PATH)) throw new Error('Database ARKAS tidak ditemukan: ' + DB_PATH + '\nPastikan ARKAS sudah pernah dibuka di laptop ini (dan tutup ARKAS bila database terkunci).');
  const Database = muatDb();
  const db = new Database(DB_PATH, { readonly: true });
  db.pragma("cipher='sqlcipher'"); db.pragma('legacy=4'); db.pragma(`key='${DB_KEY}'`);
  const npsn = db.prepare('SELECT npsn FROM mst_sekolah LIMIT 1').get();
  cetak('Sekolah: ' + (npsn ? npsn.npsn : '?') + ' · Tahun: ' + tahun);
  if (npsnHarapan && npsn && String(npsn.npsn) !== String(npsnHarapan)) {
    db.close();
    throw new Error('NPSN ARKAS laptop ini (' + npsn.npsn + ') beda dengan saat setup (' + npsnHarapan + ').\n'
      + 'Jalankan "setup" ulang dengan token sekolah NPSN ' + npsn.npsn + '.');
  }
  const wb = bangunExcel(db, tahun);
  db.close();
  const buf = await wb.xlsx.writeBuffer();
  const form = new FormData();
  form.append('tahun', String(tahun));
  form.append('berkas', new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), `arkas-${tahun}.xlsx`);
  cetak('Mengirim ' + (buf.byteLength / 1024).toFixed(0) + ' KB ke ' + server + ' ...');
  const baseUrl = server.replace(/\/$/, '');
  const headers = { Authorization: 'Bearer ' + token };
  const r = await fetch(baseUrl + '/api/v1/arkas-push', { method: 'POST', headers, body: form });
  let j = await r.json();
  if (!r.ok || !j.ok) throw new Error('Ditolak: ' + (j.pesan || r.status));
  if (r.status === 202 || j.status === 'queued' || j.status === 'processing') {
    const batasWaktu = Date.now() + 25 * 60 * 1000;
    cetak('Berkas diterima. Menunggu impor selesai...');
    while (Date.now() < batasWaktu) {
      await new Promise((resolve) => setTimeout(resolve, (j.polling_interval_seconds || 3) * 1000));
      const statusResponse = await fetch(baseUrl + '/api/v1/arkas-import/' + encodeURIComponent(j.import_id), { headers });
      j = await statusResponse.json();
      if (j.status === 'completed' && j.ok) break;
      if (j.status === 'failed' || !statusResponse.ok && statusResponse.status !== 202) {
        throw new Error('Impor gagal: ' + (j.pesan || statusResponse.status));
      }
      if (j.status === 'queued' || j.status === 'processing') cetak('Status impor: ' + j.status + '...');
    }
    if (j.status !== 'completed' || !j.ok) {
      throw new Error('Impor masih berjalan di server. ID pekerjaan: ' + j.import_id + '. Periksa status API sebelum mengirim ulang.');
    }
  }
  const l = j.laporan;
  cetak('SELESAI — transaksi +' + l.dibuat.transaksi + '/~' + l.diperbarui.transaksi
    + ', RKAS +' + l.dibuat.rkas + ', terima pajak +' + l.dibuat.pajak_terima + ', setor +' + l.dibuat.pajak_setor
    + ', dilewati ' + (l.dilewati.transaksi + l.dilewati.rkas + l.dilewati.pajak));
  if (l.rekening_tak_dikenal.length) cetak('Kode asing (' + l.rekening_tak_dikenal.length + '): ' + l.rekening_tak_dikenal.slice(0, 10).join(', '));
  if (l.peringatan.length) l.peringatan.forEach((w) => cetak('PERINGATAN: ' + w));
  return l;
}

async function tesKoneksi(server, token) {
  const r = await fetch(server.replace(/\/$/, '') + '/api/v1/ping');
  const ping = await r.json();
  const prof = await profilServer(server, token);
  const npsnFile = bacaNpsnLokal();
  const npsnToken = prof && prof.sekolah ? String(prof.sekolah.npsn || '') : '';
  return {
    ok: !!prof, server: ping.app || '?',
    pemilik: prof && prof.pengguna ? prof.pengguna.nama : null,
    npsnToken, npsnFile,
    cocok: !!(npsnToken && npsnFile && npsnToken === npsnFile),
  };
}

(async () => {
  const butuhFlag = (n) => {
    const v = arg(n);
    if (!v) throw new Error('Butuh argumen ' + n + ' dalam mode GUI.');
    return v;
  };
  // Mode GUI (--json): tanpa prompt, tanpa config; semua via argumen.
  if (JSON_MODE) {
    try {
      const cmd = process.argv[2] || 'kirim';
      if (cmd === 'info') {
        console.log(JSON.stringify({
          ok: true, adaDb: fs.existsSync(DB_PATH), npsnFile: bacaNpsnLokal(),
          agen: AGEN_VERSI, versi: bacaVersiArkas(),
        }));
      } else if (cmd === 'tes') {
        console.log(JSON.stringify(await tesKoneksi(butuhFlag('--server'), butuhFlag('--token'))));
      } else if (cmd === 'lokal') {
        console.log(JSON.stringify(await bangunLokal(butuhFlag('--keluar'), parseInt(arg('--tahun') || new Date().getFullYear(), 10))));
      } else {
        const tahun = parseInt(arg('--tahun') || new Date().getFullYear(), 10);
        const lap = await kirim(butuhFlag('--server'), butuhFlag('--token'), tahun, arg('--npsn'));
        console.log(JSON.stringify({ ok: true, laporan: lap }));
      }
    } catch (e) { console.log(JSON.stringify({ ok: false, pesan: e.message })); process.exit(1); }
    return;
  }
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  try {
    const cmd = process.argv[2] || 'kirim';
    if (cmd === 'setup') {
      const k = await setupInteraktif(rl, arg('--server'), arg('--token'));
      rl.close();
      // Langsung kirim sekalian setelah setup berhasil.
      const tahun = parseInt(arg('--tahun') || new Date().getFullYear(), 10);
      await kirim(k.server, k.token, tahun, bacaKonfig().npsn);
      return;
    }
    if (cmd === 'cek') {
      let k = bacaKonfig();
      if (!k.server) {
        await setupInteraktif(rl, null, null);
        k = bacaKonfig();
      }
      await cek(k.server);
      rl.close();
      return;
    }
    let k = bacaKonfig();
    if (!k.server || !k.token) {
      k = await setupInteraktif(rl, null, null);
      k = bacaKonfig();
    }
    rl.close();
    const tahun = parseInt(arg('--tahun') || new Date().getFullYear(), 10);
    await kirim(k.server, k.token, tahun, k.npsn);
  } catch (e) { console.error('GAGAL: ' + e.message); try { rl.close(); } catch (_) {} process.exit(1); }
})();
