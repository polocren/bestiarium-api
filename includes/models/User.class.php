<?php

class UserModel
{
    private static function pdo(): PDO
    {
        $dbPath = __DIR__ . '/../../data/bestiarium.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public static function findByEmail(string $email): ?array
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT uuid, username, email, password, created_at FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT uuid, username, email, password, created_at FROM users WHERE username = ?');
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function publicInfo(array $user): array
    {
        return [
            'uuid' => (int)$user['uuid'],
            'username' => $user['username'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
        ];
    }

    public static function existsByEmail(string $email): bool
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$email]);
        return (bool)$st->fetchColumn();
    }

    public static function existsByUsername(string $username): bool
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->execute([$username]);
        return (bool)$st->fetchColumn();
    }

    public static function create(string $username, string $email, string $password): int
    {
        $username = trim($username);
        $email = trim($email);
        if ($username === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('username, email et password sont requis');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('email invalide');
        }
        if (self::existsByUsername($username)) {
            throw new DomainException('username déjà utilisé');
        }
        if (self::existsByEmail($email)) {
            throw new DomainException('email déjà utilisé');
        }

        $pdo = self::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare('INSERT INTO users(username, email, password) VALUES(?, ?, ?)');
        $st->execute([$username, $email, $hash]);
        return (int)$pdo->lastInsertId();
    }
}
