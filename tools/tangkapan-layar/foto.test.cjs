'use strict';
/**
 * Uji bagian foto.cjs yang TIDAK membutuhkan peramban:
 * rencana halaman, penyaringan --hanya, slug nama berkas, deteksi peramban,
 * dan pembuatan laporan (index.md + index.html).
 *
 *   node tools/tangkapan-layar/foto.test.cjs
 *
 * Tangkapan layar sesungguhnya tidak diuji di sini (butuh peramban + aplikasi
 * yang berjalan); itu diuji manual di komputer operator.
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('assert');

// Arahkan keluaran uji ke folder sementara SEBELUM modul dimuat.
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'uji-foto-'));
process.argv.push('--keluar', TMP);

const foto = require('./foto.cjs');

let lulus = 0;
const uji = (nama, fn) => {
  try {
    fn();
    lulus += 1;
    console.log(`  ok  ${nama}`);
  } catch (e) {
    console.log(`  GAGAL  ${nama}\n        ${e.message}`);
    process.exitCode = 1;
  }
};

console.log('foto.cjs — bagian tanpa peramban');

uji('rencana halaman terbaca dari halaman.json', () => {
  assert.ok(Array.isArray(foto.rencana.halaman), 'halaman harus array');
  assert.ok(foto.rencana.halaman.length >= 80, `jumlah halaman ${foto.rencana.halaman.length}`);
});

uji('setiap entri punya peran, grup, label, dan path', () => {
  for (const h of foto.rencana.halaman) {
    for (const k of ['peran', 'grup', 'label', 'path']) {
      assert.ok(typeof h[k] === 'string' && h[k].length, `entri tanpa ${k}: ${JSON.stringify(h)}`);
    }
    assert.ok(h.path.startsWith('/'), `path harus diawali "/": ${h.path}`);
    assert.ok(['operator', 'kepsek', 'dinas'].includes(h.peran), `peran tak dikenal: ${h.peran}`);
  }
});

uji('tidak ada path ganda dalam satu peran', () => {
  const terlihat = new Set();
  for (const h of foto.rencana.halaman) {
    if (h.tanpaLogin) continue; // /login diambil terpisah
    const kunci = `${h.peran}:${h.path}`;
    assert.ok(!terlihat.has(kunci), `path ganda: ${kunci}`);
    terlihat.add(kunci);
  }
});

uji('setiap peran punya akun demo', () => {
  for (const peran of ['operator', 'kepsek', 'dinas']) {
    const a = foto.rencana.akun[peran];
    assert.ok(a && a.email && a.password, `akun ${peran} tidak lengkap`);
    assert.ok(a.email.includes('@'), `email ${peran} tidak valid`);
  }
});

uji('halaman login ditandai tanpaLogin dan tidak ikut daftar utama', () => {
  const login = foto.rencana.halaman.filter((h) => h.path === '/login');
  assert.strictEqual(login.length, 1);
  assert.strictEqual(login[0].tanpaLogin, true);
  assert.ok(!foto.halamanTerpilih().some((h) => h.path === '/login'));
});

uji('halaman yang butuh id punya "dari" + "pola" dan memakai penanda CONTOH', () => {
  const dinamis = foto.rencana.halaman.filter((h) => h.dari);
  assert.ok(dinamis.length >= 4, `hanya ${dinamis.length} halaman dinamis`);
  for (const h of dinamis) {
    assert.ok(h.path.includes('CONTOH'), `${h.label}: path harus memuat CONTOH`);
    assert.ok(typeof h.pola === 'string' && h.pola.length, `${h.label}: pola kosong`);
    assert.doesNotThrow(() => new RegExp(h.pola), `${h.label}: pola bukan regex valid`);
  }
});

uji('slug menghasilkan nama berkas aman', () => {
  assert.strictEqual(foto.slug('Kertas Kerja (RKAS)'), 'kertas-kerja-rkas');
  assert.strictEqual(foto.slug('PBD → RKM → RKT (Perencanaan)'), 'pbd-rkm-rkt-perencanaan');
  assert.strictEqual(foto.slug('LPJ & Tutup Buku per Tahap'), 'lpj-tutup-buku-per-tahap');
  assert.ok(!/[^a-z0-9-]/.test(foto.slug('Realisasi Penerimaan & Belanja (ARKAS)')), 'ada karakter tidak aman');
  assert.ok(foto.slug('A'.repeat(200)).length <= 60, 'slug tidak dipotong');
});

uji('cariBrowser tidak melempar walau peramban tidak ada', () => {
  // Di CI/sandbox hasilnya string kosong; di komputer operator berisi path.
  const hasil = foto.cariBrowser();
  assert.strictEqual(typeof hasil, 'string');
});

uji('laporan index.md + index.html terbentuk dan memuat status', () => {
  const contoh = [
    { peran: 'operator', grup: 'Penatausahaan', label: 'Buku Kas Umum (BKU)', path: '/pembukuan/bku', berkas: 'docs/tangkapan-layar/operator/01-buku-kas-umum-bku.png', status: 'ok', catatan: '' },
    { peran: 'operator', grup: 'Arsip', label: 'Arsip Dokumen Sah', path: '/arsip', berkas: '', status: 'galat', catatan: 'HTTP 500' },
    { peran: 'dinas', grup: 'Panel Dinas', label: 'Sekolah — Detail', path: '/dinas/sekolah/CONTOH', berkas: '', status: 'dilewati', catatan: 'tidak ada contoh data' },
  ];
  foto.tulisLaporan(contoh, ['operator: TypeError: x is undefined']);

  const md = fs.readFileSync(path.join(TMP, 'index.md'), 'utf8');
  assert.ok(md.includes('Tangkapan Layar SiapBOSP'), 'index.md tanpa judul');
  assert.ok(md.includes('/pembukuan/bku'), 'index.md tanpa path');
  assert.ok(md.includes('ok 1'), 'ringkasan ok salah');
  assert.ok(md.includes('HTTP 500'), 'catatan galat hilang');
  assert.ok(md.includes('Galat konsol peramban'), 'galat konsol tidak dilaporkan');

  const html = fs.readFileSync(path.join(TMP, 'index.html'), 'utf8');
  assert.ok(html.includes('<!doctype html>'), 'index.html bukan HTML');
  assert.ok(html.includes('01-buku-kas-umum-bku.png'), 'gambar tidak ditautkan');
  assert.ok(html.includes('tidak ada gambar'), 'halaman tanpa gambar tidak ditandai');
  assert.ok(html.includes('3 halaman'), 'jumlah halaman di header salah');
});

uji('--hanya menyaring berdasarkan path/label', () => {
  const semua = foto.halamanTerpilih().length;
  process.argv.push('--hanya', 'arsip');
  delete require.cache[require.resolve('./foto.cjs')];
  const ulang = require('./foto.cjs');
  const tersaring = ulang.halamanTerpilih();
  assert.ok(tersaring.length > 0 && tersaring.length < semua, `penyaringan tidak bekerja (${tersaring.length}/${semua})`);
  assert.ok(tersaring.every((h) => (h.path + h.label + h.grup).toLowerCase().includes('arsip')), 'ada hasil di luar filter');
});

fs.rmSync(TMP, { recursive: true, force: true });
console.log(`\n${lulus} uji lulus${process.exitCode ? ' (ada yang gagal)' : ''}`);
