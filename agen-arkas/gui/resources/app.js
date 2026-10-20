const SEP = '\\'; // paket Windows
const B = (args) => 'backend' + SEP + 'node'
  + ' "' + NL_CWD + SEP + 'backend' + SEP + 'tarik.cjs" ' + args;
const jalan = async (args) => {
  const r = await Neutralino.os.execCommand(B(args));
  const baris = r.stdOut.trim().split('\n');
  return JSON.parse(baris[baris.length - 1]);
};
const $ = (id) => document.getElementById(id);

async function init() {
  await Neutralino.init();
  const th = new Date().getFullYear();
  $('tahun').innerHTML = [th - 1, th, th + 1].map((y) => `<option ${y === th ? 'selected' : ''}>${y}</option>`).join('');
  try {
    const info = await jalan('info --json');
    const c = $('statusDb');
    if (info.adaDb) { c.textContent = 'ARKAS NPSN ' + info.npsnFile; c.className = 'chip ok'; }
    else { c.textContent = 'ARKAS tidak ditemukan'; c.className = 'chip err'; }
  } catch (e) { $('statusDb').textContent = 'backend error'; $('statusDb').className = 'chip err'; }
  const cfg = await Neutralino.storage.getData().catch(() => ({}));
  if (cfg.server) $('server').value = cfg.server;
  if (cfg.token) $('token').value = cfg.token;
  $('btnTes').onclick = tes;
  $('btnKirim').onclick = kirim;
  $('btnLagi').onclick = () => { $('hasil').classList.add('sembunyi'); $('panelSetup').classList.remove('sembunyi'); };
}

async function tes() {
  setInfo('Menghubungi server…', '');
  $('btnTes').disabled = true;
  try {
    const h = await jalan(`tes --server "${$('server').value}" --token "${$('token').value}" --json`);
    if (h.ok) {
      setInfo(`✅ ${h.pemilik || ''} (NPSN ${h.npsnToken}) — token cocok dengan ARKAS (${h.npsnFile}).`, 'ok');
      await Neutralino.storage.setData({ server: $('server').value, token: $('token').value });
    } else if (h.pemilik === null && h.server) {
      setInfo('❌ Token salah/tidak dikenal. Periksa kembali token di Profil SiapBOSP.', 'err');
    } else {
      setInfo('❌ ' + (h.pesan || 'Server tak terjangkau.'), 'err');
    }
  } catch (e) { setInfo('❌ Gagal: ' + e.message, 'err'); }
  $('btnTes').disabled = false;
}

async function kirim() {
  if (!$('server').value || !$('token').value) { setInfo('Isi server dan token dulu.', 'err'); return; }
  $('btnKirim').disabled = true;
  setInfo('⏳ Membaca ARKAS dan mengirim… (jangan tutup jendela)', '');
  try {
    const h = await jalan(`kirim --server "${$('server').value}" --token "${$('token').value}" --tahun ${$('tahun').value} --json`);
    if (!h.ok) { setInfo('❌ ' + h.pesan, 'err'); return; }
    await Neutralino.storage.setData({ server: $('server').value, token: $('token').value });
    tampilHasil(h.laporan);
  } catch (e) { setInfo('❌ Gagal: ' + e.message, 'err'); }
  $('btnKirim').disabled = false;
}

function tampilHasil(l) {
  $('panelSetup').classList.add('sembunyi');
  $('hasil').classList.remove('sembunyi');
  const lewati = l.dilewati.transaksi + l.dilewati.rkas + l.dilewati.pajak;
  $('kartuHasil').innerHTML = [
    [l.dibuat.transaksi, 'transaksi baru'], [l.diperbarui.transaksi, 'diperbarui'],
    [l.dibuat.rkas, 'rincian RKAS'], [`${l.dibuat.pajak_terima}/${l.dibuat.pajak_setor}`, 'pajak terima/setor'],
    [lewati, 'baris dilewati'], [l.rekening_tak_dikenal.length, 'kode asing'],
  ].map(([a, b]) => `<div class="stat"><b>${a}</b><span>${b}</span></div>`).join('');
  const c = $('catatan');
  const pesan = [...(l.peringatan || [])];
  if (l.rekening_tak_dikenal.length) pesan.push(l.rekening_tak_dikenal.length + ' kode rekening belum ada di master — lapor ke Dinas.');
  c.textContent = pesan.join(' ');
  c.className = pesan.length ? 'tampil' : '';
}

function setInfo(t, cls) { const e = $('infoPemilik'); e.textContent = t; e.className = 'info ' + cls; }
init();
