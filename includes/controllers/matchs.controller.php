<?php

require_once __DIR__ . '/../models/Match.class.php';
require_once __DIR__ . '/../models/Monster.class.php';

class MatchsController
{
    // GET /combat — liste les combats
    public static function index(): void
    {
        try {
            echo json_encode(['items' => MatchModel::all()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // GET /combat/{id}
    public static function show(int $id): void
    {
        try {
            $row = MatchModel::find($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Combat not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST /combat — lance un combat simple entre deux créatures
    public static function store(array $data): void
    {
        try {
            $c1 = isset($data['creature_1']) ? (int)$data['creature_1'] : 0;
            $c2 = isset($data['creature_2']) ? (int)$data['creature_2'] : 0;
            if ($c1 <= 0 || $c2 <= 0 || $c1 === $c2) {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'creature_1 et creature_2 doivent être deux ids différents']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $a = MonsterModel::find($c1);
            $b = MonsterModel::find($c2);
            if (!$a || !$b) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $scoreA = (int)($a['attaque_score'] ?? 0) + (int)($a['defense_score'] ?? 0) + (int)($a['health_score'] ?? 0);
            $scoreB = (int)($b['attaque_score'] ?? 0) + (int)($b['defense_score'] ?? 0) + (int)($b['health_score'] ?? 0);

            if ($scoreA > $scoreB) { $result = (string)$a['uuid']; }
            elseif ($scoreB > $scoreA) { $result = (string)$b['uuid']; }
            else { $result = 'draw'; }

            $id = MatchModel::create($result, $c1, $c2);
            http_response_code(201);
            echo json_encode(['uuid' => $id, 'result' => $result, 'creature_1' => $c1, 'creature_2' => $c2], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }
}
