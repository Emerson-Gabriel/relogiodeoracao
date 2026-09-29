<?php
// Copie este arquivo para config.php e ajuste. Todas as opções são opcionais.
// Também podem ser definidas por variáveis de ambiente: RELOGIO_ADMIN_PASSWORD, RELOGIO_APP_URL, RELOGIO_DB_PATH, RELOGIO_BASE_PATH.
return [
    // Código de acesso da área /admin. Vazio = área aberta (NÃO recomendado na internet).
    // Pode ser texto puro ou um hash gerado com: php -r "echo password_hash('seu-codigo', PASSWORD_DEFAULT);"
    'admin_password' => '',

    // Endereço público do site, usado para montar o link compartilhável. Ex.: 'https://oracao.suaigreja.com.br'
    'app_url' => '',

    // Caminho do arquivo SQLite (padrão: data/relogio.sqlite). Deve ficar fora da pasta public.
    // 'db_path' => __DIR__ . '/data/relogio.sqlite',
];
