<?php
/** @var array $event @var array $slots @var array $occupied @var array $errors @var array $old @var string $submission */
$selected = $old['horario'] ?? '';
?>
<section class="vigil-hero">
    <h1>Relógio de Oração</h1>
    <p class="vigil-date"><?= e(ucfirst(format_date_long($event['date']))) ?></p>
    <p class="vigil-span">Das <?= e(format_time($event['start_time'])) ?> às <?= e(format_time($event['end_time'])) ?>. Cada horário dura 1 hora.</p>
</section>

<?php require __DIR__ . '/errors_summary.php'; ?>

<form method="post" action="<?= e(url('/r/' . $event['public_token'])) ?>" class="signup-form" data-once>
    <input type="hidden" name="envio" value="<?= e($submission) ?>">

    <fieldset class="hours">
        <legend><span class="step">1</span> Escolha sua hora de oração</legend>
        <p class="hint">Mais de uma pessoa pode orar no mesmo horário, então você também pode escolher um horário que já tem participante.</p>
        <ol class="hours-list">
<?php foreach ($slots as $slot):
    $taken = isset($occupied[$slot['start']]);
    $id = 'h-' . str_replace(':', '', $slot['start']);
?>
            <li class="hour<?= $taken ? ' is-taken' : ' is-free' ?>">
                <input type="radio" name="horario" id="<?= e($id) ?>" value="<?= e($slot['start']) ?>" required<?= $selected === $slot['start'] ? ' checked' : '' ?> aria-describedby="<?= e($id) ?>-st">
                <label for="<?= e($id) ?>">
                    <span class="hour-node" aria-hidden="true"></span>
                    <span class="hour-time"><?= e($slot['start']) ?> – <?= e($slot['end']) ?></span>
                    <span class="badge" id="<?= e($id) ?>-st"><?= $taken ? 'Já tem participante(s)' : 'Disponível' ?></span>
                </label>
            </li>
<?php endforeach; ?>
        </ol>
    </fieldset>

    <div class="field name-field">
        <label for="nome"><span class="step">2</span> Seu nome</label>
        <input type="text" id="nome" name="nome" value="<?= e($old['nome'] ?? '') ?>" required minlength="<?= NAME_MIN ?>" maxlength="<?= NAME_MAX ?>" autocomplete="name" autocapitalize="words" aria-describedby="nome-dica">
        <p class="hint" id="nome-dica">Seu nome não aparece para as outras pessoas. Só a organização vê.</p>
    </div>

    <button type="submit" class="button button-primary button-block">Confirmar meu horário</button>
</form>
