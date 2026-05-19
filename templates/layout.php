<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName ?? 'Foto Scan App', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --app-bg: #f5f7fb; --app-card: #ffffff; --app-ink: #17324d; --app-accent: #0d6efd; }
        body { background: linear-gradient(180deg, #f8fbff 0%, #eef3f8 100%); color: var(--app-ink); }
        .app-shell { max-width: 1280px; }
        .hero-card { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: #fff; }
        .card { border: 0; box-shadow: 0 12px 30px rgba(23,50,77,.08); }
        .scanner-input { font-size: 1.4rem; padding: 1rem 1.1rem; }
        .btn-lg-soft { padding: .95rem 1.15rem; font-size: 1.05rem; }
        .badge-soft { background: rgba(13,110,253,.08); color: var(--app-accent); }
        .thumb { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .75rem; background: #e9eef5; }
        .brand-logo { height: 84px; width: auto; object-fit: contain; max-width: 240px; }
        .scanner-brand-logo { width: min(92vw, 900px); height: auto; max-height: 280px; object-fit: contain; display: block; margin-top: -34px; margin-bottom: -28px; }
        .scanner-app-title { font-size: clamp(1.4rem, 5.4vw, 2.2rem); font-weight: 700; line-height: 1.15; }
        .scanner-mobile-header { min-height: 0; }
        .navbar { padding-top: .45rem; padding-bottom: .45rem; }
        @media (min-width: 992px) {
            .brand-logo { height: 96px; max-width: 300px; }
            .scanner-brand-logo { width: min(94vw, 1100px); max-height: 320px; margin-top: -44px; margin-bottom: -34px; }
        }
    </style>
</head>
<body>
<?php $isAdmin = $isAdmin ?? (!empty($user) && (($user['role'] ?? '') === 'admin')); ?>
<?php $isScannerUser = !empty($user) && empty($isAdmin); ?>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top <?= $isScannerUser ? 'py-0' : '' ?>">
    <?php if ($isScannerUser): ?>
    <div class="container-fluid app-shell justify-content-center">
        <div class="scanner-mobile-header w-100 d-flex flex-column align-items-center justify-content-center p-0 gap-0">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars((string)$logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Firmenlogo" class="scanner-brand-logo">
            <?php endif; ?>
            <div class="scanner-app-title text-center"><?= htmlspecialchars($appName ?? 'Foto Scan App', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <?php else: ?>
    <div class="container-fluid app-shell">
        <span class="navbar-brand fw-bold"><?= htmlspecialchars($appName ?? 'Foto Scan App', ENT_QUOTES, 'UTF-8') ?></span>
        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
            <?php if (!empty($user) && !empty($isAdmin)): ?>
                <span class="badge rounded-pill badge-soft"><?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <a class="btn btn-outline-secondary btn-sm" href="/?route=dashboard">Dashboard</a>
                <a class="btn btn-outline-secondary btn-sm" href="/?route=search">Suche</a>
                <a class="btn btn-outline-primary btn-sm" href="/?route=admin.users">Benutzer</a>
                <a class="btn btn-outline-primary btn-sm" href="/?route=admin.categories">Kategorien</a>
                <a class="btn btn-outline-primary btn-sm" href="/?route=admin.settings">Einstellungen</a>
                <a class="btn btn-dark btn-sm" href="/?route=logout">Logout</a>
            <?php endif; ?>
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars((string)$logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Firmenlogo" class="ms-2 brand-logo">
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</nav>
<main class="container-fluid app-shell py-3 py-md-4">
    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?> shadow-sm">
            <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php require $templateFile; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
