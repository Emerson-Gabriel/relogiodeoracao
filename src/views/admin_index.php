<?php /** @var array $events @var array $errors @var array $old */ ?>
<h1>Administração</h1>
<p class="lede">Cadastre um relógio, compartilhe o link e acompanhe quem vai orar em cada horário.</p>


<section class="panel">
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
        <p class="hint full">Cada horário dura 1 hora: de 07:00 às 10:00 saem os horários 07–08, 08–09 e 09–10. Use horas completas, no mesmo dia, no horário de Brasília.</p>
        <div class="full">
            <button type="submit" class="button button-primary">Cadastrar relógio</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Relógios cadastrados</h2>
<?php if (!$events): ?>
    <p class="empty">Nenhum relógio cadastrado ainda. Preencha a data e os horários acima para criar o primeiro.</p>
<?php else: ?>
    <ul class="event-list">
<?php foreach ($events as $ev): ?>
        <li>
            <div>
                <strong><?= e(ucfirst(format_date_long($ev['date']))) ?></strong><br>
                <span class="muted">Das <?= e($ev['start_time']) ?> às <?= e($ev['end_time']) ?>, <?= count(generate_slots($ev['start_time'], $ev['end_time'])) ?> horários, <?= (int) $ev['signup_count'] ?> inscrição(ões)</span>
            </div>
            <div class="actions">
                <a class="button" href="<?= e(url('/admin/eventos/' . $ev['id'])) ?>">Ver inscritos e link</a>
            </div>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
