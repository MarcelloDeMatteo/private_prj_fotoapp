<?php
declare(strict_types=1);
?>
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Benutzer</h1>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1m0 1a6 6 0 1 1 0 12A6 6 0 0 1 8 2"/>
                    <path d="M8 4.5a.5.5 0 0 1 .5.5v2.5H11a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V8.5H5a.5.5 0 0 1 0-1h2.5V5a.5.5 0 0 1 .5-.5"/>
                </svg>
                Benutzer anlegen
            </button>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-12">
                <label for="userSearch" class="form-label"></label>
                <input id="userSearch" class="form-control" placeholder="Suche nach Benutzer, Rolle oder Status">
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Benutzer</th><th>Rolle</th><th>Status</th><th>Aktion</th></tr></thead>
            <tbody id="usersTableBody">
                <?php foreach ($users as $row): ?>
                    <tr data-user-search="<?= htmlspecialchars(strtolower((string)$row['username'] . ' ' . (string)$row['role'] . ' ' . (((int)$row['active'] === 1) ? 'aktiv' : 'gesperrt')), ENT_QUOTES, 'UTF-8') ?>">
                        <td><?= htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$row['role'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= ((int)$row['active'] === 1) ? 'aktiv' : 'gesperrt' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal<?= (int)$row['id'] ?>">Verwalten</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Benutzer anlegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="col-12"><label class="form-label">Benutzername</label><input class="form-control" name="username" required></div>
                    <div class="col-12"><label class="form-label">Passwort</label><input class="form-control" name="password" required></div>
                    <div class="col-12">
                        <label class="form-label">Rolle</label>
                        <select class="form-select" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary">Anlegen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach ($users as $row): ?>
<div class="modal fade" id="userModal<?= (int)$row['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Benutzer verwalten: <?= htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                    <div class="col-12 col-md-4"><label class="form-label">Benutzername</label><input class="form-control" name="username" value="<?= htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="col-12 col-md-4"><label class="form-label">Neues Passwort</label><input class="form-control" name="password" placeholder="Leer lassen für unverändert"></div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rolle</label>
                        <select class="form-select" name="role">
                            <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary">Speichern</button>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center border-top pt-3">
                    <div>
                        Aktueller Status: <strong><?= ((int)$row['active'] === 1) ? 'aktiv' : 'gesperrt' ?></strong>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-outline-secondary"><?= ((int)$row['active'] === 1) ? 'Inaktiv setzen' : 'Aktiv setzen' ?></button>
                        </form>

                        <?php if ((int)$row['id'] !== (int)$user['id']): ?>
                        <form method="post" onsubmit="return confirm('Benutzer wirklich löschen?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-outline-danger">Löschen</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('userSearch');
    var tableBody = document.getElementById('usersTableBody');
    if (!searchInput || !tableBody) {
        return;
    }

    searchInput.addEventListener('input', function () {
        var needle = searchInput.value.toLowerCase().trim();
        Array.prototype.forEach.call(tableBody.querySelectorAll('tr'), function (row) {
            var haystack = (row.getAttribute('data-user-search') || '').toLowerCase();
            row.style.display = haystack.indexOf(needle) !== -1 ? '' : 'none';
        });
    });
});
</script>
