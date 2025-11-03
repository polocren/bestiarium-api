<?php
// Micro-étape DB 1/4: créer la base SQLite + table users uniquement
// Utilisation (terminal): php scripts/db_init.php

declare(strict_types=1);

$dbPath = __DIR__ . '/../data/bestiarium.db';
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->beginTransaction();

    // NOTE: j’utilise le nom de table "users" (pluriel) pour éviter les collisions de mots
    // réservés dans certains SGBD. Si tu préfères exactement "user", je peux le changer.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            uuid INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    // Table type (conforme au schéma): uuid, name, created_by -> users(uuid)
    // ATTENTION: le nom de table est 'type' comme sur ton diagramme
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS type (
            uuid INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_by INTEGER NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(uuid) ON DELETE RESTRICT ON UPDATE CASCADE
        )"
    );

    // Table creatures (conforme au schéma fourni)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS creatures (
            uuid INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            type INTEGER NOT NULL,
            image TEXT,
            health_score INTEGER NOT NULL DEFAULT 0,
            defense_score INTEGER NOT NULL DEFAULT 0,
            attaque_score INTEGER NOT NULL DEFAULT 0,
            heads INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER NOT NULL,
            isHybride INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (type) REFERENCES type(uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(uuid) ON DELETE RESTRICT ON UPDATE CASCADE
        )"
    );

    // Table combat (référence deux créatures)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS combat (
            uuid INTEGER PRIMARY KEY AUTOINCREMENT,
            result TEXT,
            creature_1 INTEGER NOT NULL,
            creature_2 INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creature_1) REFERENCES creatures(uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
            FOREIGN KEY (creature_2) REFERENCES creatures(uuid) ON DELETE RESTRICT ON UPDATE CASCADE
        )"
    );

    // Données de test (idempotentes)
    $seeded = [];

    // 1) Utilisateur de démo
    $uEmail = 'alice@example.com';
    $st = $pdo->prepare('SELECT uuid FROM users WHERE email = ?');
    $st->execute([$uEmail]);
    $userId = $st->fetchColumn();
    if (!$userId) {
        $passHash = password_hash('Secret123!', PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users(username, email, password) VALUES(?,?,?)');
        $ins->execute(['alice', $uEmail, $passHash]);
        $userId = (int)$pdo->lastInsertId();
        $seeded[] = 'user: alice';
    }

    // 2) Type de démo
    $tName = 'Dragon';
    $st = $pdo->prepare('SELECT uuid FROM type WHERE name = ?');
    $st->execute([$tName]);
    $typeId = $st->fetchColumn();
    if (!$typeId) {
        $ins = $pdo->prepare('INSERT INTO type(name, created_by) VALUES(?, ?)');
        $ins->execute([$tName, $userId]);
        $typeId = (int)$pdo->lastInsertId();
        $seeded[] = 'type: Dragon';
    }

    // 3) Deux créatures de démo
    $c1Name = 'Griffon du Nord';
    $st = $pdo->prepare('SELECT uuid FROM creatures WHERE name = ?');
    $st->execute([$c1Name]);
    $c1Id = $st->fetchColumn();
    if (!$c1Id) {
        $ins = $pdo->prepare('INSERT INTO creatures(name, description, type, image, health_score, defense_score, attaque_score, heads, created_by, isHybride) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            $c1Name,
            'Créature de démo (griffon).',
            $typeId,
            null,
            80, // health
            60, // defense
            70, // attaque
            1,
            $userId,
            0,
        ]);
        $c1Id = (int)$pdo->lastInsertId();
        $seeded[] = 'creature: Griffon du Nord';
    }

    $c2Name = 'Basilic';
    $st = $pdo->prepare('SELECT uuid FROM creatures WHERE name = ?');
    $st->execute([$c2Name]);
    $c2Id = $st->fetchColumn();
    if (!$c2Id) {
        $ins = $pdo->prepare('INSERT INTO creatures(name, description, type, image, health_score, defense_score, attaque_score, heads, created_by, isHybride) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            $c2Name,
            'Créature de démo (basilic).',
            $typeId,
            null,
            65,
            40,
            85,
            1,
            $userId,
            0,
        ]);
        $c2Id = (int)$pdo->lastInsertId();
        $seeded[] = 'creature: Basilic';
    }

    // 4) Combat de démo (entre ces deux créatures)
    if (!empty($c1Id) && !empty($c2Id)) {
        // Vérifier s'il existe déjà un combat pour cette paire (peu importe l'ordre)
        $st = $pdo->prepare('SELECT uuid FROM combat WHERE (creature_1 = ? AND creature_2 = ?) OR (creature_1 = ? AND creature_2 = ?)');
        $st->execute([$c1Id, $c2Id, $c2Id, $c1Id]);
        $combatId = $st->fetchColumn();
        if (!$combatId) {
            $ins = $pdo->prepare('INSERT INTO combat(result, creature_1, creature_2) VALUES(?,?,?)');
            $ins->execute(['pending', $c1Id, $c2Id]);
            $seeded[] = 'combat: Griffon vs Basilic';
        }
    }

    $pdo->commit();

    $result = [
        'status' => 'ok',
        'db' => realpath($dbPath),
        'created' => ['users','type','creatures','combat'],
        'seeded' => $seeded,
    ];
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $result = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
}

if (PHP_SAPI === 'cli') {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
