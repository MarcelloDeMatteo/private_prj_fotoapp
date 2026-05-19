<?php
declare(strict_types=1);

namespace FotoApp;

final class PhotoRepository
{
    public function getManifest(string $manifestId): ?array
    {
        $path = $this->manifestPath($manifestId);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function saveManifest(array $manifest): void
    {
        file_put_contents($this->manifestPath($manifest['manifest_id']), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function findPhoto(string $manifestId, string $fileName): ?array
    {
        $manifest = $this->getManifest($manifestId);
        if (!$manifest) {
            return null;
        }

        foreach ($manifest['photos'] as $photo) {
            if (($photo['file_name'] ?? '') === $fileName) {
                return $photo;
            }
        }

        return null;
    }

    public function deletePhoto(string $manifestId, string $photoId, PhotoStorage $storage): void
    {
        $manifest = $this->getManifest($manifestId);
        if (!$manifest) {
            return;
        }

        foreach ($manifest['photos'] as $index => $photo) {
            if (($photo['photo_id'] ?? '') !== $photoId) {
                continue;
            }

            $storage->deleteLocalFile((string) $photo['local_path']);
            array_splice($manifest['photos'], $index, 1);
            break;
        }

        $this->saveManifest($manifest);
    }

    public function deleteManifest(string $manifestId, PhotoStorage $storage): void
    {
        $manifest = $this->getManifest($manifestId);
        if (!$manifest) {
            return;
        }

        foreach ((array)($manifest['photos'] ?? []) as $photo) {
            $storage->deleteLocalFile((string)($photo['local_path'] ?? ''));
        }

        $manifestPath = $this->manifestPath($manifestId);
        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
    }

    public function updatePhoto(string $manifestId, string $photoId, string $newOrder, string $newCategory, array $config, PhotoStorage $storage): void
    {
        $manifest = $this->getManifest($manifestId);
        if (!$manifest) {
            return;
        }

        $categoryCode = $newCategory !== '' ? $newCategory : (string) $manifest['category_code'];
        $categoryLabel = (string)($config['categories'][$categoryCode]['label'] ?? $categoryCode);
        $order = $newOrder !== '' ? $newOrder : (string) $manifest['order_number'];

        foreach ($manifest['photos'] as &$photo) {
            if (($photo['photo_id'] ?? '') !== $photoId) {
                continue;
            }

            $oldPath = (string) $photo['local_path'];
            $extension = pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'jpg';
            $sequence = $photo['sequence'] ?? 1;
            $newFileName = $storage->buildFileName($order, $categoryCode, (int) $sequence, $extension);
            $newPath = APP_UPLOADS . '/' . $newFileName;
            $storage->renameLocalFile($oldPath, $newPath);

            $photo['order_number'] = $order;
            $photo['category_code'] = $categoryCode;
            $photo['category_label'] = $categoryLabel;
            $photo['file_name'] = $newFileName;
            $photo['local_path'] = $newPath;
            $photo['updated_at'] = date('c');
            break;
        }

        $manifest['order_number'] = $order;
        $manifest['category_code'] = $categoryCode;
        $manifest['category_label'] = $categoryLabel;
        $manifest['updated_at'] = date('c');

        $this->saveManifest($manifest);
    }

    public function search(string $query, string $category, string $owner, ?int $ownerId): array
    {
        $results = [];
        foreach ($this->allManifests() as $manifest) {
            if ($ownerId !== null && (int)($manifest['user_id'] ?? 0) !== $ownerId) {
                continue;
            }
            if ($category !== '' && ($manifest['category_code'] ?? '') !== $category) {
                continue;
            }
            if ($query !== '' && !str_contains((string)($manifest['order_number'] ?? ''), $query) && !str_contains((string)($manifest['search_blob'] ?? ''), strtolower($query))) {
                continue;
            }
            if ($owner !== '' && !str_contains(strtolower((string)($manifest['username'] ?? '')), strtolower($owner))) {
                continue;
            }
            $results[] = $manifest;
        }

        usort($results, static fn (array $a, array $b): int => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));

        return $results;
    }

    public function recentForUser(?int $userId, bool $todayOnly = false): array
    {
        $items = $this->allManifests();
        if ($userId !== null) {
            $items = array_values(array_filter($items, static fn (array $item): bool => (int)($item['user_id'] ?? 0) === $userId));
        }

        if ($todayOnly) {
            $today = date('Y-m-d');
            $items = array_values(array_filter($items, static function (array $item) use ($today): bool {
                $timestamp = (string)($item['updated_at'] ?? $item['created_at'] ?? '');
                return str_starts_with($timestamp, $today);
            }));
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));

        return array_slice($items, 0, 12);
    }

    public function manifestsForGroup(string $orderNumber, string $categoryCode, int $userId, ?string $datePrefix = null): array
    {
        $items = array_values(array_filter($this->allManifests(), static function (array $item) use ($orderNumber, $categoryCode, $userId, $datePrefix): bool {
            if ((string)($item['order_number'] ?? '') !== $orderNumber) {
                return false;
            }
            if ((string)($item['category_code'] ?? '') !== $categoryCode) {
                return false;
            }
            if ((int)($item['user_id'] ?? 0) !== $userId) {
                return false;
            }
            if ($datePrefix !== null && $datePrefix !== '') {
                $timestamp = (string)($item['updated_at'] ?? $item['created_at'] ?? '');
                if (!str_starts_with($timestamp, $datePrefix)) {
                    return false;
                }
            }

            return true;
        }));

        usort($items, static fn (array $a, array $b): int => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));

        return $items;
    }

    private function allManifests(): array
    {
        $files = glob(APP_MANIFESTS . '/*.json') ?: [];
        $items = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $decoded['manifest_file'] = basename($file);
                $items[] = $decoded;
            }
        }

        return $items;
    }

    private function manifestPath(string $manifestId): string
    {
        return APP_MANIFESTS . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $manifestId) . '.json';
    }
}
