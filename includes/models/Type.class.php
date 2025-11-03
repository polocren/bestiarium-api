<?php

class TypeModel
{
    private static function pdo(): PDO
    {
        $dbPath = __DIR__ . '/../../data/bestiarium.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public static function all(): array
    {
        $pdo = self::pdo();
        $stmt = $pdo->query('SELECT uuid, name, created_by FROM type ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT uuid, name, created_by FROM type WHERE uuid = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function existsById(int $id): bool
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT 1 FROM type WHERE uuid = ?');
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    public static function existsByName(string $name): bool
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT 1 FROM type WHERE name = ?');
        $st->execute([$name]);
        return (bool)$st->fetchColumn();
    }

    public static function findByName(string $name): ?array
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT uuid, name, created_by FROM type WHERE name = ?');
        $st->execute([$name]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(string $name, int $createdBy): int
    {
        $pdo = self::pdo();
        // validate createdBy exists
        $st = $pdo->prepare('SELECT 1 FROM users WHERE uuid = ?');
        $st->execute([$createdBy]);
        if (!$st->fetchColumn()) {
            throw new RuntimeException('created_by user not found');
        }
        if (self::existsByName($name)) {
            throw new InvalidArgumentException('Type name already exists');
        }

        $ins = $pdo->prepare('INSERT INTO type(name, created_by) VALUES(?, ?)');
        $ins->execute([$name, $createdBy]);
        return (int)$pdo->lastInsertId();
    }

    public static function creaturesByType(int $typeId): array
    {
        $pdo = self::pdo();
        $sql = 'SELECT c.uuid, c.name, c.created_at
                FROM creatures c
                WHERE c.type = ?
                ORDER BY c.created_at DESC, c.uuid DESC';
        $st = $pdo->prepare($sql);
        $st->execute([$typeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
