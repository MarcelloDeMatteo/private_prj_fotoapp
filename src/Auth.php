<?php
declare(strict_types=1);

namespace FotoApp;

final class Auth
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->db->findUserByUsername($username);
        if (!$user || !(int) $user['active'] || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['view_mode'] = $user['role'] === 'admin' ? 'admin' : 'scan';

        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']) && $this->user() !== null;
    }

    public function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->db->findUserById((int) $_SESSION['user_id']);
    }

    public function isAdmin(): bool
    {
        $user = $this->user();

        return $user !== null && $user['role'] === 'admin';
    }

    public function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Keine Berechtigung.');
        }
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['view_mode']);
        session_regenerate_id(true);
    }
}
