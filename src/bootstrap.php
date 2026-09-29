<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

require __DIR__ . '/functions.php';

/*
 * Configuração: config.php (opcional) na raiz do projeto, com fallback para
 * variáveis de ambiente. Veja config.example.php.
 */
function config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $file = dirname(__DIR__) . '/config.php';
        $config = is_file($file) ? (require $file) : [];
    }
    $env = getenv('RELOGIO_' . strtoupper($key));
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = config('db_path', dirname(__DIR__) . '/data/relogio.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // WAL permite leituras durante escritas; busy_timeout faz inscrições
    // simultâneas aguardarem a vez em vez de falhar.
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS events (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            public_token TEXT NOT NULL UNIQUE,
            date         TEXT NOT NULL,          -- AAAA-MM-DD (America/Sao_Paulo)
            start_time   TEXT NOT NULL,          -- HH:MM
            end_time     TEXT NOT NULL,          -- HH:MM
            created_at   TEXT NOT NULL,
            updated_at   TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS signups (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id         INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            slot_start       TEXT NOT NULL,      -- HH:MM, início do bloco de 1 hora
            name             TEXT NOT NULL,
            submission_token TEXT NOT NULL UNIQUE, -- evita duplicação por duplo clique/reenvio
            created_at       TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS signups_event_slot ON signups(event_id, slot_start);
        SQL);
}

function now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
}

// ---------------------------------------------------------------------------
// Repositório
// ---------------------------------------------------------------------------

function create_event(string $date, string $start, string $end): array
{
    $now = now();
    $stmt = db()->prepare('INSERT INTO events (public_token, date, start_time, end_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([random_token(12), $date, $start, $end, $now, $now]);
    return find_event_by_id((int) db()->lastInsertId());
}

function find_event_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function find_event_by_token(string $token): ?array
{
    if (!is_public_token($token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM events WHERE public_token = ?');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function list_events(): array
{
    return db()->query(
        'SELECT e.*, (SELECT COUNT(*) FROM signups s WHERE s.event_id = e.id) AS signup_count
         FROM events e ORDER BY e.date DESC, e.start_time DESC, e.id DESC'
    )->fetchAll();
}

/** Somente os blocos que têm pelo menos uma inscrição — sem nomes nem contagem. */
function occupied_slots(int $eventId): array
{
    $stmt = db()->prepare('SELECT DISTINCT slot_start FROM signups WHERE event_id = ?');
    $stmt->execute([$eventId]);
    return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

/** Para a área administrativa: nomes agrupados por bloco. */
function signups_by_slot(int $eventId): array
{
    $stmt = db()->prepare('SELECT slot_start, name, created_at FROM signups WHERE event_id = ? ORDER BY slot_start, id');
    $stmt->execute([$eventId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['slot_start']][] = $row;
    }
    return $out;
}

/**
 * Registra uma inscrição. É somente INSERT (nunca sobrescreve outra inscrição).
 * Se o mesmo submission_token já foi gravado (duplo clique, recarregar a página),
 * a inscrição existente é reaproveitada.
 */
function create_signup(int $eventId, string $slotStart, string $name, string $submissionToken): void
{
    $stmt = db()->prepare(
        'INSERT INTO signups (event_id, slot_start, name, submission_token, created_at)
         VALUES (?, ?, ?, ?, ?) ON CONFLICT(submission_token) DO NOTHING'
    );
    $stmt->execute([$eventId, $slotStart, $name, $submissionToken, now()]);
}

function find_signup_by_submission(int $eventId, string $submissionToken): ?array
{
    $stmt = db()->prepare('SELECT * FROM signups WHERE event_id = ? AND submission_token = ?');
    $stmt->execute([$eventId, $submissionToken]);
    return $stmt->fetch() ?: null;
}
