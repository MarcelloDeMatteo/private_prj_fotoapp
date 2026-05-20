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
        /* CI-Farben */
        :root {
            --app-bg: #f5f7fb; --app-card: #ffffff;
            --app-ink: #494c51;
            --app-accent: #009F9B;
            --app-danger: #c3172e;
            --app-grey-dark: #494c51;
            --app-grey-light: #b6b7b9;
            /* Bootstrap 5 primary override */
            --bs-primary: #009F9B;
            --bs-primary-rgb: 0, 159, 155;
            --bs-primary-text-emphasis: #004f4d;
            --bs-primary-bg-subtle: #d6f2f1;
            --bs-primary-border-subtle: #aee6e5;
            --bs-link-color: #009F9B;
            --bs-link-hover-color: #007a77;
            /* Bootstrap 5 secondary override */
            --bs-secondary: #494c51;
            --bs-secondary-rgb: 73, 76, 81;
            /* Bootstrap 5 danger override */
            --bs-danger: #c3172e;
            --bs-danger-rgb: 195, 23, 46;
        }
        /* Button overrides */
        .btn-primary {
            --bs-btn-bg: #009F9B; --bs-btn-border-color: #009F9B;
            --bs-btn-hover-bg: #007a77; --bs-btn-hover-border-color: #007a77;
            --bs-btn-active-bg: #006b68; --bs-btn-active-border-color: #006b68;
            --bs-btn-focus-shadow-rgb: 0, 159, 155;
        }
        .btn-outline-primary {
            --bs-btn-color: #009F9B; --bs-btn-border-color: #009F9B;
            --bs-btn-hover-bg: #009F9B; --bs-btn-hover-border-color: #009F9B;
            --bs-btn-active-bg: #009F9B; --bs-btn-active-border-color: #009F9B;
            --bs-btn-focus-shadow-rgb: 0, 159, 155;
        }
        .btn-secondary {
            --bs-btn-bg: #494c51; --bs-btn-border-color: #494c51;
            --bs-btn-hover-bg: #363840; --bs-btn-hover-border-color: #363840;
        }
        .btn-outline-secondary {
            --bs-btn-color: #494c51; --bs-btn-border-color: #494c51;
            --bs-btn-hover-bg: #494c51; --bs-btn-hover-border-color: #494c51;
            --bs-btn-focus-shadow-rgb: 73, 76, 81;
        }
        .btn-danger {
            --bs-btn-bg: #c3172e; --bs-btn-border-color: #c3172e;
            --bs-btn-hover-bg: #9e1225; --bs-btn-hover-border-color: #9e1225;
        }
        body { background: linear-gradient(180deg, #f8fbff 0%, #eef3f8 100%); color: var(--app-ink); }
        .app-shell { max-width: 1280px; }
        .hero-card { background: linear-gradient(135deg, #009F9B 0%, #007a77 100%); color: #fff; }
        .card { border: 0; box-shadow: 0 12px 30px rgba(73,76,81,.10); }
        .scanner-input { font-size: 1.4rem; padding: 1rem 1.1rem; }
        .btn-lg-soft { padding: .95rem 1.15rem; font-size: 1.05rem; }
        .badge-soft { background: rgba(0,159,155,.10); color: var(--app-accent); }
        .thumb { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .75rem; background: #e9eef5; }
        .brand-logo { height: 84px; width: auto; object-fit: contain; max-width: 240px; }
        .scanner-brand-logo { width: auto; height: clamp(44px, 9vw, 68px); max-width: min(58vw, 220px); object-fit: contain; display: block; margin: 0; }
        .scanner-app-title { font-size: clamp(1rem, 4.2vw, 1.2rem); font-weight: 700; line-height: 1.2; letter-spacing: .01em; }
        .scanner-mobile-header { min-height: 0; padding: .3rem 0 .15rem; }
        .scanner-mobile-header.no-logo { padding-top: .55rem; padding-bottom: .45rem; }
        .navbar { padding-top: .45rem; padding-bottom: .45rem; }
        @media (max-width: 575.98px) {
            .navbar { padding-top: .2rem; padding-bottom: .2rem; }
            .scanner-brand-logo { height: 92px; max-width: 72vw; }
            .scanner-app-title { font-size: 2.7rem; line-height: 1.08; font-weight: 700; }
            .scanner-mobile-header { padding: .2rem 0 .15rem; }
            .scanner-mobile-header.no-logo .scanner-app-title { font-size: 3rem; }
            .app-shell { padding-left: .5rem !important; padding-right: .5rem !important; }
            main.app-shell { padding-top: .6rem !important; padding-bottom: .8rem !important; }
        }
        @media (min-width: 992px) {
            .brand-logo { height: 96px; max-width: 300px; }
            .scanner-brand-logo { height: 90px; max-width: 300px; }
            .scanner-app-title { font-size: 1.35rem; }
            .scanner-mobile-header { padding: .15rem 0; }
        }
    </style>
</head>
<body>
<?php $isAdmin = $isAdmin ?? (!empty($user) && (($user['role'] ?? '') === 'admin')); ?>
<?php $isScannerUser = !empty($user) && empty($isAdmin); ?>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top <?= $isScannerUser ? 'py-0' : '' ?>">
    <?php if ($isScannerUser): ?>
    <div class="container-fluid app-shell justify-content-center">
        <div class="scanner-mobile-header w-100 d-flex flex-column align-items-center justify-content-center p-0 gap-0 <?= empty($logoUrl) ? 'no-logo' : '' ?>">
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
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('search'), ENT_QUOTES, 'UTF-8') ?>">Suche</a>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('admin.users'), ENT_QUOTES, 'UTF-8') ?>">Benutzer</a>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('admin.categories'), ENT_QUOTES, 'UTF-8') ?>">Kategorien</a>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('admin.settings'), ENT_QUOTES, 'UTF-8') ?>">Einstellungen</a>
                <a class="btn btn-dark btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('logout'), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
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
