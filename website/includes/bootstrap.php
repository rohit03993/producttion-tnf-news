<?php

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

function site_root_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return '/';
    }
    return $dir;
}

function admin_self(string $query = ''): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    return $query === '' ? $script : $script . '?' . $query;
}

function public_self(string $query = ''): string
{
    $root = site_root_path();
    $path = ($root === '/' ? '' : $root) . '/index.php';
    return $query === '' ? $path : $path . '?' . $query;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('anupriya_host');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => site_root_path(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

function data_path(string $file): string
{
    return dirname(__DIR__) . '/data/' . $file;
}

function site_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $path = data_path('config.php');
    $loaded = is_file($path) ? include $path : [];
    if (!is_array($loaded)) {
        $loaded = [];
    }
    $config = [
        'password_hash' => (string)($loaded['password_hash'] ?? ''),
        'whatsapp' => (string)($loaded['whatsapp'] ?? ''),
        'timezone' => (string)($loaded['timezone'] ?? 'Asia/Kolkata'),
    ];
    return $config;
}

function site_timezone(): DateTimeZone
{
    try {
        return new DateTimeZone(site_config()['timezone']);
    } catch (Exception $e) {
        return new DateTimeZone('Asia/Kolkata');
    }
}

function today_string(): string
{
    return (new DateTime('now', site_timezone()))->format('Y-m-d');
}

function now_stamp(): string
{
    return (new DateTime('now', site_timezone()))->format('Y-m-d H:i');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clip(string $value, int $max): string
{
    $value = trim($value);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
    return strlen($value) > $max ? substr($value, 0, $max) : $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? '';
    $real = $_SESSION['csrf'] ?? '';
    return is_string($sent) && is_string($real) && $real !== '' && hash_equals($real, $sent);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function read_json(string $file): array
{
    $path = data_path($file);
    if (!is_file($path)) {
        return [];
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode($raw === false ? '[]' : $raw, true);
    return is_array($data) ? $data : [];
}

function write_json(string $file, array $data): bool
{
    $path = data_path($file);
    $fh = fopen($path, 'c+b');
    if ($fh === false) {
        return false;
    }
    flock($fh, LOCK_EX);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }
    ftruncate($fh, 0);
    rewind($fh);
    $ok = fwrite($fh, $json) !== false;
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}

function event_types(): array
{
    return ['Wedding', 'Corporate', 'School', 'Brand', 'Cultural'];
}

function split_events(array $events, string $today): array
{
    $upcoming = [];
    $past = [];
    foreach ($events as $event) {
        if (!is_array($event) || empty($event['date']) || !is_string($event['date'])) {
            continue;
        }
        if ($event['date'] >= $today) {
            $upcoming[] = $event;
        } else {
            $past[] = $event;
        }
    }
    usort($upcoming, function ($a, $b) {
        return strcmp((string)$a['date'], (string)$b['date']);
    });
    usort($past, function ($a, $b) {
        return strcmp((string)$b['date'], (string)$a['date']);
    });
    return [$upcoming, $past];
}

function format_event_date(string $iso): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$dt || $dt->format('Y-m-d') !== $iso) {
        return $iso;
    }
    return $dt->format('j M Y');
}

function whatsapp_link(): string
{
    $number = preg_replace('/\D+/', '', site_config()['whatsapp']);
    if ($number === '') {
        return '';
    }
    $text = rawurlencode('Hello Anupriya, I would like to book you for an event.');
    return 'https://wa.me/' . $number . '?text=' . $text;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function login_blocked(): bool
{
    $state = read_json('login.json');
    return (int)($state['until'] ?? 0) > time();
}

function login_note_failure(): void
{
    $state = read_json('login.json');
    $fails = (int)($state['fails'] ?? 0) + 1;
    $until = 0;
    if ($fails >= 8) {
        $fails = 0;
        $until = time() + 600;
    }
    write_json('login.json', ['fails' => $fails, 'until' => $until]);
}

function login_note_success(): void
{
    write_json('login.json', ['fails' => 0, 'until' => 0]);
}

function valid_date(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

start_session();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
