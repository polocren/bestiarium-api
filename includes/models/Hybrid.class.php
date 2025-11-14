<?php

class HybridModel
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
        $sql = 'SELECT 
                    h.uuid,
                    h.creature_id,
                    h.parent_1,
                    h.parent_2,
                    h.created_at,
                    c.name,
                    c.description,
                    c.image,
                    c.health_score,
                    c.defense_score,
                    c.attaque_score,
                    c.heads,
                    c.isHybride,
                    c.type AS type_uuid,
                    t.name AS type_name
                FROM hybrids h
                JOIN creatures c ON c.uuid = h.creature_id
                LEFT JOIN type t ON t.uuid = c.type
                ORDER BY h.created_at DESC, h.uuid DESC';
        $st = $pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = self::pdo();
        $sql = 'SELECT 
                    h.uuid,
                    h.creature_id,
                    h.parent_1,
                    h.parent_2,
                    h.created_at,
                    c.name,
                    c.description,
                    c.image,
                    c.health_score,
                    c.defense_score,
                    c.attaque_score,
                    c.heads,
                    c.isHybride,
                    c.type AS type_uuid,
                    t.name AS type_name
                FROM hybrids h
                JOIN creatures c ON c.uuid = h.creature_id
                LEFT JOIN type t ON t.uuid = c.type
                WHERE h.uuid = ?';
        $st = $pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(int $creatureId, int $parent1, int $parent2): int
    {
        $pdo = self::pdo();

        // Vérifie que la créature hybride existe
        $st = $pdo->prepare('SELECT 1 FROM creatures WHERE uuid = ?');
        $st->execute([$creatureId]);
        if (!$st->fetchColumn()) {
            throw new DomainException('creature hybride inconnue');
        }

        // Vérifie que les parents existent
        foreach ([$parent1, $parent2] as $pid) {
            $st = $pdo->prepare('SELECT 1 FROM creatures WHERE uuid = ?');
            $st->execute([$pid]);
            if (!$st->fetchColumn()) {
                throw new DomainException('parent de créature hybride inconnu');
            }
        }

        $ins = $pdo->prepare('INSERT INTO hybrids(creature_id, parent_1, parent_2) VALUES(?, ?, ?)');
        $ins->execute([$creatureId, $parent1, $parent2]);
        return (int)$pdo->lastInsertId();
    }
}

