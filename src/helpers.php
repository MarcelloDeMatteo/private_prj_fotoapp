<?php
declare(strict_types=1);

namespace FotoApp;

const APP_ROOT = __DIR__ . '/..';
const APP_STORAGE = APP_ROOT . '/storage';
const APP_DATA = APP_STORAGE . '/data';
const APP_UPLOADS = APP_STORAGE . '/uploads';
const APP_MANIFESTS = APP_STORAGE . '/manifests';
const APP_BRANDING = APP_DATA . '/branding';
const APP_DB = APP_STORAGE . '/app.sqlite';

function config(): array
{
    return Config::load();
}

function database(): Database
{
    return Database::instance();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consume_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Ungültige Sitzung. Bitte erneut versuchen.');
    }
}

function redirect(string $route): never
{
    header('Location: ' . route_url($route));
    exit;
}

function app_base_url(): string
{
    static $base = null;
    if (is_string($base)) {
        return $base;
    }

    $configured = trim((string) (getenv('FOTOAPP_BASE_URL') ?: 'http://172.16.11.241/fotoapp'));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        return $base;
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $base = '';
        return $base;
    }

    $base = rtrim($dir, '/');
    return $base;
}

function app_url(string $path = ''): string
{
    $base = app_base_url();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . ltrim($path, '/');
}

function route_url(string $route): string
{
    return app_url('?route=' . ltrim($route, '?'));
}

function default_category(array $config): string
{
    foreach ($config['categories'] as $code => $data) {
        if (!empty($data['is_default'])) {
            return (string) $code;
        }
    }

    return (string) ($config['default_category'] ?? array_key_first($config['categories']) ?? 'WE');
}

function save_settings(array $config): void
{
    Config::save($config);
}

function format_datetime_ch(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    try {
        $targetTimezone = new \DateTimeZone(date_default_timezone_get());
        $hasOffset = preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $value) === 1;
        $date = $hasOffset
            ? new \DateTimeImmutable($value)
            : new \DateTimeImmutable($value, $targetTimezone);
        $date = $date->setTimezone($targetTimezone);
        return $date->format('d.m.Y H:i');
    } catch (\Throwable) {
        return $value;
    }
}

function app_request_id(): string
{
    static $requestId = null;
    if (is_string($requestId) && $requestId !== '') {
        return $requestId;
    }

    $headerRequestId = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($headerRequestId !== '') {
        $requestId = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '_', $headerRequestId) ?? '', 0, 80);
        if ($requestId !== '') {
            return $requestId;
        }
    }

    try {
        $requestId = bin2hex(random_bytes(8));
    } catch (\Throwable) {
        $requestId = (string)time();
    }

    return $requestId;
}

function ini_size_to_bytes(string $size): int
{
    $trimmed = trim($size);
    if ($trimmed === '') {
        return 0;
    }

    $unit = strtolower(substr($trimmed, -1));
    $number = (float)$trimmed;

    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
        UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
        UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
        UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
        UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
        UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
        UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
        UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
        default => 'UPLOAD_ERR_UNKNOWN',
    };
}

function app_log(string $channel, array $context = []): void
{
    $safeChannel = preg_replace('/[^a-z0-9._-]/i', '_', $channel) ?: 'app';
    $path = APP_STORAGE . '/logs/' . $safeChannel . '.log';
    $entry = [
        'ts' => date('c'),
        'request_id' => app_request_id(),
        'channel' => $safeChannel,
        'route' => (string)($_GET['route'] ?? ''),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
        'remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'context' => $context,
    ];

    try {
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = json_encode([
                'ts' => date('c'),
                'request_id' => app_request_id(),
                'channel' => $safeChannel,
                'context' => ['log_error' => 'json_encode_failed'],
            ]);
        }
        if (!is_dir(APP_STORAGE . '/logs')) {
            @mkdir(APP_STORAGE . '/logs', 0777, true);
        }
        @file_put_contents($path, (string)$line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        error_log('app_log failed: ' . $e->getMessage());
    }
}
