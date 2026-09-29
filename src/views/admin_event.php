<?php /** @var array $event @var array $slots @var array $signups @var string $publicUrl */
$message = flash();
$total = array_sum(array_map('count', $signups));
$empty = count(array_filter($slots, fn ($s) => empty($signups[$s['start']])));
$shareText = 'Relógio de Oração — ' . format_date_long($event['date']) . ', das ' . format_time($event['start_time']) . ' às ' . format_time($event['end_time']) . ".\nEscolha seu horário: " . $publicUrl;
?>
<p><a href="<?= e(url('/admin')) ?>">← Voltar para a lista</a></p>
<h1><?= e(ucfirst(format_date_long($event['date']))) ?></h1>
<p class="muted">Das <?= e(format_time($event['start_time'])) ?> às <?= e(format_time($event['end_time'])) ?> · <?= count($slots) ?> horários · <?= $total ?> inscrição(ões) · <?= $empty ?> horário(s) sem ninguém</p>

<?php if ($message): ?>
<div class="alert alert-success" role="status"><?= e($message) ?></div>
<?php endif; ?>

<section class="card">
    <h2>Link para compartilhar</h2>
    <div class="copy-row">
        <label for="link-publico" class="visually-hidden">Link público</label>
        <input type="text" id="link-publico" value="<?= e($publicUrl) ?>" readonly data-select-on-focus>
        <button type="button" class="button button-primary" data-copy="#link-publico">Copiar link</button>
    </div>
    <p class="actions">
        <a class="button" href="https://wa.me/?text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener noreferrer">Enviar pelo WhatsApp</a>
        <a class="button" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Abrir página pública</a>
    </p>
    <p class="hint" data-copy-status role="status" aria-live="polite"></p>
</section>

<section class="card">
    <h2>Participantes por horário</h2>
    <table class="signups">
        <thead>
            <tr><th scope="col">Horário</th><th scope="col">Participantes</th></tr>
        </thead>
        <tbody>
<?php foreach ($slots as $slot): $people = $signups[$slot['start']] ?? []; ?>
            <tr class="<?= $people ? '' : 'is-empty' ?>">
                <th scope="row"><?= e($slot['start']) ?> – <?= e($slot['end']) ?></th>
                <td>
<?php if ($people): ?>
                    <ol>
<?php foreach ($people as $p): ?>
                        <li><?= e($p['name']) ?></li>
<?php endforeach; ?>
                    </ol>
<?php else: ?>
                    <span class="muted">Ninguém ainda</span>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
