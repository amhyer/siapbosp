{{--
  Interaksi umum tanpa handler atribut inline (audit R-03).

  Semua perilaku di bawah dulu ditulis sebagai atribut `onsubmit`/`onclick`/
  `onchange` pada markup. Handler atribut tidak bisa diotorisasi nonce/hash,
  jadi atribut itu dipindah ke `data-*` dan ditangani sekali di sini memakai
  `addEventListener` (delegasi event ke document).

  Kontrak atribut:
    data-konfirmasi="pesan"   form/tautan/tombol -> window.confirm sebelum aksi
    data-kirim-otomatis       select/input        -> form.submit() saat berubah
    data-cetak-halaman        tombol              -> window.print()
    data-tombol-sibuk="teks"  form                -> tombol submit dinonaktifkan

  Partial ini dimuat oleh layouts.app dan layouts.print supaya halaman cetak
  (yang juga punya tombol cetak) berperilaku sama.
--}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  (function () {
    'use strict';

    function setuju(el) {
      var pesan = el.getAttribute('data-konfirmasi');
      return !pesan || window.confirm(pesan);
    }

    // Konfirmasi tingkat form: dipakai form ubah/hapus data.
    document.addEventListener('submit', function (ev) {
      var form = ev.target;
      if (!form || form.tagName !== 'FORM') { return; }
      if (!setuju(form)) { ev.preventDefault(); return; }

      var teksSibuk = form.getAttribute('data-tombol-sibuk');
      if (!teksSibuk) { return; }
      var tombol = form.querySelector('button[type="submit"], button:not([type])');
      if (tombol) {
        tombol.disabled = true;
        tombol.textContent = teksSibuk;
      }
    });

    // Konfirmasi tingkat tautan/tombol (mis. tautan unduh backup, tombol kirim).
    document.addEventListener('click', function (ev) {
      var el = ev.target && ev.target.closest ? ev.target.closest('[data-konfirmasi]') : null;
      if (!el || el.tagName === 'FORM') { return; } // form ditangani event submit
      if (!setuju(el)) { ev.preventDefault(); }
    });

    // Filter yang langsung memuat ulang halaman saat nilainya berubah.
    document.addEventListener('change', function (ev) {
      var el = ev.target;
      if (!el || !el.hasAttribute || !el.hasAttribute('data-kirim-otomatis')) { return; }
      if (el.form) { el.form.submit(); }
    });

    // Tombol cetak pada toolbar halaman dokumen.
    document.addEventListener('click', function (ev) {
      var el = ev.target && ev.target.closest ? ev.target.closest('[data-cetak-halaman]') : null;
      if (!el) { return; }
      ev.preventDefault();
      window.print();
    });

    // Buka/tutup dialog modal (mis. Data Penanggung Jawab) tanpa atribut onclick.
    document.addEventListener('click', function (ev) {
      var buka = ev.target && ev.target.closest ? ev.target.closest('[data-modal-buka]') : null;
      if (buka) {
        var modal = document.getElementById(buka.getAttribute('data-modal-buka'));
        if (modal) {
          ev.preventDefault();
          modal.classList.remove('hidden');
          var det = buka.closest('details');
          if (det) { det.removeAttribute('open'); }
        }
        return;
      }
      var tutup = ev.target && ev.target.closest ? ev.target.closest('[data-modal-tutup]') : null;
      if (tutup) {
        var target = document.getElementById(tutup.getAttribute('data-modal-tutup'));
        if (target) { target.classList.add('hidden'); }
      }
    });
  })();
</script>
