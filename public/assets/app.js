(function () {
    'use strict';

    // Evita duplo envio: desabilita o botão depois do primeiro clique.
    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.sent) {
                ev.preventDefault();
                return;
            }
            form.dataset.sent = '1';
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                btn.setAttribute('aria-disabled', 'true');
                btn.classList.add('is-loading');
                btn.textContent = 'Enviando…';
            });
        });
    });

    // Pede confirmação antes de ações irreversíveis (ex.: excluir inscrição).
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        });
    });

    // Ao voltar pelo histórico, recarrega para mostrar a situação atualizada dos horários.
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted && document.querySelector('form[data-once]')) {
            window.location.reload();
        }
    });

    // Leva o foco para o resumo de erros, para leitores de tela e teclado.
    var errors = document.getElementById('erros');
    if (errors) {
        errors.focus();
    }

    // Copiar link público.
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector(btn.getAttribute('data-copy'));
            var status = document.querySelector('[data-copy-status]');
            var done = function () {
                if (status) status.textContent = 'Link copiado! Agora é só colar na conversa.';
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done, function () {
                    input.select();
                    document.execCommand('copy');
                    done();
                });
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
    });

    document.querySelectorAll('[data-select-on-focus]').forEach(function (input) {
        input.addEventListener('focus', function () { input.select(); });
    });
})();
