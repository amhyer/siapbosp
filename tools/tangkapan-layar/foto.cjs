#!/usr/bin/env node
/**
 * foto.js — tangkap layar seluruh menu & halaman SiapBOSP menjadi berkas PNG.
 *
 * Dijalankan DI KOMPUTER YANG MENJALANKAN APLIKASI (bukan di server produksi):
 *
 *   1) terminal 1:  php artisan serve            (atau akses alamat aplikasi Anda)
 *   2) terminal 2:  node tools/tangkapan-layar/foto.js
 *
 * Hasil: docs/tangkapan-layar/<peran>/<nn>-<slug>.png + index.html (galeri) +
 * index.md (daftar). Tanpa argumen, semua halaman di halaman.json diambil
 * dengan akun demo (docs/AKUN_DEMO.md).
 *
 * Pilihan:
 *   --base http://127.0.0.1:8000   alamat aplikasi (default itu)
 *   --peran operator,kepsek,dinas  peran yang diambil (default: semua)
 *   --hanya rkas,arsip             hanya halaman yang path/label-nya cocok
 *   --lebar 1440                   lebar viewport (default 1440)
 *   --tunggu 400                   jeda tambahan ms setelah halaman siap
 *   --browser "C:\...\msedge.exe"  paksa peramban tertentu
 *   --keluar docs/tangkapan-layar  folder hasil
 *   --tanpa-penuh                  tangkap hanya viewport (bukan halaman penuh)
 *   --daftar                       cetak rencana halaman lalu keluar (tanpa peramban)
 *
 * Butuh `puppeteer-core` (kecil, tanpa unduh Chrome):
 *   npm install --no-save puppeteer-core
 * Peramban: Edge/Chrome yang sudah ada di komputer dipakai lebih dulu.
 */
'use strict';

const fs = require('fs');
const path = require('path');

// ---------- argumen ----------
const argv = process.argv.slice(2);
const arg = (n, bawaan) => {
  const i = argv.indexOf(n);
  return i > -1 && argv[i + 1] ? argv[i + 1] : bawaan;
};
const ada = (n) => argv.includes(n);

const AKAR = path.resolve(__dirname, '..', '..');
const BASE = arg('--base', 'http://127.0.0.1:8000').replace(/\/+$/, '');
const LEBAR = parseInt(arg('--lebar', '1440'), 10);
const TINGGI = parseInt(arg('--tinggi', '900'), 10);
const JEDA = parseInt(arg('--tunggu', '400'), 10);
const KELUAR = path.resolve(AKAR, arg('--keluar', 'docs/tangkapan-layar'));
const PENUH = !ada('--tanpa-penuh');
const DAFTAR_SAJA = ada('--daftar');
const PERAN = (arg('--peran', 'operator,kepsek,dinas') || '').split(',').map((s) => s.trim()).filter(Boolean);
const HANYA = (arg('--hanya', '') || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
const BROWSER_PAKSA = arg('--browser', process.env.PUPPETEER_EXECUTABLE_PATH || '');

// ---------- rencana halaman ----------
const rencana = JSON.parse(fs.readFileSync(path.join(__dirname, 'halaman.json'), 'utf8'));

function halamanTerpilih() {
  return rencana.halaman.filter((h) => {
    if (h.lewati) return false;
    if (h.tanpaLogin) return false; // login diambil terpisah, sebelum masuk
    if (!PERAN.includes(h.peran)) return false;
    if (!HANYA.length) return true;
    const teks = (h.path + ' ' + h.label + ' ' + h.grup).toLowerCase();
    return HANYA.some((k) => teks.includes(k));
  });
}

/** Path ringkas untuk log. */
function page_ringkas(p) {
  return path.relative(AKAR, p);
}

function slug(teks) {
  return teks
    .toLowerCase()
    .replace(/[→]/g, '-')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60);
}

function daftar() {
  const semua = rencana.halaman.filter((h) => !h.lewati && PERAN.includes(h.peran));
  const dipilih = halamanTerpilih();
  const perGrup = new Map();
  semua.forEach((h) => {
    const kunci = `${h.peran} · ${h.grup}`;
    if (!perGrup.has(kunci)) perGrup.set(kunci, []);
    perGrup.get(kunci).push(h);
  });
  console.log(`Rencana tangkapan layar — ${semua.length} halaman, ${perGrup.size} grup\n`);
  for (const [grup, isi] of perGrup) {
    console.log(`  ${grup} (${isi.length})`);
    isi.forEach((h) => console.log(`      ${h.path.padEnd(46)} ${h.label}`));
  }
  console.log(`\nAkan diambil sekarang: ${dipilih.length} halaman`);
  console.log(`Hasil di: ${path.relative(AKAR, KELUAR)}/<peran>/`);
  if (HANYA.length) console.log(`(disaring dengan --hanya ${HANYA.join(',')})`);
}

/** Cari peramban yang sudah terpasang (Windows/macOS/Linux). */
function cariBrowser() {
  if (BROWSER_PAKSA) return BROWSER_PAKSA;
  const kandidat = process.platform === 'win32'
    ? [
      'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
      'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Google\\Chrome\\Application\\chrome.exe') : '',
    ]
    : process.platform === 'darwin'
      ? [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
      ]
      : ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/microsoft-edge'];
  return kandidat.find((p) => p && fs.existsSync(p)) || '';
}

async function main() {
  if (DAFTAR_SAJA) {
    daftar();
    return;
  }

  let puppeteer;
  try {
    puppeteer = require('puppeteer-core');
  } catch (e) {
    console.error('puppeteer-core belum terpasang. Jalankan lebih dulu:\n\n  npm install --no-save puppeteer-core\n');
    process.exit(2);
  }

  const exe = cariBrowser();
  if (!exe) {
    console.error(
      'Peramban (Edge/Chrome) tidak ditemukan.\n'
      + 'Pasang Microsoft Edge/Google Chrome, atau tunjuk berkasnya:\n'
      + '  node tools/tangkapan-layar/foto.js --browser "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"',
    );
    process.exit(3);
  }

  console.log(`Peramban : ${exe}`);
  console.log(`Alamat   : ${BASE}`);

  const browser = await puppeteer.launch({
    executablePath: exe,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--hide-scrollbars', `--window-size=${LEBAR},${TINGGI}`],
    defaultViewport: { width: LEBAR, height: TINGGI, deviceScaleFactor: 1 },
  });

  const hasil = [];   // {peran, grup, label, path, berkas, status, catatan}
  const galatKonsol = [];

  // Halaman login HARUS diambil dari konteks bersih (tanpa cookie), dan
  // sebelum peran mana pun masuk — kalau tidak, /login dialihkan ke dashboard.
  const halamanLogin = rencana.halaman.find((h) => h.tanpaLogin && !h.lewati);
  if (halamanLogin && PERAN.includes('operator')) {
    const konteksLogin = await browser.createBrowserContext();
    const p = await konteksLogin.newPage();
    const berkas = path.join(KELUAR, 'operator', '00-login.png');
    fs.mkdirSync(path.dirname(berkas), { recursive: true });
    try {
      await p.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
      await p.screenshot({ path: berkas, fullPage: PENUH });
      hasil.push({ peran: 'operator', grup: 'Utama', label: 'Login', path: '/login', berkas: path.relative(AKAR, berkas), status: 'ok', catatan: '' });
      console.log(`[login] ✓ ${page_ringkas(berkas)}`);
    } catch (e) {
      hasil.push({ peran: 'operator', grup: 'Utama', label: 'Login', path: '/login', berkas: '', status: 'gagal', catatan: e.message.slice(0, 200) });
      console.log(`[login] ✗ ${e.message.slice(0, 120)}`);
    }
    await konteksLogin.close();
  }

  const konteks = await browser.createBrowserContext();

  /** Login lewat form; mengembalikan true bila berhasil. */
  async function masuk(page, peran) {
    const akun = rencana.akun[peran];
    if (!akun) throw new Error(`Akun untuk peran "${peran}" tidak ada di halaman.json`);
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.type('#email', akun.email, { delay: 5 });
    await page.type('#password', akun.password, { delay: 5 });
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      page.click('form[action="/login"] button'),
    ]);
    const url = page.url();
    if (url.includes('/login')) {
      throw new Error(`Login gagal untuk ${akun.email} (masih di /login). Periksa kata sandi / jalankan seeder.`);
    }
    console.log(`  login ${peran} → ${url}`);
  }

  /** Ambil id contoh dari halaman sumber untuk halaman yang butuh parameter. */
  async function cariContoh(page, dari, pola) {
    await page.goto(`${BASE}${dari}`, { waitUntil: 'networkidle2', timeout: 30000 });
    const re = new RegExp(pola);
    const tautan = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
    const cocok = tautan.filter((h) => h && re.test(h.replace(/\?.*$/, '')));
    if (!cocok.length) return null;
    return cocok[0].replace(/\?.*$/, '').replace(BASE, '');
  }

  for (const peran of PERAN) {
    const daftarPeran = halamanTerpilih().filter((h) => h.peran === peran);
    if (!daftarPeran.length) continue;

    const page = await konteks.newPage();
    page.on('pageerror', (e) => galatKonsol.push(`${peran}: ${e.message}`));
    page.on('console', (m) => {
      if (m.type() === 'error') galatKonsol.push(`${peran}: ${m.text().slice(0, 200)}`);
    });

    console.log(`\n[${peran}] ${daftarPeran.length} halaman`);
    try {
      await masuk(page, peran);
    } catch (e) {
      console.error(`  GAGAL login: ${e.message}`);
      hasil.push({ peran, grup: '-', label: 'Login', path: '/login', berkas: '', status: 'gagal', catatan: e.message });
      await page.close();
      continue; // lanjut peran berikutnya
    }

    const folder = path.join(KELUAR, peran);
    fs.mkdirSync(folder, { recursive: true });

    let n = 0;
    for (const h of daftarPeran) {
      n += 1;
      const nomor = String(n).padStart(2, '0');
      const nama = `${nomor}-${slug(h.label) || slug(h.path)}.png`;
      const berkas = path.join(folder, nama);
      let url = h.path;
      let status = 'ok';
      let catatan = '';

      try {
        if (h.dari) {
          const contoh = await cariContoh(page, h.dari, h.pola || '.*');
          if (!contoh) {
            status = 'dilewati';
            catatan = `tidak ada contoh data di ${h.dari} (butuh id)`;
            hasil.push({ peran, grup: h.grup, label: h.label, path: h.path, berkas: '', status, catatan });
            console.log(`  ${nomor} ✗ ${h.label} — ${catatan}`);
            continue;
          }
          url = h.path.replace('CONTOH', path.basename(contoh));
        }

        const resp = await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle2', timeout: 45000 });
        const kode = resp ? resp.status() : 0;
        if (kode >= 400) {
          status = 'galat';
          catatan = `HTTP ${kode}`;
        }
        if (JEDA) await new Promise((r) => setTimeout(r, JEDA));
        await page.screenshot({ path: berkas, fullPage: PENUH });
        console.log(`  ${nomor} ${status === 'ok' ? '✓' : '✗'} ${h.label} (${kode})`);
      } catch (e) {
        status = 'gagal';
        catatan = e.message.slice(0, 200);
        console.log(`  ${nomor} ✗ ${h.label} — ${catatan}`);
      }

      hasil.push({ peran, grup: h.grup, label: h.label, path: url, berkas: status === 'gagal' ? '' : path.relative(AKAR, berkas), status, catatan });
    }

    await page.close();
  }

  await browser.close();

  tulisLaporan(hasil, galatKonsol);
}

function tulisLaporan(hasil, galatKonsol) {
  fs.mkdirSync(KELUAR, { recursive: true });
  const waktu = new Date().toISOString().replace('T', ' ').slice(0, 19);

  // index.md
  const baris = [
    '# Tangkapan Layar SiapBOSP',
    '',
    `Dibuat otomatis oleh \`tools/tangkapan-layar/foto.js\` pada ${waktu}.`,
    `Alamat aplikasi: ${BASE} · viewport ${LEBAR}×${TINGGI} · ${PENUH ? 'halaman penuh' : 'viewport saja'}`,
    '',
    `Total: ${hasil.length} halaman · ok ${hasil.filter((h) => h.status === 'ok').length}`,
    `· galat ${hasil.filter((h) => h.status === 'galat').length}`,
    `· gagal ${hasil.filter((h) => h.status === 'gagal').length}`,
    `· dilewati ${hasil.filter((h) => h.status === 'dilewati').length}`,
    '',
    '| # | Peran | Grup | Halaman | Path | Hasil | Berkas |',
    '|---|---|---|---|---|---|---|',
  ];
  hasil.forEach((h, i) => {
    baris.push(`| ${i + 1} | ${h.peran} | ${h.grup} | ${h.label} | \`${h.path}\` | ${h.status}${h.catatan ? ' — ' + h.catatan : ''} | ${h.berkas ? '`' + h.berkas + '`' : '—'} |`);
  });
  if (galatKonsol.length) {
    baris.push('', '## Galat konsol peramban', '');
    galatKonsol.slice(0, 50).forEach((g) => baris.push(`- ${g}`));
  }
  fs.writeFileSync(path.join(KELUAR, 'index.md'), baris.join('\n') + '\n');

  // index.html — galeri sederhana, bisa dibuka langsung dari folder.
  const kartu = hasil.map((h) => {
    const src = h.berkas ? path.relative(KELUAR, path.resolve(AKAR, h.berkas)) : '';
    const gambar = src
      ? `<a href="${src}" target="_blank"><img src="${src}" alt="${h.label}" loading="lazy"></a>`
      : `<div class="kosong">tidak ada gambar<br><small>${h.status}${h.catatan ? ' — ' + h.catatan : ''}</small></div>`;
    return `<figure><figcaption><b>${h.label}</b><br><code>${h.path}</code><br><small>${h.peran} · ${h.grup} · ${h.status}</small></figcaption>${gambar}</figure>`;
  }).join('\n');

  fs.writeFileSync(path.join(KELUAR, 'index.html'), `<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tangkapan Layar SiapBOSP</title>
<style>
 body{font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;background:#f8fafc;color:#0f172a}
 header{padding:20px 24px;background:#0f172a;color:#fff}
 header h1{margin:0 0 4px;font-size:18px} header p{margin:0;opacity:.75;font-size:12.5px}
 main{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;padding:24px}
 figure{margin:0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
 figcaption{padding:10px 12px;font-size:12.5px;border-bottom:1px solid #eef2f7}
 code{font-size:11px;color:#475569} small{color:#64748b}
 img{display:block;width:100%;height:auto}
 .kosong{padding:40px 12px;text-align:center;color:#94a3b8;background:#f1f5f9}
</style></head><body>
<header><h1>Tangkapan Layar SiapBOSP</h1><p>${hasil.length} halaman · dibuat ${waktu} · ${BASE}</p></header>
<main>${kartu}</main></body></html>
`);

  const ringkas = { ok: 0, galat: 0, gagal: 0, dilewati: 0 };
  hasil.forEach((h) => { ringkas[h.status] = (ringkas[h.status] || 0) + 1; });
  console.log(`\nSelesai: ${hasil.length} halaman — ok ${ringkas.ok}, galat ${ringkas.galat}, gagal ${ringkas.gagal}, dilewati ${ringkas.dilewati}`);
  console.log(`Buka galerinya: ${path.join(KELUAR, 'index.html')}`);
  if (galatKonsol.length) console.log(`Ada ${galatKonsol.length} galat konsol — lihat index.md bagian "Galat konsol".`);
}

if (require.main === module) {
  main().catch((e) => {
    console.error('GAGAL:', e && e.stack ? e.stack : e);
    process.exit(1);
  });
} else {
  // Dipakai oleh foto.test.cjs — bagian yang tidak butuh peramban.
  module.exports = { slug, halamanTerpilih, cariBrowser, tulisLaporan, daftar, rencana, KELUAR };
}
