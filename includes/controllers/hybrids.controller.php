<?php

require_once __DIR__ . '/../models/Monster.class.php';
require_once __DIR__ . '/../models/Type.class.php';
require_once __DIR__ . '/../polinations/Pollinations.class.php';

class HybridsController
{
    // GET /hybrids — liste les créatures hybrides
    public static function index(): void
    {
        try {
            $items = MonsterModel::allHybrids();
            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // GET /hybrids/{id} — détail d'une créature hybride
    public static function show(int $id): void
    {
        try {
            $row = MonsterModel::find($id);
            if (!$row || (int)($row['isHybride'] ?? 0) !== 1) {
                http_response_code(404);
                echo json_encode(['error' => ['message' => 'Hybrid not found']], JSON_UNESCAPED_UNICODE);
                return;
            }
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST /hybrids — fusionne deux créatures pour créer une nouvelle créature hybride
    public static function store(array $data): void
    {
        try {
            $c1 = isset($data['creature_1']) ? (int)$data['creature_1'] : 0;
            $c2 = isset($data['creature_2']) ? (int)$data['creature_2'] : 0;
            // Utilisateur effectif: JWT si présent, sinon user de démo (id=1)
            $createdBy = api_effective_user_id(1);

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

            // Type "Hybride" (créé si nécessaire)
            $typeId = 0;
            $typeName = '';
            $hybType = TypeModel::findByName('Hybride');
            if ($hybType) {
                $typeId = (int)$hybType['uuid'];
                $typeName = $hybType['name'];
            } else {
                $typeId = TypeModel::create('Hybride', $createdBy);
                $typeName = 'Hybride';
            }

            // Nom : optionnellement fourni, sinon IA
            $name = isset($data['name']) ? trim((string)$data['name']) : '';
            if ($name === '') {
                $prompt = sprintf(
                    'Fusion de deux créatures mythologiques: %s (%s) décrite comme "%s" et %s (%s) décrite comme "%s".',
                    (string)$a['name'],
                    (string)($a['type_name'] ?? ''),
                    (string)($a['description'] ?? ''),
                    (string)$b['name'],
                    (string)($b['type_name'] ?? ''),
                    (string)($b['description'] ?? '')
                );
                $name = Pollinations::nameFromPrompt($prompt);
            }

            // Heads : max des deux, éventuellement surchargé par la requête
            $heads = null;
            if (isset($data['heads'])) {
                $heads = (int)$data['heads'];
            } else {
                $ha = isset($a['heads']) ? (int)$a['heads'] : 1;
                $hb = isset($b['heads']) ? (int)$b['heads'] : 1;
                $heads = max($ha, $hb);
            }

            // Stats : moyenne simple des deux créatures
            $health = (int)round(((int)($a['health_score'] ?? 0) + (int)($b['health_score'] ?? 0)) / 2);
            $def = (int)round(((int)($a['defense_score'] ?? 0) + (int)($b['defense_score'] ?? 0)) / 2);
            $atk = (int)round(((int)($a['attaque_score'] ?? 0) + (int)($b['attaque_score'] ?? 0)) / 2);

            // Description générée via Pollinations en utilisant les descriptions des parents
            $description = Pollinations::generateHybridDescription($name, $typeName, $a, $b);

            // Création de la créature hybride dans la table creatures
            $creatureId = MonsterModel::create(
                $name,
                $typeId,
                $createdBy,
                $description,
                null,
                $health,
                $def,
                $atk,
                $heads,
                1
            );

            // Image Pollinations basée sur le nouveau nom
            $imageUrl = Pollinations::imageUrl($name, $typeName, $heads, $creatureId);
            MonsterModel::updateImage($creatureId, $imageUrl);

            $created = MonsterModel::find($creatureId);
            if ($created) {
                $created['parent_1'] = $c1;
                $created['parent_2'] = $c2;
            }

            http_response_code(201);
            echo json_encode($created ?? [
                'uuid' => $creatureId,
                'parent_1' => $c1,
                'parent_2' => $c2,
                'name' => $name,
                'type_uuid' => $typeId,
                'type_name' => $typeName,
                'image' => $imageUrl,
                'health_score' => $health,
                'defense_score' => $def,
                'attaque_score' => $atk,
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
}
