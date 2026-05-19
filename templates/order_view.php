<?php
declare(strict_types=1);
?>
<div class="card">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <div class="text-secondary small">Auftrag</div>
                <h1 class="h3 mb-1"><?= htmlspecialchars((string)$manifest['order_number'], ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="text-secondary"><?= htmlspecialchars((string)$manifest['category_code'] . ' - ' . (string)$manifest['category_label'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$manifest['username'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="text-md-end">
                <?php if (!empty($isAdmin)): ?>
                    <a href="/?route=dashboard" class="btn btn-outline-secondary">Zurück</a>
                <?php endif; ?>
                <?php if (!empty($isAdmin) && !empty($manifest['manifest_id'])): ?>
                    <form method="post" action="/?route=order.delete" onsubmit="return confirm('Auftrag wirklich löschen?');" class="d-inline-block ms-2">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="manifest_id" value="<?= htmlspecialchars((string)$manifest['manifest_id'], ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn btn-outline-danger">Auftrag löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="row g-3">
            <?php if (empty($manifest['photos'])): ?>
                <div class="col-12">
                    <div class="alert alert-light border mb-0">Dieser Auftrag enthält aktuell keine Fotos.</div>
                </div>
            <?php endif; ?>
            <?php foreach ($manifest['photos'] as $photo): ?>
                <?php $photoManifestId = (string)($photo['source_manifest_id'] ?? $manifest['manifest_id']); ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100">
                        <img class="thumb card-img-top" src="/?route=media&manifest=<?= urlencode($photoManifestId) ?>&file=<?= urlencode((string)$photo['file_name']) ?>" alt="Foto">
                        <div class="card-body">
                            <div class="fw-semibold mb-1"><?= htmlspecialchars((string)$photo['file_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small mb-3">
                                <?= htmlspecialchars((string)$photo['uploaded_by'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(\FotoApp\format_datetime_ch((string)$photo['created_at']), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($isAdmin)): ?>
                                <form method="post" action="/?route=photo.edit" class="row g-2 mb-2">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($photoManifestId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="photo_id" value="<?= htmlspecialchars((string)$photo['photo_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="col-6">
                                        <input class="form-control form-control-sm" name="order_number" value="<?= htmlspecialchars((string)$manifest['order_number'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-6">
                                        <select class="form-select form-select-sm" name="category_code">
                                            <?php foreach ($categories as $code => $info): ?>
                                                <option value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" <?= ((string)$manifest['category_code'] === (string)$code) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button class="btn btn-sm btn-primary">Speichern</button>
                                    </div>
                                </form>
                                <form method="post" action="/?route=photo.delete" onsubmit="return confirm('Foto wirklich löschen?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($photoManifestId, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="photo_id" value="<?= htmlspecialchars((string)$photo['photo_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
