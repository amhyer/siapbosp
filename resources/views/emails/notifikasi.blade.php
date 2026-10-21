<x-mail::message>
# {{ $judulEmail }}

{{ $salam }}

@foreach ($baris as $b)
{{ $b }}

@endforeach

@if ($aksiUrl)
<x-mail::button :url="$aksiUrl">
{{ $aksiLabel ?? 'Buka di SiapBOSP' }}
</x-mail::button>
@endif

Salam hormat,
**Tim SiapBOSP** — Aplikasi Tata Kelola Dana BOSP
</x-mail::message>
