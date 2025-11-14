<?php

class MonsterModel
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
        $sql = 'SELECT c.uuid, c.name, c.created_at, c.type AS type_uuid, t.name AS type_name
                FROM creatures c
                LEFT JOIN type t ON t.uuid = c.type
                ORDER BY c.created_at DESC, c.uuid DESC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function allHybrids(): array
    {
        $pdo = self::pdo();
        $sql = 'SELECT c.uuid, c.name, c.created_at, c.type AS type_uuid, t.name AS type_name
                FROM creatures c
                LEFT JOIN type t ON t.uuid = c.type
                WHERE c.isHybride = 1
                ORDER BY c.created_at DESC, c.uuid DESC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = self::pdo();
        $sql = 'SELECT c.uuid, c.name, c.description, c.type AS type_uuid, t.name AS type_name,
                       c.image, c.health_score, c.defense_score, c.attaque_score, c.heads,
                       c.created_at, c.created_by, c.isHybride
                FROM creatures c
                LEFT JOIN type t ON t.uuid = c.type
                WHERE c.uuid = ?';
        $st = $pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Création (simple). Les champs IA peuvent être fournis (image/scores) ou laissés null
    public static function create(
        string $name,
        int $typeId,
        int $createdBy,
        ?string $description = null,
        ?string $image = null,
        ?int $healthScore = null,
        ?int $defenseScore = null,
        ?int $attaqueScore = null,
        ?int $heads = null,
        int $isHybride = 0
    ): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('name requis');
        }
        $pdo = self::pdo();

        // Vérifie l'existence du type et de l'utilisateur (simple et clair)
        $st = $pdo->prepare('SELECT 1 FROM type WHERE uuid = ?');
        $st->execute([$typeId]);
        if (!$st->fetchColumn()) {
            throw new DomainException('type inexistant');
        }
        $st = $pdo->prepare('SELECT 1 FROM users WHERE uuid = ?');
        $st->execute([$createdBy]);
        if (!$st->fetchColumn()) {
            throw new DomainException('created_by inexistant');
        }

        // Conflit si le nom existe déjà
        $st = $pdo->prepare('SELECT 1 FROM creatures WHERE name = ?');
        $st->execute([$name]);
        if ($st->fetchColumn()) {
            throw new InvalidArgumentException('name déjà utilisé');
        }

        $ins = $pdo->prepare('INSERT INTO creatures(
            name, description, type, image, health_score, defense_score, attaque_score, heads, created_by, isHybride
        ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $ins->execute([
            $name,
            $description,
            $typeId,
            $image,
            $healthScore ?? 0,
            $defenseScore ?? 0,
            $attaqueScore ?? 0,
            $heads,
            $createdBy,
            $isHybride,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(
        int $id,
        string $name,
        int $typeId,
        ?string $description = null,
        ?int $heads = null,
        ?int $healthScore = null,
        ?int $defenseScore = null,
        ?int $attaqueScore = null
    ): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('name requis');
        }

        $pdo = self::pdo();

        // Vérifie que la créature existe
        $st = $pdo->prepare('SELECT uuid FROM creatures WHERE uuid = ?');
        $st->execute([$id]);
        if (!$st->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        // Vérifie l'existence du type
        $st = $pdo->prepare('SELECT 1 FROM type WHERE uuid = ?');
        $st->execute([$typeId]);
        if (!$st->fetchColumn()) {
            throw new DomainException('type inexistant');
        }

        // Conflit si le nom existe déjà pour une autre créature
        $st = $pdo->prepare('SELECT 1 FROM creatures WHERE name = ? AND uuid != ?');
        $st->execute([$name, $id]);
        if ($st->fetchColumn()) {
            throw new InvalidArgumentException('name déjà utilisé');
        }

        $st = $pdo->prepare(
            'UPDATE creatures
             SET name = ?, description = ?, type = ?, health_score = ?, defense_score = ?, attaque_score = ?, heads = ?
             WHERE uuid = ?'
        );
        $st->execute([
            $name,
            $description,
            $typeId,
            $healthScore ?? 0,
            $defenseScore ?? 0,
            $attaqueScore ?? 0,
            $heads,
            $id,
        ]);

        return true;
    }

    public static function delete(int $id): bool
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('DELETE FROM creatures WHERE uuid = ?');
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    public static function updateImage(int $id, string $url): void
    {
        $pdo = self::pdo();
        $st = $pdo->prepare('UPDATE creatures SET image = ? WHERE uuid = ?');
        $st->execute([$url, $id]);
    }
}
