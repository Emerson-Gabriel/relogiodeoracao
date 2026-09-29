<section class="card narrow">
    <h1>Administração</h1>
<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
    <form method="post" action="<?= e(url('/admin/entrar')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="codigo">Código de acesso</label>
            <input type="password" id="codigo" name="codigo" required autocomplete="current-password" autofocus>
        </div>
        <button type="submit" class="button button-primary">Entrar</button>
    </form>
</section>
