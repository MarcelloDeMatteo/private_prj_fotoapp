<?php
declare(strict_types=1);
?>
<div class="row justify-content-center align-items-center min-vh-100 py-4">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <h1 class="h3 fw-bold mb-2">Anmeldung</h1>
                    <p class="text-secondary mb-0">Einfacher Zugriff für Scan-User und Admins.</p>
                </div>
                <form method="post" action="<?= htmlspecialchars(FotoApp\route_url('login'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(\FotoApp\csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label">Benutzername</label>
                        <input type="text" name="username" class="form-control scanner-input" autocomplete="username" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Passwort</label>
                        <input type="password" name="password" class="form-control scanner-input" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary btn-lg w-100">Anmelden</button>
                </form>
                <div class="mt-4 small text-secondary">
                    Demo: admin / Admin123! oder scanner / Scanner123!
                </div>
            </div>
        </div>
    </div>
</div>
