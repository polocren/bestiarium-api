<?php

require_once __DIR__ . '/../models/Monster.class.php';
require_once __DIR__ . '/../models/Type.class.php';
require_once __DIR__ . '/../polinations/Pollinations.class.php';

class MonstersController
{
    // Essaie d'extraire un nom de type simple à partir du prompt
    private static function extractTypeNameFromPrompt(string $prompt): ?string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        // Cherche un mot juste après un déterminant français courant
        if (preg_match('/\b(?:un|une|le|la|les|des)\s+([\p{L}\-]+)/iu', $prompt, $m)) {
            $candidate = $m[1];
        } else {
            // Sinon, prend simplement le premier mot "lettres"
            $parts = preg_split('/[^\p{L}\-]+/u', $prompt, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($parts)) {
                return null;
            }
            $candidate = $parts[0];
        }

        // Normalisation simple en "Titre"
        $candidate = Pollinations::ucwordsUnicode($candidate);
        return $candidate !== '' ? $candidate : null;
    }

    public static function index(): void
    {
        try {
            $rows = MonsterModel::all();
            echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function show(int $id): void
    {
        try {
            $row = MonsterModel::find($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST /creatures — création minimale
    public static function store(array $data): void
    {
        try {
            $name = isset($data['name']) ? (string)$data['name'] : '';
            $typeRaw = $data['type'] ?? null;
            $description = isset($data['description']) ? (string)$data['description'] : null;
            $heads = isset($data['heads']) ? (int)$data['heads'] : null;

            // Utilisateur effectif: JWT si présent, sinon user de démo (id=1)
            $createdBy = api_effective_user_id(1);

            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'Field \"name\" is required']], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Résolution du type : accepte un id ou un nom
            if ($typeRaw === null || $typeRaw === '') {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'Field \"type\" (id or name) is required']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $type = null;
            $typeId = 0;
            if (is_int($typeRaw) || (is_string($typeRaw) && ctype_digit($typeRaw))) {
                $typeId = (int)$typeRaw;
                $type = TypeModel::find($typeId);
                if (!$type) {
                    http_response_code(400);
                    echo json_encode(['error' => ['message' => 'type id inconnu']], JSON_UNESCAPED_UNICODE);
                    return;
                }
            } else {
                $type = TypeModel::findByName((string)$typeRaw);
                if (!$type) {
                    http_response_code(400);
                    echo json_encode(['error' => ['message' => 'type name inconnu']], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $typeId = (int)$type['uuid'];
            }

            // Récupère le nom du type  les prompts
            $typeName = $type ? $type['name'] : '';

            // Génération IA minimale avec Pollinations si champs manquants
            if ($description === null || $description === '') {
                $description = Pollinations::generateDescription($name, $typeName);
            }
            [$h, $d, $a] = Pollinations::scores($name, $typeName);
            // Crée la créature sans image, récupère l'id pour servir de seed unique
            $id = MonsterModel::create($name, $typeId, $createdBy, $description, null, $h, $d, $a, $heads, 0);
            $imageUrl = Pollinations::imageUrl($name, $typeName, $heads, $id);
            MonsterModel::updateImage($id, $imageUrl);
            http_response_code(201);
            echo json_encode([
                'uuid' => $id,
                'name' => $name,
                'type' => $typeId,
                'created_by' => $createdBy,
                'description' => $description,
                'image' => $imageUrl,
                'health_score' => $h,
                'defense_score' => $d,
                'attaque_score' => $a,
                'heads' => $heads,
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(409);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (DomainException $e) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // PUT /creatures/{id} — mise à jour d'une créature
    public static function update(int $id, array $data): void
    {
        try {
            $existing = MonsterModel::find($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $name = isset($data['name']) ? (string)$data['name'] : (string)$existing['name'];
            $typeId = isset($data['type']) ? (int)$data['type'] : (int)$existing['type_uuid'];
            $description = isset($data['description']) ? (string)$data['description'] : (string)($existing['description'] ?? '');
            $heads = isset($data['heads']) ? (int)$data['heads'] : (isset($existing['heads']) ? (int)$existing['heads'] : null);
            $health = isset($data['health_score']) ? (int)$data['health_score'] : (int)($existing['health_score'] ?? 0);
            $def = isset($data['defense_score']) ? (int)$data['defense_score'] : (int)($existing['defense_score'] ?? 0);
            $atk = isset($data['attaque_score']) ? (int)$data['attaque_score'] : (int)($existing['attaque_score'] ?? 0);

            $ok = MonsterModel::update($id, $name, $typeId, $description, $heads, $health, $def, $atk);
            if (!$ok) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $updated = MonsterModel::find($id);
            echo json_encode($updated, JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(409);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (DomainException $e) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // DELETE /creatures/{id} suppression d'une créature
    public static function destroy(int $id): void
    {
        try {
            $existing = MonsterModel::find($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }

            try {
                $ok = MonsterModel::delete($id);
            } catch (PDOException $e) {
                http_response_code(409);
                echo json_encode([
                    'error' => [
                        'message' => 'Creature is referenced in combats or hybrids',
                        'details' => $e->getMessage(),
                    ],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$ok) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }

            http_response_code(204);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // GET /creatures/{id}/image  redirection 302 vers l'URL OU proxy binaire si ?proxy=1
    public static function image(int $id): void
    {
        try {
            $row = MonsterModel::find($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            $url = $row['image'] ?? '';
            if ($url === '' || $url === null) {
                $heads = isset($row['heads']) ? (int)$row['heads'] : null;
                $url = Pollinations::imageUrl((string)$row['name'], (string)($row['type_name'] ?? ''), $heads, (int)$row['uuid']);
            }

            // Si ?proxy=1, récupérer l'image distante et la renvoyer directement
            $proxy = isset($_GET['proxy']) ? strtolower((string)$_GET['proxy']) : '';
            if ($proxy === '1' || $proxy === 'true' || $proxy === 'yes') {
                // Tente de récupérer les headers pour connaître le Content-Type
                $headers = @get_headers($url, 1);
                $ctype = 'image/jpeg';
                if (is_array($headers)) {
                    foreach ($headers as $k => $v) {
                        if (is_string($k) && strtolower($k) === 'content-type') {
                            if (is_array($v)) { $v = end($v); }
                            if (is_string($v) && stripos($v, 'image/') === 0) { $ctype = $v; }
                            break;
                        }
                    }
                }
                $ctx = stream_context_create(['http' => ['timeout' => 12]]);
                $bin = @file_get_contents($url, false, $ctx);
                if ($bin === false) {
                    http_response_code(502);
                    echo json_encode(['error' => ['message' => 'Unable to fetch image', 'url' => $url]], JSON_UNESCAPED_UNICODE);
                    return;
                }
                header('Content-Type: ' . $ctype);
                echo $bin;
                return;
            }

            // Sinon, redirige
            header('Content-Type: text/plain; charset=utf-8');
            header('Location: ' . $url, true, 302);
            echo $url;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST /creatures/{id}/image.  (re)génère et enregistre l'URL image en DB
    public static function regenerateImage(int $id): void
    {
        try {
            $row = MonsterModel::find($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Creature not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            $heads = isset($row['heads']) ? (int)$row['heads'] : null;
            $url = Pollinations::imageUrl((string)$row['name'], (string)($row['type_name'] ?? ''), $heads, (int)$row['uuid']);
            MonsterModel::updateImage($id, $url);

            // renvoie l'objet mis à jour
            $updated = MonsterModel::find($id);
            echo json_encode($updated, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }
    // POST /creatures/generate — crée une créature à partir d'un prompt
    public static function generate(array $data): void
    {
        try {
            $prompt = isset($data['prompt']) ? trim((string)$data['prompt']) : '';
            // Utilisateur effectif: JWT si présent, sinon user de démo (id=1)
            $createdBy = api_effective_user_id(1);
            if ($prompt === '') {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'Field \"prompt\" is required']], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 1) Nom de créature (IA → fallback local)
            $name = Pollinations::nameFromPrompt($prompt);

            // 2) Déterminer le type
            $typeId = 0; $typeName = '';
            if (isset($data['type']) && $data['type'] !== '') {
                $typeRaw = $data['type'];
                if (is_int($typeRaw) || (is_string($typeRaw) && ctype_digit($typeRaw))) {
                    $typeId = (int)$typeRaw;
                    $t = TypeModel::find($typeId);
                    if (!$t) {
                        http_response_code(400);
                        echo json_encode(['error' => ['message' => 'type id inconnu']], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                } else {
                    $t = TypeModel::findByName((string)$typeRaw);
                    if (!$t) {
                        http_response_code(400);
                        echo json_encode(['error' => ['message' => 'type name inconnu']], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    $typeId = (int)$t['uuid'];
                }
                $typeName = $t['name'];
            } else {
                // b) essaie de repérer un type existant par nom dans le prompt
                $found = null;
                foreach (TypeModel::all() as $t) {
                    if (stripos($prompt, (string)$t['name']) !== false) { $found = $t; break; }
                }
                if ($found) {
                    $typeId = (int)$found['uuid']; $typeName = $found['name'];
                } else {
                    // c) sinon, essaie de déduire un nom de type à partir du prompt (ex : "tortue")
                    $candidate = self::extractTypeNameFromPrompt($prompt);
                    if ($candidate !== null) {
                        $existing = TypeModel::findByName($candidate);
                        if ($existing) {
                            $typeId = (int)$existing['uuid'];
                            $typeName = $existing['name'];
                        } else {
                            $typeId = TypeModel::create($candidate, $createdBy);
                            $typeName = $candidate;
                        }
                    } else {
                        // d) dernier recours: type "Hybride"
                        $hyb = TypeModel::findByName('Hybride');
                        if ($hyb) {
                            $typeId = (int)$hyb['uuid']; $typeName = $hyb['name'];
                        } else {
                            $typeId = TypeModel::create('Hybride', $createdBy); $typeName = 'Hybride';
                        }
                    }
                }
            }

            // 3) Heads: si un nombre est mentionné dans le prompt, l'utiliser; sinon 1..5 basé seed
            $heads = null;
            if (preg_match('/(\d+)\s*(?:tetes|têtes|heads?)/iu', $prompt, $m)) { $heads = max(1, min(10, (int)$m[1])); }
            else { $seed = crc32(strtolower($prompt)); $heads = 1 + ($seed % 5); }

            // 4) Description + image + scores
            $description = Pollinations::generateDescription($name, $typeName);
            [$h, $d, $a] = Pollinations::scores($name, $typeName);
            // 5) Insert puis image avec seed=id
            $id = MonsterModel::create($name, $typeId, $createdBy, $description, null, $h, $d, $a, $heads, 0);
            $imageUrl = Pollinations::imageUrl($name, $typeName, $heads, $id);
            MonsterModel::updateImage($id, $imageUrl);

            http_response_code(201);
            echo json_encode([
                'uuid' => $id,
                'name' => $name,
                'type' => $typeId,
                'created_by' => $createdBy,
                'description' => $description,
                'image' => $imageUrl,
                'health_score' => $h,
                'defense_score' => $d,
                'attaque_score' => $a,
                'heads' => $heads,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }
}
