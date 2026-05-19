<?php
declare(strict_types=1);

namespace FotoApp;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $flash = consume_flash();
        $templateFile = APP_ROOT . '/templates/' . $template . '.php';
        require APP_ROOT . '/templates/layout.php';
    }
}
