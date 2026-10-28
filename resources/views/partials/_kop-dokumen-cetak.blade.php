{{--
  Kepala dokumen cetak ala ARKAS 4.3.1.

  Bentuknya dibaca dari keluaran asli (bku-output.pdf, bku-pajak-output.pdf,
  bhm-output.pdf): judul berjarak lebar di tengah, baris periode di bawahnya,
  lalu blok identitas label:nilai rata kiri dengan penanda dokumen (BKU /
  BHM / …) di kanan.

  Parameter wajib: $judul, $periode, $penanda, $sekolah, $sumberDana
  Parameter opsional: $revisiKe (int), $catatan (HTML kecil di bawah kop),
  $spasi (jarak antarhuruf judul; judul panjang sebaiknya lebih rapat)
--}}
@php
  $provinsi = trim((string) ($sekolah->provinsi ?? ''));
  // ARKAS menulis "Prov. Sulawesi Selatan"; nama yang sudah berawalan "Prov"
  // tidak digandakan.
  $provinsiTeks = $provinsi === '' ? '' : (stripos($provinsi, 'prov') === 0 ? $provinsi : 'Prov. '.$provinsi);
  $desaKec = trim((string) ($sekolah->desa ?? '')) !== ''
    ? $sekolah->desa.', Kec. '.($sekolah->kecamatan ?? '')
    : (string) ($sekolah->kecamatan ?? '');
  $revisi = (int) ($revisiKe ?? 0);
@endphp
<div style="text-align:center;font-weight:700;font-size:14px;letter-spacing:{{ $spasi ?? '.45em' }}">{{ $judul }}</div>
<div style="text-align:center;font-size:12px;margin-top:.15rem">{{ $periode }}</div>

<div style="display:flex;justify-content:space-between;gap:1rem;margin:.7rem 0 .5rem;font-size:11.5px;line-height:1.55">
  <div>
    <div>NPSN : {{ $sekolah->npsn }}</div>
    <div>Nama Sekolah : {{ $sekolah->nama }}</div>
    <div>Desa/Kecamatan : {{ $desaKec !== '' ? $desaKec : '—' }}</div>
    <div>Kabupaten / Kota : {{ $sekolah->kab_kota }}</div>
    <div>Provinsi : {{ $provinsiTeks }}</div>
    <div>Sumber Dana : {{ $sumberDana }}{{ $revisi > 1 ? ' Perubahan Revisi Ke '.($revisi - 1) : '' }}</div>
  </div>
  <div style="font-weight:700;font-size:13px">{{ $penanda }}</div>
</div>
@isset($catatan)
<div style="font-size:10.5px;color:#475569;margin-bottom:.5rem">{!! $catatan !!}</div>
@endisset
