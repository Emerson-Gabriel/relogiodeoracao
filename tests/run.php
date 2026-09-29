<?php
declare(strict_types=1);

/*
 * Testes sem dependências externas. Execute com:  php tests/run.php
 *
 * - Testes unitários das regras (geração de blocos, validações).
 * - Testes de ponta a ponta: sobe o servidor embutido do PHP com um banco
 *   temporário e percorre os fluxos de administração e inscrição pública.
 */

require_once dirname(__DIR__) . '/src/functions.php';

$failures = 0;
$count = 0;

function check(bool $cond, string $label): void
{
    global $failures, $count;
    $count++;
    if ($cond) {
        echo "  ok   {$label}\n";
    } else {
        $failures++;
        echo "  FAIL {$label}\n";
    }
}

// ---------------------------------------------------------------------------
echo "Regras de negócio\n";

check(generate_slots('07:00', '10:00') === [
    ['start' => '07:00', 'end' => '08:00'],
    ['start' => '08:00', 'end' => '09:00'],
    ['start' => '09:00', 'end' => '10:00'],
], '07:00–10:00 gera 3 blocos consecutivos de 1 hora');
check(count(generate_slots('00:00', '23:00')) === 23, '00:00–23:00 gera 23 blocos');
check(generate_slots('07:30', '09:30')[1] === ['start' => '08:30', 'end' => '09:30'], 'aceita início em minuto quebrado (07:30–09:30)');
check(generate_slots('07:00', '07:00') === [], 'início igual ao fim não gera blocos');

check(validate_event('2026-10-02', '07:00', '10:00') === [], 'evento válido não tem erros');
check(validate_event('2026-10-03', '19:00', '22:00') === [], 'aceita datas que não são sexta-feira');
check(count(validate_event('', '', '')) === 3, 'data, início e fim são obrigatórios');
check(validate_event('2026-02-30', '07:00', '10:00') !== [], 'rejeita data inexistente');
check(validate_event('2026-10-02', '10:00', '07:00') !== [], 'rejeita término antes do início');
check(validate_event('2026-10-02', '22:00', '02:00') !== [], 'rejeita evento que atravessa a meia-noite');
check(validate_event('2026-10-02', '07:00', '07:00') !== [], 'rejeita início igual ao término');
$e = validate_event('2026-10-02', '07:00', '09:30');
check($e !== [] && str_contains($e[0], 'horas completas'), 'rejeita período que não forma horas completas, com mensagem clara');
check(validate_event('2026-10-02', '25:00', '26:00') !== [], 'rejeita horário inválido');

check(normalize_name("  Maria \t  da   Silva ") === 'Maria da Silva', 'normaliza espaços do nome');
check(validate_name('') !== [], 'rejeita nome vazio');
check(validate_name('A') !== [], 'rejeita nome com 1 letra');
check(validate_name('123') !== [], 'rejeita nome sem letras');
check(validate_name(str_repeat('a', 81)) !== [], 'rejeita nome muito longo');
check(validate_name('José Antônio') === [], 'aceita nome com acentos');
check(format_date_long('2026-10-02') === 'sexta-feira, 2 de outubro de 2026', 'formata data em português');
check(is_public_token(random_token(12)) && strlen(random_token(12)) === 16, 'token público tem 16 caracteres seguros para URL');

// ---------------------------------------------------------------------------
echo "\nFluxos de ponta a ponta\n";

$tmp = sys_get_temp_dir() . '/relogio-test-' . bin2hex(random_bytes(4));
mkdir($tmp);

function start_server(string $db, string $password = '', array $extraEnv = []): array
{
    $port = random_int(20000, 40000);
    $env = array_merge(getenv(), ['RELOGIO_DB_PATH' => $db, 'RELOGIO_ADMIN_PASSWORD' => $password, 'RELOGIO_APP_URL' => ''], $extraEnv);
    $root = dirname(__DIR__) . '/public';
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root, $root . '/index.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
        usleep(100_000);
    }
    return [$proc, "http://127.0.0.1:{$port}"];
}

/** Cliente HTTP mínimo com cookies. Retorna [status, headers, body]. */
function http(string $method, string $url, array $form = [], array &$cookies = [], array $extraHeaders = []): array
{
    $headers = $extraHeaders;
    if ($cookies) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($cookies), $cookies));
    }
    $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => ''];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $opts['content'] = http_build_query($form);
    }
    $opts['header'] = implode("\r\n", $headers);
    $body = file_get_contents($url, false, stream_context_create(['http' => $opts]));
    $respHeaders = $http_response_header ?? [];
    preg_match('#HTTP/\S+ (\d+)#', $respHeaders[0] ?? '', $m);
    foreach ($respHeaders as $h) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $c)) {
            $cookies[$c[1]] = $c[2];
        }
    }
    return [(int) ($m[1] ?? 0), $respHeaders, (string) $body];
}

function header_value(array $headers, string $name): ?string
{
    foreach ($headers as $h) {
        if (stripos($h, $name . ':') === 0) {
            return trim(substr($h, strlen($name) + 1));
        }
    }
    return null;
}

function csrf_from(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

[$proc, $base] = start_server($tmp . '/db.sqlite');
try {
    $jar = [];
    [$st, , $html] = http('GET', "$base/admin", [], $jar);
    check($st === 200 && str_contains($html, 'Cadastrar novo relógio'), '/admin abre sem login quando não há código configurado');
    check(str_contains($html, 'esta área não está protegida'), '/admin avisa que está desprotegido');
    $csrf = csrf_from($html);

    [$st, , $html] = http('POST', "$base/admin/eventos", ['_csrf' => $csrf, 'data' => '2026-10-02', 'inicio' => '07:00', 'fim' => '09:30'], $jar);
    check($st === 422 && str_contains($html, 'horas completas'), 'cadastro com período quebrado é rejeitado com mensagem');

    [$st, , $html] = http('POST', "$base/admin/eventos", ['_csrf' => $csrf, 'data' => '2026-10-02', 'inicio' => '22:00', 'fim' => '01:00'], $jar);
    check($st === 422, 'cadastro atravessando meia-noite é rejeitado');

    [$st] = http('POST', "$base/admin/eventos", ['_csrf' => 'errado', 'data' => '2026-10-02', 'inicio' => '07:00', 'fim' => '10:00'], $jar);
    check($st === 419, 'cadastro sem CSRF válido é recusado');

    [$st, $h] = http('POST', "$base/admin/eventos", ['_csrf' => $csrf, 'data' => '2026-10-02', 'inicio' => '07:00', 'fim' => '10:00'], $jar);
    $adminEventUrl = $base . header_value($h, 'Location');
    check($st === 303 && str_contains($adminEventUrl, '/admin/eventos/'), 'cadastro válido redireciona para a gestão do evento');

    [$st, , $html] = http('GET', $adminEventUrl, [], $jar);
    preg_match('#id="link-publico" value="([^"]+)"#', $html, $m);
    $publicUrl = $m[1] ?? '';
    check($st === 200 && preg_match('#/r/[A-Za-z0-9_-]{16}$#', $publicUrl) === 1, 'admin obtém link público com token não sequencial');
    check(str_contains($html, '07:00 – 08:00') && str_contains($html, '09:00 – 10:00') && !str_contains($html, '10:00 – 11:00'), 'admin vê os 3 blocos corretos');

    [$st, , $html] = http('GET', $publicUrl);
    check($st === 200 && substr_count($html, 'name="horario"') === 3, 'página pública mostra todos os blocos');
    check(substr_count($html, '>Disponível<') === 3, 'todos os blocos começam disponíveis');
    check(str_contains($html, 'Sexta-feira, 2 de outubro de 2026'), 'data em formato brasileiro');
    check(str_contains($html, 'noindex'), 'página pública não é indexada por buscadores');
    preg_match('/name="envio" value="([^"]+)"/', $html, $m);
    $envio1 = $m[1];

    [$st] = http('POST', $publicUrl, ['nome' => '', 'horario' => '08:00', 'envio' => $envio1]);
    check($st === 422, 'inscrição sem nome é rejeitada');
    [$st, , $html] = http('POST', $publicUrl, ['nome' => 'Maria Silva', 'horario' => '10:00', 'envio' => $envio1]);
    check($st === 422 && str_contains($html, 'não faz parte'), 'inscrição em bloco inexistente é rejeitada');
    [$st] = http('POST', $publicUrl, ['nome' => 'Maria Silva', 'horario' => '', 'envio' => $envio1]);
    check($st === 422, 'inscrição sem horário é rejeitada');

    [$st, $h] = http('POST', $publicUrl, ['nome' => 'Maria Silva', 'horario' => '08:00', 'envio' => $envio1]);
    $confirmUrl = $base . header_value($h, 'Location');
    check($st === 303 && str_contains($confirmUrl, '/confirmacao/'), 'inscrição válida redireciona para confirmação');
    [$st, , $html] = http('GET', $confirmUrl);
    check($st === 200 && str_contains($html, 'Inscrição confirmada') && str_contains($html, '08h às 09h'), 'confirmação mostra o horário escolhido');

    // Duplo clique / reenvio com o mesmo token não duplica.
    http('POST', $publicUrl, ['nome' => 'Maria Silva', 'horario' => '08:00', 'envio' => $envio1]);
    http('POST', $publicUrl, ['nome' => 'Maria Silva', 'horario' => '08:00', 'envio' => $envio1]);

    // Outra pessoa no mesmo horário.
    [, , $html] = http('GET', $publicUrl);
    preg_match('/name="envio" value="([^"]+)"/', $html, $m);
    check($m[1] !== $envio1, 'cada abertura da página gera um token de envio novo');
    [$st] = http('POST', $publicUrl, ['nome' => 'João Pereira', 'horario' => '08:00', 'envio' => $m[1]]);
    check($st === 303, 'segunda pessoa consegue se inscrever no mesmo horário');

    // Mesmo token, mas outro horário (voltou e escolheu outro): conta como nova inscrição.
    [$st] = http('POST', $publicUrl, ['nome' => 'João Pereira', 'horario' => '09:00', 'envio' => $m[1]]);
    check($st === 303, 'mesma pessoa pode escolher um segundo horário');

    [, , $html] = http('GET', $publicUrl);
    check(substr_count($html, '>Já tem participante(s)<') === 2 && substr_count($html, '>Disponível<') === 1, 'estado dos blocos é atualizado');
    check(!str_contains($html, 'Maria') && !str_contains($html, 'João') && !str_contains($html, 'Pereira'), 'página pública NÃO contém nomes no HTML');
    check(!preg_match('/\b[23]\s*(pessoas|inscri)/i', $html), 'página pública não mostra a quantidade de inscritos');

    [$st, , $html] = http('GET', $adminEventUrl, [], $jar);
    check(substr_count($html, 'Maria Silva') === 1, 'reenvios repetidos não duplicaram a inscrição');
    check(str_contains($html, 'João Pereira') && preg_match('#08:00 – 09:00</th>\s*<td>\s*<ol>\s*<li>Maria Silva</li>\s*<li>João Pereira</li>#', $html) === 1, 'admin vê as duas pessoas no mesmo horário, sem sobrescrita');

    // Inscrições simultâneas.
    [, , $html] = http('GET', $publicUrl);
    $url = $publicUrl;
    $children = [];
    for ($i = 1; $i <= 10; $i++) {
        $body = http_build_query(['nome' => "Pessoa {$i}", 'horario' => '07:00', 'envio' => random_token(16)]);
        $children[] = proc_open([PHP_BINARY, '-r', 'file_get_contents($argv[1], false, stream_context_create(["http" => ["method" => "POST", "header" => "Content-Type: application/x-www-form-urlencoded", "content" => $argv[2], "follow_location" => 0, "ignore_errors" => true]]));', $url, $body], [], $p);
    }
    foreach ($children as $c) {
        proc_close($c);
    }
    [, , $html] = http('GET', $adminEventUrl, [], $jar);
    $ok = true;
    for ($i = 1; $i <= 10; $i++) {
        $ok = $ok && str_contains($html, "<li>Pessoa {$i}</li>");
    }
    check($ok, '10 inscrições simultâneas foram todas gravadas');

    [$st, , $html] = http('GET', "$base/r/tokeninexistente1234");
    check($st === 404, 'link de edição inexistente mostra 404');
    [$st] = http('POST', "$base/r/tokeninexistente1234", ['nome' => 'Ana Souza', 'horario' => '07:00', 'envio' => random_token(16)]);
    check($st === 404, 'inscrição em edição inexistente é recusada');

    [$st, , $html] = http('GET', "$base/admin", [], $jar);
    check(str_contains($html, 'Sexta-feira, 2 de outubro de 2026') && str_contains($html, '13 inscrição(ões)'), 'admin lista as edições cadastradas');

    [$st, $h, $html] = http('GET', "$base/assets/style.css");
    check($st === 200, 'arquivos estáticos são servidos');
} finally {
    proc_terminate($proc);
    proc_close($proc);
}

// Proteções contra abuso.
[$proc, $base] = start_server($tmp . '/db3.sqlite', 'segredo123', ['RELOGIO_SIGNUP_LIMIT' => '3']);
try {
    $jar = [];
    [, , $html] = http('GET', "$base/admin/entrar", [], $jar);
    $csrf = csrf_from($html);
    for ($i = 0; $i < 5; $i++) {
        http('POST', "$base/admin/entrar", ['_csrf' => $csrf, 'codigo' => 'errado' . $i], $jar);
    }
    [$st] = http('POST', "$base/admin/entrar", ['_csrf' => $csrf, 'codigo' => 'segredo123'], $jar);
    check($st === 429, 'após 5 códigos errados, o /admin bloqueia novas tentativas');

    // Cria um evento direto no banco para testar a página pública.
    putenv('RELOGIO_DB_PATH=' . $tmp . '/db3.sqlite');
    require_once dirname(__DIR__) . '/src/bootstrap.php';
    $ev = create_event('2026-10-02', '07:00', '09:00');
    $pub = "$base/r/{$ev['public_token']}";

    [$st] = http('POST', $pub, ['nome' => 'Ana Souza', 'horario' => '07:00', 'envio' => random_token(16)], $jar, ['Origin: https://site-malicioso.example']);
    check($st === 403, 'inscrição enviada a partir de outro site é recusada');
    [$st] = http('POST', $pub, ['nome' => 'Ana Souza', 'horario' => '07:00', 'envio' => random_token(16)], $jar, ['Origin: ' . $base]);
    check($st === 303, 'inscrição com Origin do próprio site é aceita');
    http('POST', $pub, ['nome' => 'Bia Souza', 'horario' => '07:00', 'envio' => random_token(16)]);
    http('POST', $pub, ['nome' => 'Caio Souza', 'horario' => '07:00', 'envio' => random_token(16)]);
    [$st] = http('POST', $pub, ['nome' => 'Davi Souza', 'horario' => '07:00', 'envio' => random_token(16)]);
    check($st === 429, 'excesso de inscrições da mesma conexão é bloqueado');

    [, $h] = http('GET', $pub);
    check(header_value($h, 'X-Frame-Options') === 'DENY' && str_contains((string) header_value($h, 'Content-Security-Policy'), "script-src 'self'"), 'cabeçalhos de segurança presentes');
    [$st, , $html] = http('GET', "$base/r/../../config.php");
    check($st === 404 && !str_contains($html, 'admin_password'), 'tentativa de acessar arquivos por ../ não funciona');
} finally {
    proc_terminate($proc);
    proc_close($proc);
}

// Área administrativa com código de acesso.
[$proc, $base] = start_server($tmp . '/db2.sqlite', 'segredo123');
try {
    $jar = [];
    [$st, $h] = http('GET', "$base/admin", [], $jar);
    check($st === 303 && str_ends_with((string) header_value($h, 'Location'), '/admin/entrar'), 'com código configurado, /admin exige entrada');
    [, , $html] = http('GET', "$base/admin/entrar", [], $jar);
    [$st] = http('POST', "$base/admin/entrar", ['_csrf' => csrf_from($html), 'codigo' => 'errado'], $jar);
    check($st === 401, 'código incorreto é recusado');
    [, , $html] = http('GET', "$base/admin/entrar", [], $jar);
    [$st] = http('POST', "$base/admin/entrar", ['_csrf' => csrf_from($html), 'codigo' => 'segredo123'], $jar);
    [$st, , $html] = http('GET', "$base/admin", [], $jar);
    check($st === 200 && !str_contains($html, 'não está protegida'), 'código correto libera o /admin');
} finally {
    proc_terminate($proc);
    proc_close($proc);
}

array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);

echo "\n{$count} verificações, {$failures} falha(s).\n";
exit($failures ? 1 : 0);
