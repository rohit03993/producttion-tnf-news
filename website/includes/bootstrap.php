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

function admin_public_path(): string
{
    $root = site_root_path();
    return ($root === '/' ? '' : $root) . '/admin/';
}

function site_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = site_root_path();
    $base = ($https ? 'https' : 'http') . '://' . $host . ($root === '/' ? '' : $root);
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function save_site_config(array $config): bool
{
    $data = [
        'password_hash' => (string)($config['password_hash'] ?? ''),
        'whatsapp' => (string)($config['whatsapp'] ?? ''),
        'timezone' => (string)($config['timezone'] ?? 'Asia/Kolkata'),
    ];
    $body = "<?php\n\n"
        . "if (realpath(__FILE__) === realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '')) {\n"
        . "    http_response_code(403);\n"
        . "    exit;\n"
        . "}\n\n"
        . 'return ' . var_export($data, true) . ";\n";
    $path = data_path('config.php');
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $body, LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $path);
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

function default_site(): array
{
    return [
        'site_name' => 'Anupriya Productions',
        'brand_line' => 'Event Management & Media Services',
        'hero_eyebrow' => 'Event Management & Media Services',
        'hero_title' => 'Anupriya Tripathi',
        'hero_lede' => 'On the microphone for weddings, school functions, corporate events, and brand launches.',
        'hero_button' => 'Book an event',
        'about_heading' => 'About',
        'about_1' => 'Anupriya Productions is the event and media work of Anupriya Tripathi. These photographs are from wedding stages, a school function, a trade show where she carried a media pass, and a brand launch.',
        'about_2' => 'She anchors from the stage and the podium, and she stays with the audience.',
        'hosting_heading' => 'Hosting',
        'events_heading' => 'Events',
        'gallery_heading' => 'Gallery',
        'book_title' => 'Book an event',
        'book_lead' => 'Send the date and the kind of programme. The note is saved for Anupriya.',
        'footer_name' => 'Anupriya Tripathi',
        'footer_line' => 'Anchor & Event Host',
        'share_title' => 'Anupriya Productions — Event Management & Media Services',
        'share_text' => 'Anupriya Productions, led by Anupriya Tripathi, hosts weddings, school functions, corporate events, and brand launches.',
        'services' => [
            ['title' => 'Weddings', 'text' => 'Hosting for wedding celebrations, from the haldi to the reception.'],
            ['title' => 'Corporate and trade', 'text' => 'Conferences, trade shows, and formal programmes.'],
            ['title' => 'School functions', 'text' => 'Assemblies, annual days, and other school events.'],
            ['title' => 'Brand launches', 'text' => 'Product launches and in-store activations.'],
        ],
        'logo_mark' => ['file' => 'assets/img/logo-mark.webp', 'w' => 168, 'h' => 167, 'alt' => ''],
        'logo' => ['file' => 'assets/img/logo.webp', 'w' => 377, 'h' => 445, 'alt' => 'Anupriya Productions, Event Management and Media Services'],
        'about_image' => ['file' => 'assets/img/about.webp', 'w' => 781, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a black suit'],
        'hero_images' => [
            ['file' => 'assets/img/hero.webp', 'w' => 1021, 'h' => 1280, 'alt' => 'Anupriya Tripathi hosting a wedding on a floral stage'],
            ['file' => 'assets/img/g-016.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi speaking into a microphone in a red gown'],
            ['file' => 'assets/img/g-008.webp', 'w' => 679, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a navy gown against a flower wall'],
            ['file' => 'assets/img/g-007.webp', 'w' => 686, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a black gown on stage'],
            ['file' => 'assets/img/g-015.webp', 'w' => 750, 'h' => 1000, 'alt' => 'Anupriya Tripathi at a reception'],
            ['file' => 'assets/img/g-022.webp', 'w' => 563, 'h' => 1000, 'alt' => 'Anupriya Tripathi at a trade show'],
        ],
        'gallery' => [
            ['file' => 'assets/img/g-001.webp', 'w' => 798, 'h' => 1000, 'alt' => 'Anupriya Tripathi hosting a wedding, microphone in hand, on a floral stage', 'caption' => 'Wedding', 'wide' => false],
            ['file' => 'assets/img/g-016.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi speaking into a microphone in a red gown during a wedding', 'caption' => 'Live hosting', 'wide' => false],
            ['file' => 'assets/img/g-007.webp', 'w' => 686, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a black gown in front of a lit stage screen', 'caption' => 'On stage', 'wide' => false],
            ['file' => 'assets/img/g-008.webp', 'w' => 679, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a navy gown against a flower wall', 'caption' => 'Celebration', 'wide' => false],
            ['file' => 'assets/img/g-011.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a red gown on a decorated wedding stage', 'caption' => 'Wedding stage', 'wide' => false],
            ['file' => 'assets/img/g-015.webp', 'w' => 750, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a silver lehenga at a reception', 'caption' => 'Reception', 'wide' => false],
            ['file' => 'assets/img/g-006.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi at an outdoor haldi celebration', 'caption' => 'Haldi', 'wide' => false],
            ['file' => 'assets/img/g-021.webp', 'w' => 450, 'h' => 1000, 'alt' => 'Anupriya Tripathi hosting a school function with a microphone', 'caption' => 'School function', 'wide' => false],
            ['file' => 'assets/img/g-022.webp', 'w' => 563, 'h' => 1000, 'alt' => 'Anupriya Tripathi with a media pass at the UP International Trade Show', 'caption' => 'Trade show', 'wide' => false],
            ['file' => 'assets/img/g-023.webp', 'w' => 1000, 'h' => 667, 'alt' => 'Anupriya Tripathi speaking at a podium', 'caption' => 'At the podium', 'wide' => true],
            ['file' => 'assets/img/g-026.webp', 'w' => 750, 'h' => 1000, 'alt' => 'Anupriya Tripathi at a brand launch', 'caption' => 'Brand launch', 'wide' => false],
        ],
    ];
}

function is_site_image(string $path): bool
{
    return (bool)preg_match('#^assets/(img|uploads)/[A-Za-z0-9._-]+$#', $path);
}

function clean_images($rows): array
{
    $clean = [];
    if (!is_array($rows)) {
        return $clean;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $file = (string)($row['file'] ?? '');
        if (!is_site_image($file)) {
            continue;
        }
        $clean[] = [
            'file' => $file,
            'w' => max(0, (int)($row['w'] ?? 0)),
            'h' => max(0, (int)($row['h'] ?? 0)),
            'alt' => clip((string)($row['alt'] ?? ''), 180),
            'caption' => clip((string)($row['caption'] ?? ''), 80),
            'wide' => !empty($row['wide']),
        ];
    }
    return $clean;
}

function load_site(): array
{
    $site = default_site();
    $saved = read_json('site.json');
    if (!$saved || array_keys($saved) === range(0, count($saved) - 1)) {
        return $site;
    }
    foreach ($site as $key => $value) {
        if (!array_key_exists($key, $saved) || !is_string($value)) {
            continue;
        }
        $text = trim((string)$saved[$key]);
        if ($text !== '') {
            $site[$key] = $text;
        }
    }
    if (isset($saved['services']) && is_array($saved['services'])) {
        $services = [];
        foreach (array_values($saved['services']) as $index => $row) {
            if ($index > 3 || !is_array($row)) {
                continue;
            }
            $title = clip((string)($row['title'] ?? ''), 80);
            $text = clip((string)($row['text'] ?? ''), 240);
            if ($title === '' && $text === '') {
                continue;
            }
            $services[] = ['title' => $title, 'text' => $text];
        }
        if ($services) {
            $site['services'] = $services;
        }
    }
    foreach (['hero_images', 'gallery'] as $key) {
        $rows = clean_images($saved[$key] ?? null);
        if ($rows) {
            $site[$key] = $rows;
        }
    }
    foreach (['logo_mark', 'logo', 'about_image'] as $key) {
        $rows = clean_images(isset($saved[$key]) ? [$saved[$key]] : []);
        if ($rows) {
            $site[$key] = $rows[0];
        }
    }
    return $site;
}

function save_site(array $site): bool
{
    return write_json('site.json', $site);
}

function store_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || !isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
        return ['error' => 'Choose an image.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['error' => 'That image did not upload. Try a smaller file.'];
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['error' => 'Use an image smaller than 8 MB.'];
    }
    $info = @getimagesize((string)($file['tmp_name'] ?? ''));
    $loaders = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $type = is_array($info) ? (int)$info[2] : 0;
    if (!isset($loaders[$type]) || !function_exists($loaders[$type]) || !function_exists('imagejpeg')) {
        return ['error' => 'Use a JPG, PNG, or WebP image.'];
    }
    $src = @$loaders[$type]((string)$file['tmp_name']);
    if (!$src) {
        return ['error' => 'That image could not be read.'];
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min(1, 1600 / max($sw, $sh, 1));
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));
    $dst = imagecreatetruecolor($dw, $dh);
    $cream = imagecolorallocate($dst, 247, 244, 239);
    imagefilledrectangle($dst, 0, 0, $dw, $dh, $cream);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        imagedestroy($src);
        imagedestroy($dst);
        return ['error' => 'The uploads folder could not be created.'];
    }
    $name = bin2hex(random_bytes(8)) . '.jpg';
    $ok = imagejpeg($dst, $dir . '/' . $name, 82);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) {
        return ['error' => 'The image could not be saved.'];
    }
    return ['file' => 'assets/uploads/' . $name, 'w' => $dw, 'h' => $dh];
}

function forget_upload(string $path): void
{
    if (strpos($path, 'assets/uploads/') !== 0 || !is_site_image($path)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $path;
    if (is_file($full)) {
        unlink($full);
    }
}

start_session();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
