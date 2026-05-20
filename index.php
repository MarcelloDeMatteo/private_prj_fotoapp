<?php

declare(strict_types=1);

$target = __DIR__ . '/public/index.php';

if (!is_file($target)) {
    http_response_code(500);
    echo 'Fehler: app/public/index.php wurde nicht gefunden.';
    exit;
}

require $target;
