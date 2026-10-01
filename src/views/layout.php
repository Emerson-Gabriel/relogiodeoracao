<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Relógio de Oração') ?></title>
<?php if (!empty($noindex)): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <meta name="description" content="Relógio de Oração — IEADIP, Assembleia de Deus Madureira em Patrocínio/MG">
    <meta name="theme-color" content="#1f3a5f">
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body<?= !empty($admin) ? ' class="admin"' : '' ?>>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="container">
            <div class="brand">
<?php if ($logo = logo_file()): ?>
                <img src="<?= e(asset(basename($logo))) ?>" alt="" class="brand-logo" width="44" height="44">
<?php endif; ?>
                <p class="church">IEADIP · Assembleia de Deus Madureira · Patrocínio/MG</p>
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
            <p>“Orai sem cessar.” — 1 Tessalonicenses 5:17</p>
        </div>
    </footer>
</body>
</html>
