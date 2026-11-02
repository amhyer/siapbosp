<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  // Preview cetak: halaman cetak dimuat di iframe tersembunyi lalu dialog
  // cetak browser langsung dibuka — pengguna tetap di halaman ini, tak ada
  // tab HTML yang terbuka. Fallback: buka tab baru bila iframe gagal.
  window.siapbosCetakPreview = function (url) {
    var frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1100px;height:1400px;border:0;visibility:hidden';
    frame.src = url;
    var selesai = false;
    var jalankan = function () {
      if (selesai) { return; }
      selesai = true;
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      } catch (e) {
        window.open(url, '_blank');
      }
      setTimeout(function () { frame.remove(); }, 1500);
    };
    frame.onload = function () { setTimeout(jalankan, 300); };
    document.body.appendChild(frame);
    setTimeout(jalankan, 5000); // jaring pengaman bila onload tak memicu
  };
  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a[data-cetak]') : null;
    if (!a) { return; }
    ev.preventDefault();
    var teksAsli = a.innerHTML;
    a.innerHTML = '⏳ Menyiapkan…';
    window.siapbosCetakPreview(a.getAttribute('href'));
    setTimeout(function () { a.innerHTML = teksAsli; }, 1500);
  });
</script>
