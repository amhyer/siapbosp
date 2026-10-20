<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e($title ?? 'Cetak'); ?><?php if(isset($sekolah)): ?> — <?php echo e($sekolah->nama); ?><?php endif; ?></title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body{font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:24px;max-width:62rem;margin:auto;color:#0f172a;line-height:1.5}
  .kop{border-bottom:3px double #0f172a;padding-bottom:.6rem;margin-bottom:1rem;text-align:center}
  .tbl{width:100%;border-collapse:collapse;font-size:.78rem}
  .tbl th,.tbl td{border:1px solid #94a3b8;padding:.32rem .5rem;text-align:left;vertical-align:top}
  .tbl th{background:#f1f5f9}
  .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
  .pg{break-before:page}
  .toolbar{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
  .toolbar a,.toolbar button{border:1px solid #cbd5e1;background:#fff;border-radius:.5rem;padding:.4rem .8rem;font-size:.82rem;text-decoration:none;color:#0f172a;cursor:pointer}
  .toolbar a:hover,.toolbar button:hover{background:#f1f5f9}
  .ttd{display:flex;justify-content:flex-end;margin-top:2rem}
  .ttd-grid{display:grid;grid-template-columns:1fr 1fr;gap:3.5rem;text-align:center;font-size:.82rem}
  /* Kompatibilitas kelas Tailwind yang dipakai di isi LPJ cetak */
  .text-center{text-align:center}.text-right{text-align:right}.text-justify{text-align:justify}
  .font-bold,.font-extrabold{font-weight:800}.font-semibold{font-weight:600}
  .uppercase{text-transform:uppercase}.italic{font-style:italic}
  .text-xs{font-size:11px}.text-sm{font-size:13px}
  .mt-4{margin-top:1rem}.mt-8{margin-top:2rem}.mt-16{margin-top:4rem}.mt-24{margin-top:6rem}.mt-6{margin-top:1.5rem}.mt-1{margin-top:.25rem}.mt-5{margin-top:1.25rem}
  .pt-10{padding-top:2.5rem}.text-2xl{font-size:24px}.text-xl{font-size:20px}.text-lg{font-size:18px}
  .tracking-wide{letter-spacing:.05em}.leading-7{line-height:1.75}
  .w-2\/3{width:66%}.whitespace-nowrap{white-space:nowrap}.font-mono{font-family:monospace}
  .text-slate-400{color:#94a3b8}.bg-slate-50{background:#f8fafc}
  @media print{
    .nonprint{display:none !important}
    @page{size:A4;margin:1.5cm}
    body{padding:0;max-width:none}
    a{color:inherit;text-decoration:none}
  }
</style>
</head>
<body>
<?php echo $__env->yieldContent('content'); ?>
</body>
</html>
<?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/layouts/print.blade.php ENDPATH**/ ?>