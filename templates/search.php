<?php
declare(strict_types=1);

$groupedResults = [];
foreach ($results as $result) {
    $groupKey = strtolower((string)($result['order_number'] ?? '') . '|' . (string)($result['user_id'] ?? 0));
    $timestamp = (string)($result['updated_at'] ?? $result['created_at'] ?? '');
    $code = (string)($result['category_code'] ?? '');
    $label = (string)($result['category_label'] ?? $code);

    if (!isset($groupedResults[$groupKey])) {
        $groupedResults[$groupKey] = $result;
        $groupedResults[$groupKey]['photos_count'] = count((array)($result['photos'] ?? []));
        $groupedResults[$groupKey]['captures_count'] = 1;
        $groupedResults[$groupKey]['latest_timestamp'] = $timestamp;
        $groupedResults[$groupKey]['category_codes'] = $code !== '' ? [$code] : [];
        $groupedResults[$groupKey]['category_labels'] = $code !== '' ? [$code => $label] : [];
        continue;
    }

    $groupedResults[$groupKey]['photos_count'] += count((array)($result['photos'] ?? []));
    $groupedResults[$groupKey]['captures_count']++;
    if ($code !== '' && !in_array($code, (array)$groupedResults[$groupKey]['category_codes'], true)) {
        $groupedResults[$groupKey]['category_codes'][] = $code;
    }
    if ($code !== '') {
        $groupedResults[$groupKey]['category_labels'][$code] = $label;
    }
    if (strcmp($timestamp, (string)$groupedResults[$groupKey]['latest_timestamp']) > 0) {
        $groupedResults[$groupKey]['latest_timestamp'] = $timestamp;
        $groupedResults[$groupKey]['manifest_id'] = $result['manifest_id'] ?? $groupedResults[$groupKey]['manifest_id'];
    }
}

$displayResults = array_values($groupedResults);
usort($displayResults, static fn (array $a, array $b): int => strcmp((string)($b['latest_timestamp'] ?? ''), (string)($a['latest_timestamp'] ?? '')));
?>
<div class="card mb-3">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Suche</h1>
        <form id="searchForm" method="get" action="<?= htmlspecialchars(FotoApp\app_url(''), ENT_QUOTES, 'UTF-8') ?>" class="row g-2">
            <input type="hidden" name="route" value="search">
            <div class="col-12 col-md-4">
                <input id="searchQuery" class="form-control" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Auftragsnummer oder Stichwort" autocomplete="off">
            </div>
            <div class="col-12 col-md-3">
                <select id="searchCategory" class="form-select" name="category">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($categories as $code => $info): ?>
                        <option value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" <?= $category === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$code . ' - ' . (string)$info['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <input id="searchOwner" class="form-control" name="owner" value="<?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8') ?>" placeholder="Benutzer" autocomplete="off">
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button class="btn btn-primary">Suchen</button>
            </div>
            <div class="col-12">
                <div class="form-text"></div>
            </div>
        </form>
    </div>
</div>
<div id="searchResults" class="card">
    <?php if ($displayResults): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($displayResults as $result): ?>
                <?php
                $openHref = FotoApp\route_url('order.view&order=' . urlencode((string)($result['order_number'] ?? ''))
                    . '&user_id=' . urlencode((string)($result['user_id'] ?? '0')));
                $photoCount = (int)($result['photos_count'] ?? count((array)($result['photos'] ?? [])));
                $capturesCount = (int)($result['captures_count'] ?? 1);
                $categoryCodes = (array)($result['category_codes'] ?? []);
                sort($categoryCodes);
                $categoryLabelMap = (array)($result['category_labels'] ?? []);
                $categoryParts = [];
                foreach ($categoryCodes as $code) {
                    $categoryParts[] = $code . ' - ' . ($categoryLabelMap[$code] ?? $code);
                }
                $categorySummary = $categoryParts !== []
                    ? implode(', ', $categoryParts)
                    : ((string)($result['category_code'] ?? '') . ' - ' . (string)($result['category_label'] ?? ''));
                ?>
                <div class="list-group-item py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars((string)$result['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small"><?= htmlspecialchars($categorySummary, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$result['username'], ENT_QUOTES, 'UTF-8') ?><?php if ($capturesCount > 1): ?> · <?= $capturesCount ?> Erfassungen<?php endif; ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="small text-secondary"><?= $photoCount ?> Foto(s)</span>
                            <?php if (!empty($isAdmin)): ?>
                                <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('order.delete.group'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Auftrag wirklich löschen? (alle Erfassungen dieser Gruppe)');" class="m-0">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="order" value="<?= htmlspecialchars((string)($result['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="category" value="">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)($result['user_id'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($openHref, ENT_QUOTES, 'UTF-8') ?>">Öffnen</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="alert alert-light border mb-0">Keine Treffer.</div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('searchForm');
    if (!form) {
        return;
    }

    var queryInput = document.getElementById('searchQuery');
    var ownerInput = document.getElementById('searchOwner');
    var categorySelect = document.getElementById('searchCategory');
    var debounceTimer = null;
    var activeRequest = null;

    var baseUrl = <?= json_encode(FotoApp\app_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function buildSearchUrl() {
        var params = new URLSearchParams(new FormData(form));
        return baseUrl + '?' + params.toString();
    }

    function runSearch() {
        var url = buildSearchUrl();

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();
        fetch(url, {
            signal: activeRequest.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var incoming = doc.getElementById('searchResults');
                var current = document.getElementById('searchResults');
                if (incoming && current) {
                    current.innerHTML = incoming.innerHTML;
                }
                history.replaceState({}, '', url);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            });
    }

    function scheduleSearch() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(function () {
            runSearch();
        }, 350);
    }

    if (queryInput) {
        queryInput.addEventListener('input', scheduleSearch);
    }
    if (ownerInput) {
        ownerInput.addEventListener('input', scheduleSearch);
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', scheduleSearch);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        runSearch();
    });
});
</script>
