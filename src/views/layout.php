<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'Relógio de Oração') ?></title>
<?php if (!empty($noindex)): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <meta name="description" content="Relógio de Oração — IEADIP, Assembleia de Deus Madureira em Patrocínio/MG">
    <meta name="theme-color" content="#eef2f7">
    <link rel="preload" href="<?= e(url('/assets/fonts/atkinson-hyperlegible-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php if (logo_url()): ?>
    <link rel="icon" href="<?= e(logo_url()) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(logo_url()) ?>">
<?php else: ?>
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
<?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body<?= !empty($admin) ? ' class="admin"' : '' ?>>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="container">
            <div class="brand">
<?php if ($logo = logo_url()): ?>
                <img src="<?= e($logo) ?>" alt="" class="brand-logo" width="48" height="48">
<?php endif; ?>
                <p class="church"><strong>Assembleia de Deus Madureira</strong><span>IEADIP, Patrocínio (MG)</span></p>
            </div>
<?php if (!empty($admin)): ?>
            <nav class="admin-nav" aria-label="Administração">
                <a href="<?= e(url('/admin')) ?>">Relógios cadastrados</a>
<?php if (admin_is_authenticated()): ?>
                <form method="post" action="<?= e(url('/admin/sair')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Sair</button>
                </form>
<?php endif; ?>
            </nav>
<?php endif; ?>
        </div>
    </header>
    <main id="conteudo" class="container" tabindex="-1">
<?= $content ?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <p class="verse">“Orai sem cessar.”<cite>1 Tessalonicenses 5:17</cite></p>
        </div>
    </footer>
</body>
</html>
