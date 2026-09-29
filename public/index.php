<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/http.php';

// Quando executado pelo servidor embutido do PHP, arquivos estáticos são servidos diretamente.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

send_security_headers();

$method = $_SERVER['REQUEST_METHOD'];
$path = request_path();

try {
    route($method, $path);
} catch (Throwable $e) {
    error_log((string) $e);
    render_error(500, 'Ocorreu um erro inesperado', 'Tente novamente em alguns instantes. Se o problema continuar, avise a organização do relógio de oração.');
}

function route(string $method, string $path): void
{
    if ($path === '/') {
        render('home', ['title' => 'Relógio de Oração']);
        return;
    }

    // ---- Área pública -------------------------------------------------------
    if (preg_match('#^/r/([A-Za-z0-9_-]+)$#', $path, $m)) {
        $event = find_event_by_token($m[1]) ?? not_found();
        $method === 'POST' ? public_signup($event) : public_event($event);
        return;
    }
    if (preg_match('#^/r/([A-Za-z0-9_-]+)/confirmacao/([A-Za-z0-9_-]+)$#', $path, $m) && $method === 'GET') {
        $event = find_event_by_token($m[1]) ?? not_found();
        $signup = find_signup_by_submission((int) $event['id'], $m[2]) ?? not_found();
        render('public_confirm', ['title' => 'Inscrição confirmada', 'event' => $event, 'signup' => $signup, 'noindex' => true]);
        return;
    }

    // ---- Área administrativa ------------------------------------------------
    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        admin_route($method, $path);
        return;
    }

    not_found();
}

function public_event(array $event, array $errors = [], array $old = []): void
{
    header('Cache-Control: no-store');
    render('public_event', [
        'title' => 'Relógio de Oração — ' . format_date_short($event['date']),
        'event' => $event,
        'slots' => generate_slots($event['start_time'], $event['end_time']),
        'occupied' => occupied_slots((int) $event['id']),
        'errors' => $errors,
        'old' => $old,
        'submission' => random_token(16),
        'noindex' => true,
    ], $errors ? 422 : 200);
}

function public_signup(array $event): void
{
    $name = normalize_name((string) ($_POST['nome'] ?? ''));
    $slot = normalize_time((string) ($_POST['horario'] ?? ''));
    $submission = (string) ($_POST['envio'] ?? '');

    $errors = validate_name($name);
    $validStarts = array_column(generate_slots($event['start_time'], $event['end_time']), 'start');
    if ($slot === '') {
        $errors[] = 'Escolha um horário.';
    } elseif (!in_array($slot, $validStarts, true)) {
        $errors[] = 'O horário escolhido não faz parte deste relógio de oração. Escolha um dos horários da lista.';
    }
    if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $submission)) {
        $submission = random_token(16);
    }

    if ($errors) {
        public_event($event, $errors, ['nome' => $name, 'horario' => $slot]);
        return;
    }

    // Mesmo token de envio com os mesmos dados = reenvio acidental (idempotente).
    // Mesmo token com dados diferentes = nova inscrição intencional (ex.: voltou e escolheu outro horário).
    $existing = find_signup_by_submission((int) $event['id'], $submission);
    if ($existing && ($existing['slot_start'] !== $slot || $existing['name'] !== $name)) {
        $submission = random_token(16);
    }
    create_signup((int) $event['id'], $slot, $name, $submission);

    redirect('/r/' . $event['public_token'] . '/confirmacao/' . $submission);
}

// ---------------------------------------------------------------------------

function admin_route(string $method, string $path): void
{
    start_session();

    if ($path === '/admin/entrar') {
        admin_login($method);
        return;
    }
    if ($path === '/admin/sair' && $method === 'POST') {
        verify_csrf();
        $_SESSION = [];
        session_regenerate_id(true);
        redirect('/admin/entrar');
    }

    if (!admin_is_authenticated()) {
        redirect('/admin/entrar');
    }

    if ($path === '/admin' && $method === 'GET') {
        admin_index();
        return;
    }
    if ($path === '/admin/eventos' && $method === 'POST') {
        verify_csrf();
        $date = trim((string) ($_POST['data'] ?? ''));
        $start = normalize_time((string) ($_POST['inicio'] ?? ''));
        $end = normalize_time((string) ($_POST['fim'] ?? ''));
        $errors = validate_event($date, $start, $end);
        if ($errors) {
            admin_index($errors, ['data' => $date, 'inicio' => $start, 'fim' => $end]);
            return;
        }
        $event = create_event($date, $start, $end);
        flash('Relógio de oração cadastrado. Copie o link público abaixo e compartilhe.');
        redirect('/admin/eventos/' . $event['id']);
    }
    if (preg_match('#^/admin/eventos/(\d+)$#', $path, $m) && $method === 'GET') {
        $event = find_event_by_id((int) $m[1]) ?? not_found();
        render('admin_event', [
            'title' => 'Relógio de ' . format_date_short($event['date']) . ' — Administração',
            'event' => $event,
            'slots' => generate_slots($event['start_time'], $event['end_time']),
            'signups' => signups_by_slot((int) $event['id']),
            'publicUrl' => absolute_url('/r/' . $event['public_token']),
            'admin' => true,
            'noindex' => true,
        ]);
        return;
    }

    not_found();
}

function admin_index(array $errors = [], array $old = []): void
{
    render('admin_index', [
        'title' => 'Administração — Relógio de Oração',
        'events' => list_events(),
        'errors' => $errors,
        'old' => $old,
        'admin' => true,
        'noindex' => true,
    ], $errors ? 422 : 200);
}

function admin_login(string $method): void
{
    if (!admin_password_enabled() || admin_is_authenticated()) {
        redirect('/admin');
    }
    $error = null;
    if ($method === 'POST') {
        verify_csrf();
        if (check_admin_password((string) ($_POST['codigo'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect('/admin');
        }
        usleep(500_000); // atrasa tentativas por força bruta
        $error = 'Código de acesso incorreto.';
    }
    render('admin_login', ['title' => 'Entrar — Administração', 'error' => $error, 'noindex' => true], $error ? 401 : 200);
}
