<?php
declare(strict_types=1);

namespace FotoApp;

final class Config
{
    private const SETTINGS_FILE = APP_DATA . '/settings.json';

    public static function ensureDefaultFiles(): void
    {
        if (!is_file(self::SETTINGS_FILE)) {
            file_put_contents(self::SETTINGS_FILE, json_encode(self::defaults(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $categoriesFile = APP_DATA . '/categories.json';
        if (!is_file($categoriesFile)) {
            file_put_contents($categoriesFile, json_encode(self::defaults()['categories'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public static function load(): array
    {
        $settings = self::readJson(self::SETTINGS_FILE, self::defaults());
        $categories = self::readJson(APP_DATA . '/categories.json', self::defaults()['categories']);
        $settings['categories'] = $categories;

        return array_replace_recursive(self::defaults(), $settings);
    }

    public static function save(array $config): void
    {
        $settings = $config;
        $categories = $settings['categories'] ?? self::defaults()['categories'];
        unset($settings['categories']);

        file_put_contents(self::SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents(APP_DATA . '/categories.json', json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function readJson(string $path, array $default): array
    {
        if (!is_file($path)) {
            return $default;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $default;
    }

    private static function defaults(): array
    {
        return [
            'app_name' => 'Foto Scan App',
            'storage_mode' => 'local',
            'default_category' => 'WE',
            'branding' => [
                'logo_path' => '',
            ],
            'remote' => [
                'host' => '',
                'port' => 21,
                'username' => '',
                'password' => '',
                'base_path' => '',
            ],
            'categories' => [
                'WE' => ['label' => 'Wareneingang', 'is_default' => true],
                'KO' => ['label' => 'Kommissionierung', 'is_default' => false],
                'VS' => ['label' => 'Versand', 'is_default' => false],
            ],
        ];
    }
}
