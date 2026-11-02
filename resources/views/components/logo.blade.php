@props([
    'variant' => 'mark',
    'size' => 'md',
    'class' => '',
])

@php
$sizeClasses = [
    'xs' => 'h-6 w-6',
    'sm' => 'h-8 w-8',
    'md' => 'h-10 w-10',
    'lg' => 'h-12 w-12',
    'xl' => 'h-16 w-16',
];
$iconBox = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

@if ($variant === 'horizontal')
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 ' . $class]) }}>
        <div class="relative shrink-0 {{ $iconBox }} overflow-hidden rounded-xl bg-gradient-to-br from-[#1b7a4e] to-[#124f33] p-1.5 shadow-sm ring-1 ring-black/5">
            <svg class="h-full w-full" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M42.5 21.2 C41.5 17.3 37.6 15.2 32 15.2 C25.8 15.2 21.8 18.2 21.8 23 C21.8 28 26.8 29.4 32 30.9 C37.6 32.4 42.8 34.1 42.8 40.2 C42.8 45.4 38.2 49 32 49 C25.9 49 21.8 46.4 21.1 42.2"
                      stroke="#ffffff" stroke-width="5.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="text-base font-bold leading-tight tracking-tight text-slate-800">SiapBOSP</div>
            <div class="text-[11px] font-medium leading-none text-slate-500">Aplikasi Bendahara Sekolah</div>
        </div>
    </div>
@elseif ($variant === 'white')
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 ' . $class]) }}>
        <div class="relative shrink-0 {{ $iconBox }} overflow-hidden rounded-xl bg-white p-1.5 shadow-md">
            <svg class="h-full w-full" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M42.5 21.2 C41.5 17.3 37.6 15.2 32 15.2 C25.8 15.2 21.8 18.2 21.8 23 C21.8 28 26.8 29.4 32 30.9 C37.6 32.4 42.8 34.1 42.8 40.2 C42.8 45.4 38.2 49 32 49 C25.9 49 21.8 46.4 21.1 42.2"
                      stroke="#155e3c" stroke-width="5.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="text-xl font-bold leading-tight tracking-tight text-white">SiapBOSP</div>
            <div class="text-[12px] font-medium leading-none text-blue-100">Aplikasi Bendahara Sekolah</div>
        </div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'relative shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-[#1b7a4e] to-[#124f33] p-1.5 shadow-sm ring-1 ring-black/5 ' . $iconBox . ' ' . $class]) }}>
        <svg class="h-full w-full" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M42.5 21.2 C41.5 17.3 37.6 15.2 32 15.2 C25.8 15.2 21.8 18.2 21.8 23 C21.8 28 26.8 29.4 32 30.9 C37.6 32.4 42.8 34.1 42.8 40.2 C42.8 45.4 38.2 49 32 49 C25.9 49 21.8 46.4 21.1 42.2"
                  stroke="#ffffff" stroke-width="5.5" stroke-linecap="round"/>
        </svg>
    </div>
@endif
