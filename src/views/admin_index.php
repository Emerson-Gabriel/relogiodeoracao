<?php /** @var array $events @var array $errors @var array $old */ ?>
<h1>Relógio de Oração — Administração</h1>

<?php if (!admin_password_enabled()): ?>
<div class="alert alert-warning">
    <strong>Atenção:</strong> esta área não está protegida. Qualquer pessoa que souber o endereço <code>/admin</code> pode ver os nomes e cadastrar relógios.
    Defina um código de acesso (<code>admin_password</code> em <code>config.php</code>) antes de publicar na internet.
</div>
<?php endif; ?>

<section class="card">
    <h2>Cadastrar novo relógio</h2>
    <?php require __DIR__ . '/errors_summary.php'; ?>
    <form method="post" action="<?= e(url('/admin/eventos')) ?>" class="grid-form" data-once>
        <?= csrf_field() ?>
        <div class="field">
            <label for="data">Data</label>
            <input type="date" id="data" name="data" value="<?= e($old['data'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="inicio">Início</label>
            <input type="time" id="inicio" name="inicio" value="<?= e($old['inicio'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="fim">Término</label>
            <input type="time" id="fim" name="fim" value="<?= e($old['fim'] ?? '') ?>" required>
        </div>
        <p class="hint full">Os horários são divididos em blocos de 1 hora (ex.: 07:00 às 10:00 gera 07–08, 08–09 e 09–10). O período precisa ter horas completas e terminar no mesmo dia. Horário de Brasília.</p>
        <div class="full">
            <button type="submit" class="button button-primary">Cadastrar</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Relógios cadastrados</h2>
<?php if (!$events): ?>
    <p>Nenhum relógio cadastrado ainda.</p>
<?php else: ?>
    <ul class="event-list">
<?php foreach ($events as $ev): ?>
        <li>
            <div>
                <strong><?= e(ucfirst(format_date_long($ev['date']))) ?></strong><br>
                <span class="muted"><?= e($ev['start_time']) ?> às <?= e($ev['end_time']) ?> · <?= count(generate_slots($ev['start_time'], $ev['end_time'])) ?> horários · <?= (int) $ev['signup_count'] ?> inscrição(ões)</span>
            </div>
            <div class="actions">
                <a class="button" href="<?= e(url('/admin/eventos/' . $ev['id'])) ?>">Ver inscritos e link</a>
            </div>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
