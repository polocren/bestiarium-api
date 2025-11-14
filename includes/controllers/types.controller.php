<?php

require_once __DIR__ . '/../models/Type.class.php';

class TypesController
{
    public static function index(): void
    {
        try {
            $rows = TypeModel::all();
            echo json_encode([ 'items' => $rows ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([ 'error' => [ 'message' => $e->getMessage() ] ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function show(int $id): void
    {
        try {
            $row = TypeModel::find($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Type not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([ 'error' => [ 'message' => $e->getMessage() ] ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function creatures(int $id): void
    {
        try {
            if (!TypeModel::existsById($id)) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Type not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            $rows = TypeModel::creaturesByType($id);
            $type = TypeModel::find($id);
            echo json_encode([ 'type' => $type, 'items' => $rows ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([ 'error' => [ 'message' => $e->getMessage() ] ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function store(array $data): void
    {
        try {
            $name = isset($data['name']) ? trim((string)$data['name']) : '';
            // Utilisateur effectif: JWT si présent, sinon user de démo (id=1)
            $createdBy = api_effective_user_id(1);

            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'Field "name" is required']], JSON_UNESCAPED_UNICODE);
                return;
            }
            $id = TypeModel::create($name, $createdBy);
            http_response_code(201);
            echo json_encode(['uuid' => $id, 'name' => $name, 'created_by' => $createdBy], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(409);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([ 'error' => [ 'message' => $e->getMessage() ] ], JSON_UNESCAPED_UNICODE);
        }
    }
}
