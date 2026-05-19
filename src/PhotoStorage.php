<?php
declare(strict_types=1);

namespace FotoApp;

final class PhotoStorage
{
    public function __construct(private array $config)
    {
    }

    public function storeUploads(string $orderNumber, string $categoryCode, mixed $files, array $user, array $config): array
    {
        $categoryCode = $categoryCode !== '' ? $categoryCode : default_category($config);
        $categoryLabel = (string)($config['categories'][$categoryCode]['label'] ?? $categoryCode);
        $manifestId = $this->manifestId($orderNumber, $categoryCode);
        $timestamp = date('c');
        $photos = [];
        $sequence = $this->nextSequenceForOrderCategory($orderNumber, $categoryCode);

        $uploadedFiles = $this->normalizeUploadArray($files);
        foreach ($uploadedFiles as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmp = (string) $file['tmp_name'];
            if (!is_uploaded_file($tmp)) {
                continue;
            }

            $extension = $this->extensionFor($file['name'] ?? '', $tmp);
            $fileName = $this->buildFileName($orderNumber, $categoryCode, $sequence, $extension);
            $localPath = APP_UPLOADS . '/' . $fileName;
            if (!move_uploaded_file($tmp, $localPath)) {
                continue;
            }

            $this->syncRemote($localPath, $fileName);

            $photos[] = [
                'photo_id' => bin2hex(random_bytes(8)),
                'file_name' => $fileName,
                'local_path' => $localPath,
                'mime_type' => (string)($file['type'] ?? 'image/jpeg'),
                'sequence' => $sequence,
                'order_number' => $orderNumber,
                'category_code' => $categoryCode,
                'category_label' => $categoryLabel,
                'uploaded_by' => $user['username'],
                'user_id' => (int) $user['id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $sequence++;
        }

        $manifest = [
            'manifest_id' => $manifestId,
            'order_number' => $orderNumber,
            'category_code' => $categoryCode,
            'category_label' => $categoryLabel,
            'username' => $user['username'],
            'user_id' => (int) $user['id'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'search_blob' => strtolower($orderNumber . ' ' . $categoryCode . ' ' . $categoryLabel . ' ' . $user['username']),
            'photos' => $photos,
        ];

        file_put_contents(APP_MANIFESTS . '/' . $manifestId . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['manifest' => $manifest];
    }

    public function buildFileName(string $orderNumber, string $categoryCode, int $sequence, string $extension): string
    {
        $safeOrder = preg_replace('/[^A-Za-z0-9._-]/', '_', $orderNumber);
        $safeCategory = preg_replace('/[^A-Za-z0-9._-]/', '_', $categoryCode);

        return sprintf('co_%s_%s_%03d.%s', $safeOrder, $safeCategory, $sequence, strtolower($extension));
    }

    public function streamLocalFile(string $path, string $mimeType): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Datei nicht gefunden';
            return;
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }

    public function deleteLocalFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function renameLocalFile(string $oldPath, string $newPath): void
    {
        if (is_file($oldPath)) {
            rename($oldPath, $newPath);
        }
    }

    private function syncRemote(string $localPath, string $fileName): void
    {
        $remote = $this->config['remote'] ?? [];
        $mode = $this->determineRemoteMode($remote);
        if ($mode === 'local') {
            return;
        }

        if ($mode === 'ftp' && function_exists('ftp_connect')) {
            $conn = @ftp_connect((string) $remote['host'], (int)($remote['port'] ?? 21), 20);
            if (!$conn) {
                return;
            }
            if (@ftp_login($conn, (string) $remote['username'], (string) $remote['password'])) {
                $basePath = trim((string)($remote['base_path'] ?? ''), '/');
                if ($basePath !== '') {
                    $parts = explode('/', $basePath);
                    $current = '';
                    foreach ($parts as $part) {
                        $current .= '/' . $part;
                        @ftp_mkdir($conn, $current);
                    }
                }
                @ftp_put($conn, rtrim((string)($remote['base_path'] ?? ''), '/') . '/' . $fileName, $localPath, FTP_BINARY);
            }
            @ftp_close($conn);
        }

        if ($mode === 'sftp' && function_exists('ssh2_connect')) {
            $conn = @ssh2_connect((string) $remote['host'], (int)($remote['port'] ?? 22));
            if (!$conn) {
                return;
            }
            if (@ssh2_auth_password($conn, (string) $remote['username'], (string) $remote['password'])) {
                $sftp = @ssh2_sftp($conn);
                if ($sftp) {
                    $remotePath = 'ssh2.sftp://' . intval($sftp) . '/' . trim((string)($remote['base_path'] ?? ''), '/') . '/' . $fileName;
                    $remoteStream = fopen($remotePath, 'w');
                    if ($remoteStream) {
                        $localStream = fopen($localPath, 'r');
                        if ($localStream) {
                            stream_copy_to_stream($localStream, $remoteStream);
                            fclose($localStream);
                        }
                        fclose($remoteStream);
                    }
                }
            }
        }
    }

    private function normalizeUploadArray(mixed $files): array
    {
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }

        $normalized = [];
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($index = 0; $index < $count; $index++) {
                $normalized[] = [
                    'name' => $files['name'][$index] ?? '',
                    'type' => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$index] ?? 0,
                ];
            }

            return $normalized;
        }

        return [$files];
    }

    private function extensionFor(string $originalName, string $tmpPath): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        $info = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $info ? finfo_file($info, $tmpPath) : null;
        if ($info) {
            finfo_close($info);
        }

        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    private function manifestId(string $orderNumber, string $categoryCode): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9_-]/', '_', $orderNumber . '_' . $categoryCode) . '_' . date('YmdHis'));
    }

    private function determineRemoteMode(array $remote): string
    {
        $host = trim((string)($remote['host'] ?? ''));
        $username = trim((string)($remote['username'] ?? ''));
        $password = (string)($remote['password'] ?? '');

        if ($host === '' || $username === '' || $password === '') {
            return 'local';
        }

        $port = (int)($remote['port'] ?? 21);
        return $port === 22 ? 'sftp' : 'ftp';
    }

    private function nextSequenceForOrderCategory(string $orderNumber, string $categoryCode): int
    {
        $safeOrder = preg_replace('/[^A-Za-z0-9._-]/', '_', $orderNumber);
        $safeCategory = preg_replace('/[^A-Za-z0-9._-]/', '_', $categoryCode);
        $pattern = APP_UPLOADS . '/co_' . $safeOrder . '_' . $safeCategory . '_*.*';

        $maxSequence = 0;
        foreach (glob($pattern) ?: [] as $file) {
            $fileName = basename($file);
            if (!preg_match('/^co_[^_]+_[^_]+_(\d+)\.[a-z0-9]+$/i', $fileName, $matches)) {
                continue;
            }

            $sequence = (int)($matches[1] ?? 0);
            if ($sequence > $maxSequence) {
                $maxSequence = $sequence;
            }
        }

        return $maxSequence + 1;
    }
}
