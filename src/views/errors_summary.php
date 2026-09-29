<?php if (!empty($errors)): ?>
<div class="alert alert-error" role="alert" tabindex="-1" id="erros">
    <p><strong><?= count($errors) === 1 ? 'Corrija o item abaixo:' : 'Corrija os itens abaixo:' ?></strong></p>
    <ul>
<?php foreach ($errors as $error): ?>
        <li><?= e($error) ?></li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
