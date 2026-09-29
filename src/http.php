<?php
declare(strict_types=1);

/*
 * Utilitários HTTP: caminhos, renderização, sessão, CSRF e acesso ao /admin.
 */

/** Caminho base quando a aplicação roda em um subdiretório (ex.: /relogio). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_path');
    if ($configured !== null) {
        return $base = rtrim((string) $configured, '/');
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Hospedagem em que o .htaccess da raiz reescreve para /public: as URLs não contêm "/public".
    if (str_ends_with($base, '/public') && !str_starts_with($uri, $base . '/') && $uri !== $base) {
        $base = substr($base, 0, -strlen('/public'));
    }
    return $base;
}

function request_path(): string
{
    $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    if (str_starts_with($uri, '/index.php')) {
        $uri = substr($uri, strlen('/index.php'));
    }
    $uri = '/' . trim($uri, '/');
    return $uri;
}

function url(string $path): string
{
    return base_path() . $path;
}

function absolute_url(string $path): string
{
    $appUrl = config('app_url');
    if ($appUrl) {
        return rtrim((string) $appUrl, '/') . $path;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $file): string
{
    $path = dirname(__DIR__) . '/public/assets/' . $file;
    $v = is_file($path) ? filemtime($path) : 0;
    return url('/assets/' . $file) . '?v=' . $v;
}

function render(string $view, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

function render_error(int $status, string $heading, string $message): void
{
    if (!headers_sent()) {
        render('error', ['title' => $heading, 'heading' => $heading, 'message' => $message, 'noindex' => true], $status);
    }
}

function not_found(): never
{
    render_error(404, 'Página não encontrada', 'Confira se o link foi copiado por completo. Se ele veio pelo WhatsApp, peça o link novamente à organização.');
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/**
 * Recusa envios de formulário feitos a partir de outros sites. Navegadores
 * informam a origem no cabeçalho Origin; sem ele (navegadores antigos), aceita.
 */
function same_origin_request(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || $origin === 'null') {
        return true;
    }
    return strcasecmp((string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : ''), (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (($_SERVER['HTTPS'] ?? '') === 'on') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

// ---------------------------------------------------------------------------
// Sessão, CSRF e mensagens (usados apenas na área administrativa)
// ---------------------------------------------------------------------------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('relogio_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/admin',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        render_error(419, 'Sessão expirada', 'A página ficou aberta por muito tempo. Volte, recarregue a página e tente de novo.');
        exit;
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

// ---------------------------------------------------------------------------
// Proteção opcional do /admin por código de acesso
// ---------------------------------------------------------------------------

function admin_password_enabled(): bool
{
    return (string) config('admin_password', '') !== '';
}

function admin_is_authenticated(): bool
{
    return admin_password_enabled() && !empty($_SESSION['admin']);
}

function check_admin_password(string $attempt): bool
{
    $expected = (string) config('admin_password', '');
    if ($expected === '') {
        return false;
    }
    // Aceita tanto texto puro quanto um hash gerado por password_hash().
    if (str_starts_with($expected, '$2y$') || str_starts_with($expected, '$argon2')) {
        return password_verify($attempt, $expected);
    }
    return hash_equals($expected, $attempt);
}
