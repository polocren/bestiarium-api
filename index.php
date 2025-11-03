<?php

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/includes/controllers/types.controller.php';
require_once __DIR__ . '/includes/controllers/monsters.controller.php';
require_once __DIR__ . '/includes/controllers/auth.controller.php';
require_once __DIR__ . '/includes/controllers/matchs.controller.php';

if ($path === '/health') {
    echo json_encode([ 'db' => 'not_configured' ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/types' && $method === 'GET') { TypesController::index(); exit; }

// GET /types/{id} : détail d’un typ
if (preg_match('#^/types/(\d+)$#', $path, $m) && $method === 'GET') { TypesController::show((int)$m[1]); exit; }

// GET /types/{id}/creatures:  liste des créatures d’un type
if (preg_match('#^/types/(\d+)/creatures$#', $path, $m) && $method === 'GET') { TypesController::creatures((int)$m[1]); exit; }

// POST /types:  crée un type simple { name, created_by? }
if ($path === '/types' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    TypesController::store($data); exit;
}

// POST /auth/login — connecter un utilisateur (email/username + password)
if ($path === '/auth/login' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    AuthController::login($data); exit;
}

// POST /auth/register; cré un compte utilisateur
if ($path === '/auth/register' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    AuthController::register($data); exit;
}

// GET /creatures: liste des créatures
if ($path === '/creatures' && $method === 'GET') { MonstersController::index(); exit; }

// GET /creatures/{id} : détail d’une créature
if (preg_match('#^/creatures/(\d+)$#', $path, $m) && $method === 'GET') { MonstersController::show((int)$m[1]); exit; }

// GET /creatures/{id}/image : redirige vers l'image Pollinations
if (preg_match('#^/creatures/(\d+)/image$#', $path, $m) && $method === 'GET') { MonstersController::image((int)$m[1]); exit; }
// POST /creatures/{id}/image : (re)génère et enregistre l'URL image
if (preg_match('#^/creatures/(\d+)/image$#', $path, $m) && $method === 'POST') { MonstersController::regenerateImage((int)$m[1]); exit; }

// POST /creatures — création avec IA (Pollinations)
if ($path === '/creatures' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    MonstersController::store($data); exit;
}

// POST /creatures/generate — création depuis un prompt
if ($path === '/creatures/generate' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    MonstersController::generate($data); exit;
}

// Combat
if ($path === '/combat' && $method === 'GET') { MatchsController::index(); exit; }
if (preg_match('#^/combat/(\d+)$#', $path, $m) && $method === 'GET') { MatchsController::show((int)$m[1]); exit; }
if ($path === '/combat' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); echo json_encode(['error' => ['message' => 'Invalid JSON body']]); exit; }
    MatchsController::store($data); exit;
}

// racine ou 404 JSON par défaut
if ($path === '/' && $method === 'GET') {
    echo json_encode([ 'status' => 'ok' ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => ['message' => 'Route not found', 'path' => $path]], JSON_UNESCAPED_UNICODE);
}
