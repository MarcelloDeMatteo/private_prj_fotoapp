<?php
declare(strict_types=1);
?>
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Kategorien</h1>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1m0 1a6 6 0 1 1 0 12A6 6 0 0 1 8 2"/>
                    <path d="M8 4.5a.5.5 0 0 1 .5.5v2.5H11a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V8.5H5a.5.5 0 0 1 0-1h2.5V5a.5.5 0 0 1 .5-.5"/>
                </svg>
                Kategorie hinzufügen
            </button>
        </div>

        <div class="row g-2 align-items-end">
            <div class="col-12">
                <label for="categorySearch" class="form-label"></label>
                <input id="categorySearch" class="form-control" placeholder="Suche nach Code, Bezeichnung oder Default">
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Bezeichnung</th>
                    <th>Default</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody id="categoriesTableBody">
                <?php foreach ($categories as $code => $info): ?>
                    <tr data-category-search="<?= htmlspecialchars(strtolower((string)$code . ' ' . (string)($info['label'] ?? '') . ' ' . (!empty($info['is_default']) ? 'default' : '')), ENT_QUOTES, 'UTF-8') ?>">
                        <td><?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($info['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($info['is_default']) ? 'Ja' : 'Nein' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>">Verwalten</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kategorie hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="col-12 col-md-4">
                        <label class="form-label">Code</label>
                        <input class="form-control" name="code" maxlength="20" required>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">Bezeichnung</label>
                        <input class="form-control" name="label" required>
                    </div>
                    <div class="col-12 form-check ms-1 mt-2">
                        <input class="form-check-input" id="createCategoryDefault" type="checkbox" name="is_default" value="1">
                        <label class="form-check-label" for="createCategoryDefault">Als Default setzen</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end mt-3">
                        <button class="btn btn-primary">Anlegen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach ($categories as $code => $info): ?>
<div class="modal fade" id="categoryModal<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kategorie verwalten: <?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="original_code" value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="col-12 col-md-4">
                        <label class="form-label">Code</label>
                        <input class="form-control" name="code" maxlength="20" value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">Bezeichnung</label>
                        <input class="form-control" name="label" value="<?= htmlspecialchars((string)($info['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-12 form-check ms-1 mt-2">
                        <input class="form-check-input" id="categoryDefault<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="is_default" value="1" <?= !empty($info['is_default']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="categoryDefault<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>">Als Default setzen</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end mt-3">
                        <button class="btn btn-primary">Speichern</button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('Kategorie wirklich löschen?');" class="d-flex justify-content-end border-top pt-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="code" value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('categorySearch');
    var tableBody = document.getElementById('categoriesTableBody');
    if (!searchInput || !tableBody) {
        return;
    }

    searchInput.addEventListener('input', function () {
        var needle = searchInput.value.toLowerCase().trim();
        Array.prototype.forEach.call(tableBody.querySelectorAll('tr'), function (row) {
            var haystack = (row.getAttribute('data-category-search') || '').toLowerCase();
            row.style.display = haystack.indexOf(needle) !== -1 ? '' : 'none';
        });
    });
});
</script>
