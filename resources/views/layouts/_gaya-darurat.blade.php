{{--
  Gaya darurat bila aset Vite (public/build) belum dibangun — dipakai oleh
  layouts/app dan layouts/guest pada cabang fallback.

  Sebelumnya hanya layouts/app yang punya blok gaya ringkas (dan halaman
  login sama sekali tidak punya), sehingga kelas komponen dari
  resources/css/app.css (.lbl, .btn-sm, .navw, .alert, .toolbar, .bdan/.bdel,
  dst.) hilang dan tampilan rusak. Blok ini menulis ulang SELURUH kelas
  komponen app.css sebagai CSS biasa (tanpa @apply, warna literal) supaya
  fallback tampil setara dengan aset hasil build. Utility Tailwind tetap
  dihasilkan oleh /tailwind.js (build browser).

  Jangan menambah aturan baru di sini tanpa memperbarui
  resources/css/app.css; tests/Feature/CssHalamanTest.php memeriksa kedua
  berkas tetap sepadan.
--}}
<style>
  /* ===== Dasar (app.css @layer base) ===== */
  body {
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    -webkit-font-smoothing: antialiased;
  }
  ::selection { background: #c7d2fe; color: #312e81; }

  /* ===== Form ===== */
  .inp {
    box-sizing: border-box; width: 100%; border-radius: .5rem; border: 1px solid #cbd5e1;
    background: #fff; padding: .5rem .75rem; font-size: .875rem; color: #1e293b;
    box-shadow: 0 1px 2px 0 rgb(15 23 42 / .05); transition: border-color .15s, box-shadow .15s;
  }
  .inp::placeholder { color: #94a3b8; }
  .inp:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px #c7d2fe; }
  .inp-sm { padding: .375rem .625rem; font-size: 13px; }
  select.inp { padding-right: 2rem; }
  label.lbl { display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #475569; }

  /* ===== Tombol ===== */
  .btn, .btn2, .btn-danger {
    display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
    border-radius: .5rem; border: 1px solid transparent; padding: .5rem 1rem;
    font-size: 13px; font-weight: 600; line-height: 1.25; text-decoration: none; cursor: pointer;
    box-shadow: 0 1px 2px 0 rgb(15 23 42 / .05);
    transition: background-color .15s, border-color .15s, color .15s;
  }
  .btn { background: #4f46e5; color: #fff; }
  .btn:hover { background: #4338ca; }
  .btn:active { background: #3730a3; }
  .btn:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 1px; }
  .btn:disabled { cursor: not-allowed; opacity: .6; }
  .btn2 { background: #fff; border-color: #cbd5e1; color: #475569; }
  .btn2:hover { border-color: #94a3b8; background: #f8fafc; color: #1e293b; }
  .btn2:focus-visible { outline: 2px solid #e2e8f0; outline-offset: 1px; }
  .btn-danger { background: #e11d48; color: #fff; }
  .btn-danger:hover { background: #be123c; }
  .btn-sm { padding: .375rem .75rem; font-size: .75rem; }

  /* ===== Kartu & tabel ===== */
  .card {
    border-radius: 1rem; border: 1px solid rgb(226 232 240 / .7); background: #fff;
    padding: 1rem; box-shadow: 0 1px 2px rgb(15 23 42 / .06);
  }
  @media (min-width: 640px) { .card { padding: 1.25rem; } }
  .card-title { font-size: .875rem; font-weight: 600; color: #334155; }
  .tbl-wrap { margin-inline: -1rem; overflow-x: auto; padding-inline: 1rem; }
  @media (min-width: 640px) {
    .tbl-wrap { margin-inline: 0; border: 1px solid #e2e8f0; border-radius: .75rem; padding-inline: 0; }
  }
  .tbl { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 13px; }
  .tbl th {
    border-bottom: 1px solid #e2e8f0; background: #f8fafc; padding: .625rem .75rem;
    text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .025em; color: #64748b;
  }
  .tbl td { border-bottom: 1px solid #f1f5f9; padding: .625rem .75rem; vertical-align: top; color: #334155; }
  .tbl tbody tr:last-child td { border-bottom: 0; }
  .tbl tbody tr:hover td { background: rgb(248 250 252 / .7); }
  .tbl tfoot td { background: #f8fafc; font-weight: 700; color: #1e293b; }
  .num { white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }
  .badge {
    display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap;
    border-radius: 999px; padding: .125rem .625rem; font-size: 11px; font-weight: 600;
  }
  .bdan { background: #e0e7ff; color: #4338ca; }
  .bdan:hover { background: #c7d2fe; }
  .bdel { background: #ffe4e6; color: #be123c; }
  .bdel:hover { background: #fecdd3; }

  /* ===== Sidebar ===== */
  .navw a, .navw .menu-btn {
    display: flex; width: 100%; align-items: center; gap: .625rem; border-radius: .75rem;
    padding: .5rem .75rem; text-align: left; font-size: 13.5px; color: #cbd5e1;
    text-decoration: none; transition: background-color .15s, color .15s;
  }
  .navw a:hover, .navw .menu-btn:hover { background: rgb(255 255 255 / .1); color: #fff; text-decoration: none; }
  .navw a.on { background: #4f46e5; font-weight: 600; color: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / .1); }
  .navw .menu-btn { font-weight: 600; color: #e2e8f0; }
  .navw .chev { margin-left: auto; font-size: 11px; color: #64748b; transition: transform .2s; transform: rotate(-90deg); }
  .navw .grp.open .chev { transform: rotate(0deg); }
  .navw .grp.open > .menu-btn { color: #fff; }
  .navw .sub {
    margin-left: 1.25rem; display: flex; flex-direction: column; gap: .125rem;
    border-left: 1px solid rgb(255 255 255 / .15); padding: .25rem 0 .25rem .75rem;
  }

  /* ===== Alert, empty, header halaman, toolbar ===== */
  .alert { display: flex; align-items: flex-start; gap: .625rem; border: 1px solid; border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem; }
  .alert-ok { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
  .alert-warn { border-color: #fde68a; background: #fffbeb; color: #92400e; }
  .alert-err { border-color: #fecdd3; background: #fff1f2; color: #be123c; }
  .empty { padding-block: 2.5rem; text-align: center; font-size: .875rem; color: #94a3b8; }
  .page-head { display: flex; flex-direction: column; gap: .75rem; }
  @media (min-width: 640px) { .page-head { flex-direction: row; align-items: center; justify-content: space-between; } }
  .page-desc { margin-top: .25rem; font-size: 13px; color: #64748b; }
  .toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; border-radius: 1rem;
    border: 1px solid rgb(226 232 240 / .7); background: #fff; padding: .75rem 1rem;
    box-shadow: 0 1px 2px rgb(15 23 42 / .06);
  }
  @media (min-width: 640px) { .toolbar { padding: .75rem 1.25rem; } }

  /* ===== Paginasi bawaan Laravel ===== */
  nav[role="navigation"] [aria-label="Pagination Navigation"] { margin-top: .75rem; }

  /* ===== Cetak & global ===== */
  @media print { .nonprint { display: none !important; } }
  #sidebar nav::-webkit-scrollbar { width: 6px; }
  #sidebar nav::-webkit-scrollbar-thumb { background: #334155; border-radius: 999px; }
  canvas { max-width: 100%; }
</style>
