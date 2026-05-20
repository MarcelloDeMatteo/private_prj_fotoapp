<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-12">
        <div class="card hero-card">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <div class="text-uppercase opacity-75 small">Admin-Modus</div>
                    <h2 class="h4 mb-0">Verwaltung und Kontrolle</h2>
                </div>
                <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('mode.switch'), ENT_QUOTES, 'UTF-8') ?>" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <select class="form-select" name="mode">
                        <option value="admin" <?= $mode === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="scan" <?= $mode === 'scan' ? 'selected' : '' ?>>Scanner</option>
                    </select>
                    <button class="btn btn-light">Ansicht wechseln</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Kategorien-Kachel ausgeblendet -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3 class="h5 mb-3">System-Nachricht für Scanner-Nutzer</h3>
                <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('admin.broadcast'), ENT_QUOTES, 'UTF-8') ?>" class="vstack gap-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <textarea name="message" class="form-control" rows="3" placeholder="Schreibe eine Nachricht für alle Scanner-Nutzer (wird als Popup angezeigt)..."></textarea>
                    <button type="submit" class="btn btn-primary">Nachricht senden</button>
                </form>
                <?php if (!empty($lastMessage)): ?>
                    <hr>
                    <div class="small text-secondary">
                        <strong>Letzte Nachricht:</strong> <?= htmlspecialchars((string)$lastMessage['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        <br><small><?= htmlspecialchars((string)$lastMessage['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
