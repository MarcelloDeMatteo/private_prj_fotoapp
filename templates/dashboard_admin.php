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
</div>
