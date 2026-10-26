{{--
  Watermark DRAFT bersama untuk semua halaman cetak (ARKAS 4 Bab XXI).

  Parameter:
    $isDraft    (bool)   tampilkan watermark bila benar
    $draftPesan (string) penjelasan singkat kenapa dokumen belum final

  Menyatu dengan layout cetak: elemen bertanda `nonprint` tidak ikut tercetak,
  sedangkan tanda airnya sendiri ikut tercetak.
--}}
@if(!empty($isDraft))
<div style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:50">
  <span style="font-size:110px;font-weight:900;letter-spacing:.25em;color:rgba(225,29,72,.13);transform:rotate(-28deg);user-select:none">DRAFT</span>
</div>
<div class="nonprint" style="background:#fff1f2;border:1px solid #fda4af;color:#9f1239;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:12px">
  ⚠️ <b>Watermark DRAFT aktif</b> (sesuai ARKAS 4 Bab XXI): {{ $draftPesan ?? 'dokumen ini belum difinalkan.' }}
</div>
@endif
