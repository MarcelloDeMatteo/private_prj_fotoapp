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
        $date = new \DateTimeImmutable($value);
        $date = $date->setTimezone(new \DateTimeZone('Europe/Zurich'));
        return $date->format('d.m.Y H:i');
    } catch (\Throwable) {
        return $value;
    }
}

function get_current_message(): ?array
{
    $file = APP_MANIFESTS . '/.broadcast_message.json';
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

function save_message(string $message): bool
{
    $data = [
        'id' => bin2hex(random_bytes(8)),
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $file = APP_MANIFESTS . '/.broadcast_message.json';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('JSON encoding failed for broadcast message');
        return false;
    }
    $written = @file_put_contents($file, $json);
    if ($written === false) {
        error_log('Failed to write broadcast message to ' . $file . ' - Permission denied or directory does not exist');
        return false;
    }
    return true;
}
