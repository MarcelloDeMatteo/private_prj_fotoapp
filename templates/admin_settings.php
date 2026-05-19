<?php
declare(strict_types=1);
?>
<div class="card">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Einstellungen</h1>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="col-12 col-md-6"><label class="form-label">App Name</label><input class="form-control" name="app_name" value="<?= htmlspecialchars((string)$config['app_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12 col-md-6">
                <label class="form-label">Firmenlogo</label>
                <input class="form-control" type="file" name="company_logo" accept="image/png,image/jpeg,image/webp,image/gif">
                <div class="form-text">Empfohlen: transparentes PNG, Höhe ca. 40-80px.</div>
            </div>
            <div class="col-12"><hr></div>
            <div class="col-12 col-md-4"><label class="form-label">Host</label><input class="form-control" name="remote_host" value="<?= htmlspecialchars((string)($config['remote']['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12 col-md-2"><label class="form-label">Port</label><input class="form-control" name="remote_port" type="number" value="<?= (int)($config['remote']['port'] ?? 21) ?>"></div>
            <div class="col-12 col-md-3"><label class="form-label">Benutzer</label><input class="form-control" name="remote_username" value="<?= htmlspecialchars((string)($config['remote']['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12 col-md-3"><label class="form-label">Passwort</label><input class="form-control" name="remote_password" type="password" value="<?= htmlspecialchars((string)($config['remote']['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12"><label class="form-label">Basis-Pfad</label><input class="form-control" name="remote_base_path" value="<?= htmlspecialchars((string)($config['remote']['base_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-12">
                <div class="form-text">Protokoll wird automatisch ermittelt: Port 22 = SFTP, sonst FTP. Ohne Host/Benutzer/Passwort bleibt der Speicher lokal.</div>
            </div>
            <div class="col-12"><button class="btn btn-primary">Speichern</button></div>
        </form>
    </div>
</div>
