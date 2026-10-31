{{--
  Gaya darurat bila aset Vite (public/build) belum dibangun — dipakai oleh
  layouts/app dan layouts/guest pada cabang fallback.

  Blok ini menulis ulang SELURUH kelas komponen app.css sebagai CSS biasa
  (tanpa @apply, warna literal) supaya fallback tampil setara dengan aset
  hasil build. Utility Tailwind tetap dihasilkan oleh /tailwind.js (build
  browser).

  Jangan menambah aturan baru di sini tanpa memperbarui
  resources/css/app.css; tests/Feature/CssHalamanTest.php memeriksa kedua
  berkas tetap sepadan.
--}}
<style>
  /* Tipografi ARKAS: Inter disimpan lokal (public/fonts), sama dengan app.css. */
  @font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('/fonts/inter-latin-wght-normal.woff2') format('woff2');
  }
  /* ===== Dasar (app.css @layer base) ===== */
  body {
    font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    -webkit-font-smoothing: antialiased;
  }
  ::selection { background: #cfe3fc; color: #103178; }

  /* ===== Form ===== */
  .inp {
    box-sizing: border-box; width: 100%; border-radius: 4px; border: 1px solid #c9cbcf;
    background: #fff; padding: .5rem .75rem; font-size: .9375rem; line-height: 1.4; color: #2f3031;
    transition: border-color .15s, box-shadow .15s;
  }
  .inp::placeholder { color: #94a3b8; }
  .inp:focus { border-color: #0b5fef; outline: none; box-shadow: 0 0 0 2px #dbeafe; }
  .inp-sm { padding: .375rem .625rem; font-size: 13px; }
  select.inp { padding-right: 2rem; }
  label.lbl { display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #65696c; }

  /* ===== Tombol ===== */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
    border: 0; border-radius: 4px; background: #0b5fef; padding: .5rem 1rem;
    font-size: .9375rem; font-weight: 600; line-height: 1.25; color: #fff;
    text-decoration: none; cursor: pointer;
    transition: background-color .2s, color .2s, box-shadow .2s;
  }
  .btn { background: #0b5fef; color: #fff; }
  .btn:hover { background: #1e429f; }
  .btn:active { background: #1e3a8a; }
  .btn:focus-visible { outline: 2px solid #93c5fd; outline-offset: 1px; }
  .btn:disabled { cursor: not-allowed; opacity: .6; }
  .btn2 { background: #fff; border-color: #cbd5e1; color: #475569; }
  .btn2:hover { border-color: #94a3b8; background: #f8fafc; color: #1e293b; }
  .btn2:focus-visible { outline: 2px solid #e2e8f0; outline-offset: 1px; }
  .btn-black { background: #2f3031; color: #fff; }
  .btn-black:hover { background: #45474a; color: #fff; }
  .btn-danger { background: #e11d48; color: #fff; }
  .btn-danger:hover { background: #be123c; }
  .btn-sm { padding: .375rem .75rem; font-size: .75rem; }

  /* ===== Kartu & tabel ===== */
  .card {
    border: 1px solid #d9dbdd; border-radius: 4px; background: #fff;
    padding: 1.25rem; box-shadow: none;
  }
  @media (min-width: 640px) { .card { padding: 1.5rem; } }
  .card-title { font-size: .9375rem; font-weight: 600; color: #2f3031; }
  .tbl-wrap { margin-left: -1rem; margin-right: -1rem; overflow-x: auto; padding-left: 1rem; padding-right: 1rem; }
  @media (min-width: 640px) {
    .tbl-wrap { margin-left: 0; margin-right: 0; border: 1px solid #d9dbdd; border-radius: 4px; padding-left: 0; padding-right: 0; }
  }
  .tbl { width: 100%; min-width: 640px; border-collapse: collapse; font-size: .875rem; }
  .tbl th {
    border-bottom: 1px solid #d9dbdd; background: #f5f5f5; padding: .5rem .75rem;
    text-align: left; font-size: .75rem; font-weight: 600; letter-spacing: 0;
    text-transform: none; color: #65696c;
  }
  .tbl td { border-bottom: 1px solid #efefef; padding: .75rem 1rem; vertical-align: top; color: #2f3031; }
  .tbl tbody tr:last-child td { border-bottom: 0; }
  .tbl tbody tr:hover td { background: #fafafa; }
  .tbl tfoot td { background: #f5f5f5; font-weight: 600; color: #2f3031; }
  /* Baris baru disimpan — kilat kuning (sama dengan jalur build). */
  .kilat td { animation: kilat-baris 2.6s ease-out 1; }
  @keyframes kilat-baris { 0%, 25% { background-color: #fef3c7; } 100% { background-color: transparent; } }
  @media (prefers-reduced-motion: reduce) {
    .kilat td { animation: none; background-color: #fef3c7; }
  }
  .num { white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }

  /* Jejak cetak kerangka aplikasi: tersembunyi di layar, tampil di kertas. */
  .cetak-kaki { display: none; }
  @media print {
    .cetak-kaki { display: block; margin-top: 1rem; border-top: 1px solid #cbd5e1;
      padding-top: .35rem; font-size: 9pt; color: #475569; }
  }
  .badge {
    display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap;
    border-radius: .25rem; padding: .125rem .5rem; font-size: .6875rem;
    font-weight: 600; line-height: 1.4;
  }
  .bdan { background: #e1effe; color: #054bcc; }
  .bdan:hover { background: #cfe3fc; }
  .bdel { background: #ffe6e7; color: #c82228; }
  .bdel:hover { background: #facccf; }

  /* ===== Kerangka ARKAS ===== */
  .arkas-topbar {
    position: sticky; top: 0; z-index: 30; display: flex; height: 54px;
    align-items: center; justify-content: space-between; gap: 12px;
    background: #fff; padding: 0 21px; box-shadow: 0 2px 4px -4px #2f3031;
  }
  .arkas-logo {
    display: inline-flex; height: 32px; width: 32px; flex: 0 0 32px;
    align-items: center; justify-content: center; border-radius: 6px;
    background: #1e3a8a; color: #fff; font-size: 16px; font-weight: 700; line-height: 1;
  }
  .arkas-avatar {
    display: inline-flex; height: 36px; width: 36px; flex: 0 0 36px;
    align-items: center; justify-content: center; border-radius: 9999px;
    background: #1e3a8a; color: #fff; font-size: 14px; font-weight: 600;
  }
  .arkas-school-btn {
    display: flex; cursor: pointer; align-items: center; gap: 8px;
    border: 0; background: transparent; padding: 4px 0 4px 8px; text-align: left;
  }
  .arkas-dropdown {
    position: absolute; right: 0; top: calc(100% + 2px); z-index: 50; width: 268px;
    border-radius: 0 0 4px 4px; background: #fff; padding: 4px 0;
    box-shadow: 0 8px 20px rgba(37, 40, 43, .2);
  }
  .arkas-dropdown a, .arkas-dropdown button {
    display: flex; width: 100%; align-items: center; border: 0; background: transparent;
    padding: 12px 24px; text-align: left; font-size: 16px; font-weight: 400;
    color: #000; text-decoration: none; cursor: pointer;
  }
  .arkas-dropdown a:hover, .arkas-dropdown button:hover { background: #f5f5f5; }
  .arkas-dropdown svg { height: 24px; width: 24px; margin-right: 12px; flex: 0 0 24px; }

  .arkas-sidebar { width: 260px; background: #1e3a8a; color: #fff; }
  .arkas-content { min-height: calc(100vh - 54px); background: #fafafa; }
  .arkas-main { width: 100%; max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
  @media (min-width: 640px) { .arkas-main { padding-left: 24px; padding-right: 24px; } }
  @media (min-width: 1024px) { .arkas-main { padding: 32px 40px 48px; } }
  .arkas-page-head {
    display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
    gap: 12px; margin-bottom: 20px;
  }
  .arkas-page-title { font-size: 22px; font-weight: 600; line-height: 1.3; color: #2f3031; }
  .arkas-page-sub { margin-top: 2px; max-width: 56rem; font-size: 12px; line-height: 1.45; color: #65696c; }
  .arkas-year {
    border: 1px solid #c9cbcf; border-radius: 4px; background: #fff;
    padding: 4px 8px; font-size: 13px; line-height: 1.4; color: #2f3031;
  }
  .arkas-year:focus { border-color: #0b5fef; outline: none; box-shadow: 0 0 0 2px #e1effe; }
  .arkas-icon-btn {
    display: inline-flex; height: 34px; width: 34px; align-items: center; justify-content: center;
    border: 1px solid #d9dbdd; border-radius: 4px; background: #fff; color: #2f3031; cursor: pointer;
  }
  .arkas-icon-btn:hover { background: #f5f5f5; }

  /* ===== Sidebar ===== */
  .navw { display: flex; flex-direction: column; }
  .navw-utama { display: flex; flex-direction: column; gap: 1px; }
  .navw-area-row { display: flex; align-items: center; gap: 2px; }
  .navw-area {
    display: block; flex: 1; border-radius: 4px; padding: 8px 23px; margin: 1px 0;
    color: #fff; font-size: 16px; line-height: 1.35; text-decoration: none;
  }
  .navw a:hover, .navw .menu-btn:hover { background: rgb(255 255 255 / .1); color: #fff; text-decoration: none; }
  .navw a.on { background: #0b5fef; font-weight: 600; color: #fff; }
  .navw .menu-btn { font-weight: 600; color: #e2e8f0; }
  .navw .chev { margin-left: auto; font-size: 11px; color: #64748b; transition: transform .2s; transform: rotate(-90deg); }
  .navw .grp.open .chev { transform: rotate(0deg); }
  .navw .grp.open > .menu-btn { color: #fff; }
  .navw .sub {
    margin-left: 1.25rem; display: flex; flex-direction: column; gap: .125rem;
    border-left: 1px solid rgb(255 255 255 / .15); padding: .25rem 0 .25rem .75rem;
  }
  .navw-chev:hover { background: rgba(255, 255, 255, .1); color: #fff; }
  .navw-chev svg { transition: transform .2s; }
  .grp.open .navw-chev svg { transform: rotate(180deg); }
  .navw-sub {
    display: flex; flex-direction: column; gap: 1px; margin: 2px 0 4px 23px;
    border-left: 1px solid rgba(255, 255, 255, .15); padding: 2px 0 2px 8px;
  }
  .navw-sub a {
    display: block; border-radius: 4px; padding: 6px 12px;
    color: #dbeafe; font-size: 13.5px; text-decoration: none;
  }
  .navw-sub a:hover { background: rgba(255, 255, 255, .08); color: #fff; }
  .navw-sub a.on { background: rgba(255, 255, 255, .12); color: #fff; font-weight: 600; }
  .navw-sub p {
    padding: 6px 12px 2px; color: #93c5fd; font-size: 10px;
    font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
  }
  .navw-sep {
    display: flex; align-items: center; gap: 8px; margin: 16px 0 5px; padding: 0 23px;
    color: #93c5fd; font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
  }
  .navw-sep::after { content: ''; flex: 1; height: 1px; background: rgba(255, 255, 255, .15); }
  .navw-extra {
    display: block; border-radius: 4px; padding: 6px 23px;
    color: #dbeafe; font-size: 13.5px; text-decoration: none;
  }
  .navw-extra:hover { background: rgba(255, 255, 255, .08); color: #fff; }
  .navw-extra.on { background: rgba(255, 255, 255, .12); color: #fff; font-weight: 600; }
  .navw-group > .menu-btn {
    display: flex; width: 100%; align-items: center; justify-content: space-between;
    border: 0; border-radius: 4px; background: transparent; padding: 7px 23px;
    text-align: left; color: #dbeafe; font-size: 13.5px; font-weight: 600; cursor: pointer;
  }
  .navw-group > .menu-btn:hover { background: rgba(255, 255, 255, .08); color: #fff; }
  .navw-group .chev { height: 16px; width: 16px; flex: 0 0 16px; transition: transform .2s; }
  .navw-group.open .chev { transform: rotate(180deg); }
  .navw-util {
    display: flex; align-items: center; border-radius: 4px; padding: 8px 12px;
    color: #fff; font-size: 16px; text-decoration: none;
  }
  .navw-util:hover { color: #fff; font-weight: 600; }
  .navw-util.on { background: #1d4ed8; color: #fff; font-weight: 600; }
  .navw-util svg { height: 24px; width: 24px; margin-right: 15px; flex: 0 0 24px; }
  @media (min-width: 1024px) { .sidebar-collapsed { margin-left: -260px; } }

  /* ===== Alert, empty, header halaman, toolbar ===== */
  .alert { display: flex; align-items: flex-start; gap: 10px; border: 1px solid; border-radius: 4px; padding: 12px 16px; font-size: 14px; }
  .alert-ok { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
  .alert-warn { border-color: #fde68a; background: #fffbeb; color: #92400e; }
  .alert-err { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }
  .empty { padding: 2.5rem 0; text-align: center; font-size: 14px; color: #808489; }
  .page-head { display: flex; flex-direction: column; gap: 12px; }
  @media (min-width: 640px) { .page-head { flex-direction: row; align-items: center; justify-content: space-between; } }
  .page-desc { margin-top: 4px; font-size: 13px; color: #65696c; }
  .toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    border: 1px solid #d9dbdd; border-radius: 4px; background: #fff; padding: 12px 16px;
  }

  /* ===== Paginasi bawaan Laravel ===== */
  nav[role="navigation"] [aria-label="Pagination Navigation"] { margin-top: 12px; }

  /* ===== Cetak & global ===== */
  @media print { .nonprint { display: none !important; } }
  #sidebar nav::-webkit-scrollbar { width: 6px; }
  #sidebar nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .25); border-radius: 999px; }
  canvas { max-width: 100%; }
</style>
