<?php
declare(strict_types=1);

namespace FotoApp;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/PhotoStorage.php';
require_once __DIR__ . '/PhotoRepository.php';
require_once __DIR__ . '/View.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'FotoApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

foreach ([APP_STORAGE, APP_DATA, APP_UPLOADS, APP_MANIFESTS, APP_BRANDING, APP_STORAGE . '/logs'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Config::ensureDefaultFiles();
$settings = Config::load();
$configuredTimezone = trim((string)($settings['timezone'] ?? 'Europe/Zurich'));
if ($configuredTimezone === '') {
    $configuredTimezone = 'Europe/Zurich';
}

try {
    date_default_timezone_set($configuredTimezone);
} catch (\Throwable) {
    date_default_timezone_set('Europe/Zurich');
}

Database::instance();
