<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? $rows : [];
$totalRows = (int)($totalRows ?? 0);
$filters = is_array($filters ?? null) ? $filters : [];
$logExists = !empty($logExists);
$logPath = (string)($logPath ?? '');
?>
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Upload-Diagnose</h2>
                        <div class="text-muted small">
                            Zeigt die letzten 200 Treffer (gesamt gefiltert: <?= $totalRows ?>).
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(FotoApp\route_url('admin.logs'), ENT_QUOTES, 'UTF-8') ?>">Filter zuruecksetzen</a>
                    </div>
                </div>

                <form method="get" action="<?= htmlspecialchars(FotoApp\route_url('admin.logs'), ENT_QUOTES, 'UTF-8') ?>" class="row g-2 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1" for="request_id">Request-ID</label>
                        <input class="form-control form-control-sm" id="request_id" name="request_id" value="<?= htmlspecialchars((string)($filters['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1" for="status">Status</label>
                        <input class="form-control form-control-sm" id="status" name="status" placeholder="failed_move_uploaded_file" value="<?= htmlspecialchars((string)($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1" for="error_label">Error Label</label>
                        <input class="form-control form-control-sm" id="error_label" name="error_label" placeholder="UPLOAD_ERR_PARTIAL" value="<?= htmlspecialchars((string)($filters['error_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1" for="ip">IP</label>
                        <input class="form-control form-control-sm" id="ip" name="ip" value="<?= htmlspecialchars((string)($filters['ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1" for="from">Von</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="from" name="from" value="<?= htmlspecialchars((string)($filters['from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1" for="to">Bis</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="to" name="to" value="<?= htmlspecialchars((string)($filters['to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-1 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" id="failures_only" name="failures_only" <?= !empty($filters['failures_only']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="failures_only">Nur Fehler</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary btn-sm" type="submit">Filtern</button>
                        <button class="btn btn-outline-primary btn-sm" type="submit" name="export" value="csv">CSV Export</button>
                    </div>
                </form>

                <?php if (!$logExists): ?>
                    <div class="alert alert-warning mb-0">
                        Logdatei noch nicht vorhanden: <?= htmlspecialchars($logPath, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php elseif ($rows === []): ?>
                    <div class="alert alert-secondary mb-0">
                        Keine Eintraege fuer die gesetzten Filter gefunden.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Zeit</th>
                                <th>Request-ID</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Error</th>
                                <th>Auftrag</th>
                                <th>User</th>
                                <th>Datei</th>
                                <th>Groesse</th>
                                <th>IP</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $isFailure = str_starts_with((string)($row['status'] ?? ''), 'failed_'); ?>
                                <tr class="<?= $isFailure ? 'table-danger' : '' ?>">
                                    <td class="text-nowrap"><?= htmlspecialchars((string)($row['ts'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars((string)($row['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['event'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['error_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end"><?= (int)($row['size'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string)($row['ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php if (!empty($row['user_agent'])): ?>
                                    <tr>
                                        <td></td>
                                        <td colspan="9" class="text-muted small">
                                            UA: <?= htmlspecialchars((string)$row['user_agent'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
