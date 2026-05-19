<?php
declare(strict_types=1);
?>
<style>
.category-toggle-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .65rem;
}

.category-tile {
    width: 100%;
    aspect-ratio: 1 / 1;
    border: 2px solid rgba(255, 255, 255, .35);
    border-radius: 1rem;
    padding: .75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: rgba(255, 255, 255, .08);
    cursor: pointer;
    transition: all .18s ease;
}

.category-tile-code {
    font-size: 2.1rem;
    font-weight: 700;
    line-height: 1;
}

.btn-check:checked + .category-tile {
    background: #fff;
    color: #0b5ed7;
    border-color: #fff;
    box-shadow: 0 10px 18px rgba(0, 0, 0, .18);
}
</style>
<?php
$selectedCategory = (string)($defaultCategory ?? '');
if ($selectedCategory === '' && !empty($categories)) {
    $categoryKeys = array_keys($categories);
    $selectedCategory = (string)($categoryKeys[0] ?? '');
}

$recentItems = $recent;
if (empty($isAdmin)) {
    $groupedRecent = [];
    foreach ($recent as $item) {
        $groupKey = strtolower((string)($item['order_number'] ?? '') . '|' . (string)($item['category_code'] ?? '') . '|' . (string)($item['user_id'] ?? 0));
        $timestamp = (string)($item['updated_at'] ?? $item['created_at'] ?? '');
        $groupDate = substr($timestamp, 0, 10);
        if ($groupDate === '') {
            $groupDate = date('Y-m-d');
        }
        if (!isset($groupedRecent[$groupKey])) {
            $groupedRecent[$groupKey] = $item;
            $groupedRecent[$groupKey]['photos_count'] = count((array)($item['photos'] ?? []));
            $groupedRecent[$groupKey]['captures_count'] = 1;
            $groupedRecent[$groupKey]['latest_timestamp'] = $timestamp;
            $groupedRecent[$groupKey]['group_date'] = $groupDate;
            continue;
        }

        $groupedRecent[$groupKey]['photos_count'] += count((array)($item['photos'] ?? []));
        $groupedRecent[$groupKey]['captures_count']++;
        if (strcmp($timestamp, (string)$groupedRecent[$groupKey]['latest_timestamp']) > 0) {
            $groupedRecent[$groupKey]['latest_timestamp'] = $timestamp;
            $groupedRecent[$groupKey]['manifest_id'] = $item['manifest_id'] ?? $groupedRecent[$groupKey]['manifest_id'];
        }
    }

    $recentItems = array_values($groupedRecent);
    usort($recentItems, static fn (array $a, array $b): int => strcmp((string)($b['latest_timestamp'] ?? ''), (string)($a['latest_timestamp'] ?? '')));
}
?>
<?php if (!empty($isAdmin)): ?>
<div class="card mb-3">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
        <div>
            <div class="text-secondary small text-uppercase">Ansicht</div>
            <div class="h5 mb-0">Scan-Modus aktiv</div>
        </div>
        <form method="post" action="/?route=mode.switch" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <select class="form-select" name="mode">
                <option value="scan" <?= $mode === 'scan' ? 'selected' : '' ?>>Scanner</option>
                <option value="admin" <?= $mode === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <button class="btn btn-primary">Ansicht wechseln</button>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card hero-card">
            <div class="card-body p-4">
                <div class="mb-3">
                    <div class="text-uppercase opacity-75 small">Scan-Modus</div>
                    <h2 class="h4 mb-0">Auftrag erfassen</h2>
                </div>
                <form method="post" action="/?route=scan.upload" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label class="form-label">Auftragsnummer</label>
                        <input class="form-control scanner-input" name="order_number" placeholder="Auftragsnummer scannen oder tippen" autofocus required>
                    </div>
                    <div>
                        <label class="form-label">Kategorie</label>
                        <div class="category-toggle-row" role="radiogroup" aria-label="Kategorie">
                            <?php foreach ($categories as $code => $info): ?>
                                <?php $categoryInputId = 'category_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$code); ?>
                                <input
                                    class="btn-check"
                                    type="radio"
                                    name="category_code"
                                    id="<?= htmlspecialchars($categoryInputId, ENT_QUOTES, 'UTF-8') ?>"
                                    value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $selectedCategory === (string)$code ? 'checked' : '' ?>
                                    required
                                >
                                <label class="category-tile" for="<?= htmlspecialchars($categoryInputId, ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="category-tile-code"><?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Fotos</label>
                        <input class="form-control scanner-input" type="file" name="photos[]" accept="image/*" capture="environment" multiple required>
                    </div>
                    <button class="btn btn-light btn-lg-soft fw-semibold">Fotos hochladen</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <h3 class="h5 mb-3"><?= !empty($isAdmin) ? 'Letzte Erfassungen' : 'Heutige Erfassungen' ?></h3>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentItems as $item): ?>
                        <?php $photoCount = isset($item['photos_count']) ? (int)$item['photos_count'] : count((array)($item['photos'] ?? [])); ?>
                        <?php
                        $detailHref = '/?route=order.view&manifest=' . urlencode((string)($item['manifest_id'] ?? ''));
                        if (empty($isAdmin) && isset($item['group_date'])) {
                            $detailHref = '/?route=order.view&order=' . urlencode((string)($item['order_number'] ?? ''))
                                . '&category=' . urlencode((string)($item['category_code'] ?? ''))
                                . '&user_id=' . urlencode((string)($item['user_id'] ?? '0'))
                                . '&date=' . urlencode((string)$item['group_date']);
                        }
                        ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars((string)$item['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-secondary small"><?= htmlspecialchars((string)$item['category_code'] . ' - ' . (string)$item['category_label'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$item['username'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <span class="badge text-bg-primary rounded-pill"><?= $photoCount ?> Fotos</span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$recentItems): ?>
                        <div class="text-secondary">Noch keine Erfassungen vorhanden.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
