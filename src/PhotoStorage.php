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
        $requestId = $this->requestId();
        $categoryCode = $categoryCode !== '' ? $categoryCode : default_category($config);
        $categoryLabel = (string)($config['categories'][$categoryCode]['label'] ?? $categoryCode);
        $manifestId = $this->manifestId($orderNumber, $categoryCode);
        $timestamp = date('c');
        $photos = [];
        $sequence = $this->nextSequenceForOrderCategory($orderNumber, $categoryCode);
        $failures = [];

        $this->logDiagnostics([
            'event' => 'scan_upload_start',
            'request_id' => $requestId,
            'order_number' => $orderNumber,
            'category_code' => $categoryCode,
            'user_id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'upload_tmp_dir' => (string)(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
            'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
            'post_max_size' => (string)ini_get('post_max_size'),
            'memory_limit' => (string)ini_get('memory_limit'),
            'max_file_uploads' => (string)ini_get('max_file_uploads'),
            'app_uploads_dir' => APP_UPLOADS,
            'app_uploads_dir_writable' => is_writable(APP_UPLOADS),
        ]);

        $uploadedFiles = $this->normalizeUploadArray($files);
        foreach ($uploadedFiles as $index => $file) {
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $fileContext = [
                'event' => 'scan_upload_file',
                'request_id' => $requestId,
                'index' => $index,
                'original_name' => (string)($file['name'] ?? ''),
                'mime' => (string)($file['type'] ?? ''),
                'size' => (int)($file['size'] ?? 0),
                'error_code' => $errorCode,
                'error_label' => $this->uploadErrorMessage($errorCode),
            ];

            if ($errorCode !== UPLOAD_ERR_OK) {
                $failures[] = $fileContext;
                $this->logDiagnostics($fileContext + ['status' => 'failed_before_move']);
                continue;
            }

            $tmp = (string) $file['tmp_name'];
            if (!is_uploaded_file($tmp)) {
                $failure = $fileContext + [
                    'status' => 'failed_not_uploaded_file',
                    'tmp_name' => $tmp,
                    'tmp_exists' => is_file($tmp),
                    'tmp_dir' => dirname($tmp),
                    'tmp_dir_writable' => is_writable(dirname($tmp)),
                ];
                $failures[] = $failure;
                $this->logDiagnostics($failure);
                continue;
            }

            $extension = $this->extensionFor($file['name'] ?? '', $tmp);
            $fileName = $this->buildFileName($orderNumber, $categoryCode, $sequence, $extension);
            $localPath = APP_UPLOADS . '/' . $fileName;

            $incomingSize = (int)($file['size'] ?? 0);
            $targetDir = dirname($localPath);
            $freeBytes = @disk_free_space($targetDir);
            $requiredBytes = max(1024 * 1024, $incomingSize + (5 * 1024 * 1024));
            if ($freeBytes !== false && $freeBytes < $requiredBytes) {
                $failure = $fileContext + [
                    'status' => 'failed_no_space_precheck',
                    'tmp_name' => $tmp,
                    'local_path' => $localPath,
                    'incoming_size_bytes' => $incomingSize,
                    'free_bytes' => (int)$freeBytes,
                    'required_bytes' => $requiredBytes,
                ];
                $failures[] = $failure;
                $this->logDiagnostics($failure);
                continue;
            }

            if (!move_uploaded_file($tmp, $localPath)) {
                $lastError = error_get_last();
                $lastErrorText = is_array($lastError) ? (string)($lastError['message'] ?? '') : '';
                $isNoSpace = stripos($lastErrorText, 'No space left on device') !== false
                    || stripos($lastErrorText, 'errno=28') !== false;
                $failure = $fileContext + [
                    'status' => $isNoSpace ? 'failed_no_space' : 'failed_move_uploaded_file',
                    'tmp_name' => $tmp,
                    'local_path' => $localPath,
                    'target_dir_writable' => is_writable(dirname($localPath)),
                    'last_error' => $lastErrorText,
                ];
                $failures[] = $failure;
                $this->logDiagnostics($failure);
                continue;
            }

            $compression = $this->compressImageInPlace($localPath);
            $this->logDiagnostics($fileContext + [
                'event' => 'scan_upload_compression',
                'compression_applied' => (bool)($compression['applied'] ?? false),
                'compression_reason' => (string)($compression['reason'] ?? ''),
                'compression_before_bytes' => (int)($compression['before_bytes'] ?? 0),
                'compression_after_bytes' => (int)($compression['after_bytes'] ?? 0),
                'compression_saved_bytes' => (int)($compression['saved_bytes'] ?? 0),
            ]);

            $this->syncRemote($localPath, $fileName);
            $this->logDiagnostics($fileContext + [
                'status' => 'stored_local',
                'file_name' => $fileName,
                'local_path' => $localPath,
            ]);

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

        $savedCount = count($photos);
        $noSpaceFailures = 0;
        foreach ($failures as $failure) {
            $status = (string)($failure['status'] ?? '');
            if ($status === 'failed_no_space' || $status === 'failed_no_space_precheck') {
                $noSpaceFailures++;
            }
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

        if ($savedCount > 0) {
            file_put_contents(APP_MANIFESTS . '/' . $manifestId . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->logDiagnostics([
            'event' => 'scan_upload_finish',
            'request_id' => $requestId,
            'manifest_id' => $manifestId,
            'saved_count' => $savedCount,
            'attempted_count' => count($uploadedFiles),
            'failed_count' => count($failures),
            'empty_post_possible' => ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
                && empty($_POST)
                && empty($_FILES)
            && ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $this->iniSizeToBytes((string)ini_get('post_max_size'))),
        ]);

        return [
            'manifest' => $manifest,
            'saved_count' => $savedCount,
            'attempted_count' => count($uploadedFiles),
            'failed_count' => count($failures),
            'no_space_failures' => $noSpaceFailures,
            'request_id' => $requestId,
        ];
    }

    private function requestId(): string
    {
        if (function_exists('FotoApp\\app_request_id')) {
            return app_request_id();
        }

        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return (string)time();
        }
    }

    private function logDiagnostics(array $context): void
    {
        if (function_exists('FotoApp\\app_log')) {
            app_log('upload-diagnostics', $context);
            return;
        }

        error_log('upload-diagnostics fallback: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        if (function_exists('FotoApp\\upload_error_message')) {
            return upload_error_message($errorCode);
        }

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

    private function iniSizeToBytes(string $size): int
    {
        if (function_exists('FotoApp\\ini_size_to_bytes')) {
            return ini_size_to_bytes($size);
        }

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

    private function compressImageInPlace(string $path): array
    {
        $beforeBytes = (int)(@filesize($path) ?: 0);
        if ($beforeBytes <= 0) {
            return [
                'applied' => false,
                'reason' => 'file_size_unknown',
                'before_bytes' => 0,
                'after_bytes' => 0,
                'saved_bytes' => 0,
            ];
        }

        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            return [
                'applied' => false,
                'reason' => 'gd_not_available',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo)) {
            return [
                'applied' => false,
                'reason' => 'not_an_image',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        $mime = (string)($imageInfo['mime'] ?? '');
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return [
                'applied' => false,
                'reason' => 'invalid_dimensions',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        $source = $this->createImageResource($path, $mime);
        if ($source === null) {
            return [
                'applied' => false,
                'reason' => 'unsupported_mime_' . ($mime !== '' ? $mime : 'unknown'),
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        $target = $source;
        $maxDimension = 2560;
        $largest = max($width, $height);
        if ($largest > $maxDimension) {
            $scale = $maxDimension / $largest;
            $newWidth = max(1, (int)round($width * $scale));
            $newHeight = max(1, (int)round($height * $scale));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized !== false) {
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                }
                imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                $target = $resized;
            }
        }

        $tmpCompressed = $path . '.compressed';
        $written = $this->writeCompressedImage($target, $tmpCompressed, $mime);

        if (is_resource($source) || $source instanceof \GdImage) {
            imagedestroy($source);
        }
        if ($target !== $source && (is_resource($target) || $target instanceof \GdImage)) {
            imagedestroy($target);
        }

        if (!$written || !is_file($tmpCompressed)) {
            if (is_file($tmpCompressed)) {
                @unlink($tmpCompressed);
            }
            return [
                'applied' => false,
                'reason' => 'compression_write_failed',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        $afterBytes = (int)(@filesize($tmpCompressed) ?: 0);
        if ($afterBytes <= 0 || $afterBytes >= $beforeBytes) {
            @unlink($tmpCompressed);
            return [
                'applied' => false,
                'reason' => 'not_smaller',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        if (!@rename($tmpCompressed, $path)) {
            @unlink($tmpCompressed);
            return [
                'applied' => false,
                'reason' => 'replace_failed',
                'before_bytes' => $beforeBytes,
                'after_bytes' => $beforeBytes,
                'saved_bytes' => 0,
            ];
        }

        return [
            'applied' => true,
            'reason' => 'compressed',
            'before_bytes' => $beforeBytes,
            'after_bytes' => $afterBytes,
            'saved_bytes' => $beforeBytes - $afterBytes,
        ];
    }

    private function createImageResource(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function writeCompressedImage($image, string $path, string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagejpeg') ? (bool)@imagejpeg($image, $path, 80) : false,
            'image/png' => function_exists('imagepng') ? (bool)@imagepng($image, $path, 8) : false,
            'image/webp' => function_exists('imagewebp') ? (bool)@imagewebp($image, $path, 78) : false,
            default => false,
        };
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
            // Extract trailing sequence regardless of underscores in sanitized order/category values.
            if (!preg_match('/_(\d+)\.[a-z0-9]+$/i', $fileName, $matches)) {
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
