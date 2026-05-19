<?php
declare(strict_types=1);
?>
<div class="card mb-3">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Suche</h1>
        <form id="searchForm" method="get" action="/" class="row g-2">
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
    <?php if ($results): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($results as $result): ?>
                <div class="list-group-item py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars((string)$result['order_number'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-secondary small"><?= htmlspecialchars((string)$result['category_code'] . ' - ' . (string)$result['category_label'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$result['username'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="small text-secondary"><?= count((array)$result['photos']) ?> Foto(s)</span>
                            <?php if (!empty($isAdmin)): ?>
                                <form method="post" action="/?route=order.delete" onsubmit="return confirm('Auftrag wirklich löschen?');" class="m-0">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="manifest_id" value="<?= htmlspecialchars((string)$result['manifest_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="/?route=order.view&manifest=<?= urlencode((string)$result['manifest_id']) ?>">Öffnen</a>
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

    function buildSearchUrl() {
        var params = new URLSearchParams(new FormData(form));
        return '/?' + params.toString();
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
