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
    header('Location: /?route=' . $route);
    exit;
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
