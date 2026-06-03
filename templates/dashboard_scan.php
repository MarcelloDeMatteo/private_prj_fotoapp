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
    color: #009F9B;
    border-color: #fff;
    box-shadow: 0 10px 18px rgba(0, 0, 0, .18);
}

@media (max-width: 575.98px) {
    .row.g-3 {
        --bs-gutter-x: .65rem;
        --bs-gutter-y: .65rem;
    }

    .hero-card .card-body,
    .card .card-body {
        padding: 1rem !important;
    }

    .hero-card .h4 {
        font-size: 1.2rem;
    }

    .scanner-input {
        font-size: 1.1rem;
        padding: .75rem .85rem;
    }

    .form-label {
        margin-bottom: .35rem;
    }

    .vstack.gap-3 {
        gap: .75rem !important;
    }

    .d-flex.justify-content-between.align-items-center.bg-white {
        gap: .5rem;
        align-items: flex-start !important;
    }

    .d-flex.justify-content-between.align-items-center.bg-white .btn {
        padding: .2rem .5rem;
        font-size: .78rem;
        white-space: nowrap;
    }

    .category-toggle-row {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .45rem;
    }

    .category-tile {
        aspect-ratio: auto;
        min-height: 82px;
        border-radius: .75rem;
        padding: .45rem;
    }

    .category-tile-code {
        font-size: 1.5rem;
    }

    #cameraButton {
        height: 74px !important;
        font-size: 30px !important;
        border-radius: .75rem;
    }

    .list-group-item {
        padding: .65rem 0;
    }

    .list-group-item .fw-semibold {
        font-size: .98rem;
    }

    .list-group-item .small {
        font-size: .82rem;
    }

    .list-group-item .badge {
        font-size: .72rem;
    }
}
</style>
<?php
$selectedCategory = (string)($activeCategoryCode ?? '');
if ($selectedCategory !== '' && !array_key_exists($selectedCategory, (array)$categories)) {
    $selectedCategory = '';
}
if ($selectedCategory === '') {
    $selectedCategory = (string)($defaultCategory ?? '');
}
if ($selectedCategory === '' && !empty($categories)) {
    $categoryKeys = array_keys($categories);
    $selectedCategory = (string)($categoryKeys[0] ?? '');
}

$activeOrderNumber = trim((string)($activeOrderNumber ?? ''));
$timerSeconds = (int)($scanTimerSeconds ?? 60);
if ($timerSeconds < 5) {
    $timerSeconds = 5;
}
$timerEnabled = !empty($scanTimerEnabled);

$recentItems = $recent;
if (empty($isAdmin)) {
    $groupedRecent = [];
    foreach ($recent as $item) {
        $groupKey = strtolower((string)($item['order_number'] ?? '') . '|' . (string)($item['user_id'] ?? 0));
        $timestamp = (string)($item['updated_at'] ?? $item['created_at'] ?? '');
        $groupDate = substr($timestamp, 0, 10);
        $code = (string)($item['category_code'] ?? '');
        $label = (string)($item['category_label'] ?? $code);
        if ($groupDate === '') {
            $groupDate = date('Y-m-d');
        }
        if (!isset($groupedRecent[$groupKey])) {
            $groupedRecent[$groupKey] = $item;
            $groupedRecent[$groupKey]['photos_count'] = count((array)($item['photos'] ?? []));
            $groupedRecent[$groupKey]['captures_count'] = 1;
            $groupedRecent[$groupKey]['latest_timestamp'] = $timestamp;
            $groupedRecent[$groupKey]['group_date'] = $groupDate;
            $groupedRecent[$groupKey]['category_codes'] = $code !== '' ? [$code] : [];
            $groupedRecent[$groupKey]['category_labels'] = $code !== '' ? [$code => $label] : [];
            continue;
        }

        $groupedRecent[$groupKey]['photos_count'] += count((array)($item['photos'] ?? []));
        $groupedRecent[$groupKey]['captures_count']++;
        if ($code !== '' && !in_array($code, (array)$groupedRecent[$groupKey]['category_codes'], true)) {
            $groupedRecent[$groupKey]['category_codes'][] = $code;
        }
        if ($code !== '') {
            $groupedRecent[$groupKey]['category_labels'][$code] = $label;
        }
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
        <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('mode.switch'), ENT_QUOTES, 'UTF-8') ?>" class="d-flex gap-2 align-items-center">
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
                <?php if ($activeOrderNumber !== ''): ?>
                    <div class="d-flex justify-content-between align-items-center bg-white bg-opacity-10 border border-light border-opacity-25 rounded-3 p-2 mb-3" id="activeOrderBar">
                        <div class="small d-flex align-items-center gap-2">
                            <?php if ($timerEnabled): ?>
                                <svg id="orderCountdownRing" width="34" height="34" viewBox="0 0 34 34" style="flex-shrink:0;cursor:default" title="Auftrag läuft ab">
                                    <circle cx="17" cy="17" r="14" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3"/>
                                    <circle id="orderCountdownArc" cx="17" cy="17" r="14" fill="none" stroke="#ffffff" stroke-width="3"
                                        stroke-dasharray="87.96" stroke-dashoffset="0"
                                        stroke-linecap="round"
                                        transform="rotate(-90 17 17)"
                                        style="transition:stroke 0.5s"/>
                                    <text id="orderCountdownText" x="17" y="21" text-anchor="middle"
                                        font-size="10" fill="white" font-family="system-ui,sans-serif" font-weight="700"><?= $timerSeconds ?></text>
                                </svg>
                            <?php endif; ?>
                            Aktiver Auftrag: <strong><?= htmlspecialchars($activeOrderNumber, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('order.reset'), ENT_QUOTES, 'UTF-8') ?>" class="m-0" id="orderResetForm">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn btn-sm btn-outline-light">Nächster Auftrag</button>
                        </form>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('scan.upload'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label class="form-label">Auftragsnummer</label>
                        <input class="form-control scanner-input" name="order_number" value="<?= htmlspecialchars($activeOrderNumber, ENT_QUOTES, 'UTF-8') ?>" placeholder="Auftragsnummer scannen oder tippen" <?= $activeOrderNumber === '' ? 'autofocus' : '' ?> required>
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
                        <label class="form-label"></label>
                        <div class="text-center">
                            <button type="button" class="btn btn-light btn-lg" id="cameraButton" style="width: 100%; height: 120px; font-size: 48px; display: flex; align-items: center; justify-content: center;">
                                📷
                            </button>
                        </div>
                        <input class="form-control scanner-input" type="file" name="photos[]" accept="image/*" capture="environment" multiple required style="display: none;">
                    </div>
                </form>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var photosInput = document.querySelector('input[name="photos[]"]');
            var uploadForm = document.querySelector('form[action*="scan.upload"]');
            var cameraButton = document.getElementById('cameraButton');
            
            if (!photosInput || !uploadForm) {
                return;
            }

            // Kamera-Button triggert File-Input
            if (cameraButton) {
                cameraButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    photosInput.click();
                });
            }

            // Auto-Submit bei Fotoaufnahme
            photosInput.addEventListener('change', function () {
                if (photosInput.files && photosInput.files.length > 0) {
                    uploadForm.submit();
                }
            });
        });

        // Auftrag-Timeout Countdown (konfigurierbar)
        (function () {
            var TIMER_ENABLED = <?= json_encode($timerEnabled) ?>;
            if (!TIMER_ENABLED) {
                return;
            }
            var arc        = document.getElementById('orderCountdownArc');
            var label      = document.getElementById('orderCountdownText');
            var resetForm  = document.getElementById('orderResetForm');
            if (!arc || !label || !resetForm) { return; }

            var TIMEOUT      = <?= (int)$timerSeconds ?>;
            var CIRCUMF      = 87.96;
            var ORDER_NUMBER = <?= json_encode($activeOrderNumber ?? '') ?>;
            var STORAGE_KEY  = 'fotoapp_order_start_' + ORDER_NUMBER;

            var stored = sessionStorage.getItem(STORAGE_KEY);
            var startTime = stored ? parseInt(stored, 10) : Date.now();
            if (!stored) { sessionStorage.setItem(STORAGE_KEY, startTime); }

            function tick() {
                var elapsed   = (Date.now() - startTime) / 1000;
                var remaining = Math.max(0, TIMEOUT - elapsed);
                var fraction  = remaining / TIMEOUT;

                arc.setAttribute('stroke-dashoffset', CIRCUMF * (1 - fraction));
                label.textContent = Math.ceil(remaining);

                if (remaining <= 10) {
                    arc.setAttribute('stroke', '#ffc107');
                }
                if (remaining <= 5) {
                    arc.setAttribute('stroke', '#ff4444');
                }

                if (remaining <= 0) {
                    sessionStorage.removeItem(STORAGE_KEY);
                    resetForm.submit();
                    return;
                }
                requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        })();
        </script>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <h3 class="h5 mb-3"><?= !empty($isAdmin) ? 'Letzte Erfassungen' : 'Heutige Erfassungen' ?></h3>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentItems as $item): ?>
                        <?php $photoCount = isset($item['photos_count']) ? (int)$item['photos_count'] : count((array)($item['photos'] ?? [])); ?>
                        <?php
                        $categoryCodes = (array)($item['category_codes'] ?? []);
                        sort($categoryCodes);
                        $categoryLabelMap = (array)($item['category_labels'] ?? []);
                        $categoryParts = [];
                        foreach ($categoryCodes as $code) {
                            $categoryParts[] = $code . ' - ' . ($categoryLabelMap[$code] ?? $code);
                        }
                        $categorySummary = $categoryParts !== []
                            ? implode(', ', $categoryParts)
                            : ((string)$item['category_code'] . ' - ' . (string)$item['category_label']);
                        $detailHref = FotoApp\route_url('order.view&manifest=' . urlencode((string)($item['manifest_id'] ?? '')));
                        if (empty($isAdmin) && isset($item['group_date'])) {
                            $detailHref = FotoApp\route_url('order.view&order=' . urlencode((string)($item['order_number'] ?? ''))
                                . '&user_id=' . urlencode((string)($item['user_id'] ?? '0'))
                                . '&date=' . urlencode((string)$item['group_date']));
                        }
                        ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars((string)$item['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-secondary small"><?= htmlspecialchars($categorySummary, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$item['username'], ENT_QUOTES, 'UTF-8') ?></div>
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
