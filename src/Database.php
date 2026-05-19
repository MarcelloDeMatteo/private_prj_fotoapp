<?php
declare(strict_types=1);

namespace FotoApp;

use PDO;

final class Database
{
    private static ?self $instance = null;
    private bool $sqliteAvailable;
    private ?PDO $pdo = null;
    private string $filePath;
    private array $users = [];

    private function __construct()
    {
        $this->sqliteAvailable = extension_loaded('pdo_sqlite') || class_exists(PDO::class) && in_array('sqlite', PDO::getAvailableDrivers(), true);
        $this->filePath = APP_DATA . '/users.json';

        if ($this->sqliteAvailable) {
            $this->initializeSqlite();
            return;
        }

        $this->load();
        $this->seed();
    }

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function seed(): void
    {
        if ($this->users !== []) {
            return;
        }

        $this->createUser('admin', 'Admin123!', 'admin');
        $this->createUser('scanner', 'Scanner123!', 'user');
    }

    public function createUser(string $username, string $password, string $role): int
    {
        if ($this->sqliteAvailable) {
            $now = date('c');
            $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, role, active, created_at, updated_at) VALUES (:username, :password_hash, :role, 1, :created_at, :updated_at)');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role === 'admin' ? 'admin' : 'user',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        if ($this->findUserByUsername($username)) {
            throw new \RuntimeException('Benutzername bereits vorhanden.');
        }

        $now = date('c');
        $id = $this->nextId();
        $this->users[] = [
            'id' => $id,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role === 'admin' ? 'admin' : 'user',
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->save();

        return $id;
    }

    public function updateUser(int $id, string $username, string $password, string $role): void
    {
        if ($this->sqliteAvailable) {
            $params = [
                ':id' => $id,
                ':username' => $username,
                ':role' => $role === 'admin' ? 'admin' : 'user',
                ':updated_at' => date('c'),
            ];
            $sql = 'UPDATE users SET username = :username, role = :role, updated_at = :updated_at';
            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return;
        }

        foreach ($this->users as &$user) {
            if ((int) $user['id'] !== $id) {
                continue;
            }

            $user['username'] = $username;
            $user['role'] = $role === 'admin' ? 'admin' : 'user';
            $user['updated_at'] = date('c');
            if ($password !== '') {
                $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $this->save();
            return;
        }
    }

    public function toggleUser(int $id): void
    {
        if ($this->sqliteAvailable) {
            $stmt = $this->pdo->prepare('UPDATE users SET active = CASE active WHEN 1 THEN 0 ELSE 1 END, updated_at = :updated_at WHERE id = :id');
            $stmt->execute([':updated_at' => date('c'), ':id' => $id]);

            return;
        }

        foreach ($this->users as &$user) {
            if ((int) $user['id'] !== $id) {
                continue;
            }

            $user['active'] = ((int) $user['active'] === 1) ? 0 : 1;
            $user['updated_at'] = date('c');
            $this->save();
            return;
        }
    }

    public function deleteUser(int $id): void
    {
        if ($this->sqliteAvailable) {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);

            return;
        }

        foreach ($this->users as $index => $user) {
            if ((int) $user['id'] !== $id) {
                continue;
            }

            array_splice($this->users, $index, 1);
            $this->save();
            return;
        }
    }

    public function findUserByUsername(string $username): ?array
    {
        if ($this->sqliteAvailable) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch();

            return $row ?: null;
        }

        foreach ($this->users as $user) {
            if ((string) $user['username'] === $username) {
                return $user;
            }
        }

        return null;
    }

    public function findUserById(int $id): ?array
    {
        if ($this->sqliteAvailable) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return $row ?: null;
        }

        foreach ($this->users as $user) {
            if ((int) $user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    public function allUsers(): array
    {
        if ($this->sqliteAvailable) {
            return $this->pdo->query('SELECT * FROM users ORDER BY role DESC, username ASC')->fetchAll();
        }

        $users = $this->users;
        usort($users, static function (array $left, array $right): int {
            $roleComparison = strcmp((string) $right['role'], (string) $left['role']);
            if ($roleComparison !== 0) {
                return $roleComparison;
            }

            return strcmp((string) $left['username'], (string) $right['username']);
        });

        return $users;
    }

    public function countActiveAdmins(): int
    {
        if ($this->sqliteAvailable) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
            return (int) $stmt->fetchColumn();
        }

        $count = 0;
        foreach ($this->users as $user) {
            if (($user['role'] ?? '') === 'admin' && (int)($user['active'] ?? 0) === 1) {
                $count++;
            }
        }

        return $count;
    }

    private function load(): void
    {
        if (!is_file($this->filePath)) {
            $this->users = [];
            $this->save();
            return;
        }

        $decoded = json_decode((string) file_get_contents($this->filePath), true);
        $this->users = is_array($decoded) ? array_values($decoded) : [];
    }

    private function save(): void
    {
        file_put_contents($this->filePath, json_encode($this->users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function initializeSqlite(): void
    {
        $this->pdo = new PDO('sqlite:' . APP_DB, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "user",
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $this->seedSqlite();
        }
    }

    private function seedSqlite(): void
    {
        $now = date('c');
        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, role, active, created_at, updated_at) VALUES (:username, :password_hash, :role, 1, :created_at, :updated_at)');
        foreach ([
            ['admin', 'Admin123!', 'admin'],
            ['scanner', 'Scanner123!', 'user'],
        ] as [$username, $password, $role]) {
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    private function nextId(): int
    {
        $ids = array_map(static fn (array $user): int => (int) $user['id'], $this->users);
        return $ids === [] ? 1 : max($ids) + 1;
    }
}
