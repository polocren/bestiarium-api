<?php

class MatchModel
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
        $sql = 'SELECT uuid, result, creature_1, creature_2, created_at FROM combat ORDER BY created_at DESC, uuid DESC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT uuid, result, creature_1, creature_2, created_at FROM combat WHERE uuid = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(string $result, int $c1, int $c2): int
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('INSERT INTO combat(result, creature_1, creature_2) VALUES(?, ?, ?)');
        $st->execute([$result, $c1, $c2]);
        return (int)$pdo->lastInsertId();
    }
}
