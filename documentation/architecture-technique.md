# Bestiarium – Architecture technique

## Contexte et objectif

Bestiarium est une API REST en PHP natif permettant de gérer un bestiaire de créatures mythologiques :

- Création et gestion de créatures (CRUD)
- Génération de créatures à partir d’un prompt IA (Pollinations)
- Fusion de deux créatures pour produire un hybride
- Combats entre deux créatures avec calcul d’un gagnant

L’objectif est de mettre en pratique :

- une architecture REST simple mais complète,
- l’accès à une base SQLite via PDO,
- l’intégration d’une API externe (Pollinations),
- une séparation claire des responsabilités (routing, contrôleurs, modèles, utilitaires).

## Stack technique

- **Langage** : PHP 8.x (sans framework)
- **Base de données** : SQLite (`data/bestiarium.db`)
- **Accès DB** : PDO (`PDO::ATTR_ERRMODE = EXCEPTION`)
- **Serveur HTTP** : serveur interne PHP (`php -S localhost:8000 index.php`)
- **API externe** : Pollinations (`image.pollinations.ai`, `text.pollinations.ai`)
- **Format d’échange** : JSON (UTF‑8)

## Organisation du code

Racine :

- `index.php` : point d’entrée de l’API (routing basique)
- `scripts/db_init.php` : création et seed de la base SQLite
- `includes/` : logique applicative
  - `init.api.php` : helpers d’authentification JWT / utilisateur courant
  - `controllers/` : contrôleurs REST
  - `models/` : accès base de données
  - `polinations/` : intégration Pollinations (texte + image)

### Routing (index.php)

`index.php` centralise le dispatch en fonction de :

- l’URL (`$_SERVER['REQUEST_URI']` → `$path`)
- la méthode HTTP (`$_SERVER['REQUEST_METHOD']` → `$method`)

Exemples :

- `GET /creatures` → `MonstersController::index()`
- `GET /creatures/{id}` → `MonstersController::show($id)`
- `POST /creatures` → `MonstersController::store($data)`
- `POST /creatures/generate` → `MonstersController::generate($data)`
- `POST /hybrids` → `HybridsController::store($data)`
- `POST /combat` → `MatchsController::store($data)`

Le body JSON est lu via `file_get_contents('php://input')` puis décodé avec `json_decode(..., true)`. En cas de JSON invalide, l’API renvoie `400 { "error": { "message": "Invalid JSON body" } }`.

### Contrôleurs (includes/controllers)

- `AuthController` : inscription (`/auth/register`), login JWT (`/auth/login`)
- `TypesController` : CRUD minimal des types (`/types`)
- `MonstersController` :
  - `index`, `show`, `store`, `update`, `destroy`
  - génération d’images (`image`, `regenerateImage`)
  - génération complète à partir d’un prompt (`generate`)
- `HybridsController` :
  - `index`, `show`
  - `store` : fusion de deux créatures → créature hybride (`isHybride = 1`)
- `MatchsController` :
  - `index`, `show`
  - `store` : création d’un combat avec calcul du vainqueur

Les contrôleurs ne contiennent pas de SQL : ils délèguent tout l’accès aux données aux classes `*Model`.

### Modèles (includes/models)

- `UserModel` :
  - recherche par email / username
  - création d’un utilisateur (mot de passe hashé)
- `TypeModel` :
  - `all`, `find`, `findByName`, `create`
  - `creaturesByType`
- `MonsterModel` :
  - `all` / `allHybrids` (filtre `isHybride = 1`)
  - `find`
  - `create` (avec validations métier : type existant, user existant, nom unique)
  - `update`, `delete`, `updateImage`
- `MatchModel` :
  - `all`, `find`, `create`

Chaque modèle gère sa propre connexion PDO SQLite (`sqlite:data/bestiarium.db`) via une méthode privée `pdo()`.

### Utilitaires d’authentification (includes/init.api.php)

Objectif : permettre l’usage d’un JWT **sans l’imposer**.

- Lecture du header `Authorization: Bearer ...`
- Vérification d’un JWT HS256 avec une clé `JWT_SECRET` (ou `dev-secret-change-me`)
- `api_current_user_id()` :
  - renvoie l’id utilisateur (claim `sub`) si le JWT est valide
  - sinon renvoie `0`
- `api_effective_user_id($fallbackId = 1)` :
  - renvoie `api_current_user_id()` si > 0
  - sinon renvoie `$fallbackId` (par défaut `1`, l’utilisateur de démo)

Les routes d’écriture (types, créatures, hybrides) utilisent `api_effective_user_id(1)` pour définir `created_by` :

- si un token est présent → l’auteur est l’utilisateur connecté
- sinon → auteur = utilisateur de démo (id 1)

## Modèle de données

### Tables

- `users`
  - `uuid` (PK)
  - `username` (UNIQUE)
  - `email` (UNIQUE)
  - `password` (hash)
  - `created_at`

- `type`
  - `uuid` (PK)
  - `name` (UNIQUE)
  - `created_by` → `users(uuid)`

- `creatures`
  - `uuid` (PK)
  - `name` (UNIQUE)
  - `description` (nullable)
  - `type` → `type(uuid)`
  - `image` (URL Pollinations)
  - `health_score`, `defense_score`, `attaque_score`
  - `heads` (nullable)
  - `created_at`
  - `created_by` → `users(uuid)`
  - `isHybride` (0 = créature “normale”, 1 = hybride)

- `combat`
  - `uuid` (PK)
  - `result` (id gagnant ou `"draw"`)
  - `creature_1` → `creatures(uuid)`
  - `creature_2` → `creatures(uuid)`
  - `created_at`

Un diagramme plus visuel est disponible dans `documentation/bdd-bestiarum.png`.

## Intégration Pollinations

La classe `Pollinations` fournit :

- `imageUrl($name, $typeName, $heads, $seed)` :
  - construit un prompt à partir d’un template (`monster.image.prompt`)
  - renvoie une URL Pollinations avec un `seed` basé sur l’id de la créature
- `generateDescription($name, $typeName)` :
  - appelle `text.pollinations.ai` pour obtenir une description
  - fallback local si l’API est indisponible
- `generateHybridDescription($name, $typeName, $parentA, $parentB)` :
  - génère une description à partir des noms/types/descriptions des parents
- `nameFromPrompt($prompt)` :
  - propose un nom court à partir d’un prompt
  - fallback local si l’API texte ne répond pas
- `scores($name, $typeName)` :
  - calcule des scores pseudo‑aléatoires mais déterministes via un LCG

## Flux principaux

### Création d’une créature à partir d’un prompt

1. `POST /creatures/generate` avec `{ "prompt": "une licorne géante et dangereuse" }`
2. `MonstersController::generate` :
   - détermine ou crée un type (ex. `Licorne`)
   - génère un nom, une description, des scores, un nombre de têtes
   - appelle `MonsterModel::create(...)`
   - génère l’URL d’image avec `Pollinations::imageUrl(...)`
3. Retourne la créature complète (JSON).

### Fusion de deux créatures (hybride)

1. `POST /hybrids` avec `{ "creature_1": 1, "creature_2": 2 }`
2. `HybridsController::store` :
   - charge les deux parents
   - assure l’existence du type `Hybride`
   - calcule les scores comme moyenne des scores des parents
   - génère un nom d’hybride
   - génère une description d’hybride (`generateHybridDescription`)
   - crée une nouvelle entrée dans `creatures` avec `isHybride = 1`
3. Retourne l’hybride, avec `parent_1` et `parent_2` dans la réponse.

### Combat

1. `POST /combat` avec `{ "creature_1": X, "creature_2": Y }`
2. `MatchsController::store` :
   - charge les deux créatures
   - calcule la somme `attaque_score + defense_score + health_score`
   - détermine le gagnant (`result = uuid gagnant` ou `"draw"`)
   - insère la ligne dans `combat`
3. Retourne `uuid`, `result`, `creature_1`, `creature_2`.

Cette documentation technique décrit la structure et le fonctionnement interne ; les détails des endpoints (URL, exemples de requêtes/réponses) sont détaillés dans `documentation/endpoints.md`.

