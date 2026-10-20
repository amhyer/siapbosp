<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Masuk ke SiapBOSP — Sistem Akuntansi Dana BOSP.">
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5">
<link rel="apple-touch-icon" href="/icons/icon.svg">
<title><?php echo e($title ?? 'Masuk'); ?> — SiapBOSP</title>
<?php echo app('Illuminate\Foundation\Vite')->fonts(); ?>
<?php if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))): ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
<?php else: ?>
    <script src="/tailwind.js"></script>
<?php endif; ?>
</head>
<body class="min-h-screen bg-slate-950">
<?php echo $__env->yieldContent('content'); ?>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/layouts/guest.blade.php ENDPATH**/ ?>