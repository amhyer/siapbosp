#!/usr/bin/env node
// CekSumberDana — probe READ-ONLY untuk menjawab satu pertanyaan:
// "apa isi daftar sumber dana di arkas.db laptop bendahara?"
//
// Dipakai sebelum membangun fitur "Sumber Dana Tambahan" per sekolah: kita
// perlu tahu apakah tabel `ref_sumber_dana` benar-benar memuat sumber dana
// TAMBAHAN (bukan hanya 6-8 referensi resmi), dan nama kolomnya apa.
//
// Jaminan keamanan:
//   - database dibuka `readonly: true` (tidak ada INSERT/UPDATE/DELETE);
//   - skrip ini TIDAK menyalin/mengunggah arkas.db ke mana pun;
//   - laporan hanya berisi nama sumber dana + jumlah baris, bukan nominal
//     transaksi, bukan nama orang tua/siswa.
//
// Pakai:
//   node cek-sumber-dana.js                        (cari arkas.db di lokasi standar)
//   node cek-sumber-dana.js --db "C:\salinan\arkas.db"
//   node cek-sumber-dana.js --keluar hasil-sumber-dana.txt --json
//
// Tanpa Node terpasang? Salin berkas ini ke folder `agen\TarikArkas\` di
// aplikasi SiapBOSP portabel, lalu jalankan `node.exe cek-sumber-dana.js`
// (muatDb() otomatis memakai `lib\better-sqlite3-multiple-ciphers` di folder itu).
//
// Hasil: berkas teks (default `hasil-sumber-dana.txt` di folder aktif) yang
// bisa langsung ditempel ke laporan uji / diunggah ke GitHub issue.

const fs = require('fs');
const os = require('os');
const path = require('path');

const arg = (n) => {
  const i = process.argv.indexOf(n);
  return i > -1 ? process.argv[i + 1] : null;
};

// Kunchi SQLCipher ARKAS desktop — sama dengan tarik-arkas.js. Kunci ini
// TIDAK membuka data selain milik sekolah di laptop itu sendiri.
const DB_KEY = 'K3md1kbudRIS3n4yan';
const DB_PATH = arg('--db') || process.env.ARKAS_DB
  || path.join(os.homedir(), 'AppData', 'Roaming', 'Arkas', 'arkas.db');
const KELUAR = arg('--keluar') || path.join(process.cwd(), 'hasil-sumber-dana.txt');
const MAX_BARIS = parseInt(arg('--max') || '200', 10);
const TAHUN = arg('--tahun') ? parseInt(arg('--tahun'), 10) : null;
const JSON_MODE = process.argv.includes('--json');

function muatDb() {
  try {
    return require(path.join(path.dirname(process.execPath), 'lib', 'better-sqlite3-multiple-ciphers'));
  } catch (e) {
    return require('better-sqlite3-multiple-ciphers');
  }
}

const baris = [];
const t = (s = '') => baris.push(s);
const aman = (fn, cadangan) => {
  try {
    return fn();
  } catch (e) {
    return cadangan;
  }
};

/** Nilai sel -> teks satu baris (buang newline agar laporan tetap rapi). */
const sel = (v) => {
  if (v === null || v === undefined) return '';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v).replace(/\r?\n/g, ' ').trim();
};

/** Cetak tabel apa adanya: header + baris, dipisah " | ". */
function dumpTabel(db, nama, hasil) {
  const info = aman(() => db.prepare(`PRAGMA table_info(${nama})`).all(), []);
  const jumlah = aman(() => db.prepare(`SELECT COUNT(*) c FROM ${nama}`).get().c || 0, 0);
  const kolom = info.map((k) => k.name);
  const rows = aman(() => db.prepare(`SELECT * FROM ${nama} LIMIT ${MAX_BARIS}`).all(), []);

  t(`\n### Tabel: ${nama}`);
  t(`Kolom (${kolom.length}): ${kolom.join(', ')}`);
  t(`Jumlah baris: ${jumlah}${jumlah > rows.length ? ` (ditampilkan ${rows.length} pertama)` : ''}`);
  if (rows.length) {
    t(`Header: ${kolom.join(' | ')}`);
    rows.forEach((r) => t(`Data: ${kolom.map((k) => sel(r[k])).join(' | ')}`));
  }

  hasil.tabel[nama] = { kolom, jumlah, contoh: rows };
  return { kolom, jumlah, rows };
}

function jalankan() {
  const mulai = Date.now();
  const hasil = {
    alat: 'cek-sumber-dana',
    waktu: new Date().toISOString(),
    db: DB_PATH,
    ok: false,
    pesan: null,
    arkas: {},
    tabel: {},
    pakai_per_tahun: [],
    dipakai_tanpa_master: [],
    tanya: {},
  };

  t('LAPORAN UJI — Daftar Sumber Dana di ARKAS lokal');
  t('='.repeat(60));
  t(`Dibuat: ${hasil.waktu}`);
  t(`Database: ${DB_PATH}`);

  if (!fs.existsSync(DB_PATH)) {
    throw new Error(
      'Database ARKAS tidak ditemukan di: ' + DB_PATH + '\n'
      + 'Periksa dengan urutan ini:\n'
      + '  1) apakah ARKAS pernah dibuka di laptop ini (bukan hanya dipasang);\n'
      + '  2) jalankan ARKAS sekali, lalu tutup;\n'
      + '  3) kalau tetap tidak ada, cari manual: %AppData%\\Roaming\\Arkas\\arkas.db\n'
      + '     lalu jalankan ulang dengan --db "C:\\path\\arkas.db".');
  }

  const stat = fs.statSync(DB_PATH);
  hasil.ukuran_kb = Math.round(stat.size / 1024);
  hasil.diubah = stat.mtime.toISOString();
  t(`Ukuran: ${hasil.ukuran_kb} KB · terakhir diubah ${hasil.diubah}`);

  const Database = muatDb();
  const db = new Database(DB_PATH, { readonly: true });
  try {
    // Kunci harus dipasang SEBELUM query pertama.
    db.pragma("cipher='sqlcipher'");
    db.pragma('legacy=4');
    db.pragma(`key='${DB_KEY}'`);

    // Bukti database benar-benar terbuka & terbaca.
    const jmlTabel = aman(() => db.prepare("SELECT COUNT(*) c FROM sqlite_master WHERE type='table'").get().c, -1);
    if (jmlTabel < 0) {
      throw new Error('Database tidak bisa dibaca (kunci salah / berkas rusak / ARKAS masih membuka).\n'
        + 'Tutup ARKAS lebih dulu, lalu ulangi. Kalau tetap gagal, salin arkas.db ke folder lain dan pakai --db.');
    }
    t(`Tabel terbaca: ${jmlTabel}`);
    hasil.ok = true;

    // Identitas ARKAS: versi & tahun aktif (untuk laporan).
    const cfg = {};
    aman(() => db.prepare('SELECT varname, varvalue FROM app_config').all().forEach((r) => { cfg[r.varname] = r.varvalue; }), null);
    hasil.arkas = {
      versi: cfg.version_app ? String(cfg.version_app) : null,
      tahun_aktif: cfg.tahun_aktif ? String(cfg.tahun_aktif) : null,
      nama_app: aman(() => (db.prepare('SELECT nama FROM app LIMIT 1').get() || {}).nama || null, null),
      npsn: aman(() => String((db.prepare('SELECT npsn FROM mst_sekolah LIMIT 1').get() || {}).npsn || ''), ''),
    };
    t(`ARKAS: ${hasil.arkas.nama_app || '?'} ${hasil.arkas.versi || '?'} · tahun aktif ${hasil.arkas.tahun_aktif || '?'} · NPSN ${hasil.arkas.npsn || '?'}`);

    // Semua tabel yang namanya mengandung "sumber" — inilah yang dicari.
    // Kalau "Sumber Dana Tambahan" disimpan di tabel lain, ia akan muncul di sini.
    const tabelSumber = aman(() => db.prepare(
      "SELECT name FROM sqlite_master WHERE type='table' AND lower(name) LIKE '%sumber%' ORDER BY name"
    ).all().map((r) => r.name), []);
    t(`\nTabel ber-nama "sumber": ${tabelSumber.length ? tabelSumber.join(', ') : '(tidak ada)'}`);
    hasil.tabel_sumber = tabelSumber;

    tabelSumber.forEach((nama) => dumpTabel(db, nama, hasil));

    // Pemakaian nyata: sumber dana mana yang benar-benar dipakai per tahun.
    const pakai = aman(() => db.prepare(
      'SELECT a.tahun_anggaran AS tahun, a.id_ref_sumber_dana AS id, '
      + '(SELECT nama_sumber_dana FROM ref_sumber_dana s WHERE s.id_ref_sumber_dana = a.id_ref_sumber_dana) AS nama, '
      + 'COUNT(*) AS jml FROM anggaran a GROUP BY a.tahun_anggaran, a.id_ref_sumber_dana '
      + 'ORDER BY a.tahun_anggaran, a.id_ref_sumber_dana'
    ).all(), []);
    hasil.pakai_per_tahun = pakai;
    t('\n### Sumber dana yang dipakai di tabel `anggaran` (per tahun)');
    if (!pakai.length) {
      t('(kosong — tabel anggaran belum berisi, atau nama kolomnya berbeda)');
    } else {
      t('tahun | id_ref_sumber_dana | nama | jumlah baris anggaran');
      pakai
        .filter((r) => !TAHUN || parseInt(r.tahun, 10) === TAHUN)
        .forEach((r) => t(`${sel(r.tahun)} | ${sel(r.id)} | ${sel(r.nama) || '(tanpa nama)'} | ${sel(r.jml)}`));
    }

    // Pemakaian di kas_umum (kalau kolomnya ada) — memastikan sumber dana
    // tambahan juga dipakai pada transaksi, bukan hanya perencanaan.
    const pakaiKas = aman(() => db.prepare(
      'SELECT a.tahun_anggaran AS tahun, a.id_ref_sumber_dana AS id, '
      + '(SELECT nama_sumber_dana FROM ref_sumber_dana s WHERE s.id_ref_sumber_dana = a.id_ref_sumber_dana) AS nama, '
      + 'COUNT(*) AS jml FROM kas_umum k JOIN anggaran a ON a.id_anggaran = k.id_anggaran '
      + 'WHERE COALESCE(k.soft_delete, 0) = 0 GROUP BY a.tahun_anggaran, a.id_ref_sumber_dana'
    ).all(), null);
    if (pakaiKas) {
      hasil.pakai_kas = pakaiKas;
      t('\n### Sumber dana yang dipakai di transaksi `kas_umum` (per tahun)');
      if (!pakaiKas.length) t('(kosong)');
      else {
        t('tahun | id | nama | jumlah transaksi');
        pakaiKas
          .filter((r) => !TAHUN || parseInt(r.tahun, 10) === TAHUN)
          .forEach((r) => t(`${sel(r.tahun)} | ${sel(r.id)} | ${sel(r.nama)} | ${sel(r.jml)}`));
      }
    }

    // Pertanyaan inti: apakah ada sumber dana yang DIPAKAI tetapi TIDAK ADA
    // di tabel master `ref_sumber_dana`? Kalau ada, tab "Sumber Dana Tambahan"
    // tidak bisa hanya membaca ref_sumber_dana.
    const master = aman(() => db.prepare('SELECT id_ref_sumber_dana, nama_sumber_dana FROM ref_sumber_dana').all(), []);
    const namaMaster = new Set(master.map((r) => sel(r.nama_sumber_dana)));
    const idMaster = new Set(master.map((r) => sel(r.id_ref_sumber_dana)));

    // Dua sudut pandang, karena keduanya bisa gagal sendiri-sendiri:
    //   a) nama pemakaian tidak ada di master (nama tak dikenal ARKAS);
    //   b) id pemakaian tidak ada di master (baris master memang tak ada —
    //      gejala khas daftar yang belum pernah diunduh dari pusat).
    const dipakai = new Set(pakai.map((r) => sel(r.nama)).filter((n) => n !== ''));
    const tanpaMaster = [...dipakai].filter((n) => !namaMaster.has(n));
    const idTanpaMaster = [...new Set(pakai.map((r) => sel(r.id)).filter((x) => x !== ''))]
      .filter((x) => !idMaster.has(x));
    hasil.dipakai_tanpa_master = tanpaMaster;
    hasil.id_tanpa_master = idTanpaMaster;

    t('\n### Sumber dana dipakai tetapi TIDAK ada di ref_sumber_dana');
    t(`- berdasarkan NAMA: ${tanpaMaster.length ? tanpaMaster.join(', ') : 'tidak ada'}`);
    t(`- berdasarkan ID:   ${idTanpaMaster.length
      ? idTanpaMaster.map((x) => `id ${x} (${(pakai.find((r) => sel(r.id) === x) || {}).nama || 'tanpa nama'})`).join(', ')
      : 'tidak ada'}`);

    // Daftar pertanyaan yang jawabannya ditentukan laporan ini.
    hasil.tanya = {
      jumlah_master: namaMaster.size,
      ada_tabel_tambahan: tabelSumber.filter((n) => !/^ref_sumber_dana$/i.test(n)),
      master_hanya_standar: namaMaster.size > 0 && namaMaster.size <= 8 && tanpaMaster.length === 0,
      ada_id_tanpa_master: idTanpaMaster.length,
    };
    t('\n### Ringkasan untuk keputusan desain');
    t(`- jumlah baris master ref_sumber_dana: ${namaMaster.size}`);
    t(`- tabel lain ber-nama "sumber": ${hasil.tanya.ada_tabel_tambahan.length ? hasil.tanya.ada_tabel_tambahan.join(', ') : 'tidak ada'}`);
    t(`- sumber dana terpakai tanpa master: ${tanpaMaster.length} nama, ${idTanpaMaster.length} id`);
    t(`- kesimpulan awal: ${hasil.tanya.master_hanya_standar
      ? 'ref_sumber_dana tampak hanya berisi daftar standar; sumber dana tambahan kemungkinan disimpan di tempat lain / diunduh ke tabel berbeda'
      : 'ref_sumber_dana tampak memuat sumber dana non-standar juga'}`);
    t(`\nSelesai dalam ${Date.now() - mulai} ms. Tidak ada data yang diubah (read-only).`);
  } finally {
    try {
      db.close();
    } catch (e) { /* sudah tertutup */ }
  }

  fs.writeFileSync(KELUAR, baris.join('\n') + '\n', 'utf8');
  const keluarJson = KELUAR.replace(/\.txt$/i, '') + '.json';
  fs.writeFileSync(keluarJson, JSON.stringify(hasil, null, 2), 'utf8');

  return { hasil, keluarTxt: KELUAR, keluarJson };
}

try {
  const { hasil, keluarTxt, keluarJson } = jalankan();
  if (JSON_MODE) {
    console.log(JSON.stringify({ ok: hasil.ok, laporan: keluarTxt, json: keluarJson, ringkas: hasil.tanya }, null, 2));
  } else {
    console.log(baris.join('\n'));
    console.log(`\nLaporan ditulis ke:\n  ${keluarTxt}\n  ${keluarJson}`);
    console.log('Bagikan berkas .txt (atau JSON) itu — JANGAN bagikan arkas.db.');
  }
} catch (e) {
  const pesan = e && e.message ? e.message : String(e);
  console.error('GAGAL: ' + pesan);
  try {
    fs.writeFileSync(KELUAR, 'LAPORAN UJI — GAGAL\n' + '='.repeat(60) + '\n' + pesan + '\n', 'utf8');
    console.error('Catatan kegagalan ditulis ke: ' + KELUAR);
  } catch (e2) { /* abaikan */ }
  process.exit(1);
}
