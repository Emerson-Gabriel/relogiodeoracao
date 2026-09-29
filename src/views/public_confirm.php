<?php /** @var array $event @var array $signup */
$end = minutes_to_time(time_to_minutes($signup['slot_start']) + SLOT_MINUTES);
?>
<section class="card center confirm" role="status">
    <div class="check" aria-hidden="true">✓</div>
    <h1>Inscrição confirmada!</h1>
    <p><strong><?= e($signup['name']) ?></strong>, seu horário de oração é:</p>
    <p class="confirm-slot"><?= e(format_time($signup['slot_start'])) ?> às <?= e(format_time($end)) ?></p>
    <p><?= e(ucfirst(format_date_long($event['date']))) ?></p>
    <p class="hint">Dica: tire um print desta tela ou anote na sua agenda para lembrar.</p>
    <p><a class="button" href="<?= e(url('/r/' . $event['public_token'])) ?>">Escolher outro horário também</a></p>
</section>
