<?php
declare(strict_types=1);

$bootstrapCandidates = array_values(array_filter([
    dirname(__DIR__) . '/src/bootstrap.php',
    __DIR__ . '/../src/bootstrap.php',
    (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../src/bootstrap.php',
    getcwd() . '/src/bootstrap.php',
]));

$bootstrapPath = null;
foreach ($bootstrapCandidates as $candidate) {
    $resolved = realpath($candidate);
    if (is_string($resolved) && $resolved !== '' && is_file($resolved)) {
        $bootstrapPath = $resolved;
        break;
    }
}

$appRoot = $bootstrapPath !== null ? dirname($bootstrapPath, 2) : dirname(__DIR__);
$logDir = $appRoot . '/storage/logs';
$logFile = $logDir . '/php-error.log';

if (is_dir(dirname($logDir)) || @mkdir(dirname($logDir), 0777, true)) {
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @ini_set('log_errors', '1');
    @ini_set('error_log', $logFile);
}

function render_bootstrap_error(string $message): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "Foto Scan App - Startfehler\n";
    echo $message . "\n";
    echo "Details im Server-Log: storage/logs/php-error.log\n";
}

if (PHP_VERSION_ID < 80000) {
    render_bootstrap_error('Diese Anwendung benötigt mindestens PHP 8.0 (empfohlen: 8.2+). Aktuell: ' . PHP_VERSION);
    exit;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    error_log(sprintf(
        'Fatal startup error: %s in %s:%d',
        (string) ($error['message'] ?? 'unknown error'),
        (string) ($error['file'] ?? 'unknown file'),
        (int) ($error['line'] ?? 0)
    ));

    render_bootstrap_error('Ein fataler Fehler ist beim Start aufgetreten.');
});

if ($bootstrapPath === null) {
    render_bootstrap_error('Bootstrap nicht gefunden. Gepruefte Pfade: ' . implode(' | ', $bootstrapCandidates));
    exit;
}

try {
    require $bootstrapPath;
} catch (\Throwable $error) {
    error_log(sprintf('Bootstrap exception: %s in %s:%d', $error->getMessage(), $error->getFile(), $error->getLine()));
    render_bootstrap_error('Die Anwendung konnte nicht initialisiert werden.');
    exit;
}

use FotoApp\Auth;
use FotoApp\PhotoRepository;
use FotoApp\PhotoStorage;
use FotoApp\View;

function resolve_logo_path(array $config): ?string
{
    $configured = trim((string)($config['branding']['logo_path'] ?? ''));
    if ($configured !== '' && is_file($configured)) {
        return $configured;
    }

    $candidates = [
        FotoApp\APP_BRANDING . '/logo.png',
        FotoApp\APP_BRANDING . '/logo.jpg',
        FotoApp\APP_BRANDING . '/logo.jpeg',
        FotoApp\APP_BRANDING . '/logo.webp',
        FotoApp\APP_BRANDING . '/logo.gif',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$config = FotoApp\config();
$logoPath = resolve_logo_path($config);
$logoUrl = $logoPath !== null ? FotoApp\route_url('logo') : null;
$db = FotoApp\database();
$auth = new Auth($db);
$storage = new PhotoStorage($config);
$photos = new PhotoRepository();

$route = (string)($_GET['route'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($route === 'login' && $method === 'POST') {
    FotoApp\verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($auth->attempt($username, $password)) {
        FotoApp\flash('success', 'Login erfolgreich.');
        FotoApp\redirect('dashboard');
    }

    FotoApp\flash('danger', 'Login fehlgeschlagen.');
    FotoApp\redirect('login');
}

if ($route === 'logout') {
    $auth->logout();
    FotoApp\flash('info', 'Abgemeldet.');
    FotoApp\redirect('login');
}

if ($route === 'logo') {
    $logoPath = resolve_logo_path($config);
    if ($logoPath === null) {
        http_response_code(404);
        echo 'Logo nicht gefunden';
        return;
    }

    $realLogo = realpath($logoPath) ?: '';
    $realStorage = realpath(FotoApp\APP_STORAGE) ?: '';
    if ($realLogo === '' || $realStorage === '' || !str_starts_with($realLogo, $realStorage)) {
        http_response_code(403);
        echo 'Zugriff verweigert';
        return;
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detectedMime = mime_content_type($realLogo);
        if (is_string($detectedMime) && $detectedMime !== '') {
            $mime = $detectedMime;
        }
    } elseif (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = finfo_file($finfo, $realLogo);
            finfo_close($finfo);
            if (is_string($detectedMime) && $detectedMime !== '') {
                $mime = $detectedMime;
            }
        }
    }

    if ($mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo($realLogo, PATHINFO_EXTENSION));
        $mimeByExt = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        $mime = $mimeByExt[$ext] ?? $mime;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($realLogo));
    readfile($realLogo);
    return;
}

if (!$auth->check() && $route !== 'login') {
    FotoApp\redirect('login');
}

$user = $auth->user();

if ($route === 'mode.switch' && $method === 'POST') {
    FotoApp\verify_csrf();
    $mode = ($_POST['mode'] ?? 'scan') === 'admin' ? 'admin' : 'scan';
    if (!$auth->isAdmin()) {
        $mode = 'scan';
    }
    $_SESSION['view_mode'] = $mode;
    FotoApp\redirect('dashboard');
}

if ($route === 'order.reset' && $method === 'POST') {
    FotoApp\verify_csrf();
    unset($_SESSION['active_order_number'], $_SESSION['active_category_code']);
    FotoApp\flash('info', 'Aktiver Auftrag zurückgesetzt. Bitte neuen Auftrag scannen.');
    FotoApp\redirect('dashboard');
}

if ($route === 'login') {
    View::render('login', [
        'appName' => $config['app_name'],
        'user' => $user,
        'isAdmin' => $auth->isAdmin(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

if ($route === 'media') {
    $manifestId = (string)($_GET['manifest'] ?? '');
    $file = (string)($_GET['file'] ?? '');
    $entry = $photos->findPhoto($manifestId, $file);
    if (!$entry) {
        http_response_code(404);
        echo 'Nicht gefunden';
        return;
    }

    $storage->streamLocalFile((string) $entry['local_path'], (string) ($entry['mime_type'] ?? 'application/octet-stream'));
    return;
}

if ($route === 'scan.upload' && $method === 'POST') {
    FotoApp\verify_csrf();
    $submittedOrder = trim((string)($_POST['order_number'] ?? ''));
    $orderNumber = $submittedOrder !== '' ? $submittedOrder : trim((string)($_SESSION['active_order_number'] ?? ''));
    $submittedCategory = trim((string)($_POST['category_code'] ?? ''));
    $categoryCode = $submittedCategory !== '' ? $submittedCategory : trim((string)($_SESSION['active_category_code'] ?? ''));
    $files = $_FILES['photos'] ?? null;

    if ($orderNumber === '') {
        FotoApp\flash('danger', 'Bitte zuerst eine Auftragsnummer eingeben.');
        FotoApp\redirect('dashboard');
    }

    $result = $storage->storeUploads($orderNumber, $categoryCode, $files, $user, $config);
    $photos->saveManifest($result['manifest']);
    $_SESSION['active_order_number'] = $orderNumber;
    $_SESSION['active_category_code'] = (string)($result['manifest']['category_code'] ?? $categoryCode);
    FotoApp\flash('success', sprintf('%d Foto(s) gespeichert.', count($result['manifest']['photos'])));
    if ($auth->isAdmin()) {
        FotoApp\redirect('order.view&manifest=' . urlencode($result['manifest']['manifest_id']));
    }
    FotoApp\redirect('dashboard');
}

if ($route === 'order.view') {
    $manifestId = (string)($_GET['manifest'] ?? '');
    if ($manifestId !== '') {
        $manifest = $photos->getManifest($manifestId);
        if (!$manifest) {
            FotoApp\flash('danger', 'Auftrag nicht gefunden.');
            FotoApp\redirect('dashboard');
        }
    } else {
        $groupOrder = trim((string)($_GET['order'] ?? ''));
        $groupCategory = trim((string)($_GET['category'] ?? ''));
        $groupUserId = (int)($_GET['user_id'] ?? 0);
        $groupDate = trim((string)($_GET['date'] ?? ''));

        if ($groupOrder === '' || $groupCategory === '' || $groupUserId <= 0) {
            FotoApp\flash('danger', 'Auftrag nicht gefunden.');
            FotoApp\redirect('dashboard');
        }
        if (!$auth->isAdmin() && $groupUserId !== (int)$user['id']) {
            FotoApp\flash('danger', 'Keine Berechtigung.');
            FotoApp\redirect('dashboard');
        }

        $manifests = $photos->manifestsForGroup($groupOrder, $groupCategory, $groupUserId, $groupDate !== '' ? $groupDate : null);
        if (!$manifests) {
            FotoApp\flash('danger', 'Auftrag nicht gefunden.');
            FotoApp\redirect('dashboard');
        }

        $first = $manifests[0];
        $combinedPhotos = [];
        foreach ($manifests as $entry) {
            foreach ((array)($entry['photos'] ?? []) as $photo) {
                $photo['source_manifest_id'] = (string)($entry['manifest_id'] ?? '');
                $combinedPhotos[] = $photo;
            }
        }
        usort($combinedPhotos, static fn (array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

        $manifest = [
            'manifest_id' => '',
            'order_number' => $groupOrder,
            'category_code' => $groupCategory,
            'category_label' => (string)($first['category_label'] ?? ($config['categories'][$groupCategory]['label'] ?? $groupCategory)),
            'username' => (string)($first['username'] ?? ''),
            'user_id' => $groupUserId,
            'photos' => $combinedPhotos,
        ];
    }

    View::render('order_view', [
        'appName' => $config['app_name'],
        'user' => $user,
        'manifest' => $manifest,
        'isAdmin' => $auth->isAdmin(),
        'categories' => $config['categories'],
        'csrf' => FotoApp\csrf_token(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

if ($route === 'photo.delete' && $method === 'POST') {
    FotoApp\verify_csrf();
    $manifestId = (string)($_POST['manifest_id'] ?? '');
    $photoId = (string)($_POST['photo_id'] ?? '');
    if (!$auth->isAdmin()) {
        FotoApp\flash('danger', 'Keine Berechtigung.');
        FotoApp\redirect('dashboard');
    }
    $photos->deletePhoto($manifestId, $photoId, $storage);
    FotoApp\flash('success', 'Foto gelöscht.');
    FotoApp\redirect('order.view&manifest=' . urlencode($manifestId));
}

if ($route === 'order.delete' && $method === 'POST') {
    FotoApp\verify_csrf();
    if (!$auth->isAdmin()) {
        FotoApp\flash('danger', 'Keine Berechtigung.');
        FotoApp\redirect('dashboard');
    }

    $manifestId = (string)($_POST['manifest_id'] ?? '');
    if ($manifestId === '') {
        FotoApp\flash('danger', 'Auftrag nicht gefunden.');
        FotoApp\redirect('search');
    }

    $photos->deleteManifest($manifestId, $storage);
    FotoApp\flash('success', 'Auftrag gelöscht.');
    FotoApp\redirect('search');
}

if ($route === 'order.delete.group' && $method === 'POST') {
    FotoApp\verify_csrf();
    if (!$auth->isAdmin()) {
        FotoApp\flash('danger', 'Keine Berechtigung.');
        FotoApp\redirect('dashboard');
    }

    $groupOrder = trim((string)($_POST['order'] ?? ''));
    $groupCategory = trim((string)($_POST['category'] ?? ''));
    $groupUserId = (int)($_POST['user_id'] ?? 0);
    if ($groupOrder === '' || $groupCategory === '' || $groupUserId <= 0) {
        FotoApp\flash('danger', 'Auftrag nicht gefunden.');
        FotoApp\redirect('search');
    }

    $manifests = $photos->manifestsForGroup($groupOrder, $groupCategory, $groupUserId);
    if (!$manifests) {
        FotoApp\flash('danger', 'Auftrag nicht gefunden.');
        FotoApp\redirect('search');
    }

    foreach ($manifests as $entry) {
        $photos->deleteManifest((string)($entry['manifest_id'] ?? ''), $storage);
    }

    FotoApp\flash('success', sprintf('%d Auftragseintrag/-einträge gelöscht.', count($manifests)));
    FotoApp\redirect('search');
}

if ($route === 'photo.edit' && $method === 'POST') {
    FotoApp\verify_csrf();
    if (!$auth->isAdmin()) {
        FotoApp\flash('danger', 'Keine Berechtigung.');
        FotoApp\redirect('dashboard');
    }
    $manifestId = (string)($_POST['manifest_id'] ?? '');
    $photoId = (string)($_POST['photo_id'] ?? '');
    $newOrder = trim((string)($_POST['order_number'] ?? ''));
    $newCategory = trim((string)($_POST['category_code'] ?? ''));
    $photos->updatePhoto($manifestId, $photoId, $newOrder, $newCategory, $config, $storage);
    FotoApp\flash('success', 'Foto aktualisiert.');
    FotoApp\redirect('order.view&manifest=' . urlencode($manifestId));
}

if ($route === 'search') {
    $query = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $owner = trim((string)($_GET['owner'] ?? ''));
    $results = $photos->search($query, $category, $owner, $auth->isAdmin() ? null : (int) $user['id']);

    View::render('search', [
        'appName' => $config['app_name'],
        'user' => $user,
        'results' => $results,
        'query' => $query,
        'category' => $category,
        'owner' => $owner,
        'categories' => $config['categories'],
        'csrf' => FotoApp\csrf_token(),
        'isAdmin' => $auth->isAdmin(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

if ($route === 'admin.users') {
    $auth->requireAdmin();
    if ($method === 'POST') {
        FotoApp\verify_csrf();
        $action = (string)($_POST['action'] ?? 'create');
        $id = (int)($_POST['user_id'] ?? 0);
        try {
            if ($action === 'create') {
                $db->createUser(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''), (string)($_POST['role'] ?? 'user'));
                FotoApp\flash('success', 'Benutzer angelegt.');
            } elseif ($action === 'update') {
                $db->updateUser($id, trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''), (string)($_POST['role'] ?? 'user'));
                FotoApp\flash('success', 'Benutzer gespeichert.');
            } elseif ($action === 'toggle') {
                $target = $db->findUserById($id);
                if ($target) {
                    $isDisabling = (int)$target['active'] === 1;
                    $isLastActiveAdmin = $target['role'] === 'admin' && $isDisabling && $db->countActiveAdmins() <= 1;
                    if ($isLastActiveAdmin) {
                        FotoApp\flash('danger', 'Der letzte aktive Admin kann nicht deaktiviert werden.');
                    } else {
                        $db->toggleUser($id);
                        FotoApp\flash('success', 'Benutzerstatus geändert.');
                    }
                }
            } elseif ($action === 'delete') {
                if ($id === (int)$user['id']) {
                    FotoApp\flash('danger', 'Du kannst deinen eigenen Benutzer nicht löschen.');
                } else {
                    $target = $db->findUserById($id);
                    $isLastActiveAdmin = $target && $target['role'] === 'admin' && (int)$target['active'] === 1 && $db->countActiveAdmins() <= 1;
                    if ($isLastActiveAdmin) {
                        FotoApp\flash('danger', 'Der letzte aktive Admin kann nicht gelöscht werden.');
                    } else {
                        $db->deleteUser($id);
                        FotoApp\flash('success', 'Benutzer gelöscht.');
                    }
                }
            }
        } catch (\Throwable $error) {
            FotoApp\flash('danger', 'Aktion fehlgeschlagen: ' . $error->getMessage());
        }
        FotoApp\redirect('admin.users');
    }

    View::render('admin_users', [
        'appName' => $config['app_name'],
        'user' => $user,
        'users' => $db->allUsers(),
        'csrf' => FotoApp\csrf_token(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

if ($route === 'admin.categories') {
    $auth->requireAdmin();
    if ($method === 'POST') {
        FotoApp\verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $categories = (array)($config['categories'] ?? []);

        if ($action === 'create') {
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $label = trim((string)($_POST['label'] ?? ''));
            $isDefault = !empty($_POST['is_default']);

            if ($code === '' || $label === '') {
                FotoApp\flash('danger', 'Code und Bezeichnung sind erforderlich.');
                FotoApp\redirect('admin.categories');
            }
            if (isset($categories[$code])) {
                FotoApp\flash('danger', 'Kategorie-Code existiert bereits.');
                FotoApp\redirect('admin.categories');
            }

            if ($isDefault) {
                foreach ($categories as $existingCode => $existingCategory) {
                    $categories[$existingCode]['is_default'] = false;
                }
            }

            $categories[$code] = [
                'label' => $label,
                'is_default' => $isDefault,
            ];
            $config['categories'] = $categories;
            FotoApp\save_settings($config);
            FotoApp\flash('success', 'Kategorie angelegt.');
        } elseif ($action === 'update') {
            $originalCode = strtoupper(trim((string)($_POST['original_code'] ?? '')));
            $newCode = strtoupper(trim((string)($_POST['code'] ?? '')));
            $label = trim((string)($_POST['label'] ?? ''));
            $isDefault = !empty($_POST['is_default']);

            if ($originalCode === '' || !isset($categories[$originalCode])) {
                FotoApp\flash('danger', 'Kategorie nicht gefunden.');
                FotoApp\redirect('admin.categories');
            }
            if ($newCode === '' || $label === '') {
                FotoApp\flash('danger', 'Code und Bezeichnung sind erforderlich.');
                FotoApp\redirect('admin.categories');
            }
            if ($newCode !== $originalCode && isset($categories[$newCode])) {
                FotoApp\flash('danger', 'Neuer Kategorie-Code existiert bereits.');
                FotoApp\redirect('admin.categories');
            }

            $updatedCategory = [
                'label' => $label,
                'is_default' => $isDefault,
            ];

            if ($newCode !== $originalCode) {
                unset($categories[$originalCode]);
            }
            $categories[$newCode] = $updatedCategory;

            if ($isDefault) {
                foreach ($categories as $existingCode => $existingCategory) {
                    $categories[$existingCode]['is_default'] = ($existingCode === $newCode);
                }
            }

            $config['categories'] = $categories;
            FotoApp\save_settings($config);
            FotoApp\flash('success', 'Kategorie gespeichert.');
        } elseif ($action === 'delete') {
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            if ($code === '' || !isset($categories[$code])) {
                FotoApp\flash('danger', 'Kategorie nicht gefunden.');
                FotoApp\redirect('admin.categories');
            }
            if (count($categories) <= 1) {
                FotoApp\flash('danger', 'Mindestens eine Kategorie muss bestehen bleiben.');
                FotoApp\redirect('admin.categories');
            }

            $wasDefault = !empty($categories[$code]['is_default']);
            unset($categories[$code]);

            if ($wasDefault) {
                $firstCode = array_key_first($categories);
                if ($firstCode !== null) {
                    $categories[$firstCode]['is_default'] = true;
                }
            }

            $config['categories'] = $categories;
            FotoApp\save_settings($config);
            FotoApp\flash('success', 'Kategorie gelöscht.');
        } else {
            FotoApp\flash('danger', 'Unbekannte Aktion.');
        }

        FotoApp\redirect('admin.categories');
    }

    View::render('admin_categories', [
        'appName' => $config['app_name'],
        'user' => $user,
        'categories' => $config['categories'],
        'csrf' => FotoApp\csrf_token(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

if ($route === 'admin.broadcast') {
    $auth->requireAdmin();
    if ($method === 'POST') {
        FotoApp\verify_csrf();
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message !== '') {
            if (FotoApp\save_message($message)) {
                FotoApp\flash('success', 'Nachricht versendet.');
            } else {
                FotoApp\flash('error', 'Nachricht konnte nicht gespeichert werden. Bitte Serverberechtigungen prüfen.');
            }
        }
        FotoApp\redirect('dashboard');
    }
}

if ($route === 'admin.settings') {
    $auth->requireAdmin();
    if ($method === 'POST') {
        FotoApp\verify_csrf();
        $config['app_name'] = trim((string)($_POST['app_name'] ?? $config['app_name']));
        $config['remote'] = [
            'host' => trim((string)($_POST['remote_host'] ?? '')),
            'port' => (int)($_POST['remote_port'] ?? 21),
            'username' => trim((string)($_POST['remote_username'] ?? '')),
            'password' => (string)($_POST['remote_password'] ?? ''),
            'base_path' => trim((string)($_POST['remote_base_path'] ?? '')),
        ];

        $logoFile = $_FILES['company_logo'] ?? null;
        if (is_array($logoFile) && (($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
            $tmp = (string)($logoFile['tmp_name'] ?? '');
            if (is_uploaded_file($tmp)) {
                $mime = '';
                if (function_exists('mime_content_type')) {
                    $detectedMime = mime_content_type($tmp);
                    if (is_string($detectedMime)) {
                        $mime = $detectedMime;
                    }
                } elseif (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $detectedMime = finfo_file($finfo, $tmp);
                        finfo_close($finfo);
                        if (is_string($detectedMime)) {
                            $mime = $detectedMime;
                        }
                    }
                }

                if ($mime === '') {
                    $extensionFromName = strtolower(pathinfo((string)($logoFile['name'] ?? ''), PATHINFO_EXTENSION));
                    $mimeFromExtension = [
                        'png' => 'image/png',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif',
                    ];
                    $mime = $mimeFromExtension[$extensionFromName] ?? '';
                }

                $extensions = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];
                if (isset($extensions[$mime])) {
                    $newPath = FotoApp\APP_BRANDING . '/logo.' . $extensions[$mime];
                    $oldPath = (string)($config['branding']['logo_path'] ?? '');
                    if ($oldPath !== '' && is_file($oldPath) && $oldPath !== $newPath) {
                        unlink($oldPath);
                    }
                    move_uploaded_file($tmp, $newPath);
                    $config['branding']['logo_path'] = $newPath;
                }
            }
        }

        FotoApp\save_settings($config);
        FotoApp\flash('success', 'Einstellungen gespeichert.');
        FotoApp\redirect('admin.settings');
    }

    View::render('admin_settings', [
        'appName' => $config['app_name'],
        'user' => $user,
        'config' => $config,
        'csrf' => FotoApp\csrf_token(),
        'logoUrl' => $logoUrl,
    ]);
    return;
}

$mode = $_SESSION['view_mode'] ?? 'scan';
if ($mode !== 'admin') {
    $mode = 'scan';
}

if ($mode === 'admin' && $auth->isAdmin()) {
    $orderStats = $photos->countOrdersByPeriod();
    View::render('dashboard_admin', [
        'appName' => $config['app_name'],
        'user' => $user,
        'categories' => $config['categories'],
        'orderStats' => $orderStats,
        'lastMessage' => FotoApp\get_current_message(),
        'csrf' => FotoApp\csrf_token(),
        'isAdmin' => $auth->isAdmin(),
        'mode' => $mode,
        'logoUrl' => $logoUrl,
    ]);
} else {
    View::render('dashboard_scan', [
        'appName' => $config['app_name'],
        'user' => $user,
        'categories' => $config['categories'],
        'defaultCategory' => FotoApp\default_category($config),
        'recent' => $photos->recentForUser($auth->isAdmin() ? null : (int) $user['id'], !$auth->isAdmin()),
        'csrf' => FotoApp\csrf_token(),
        'isAdmin' => $auth->isAdmin(),
        'mode' => $mode,
        'logoUrl' => $logoUrl,
        'activeOrderNumber' => (string)($_SESSION['active_order_number'] ?? ''),
        'activeCategoryCode' => (string)($_SESSION['active_category_code'] ?? ''),
        'currentMessage' => FotoApp\get_current_message(),
    ]);
}
