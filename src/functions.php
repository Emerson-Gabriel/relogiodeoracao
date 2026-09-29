<?php
declare(strict_types=1);

/*
 * Regras de negócio puras (sem banco nem HTTP), para facilitar os testes.
 */

const SLOT_MINUTES = 60;
const NAME_MIN = 2;
const NAME_MAX = 80;

/** Converte "HH:MM" em minutos desde 00:00, ou null se inválido. */
function time_to_minutes(string $time): ?int
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
        return null;
    }
    return (int) $m[1] * 60 + (int) $m[2];
}

function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/** Aceita "HH:MM" ou "HH:MM:SS" (alguns navegadores enviam segundos). */
function normalize_time(string $time): string
{
    $time = trim($time);
    if (preg_match('/^(\d{2}:\d{2}):00$/', $time, $m)) {
        return $m[1];
    }
    return $time;
}

function is_valid_date(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $mo, $d] = array_map('intval', explode('-', $date));
    return checkdate($mo, $d, $y);
}

/**
 * Valida os dados de uma edição. Retorna lista de mensagens de erro (vazia = ok).
 */
function validate_event(string $date, string $start, string $end): array
{
    $errors = [];
    if ($date === '') {
        $errors[] = 'Informe a data do relógio de oração.';
    } elseif (!is_valid_date($date)) {
        $errors[] = 'A data informada não é válida.';
    }

    $s = $start === '' ? null : time_to_minutes($start);
    $e = $end === '' ? null : time_to_minutes($end);

    if ($start === '') {
        $errors[] = 'Informe o horário de início.';
    } elseif ($s === null) {
        $errors[] = 'O horário de início não é válido. Use o formato HH:MM.';
    }
    if ($end === '') {
        $errors[] = 'Informe o horário de término.';
    } elseif ($e === null) {
        $errors[] = 'O horário de término não é válido. Use o formato HH:MM.';
    }

    if ($s !== null && $e !== null) {
        if ($e <= $s) {
            $errors[] = 'O horário de término deve ser depois do horário de início, no mesmo dia (o evento não pode passar da meia-noite).';
        } elseif (($e - $s) % SLOT_MINUTES !== 0) {
            $errors[] = 'Cada horário de oração dura exatamente 1 hora, então o período entre início e término precisa ter horas completas (por exemplo, 07:00 às 10:00). Ajuste o horário de término.';
        }
    }
    return $errors;
}

/**
 * Gera os blocos de 1 hora. Cada item: ['start' => 'HH:MM', 'end' => 'HH:MM'].
 * O horário de término é o limite do último bloco.
 */
function generate_slots(string $start, string $end): array
{
    $s = time_to_minutes($start);
    $e = time_to_minutes($end);
    if ($s === null || $e === null || $e <= $s || ($e - $s) % SLOT_MINUTES !== 0) {
        return [];
    }
    $slots = [];
    for ($t = $s; $t < $e; $t += SLOT_MINUTES) {
        $slots[] = ['start' => minutes_to_time($t), 'end' => minutes_to_time($t + SLOT_MINUTES)];
    }
    return $slots;
}

/** Remove espaços extras e caracteres de controle do nome. */
function normalize_name(string $name): string
{
    $name = preg_replace('/[\p{C}]+/u', ' ', $name) ?? '';
    $name = preg_replace('/\s+/u', ' ', $name) ?? '';
    return trim($name);
}

function validate_name(string $name): array
{
    $len = mb_strlen($name);
    if ($len === 0) {
        return ['Informe o seu nome.'];
    }
    if ($len < NAME_MIN) {
        return ['O nome precisa ter pelo menos ' . NAME_MIN . ' letras.'];
    }
    if ($len > NAME_MAX) {
        return ['O nome pode ter no máximo ' . NAME_MAX . ' caracteres.'];
    }
    if (!preg_match('/\p{L}/u', $name)) {
        return ['Informe um nome válido.'];
    }
    return [];
}

/** Token aleatório seguro em base64url (sem "=", "+", "/"). */
function random_token(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function is_public_token(string $t): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{16,64}$/', $t);
}

/** "2026-10-02" -> "sexta-feira, 2 de outubro de 2026" */
function format_date_long(string $date): string
{
    static $days = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    static $months = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $dt = new DateTimeImmutable($date, new DateTimeZone('America/Sao_Paulo'));
    return sprintf('%s, %d de %s de %d', $days[(int) $dt->format('w')], (int) $dt->format('j'), $months[(int) $dt->format('n')], (int) $dt->format('Y'));
}

/** "2026-10-02" -> "02/10/2026" */
function format_date_short(string $date): string
{
    return (new DateTimeImmutable($date))->format('d/m/Y');
}

/** "07:00" -> "07h", "07:30" -> "07h30" */
function format_time(string $time): string
{
    [$h, $m] = explode(':', $time);
    return $m === '00' ? "{$h}h" : "{$h}h{$m}";
}
