# Bestiarium API – L’Atelier des Créatures Mythologiques

API REST en PHP natif, SQLite + PDO, qui permet de :

- Gérer des créatures mythologiques (CRUD)
- Générer leurs visuels et descriptions via Pollinations
- Fusionner deux créatures en hybride
- Faire combattre deux créatures et déterminer un gagnant

## Installation rapide

- PHP 8.x recommandé
- Lancer l’init de la base SQLite :

```bash
php scripts/db_init.php
```

Le script crée `data/bestiarium.db` avec les tables `users`, `type`, `creatures`, `combat` et quelques données de démo.

Le point d’entrée HTTP est `index.php`.

## Créer un compte utilisateur

L’API n’a pas de pages HTML : tout se fait en JSON sur les routes REST.

1. D’abord, initialise la base :

```bash
php scripts/db_init.php
```

Ce script crée aussi un utilisateur de démo :

- email : `alice@example.com`
- mot de passe : `Secret123!`

2. Pour créer ton propre compte, envoie un `POST /auth/register` avec un JSON :

```bash
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "mon_pseudo",
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }'
```

Réponse attendue (exemple) :

```json
{
  "user": {
    "uuid": 2,
    "username": "mon_pseudo",
    "email": "moi@example.com",
    "created_at": "2024-11-03 10:00:00"
  }
}
```

Règles de sécurité côté API :

- le mot de passe est toujours hashé avec `password_hash` avant stockage
- l’email doit être valide et unique
- le username doit être unique

3. Pour te connecter, utilise `POST /auth/login` avec soit l’email, soit le username :

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }'
```

Réponse (simplifiée) :

```json
{
  "access_token": "JWT_Ici",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "uuid": 2,
    "username": "mon_pseudo",
    "email": "moi@example.com",
    "created_at": "2024-11-03 10:00:00"
  }
}
```

Le `access_token` est un JWT signé (HS256) avec la clé `JWT_SECRET` de ton environnement (ou `dev-secret-change-me` si non définie).  
Actuellement, le JWT est **optionnel** : si tu n’en envoies pas, l’API utilise l’utilisateur de démo (id = 1) comme auteur par défaut (`created_by`). Si tu fournis un JWT valide, les routes d’écriture utilisent son `sub` comme `created_by`.

## Principales routes JSON

- `GET /health` : ping simple (JSON)
- `POST /auth/register` : créer un utilisateur `{ username, email, password }`
- `POST /auth/login` : login, renvoie un JWT basique

### Types

- `GET /types` : liste des types
- `GET /types/{id}` : détail
- `GET /types/{id}/creatures` : créatures d’un type
- `POST /types` : créer un type
  - Body : `{ "name": "Dragon" }`
  - L’API récupère l’auteur à partir du JWT si présent, sinon utilise l’utilisateur de démo (id = 1)

### Créatures

- `GET /creatures` : liste des créatures
- `GET /creatures/{id}` : détail
- `POST /creatures` : création classique
  - Champs typiques :
    - `name` (obligatoire, unique)
    - `type` (id ou nom de type, ex : `1` ou `"Dragon"`)
    - `description?`, `heads?`
  - L’auteur (`created_by`) est déduit automatiquement du JWT si présent, sinon user de démo id = 1
  - Description, scores et image sont complétés via Pollinations si manquants
- `PUT /creatures/{id}` : mise à jour (name, type, description, scores, heads)
- `DELETE /creatures/{id}` : suppression (échoue si utilisée dans des combats/hybrides)
- `GET /creatures/{id}/image` : redirection vers l’URL Pollinations
- `GET /creatures/{id}/image?proxy=1` : proxy binaire de l’image
- `POST /creatures/{id}/image` : régénère l’URL d’image et la stocke
- `POST /creatures/generate` : crée une créature à partir d’un `prompt` IA

### Hybrides

- `GET /hybrids` : liste des créatures hybrides
- `GET /hybrids/{id}` : détail d’un hybride (avec stats et type)
- `POST /hybrids` : fusionne deux créatures

Body attendu :

```json
{
  "creature_1": 1,
  "creature_2": 2,
  "name": "optionnel",
  "heads": 3
}
```

L’API :

- vérifie l’existence des deux créatures parents (ids différents)
- crée une nouvelle créature marquée `isHybride = 1` dans `creatures`, avec `created_by` déduit du JWT si présent (sinon user de démo id = 1)
- calcule ses scores comme moyenne de ceux des parents
- génère un nom + une description d’hybride à partir des noms/types/descriptions des parents via `Pollinations`
- génère une image avec `Pollinations`
- renvoie l’enfant créé, avec en plus `parent_1` et `parent_2` dans la réponse (la relation aux parents n’est pas stockée dans une table dédiée)

### Combats

- `GET /combat` : liste des combats
- `GET /combat/{id}` : détail d’un combat
- `POST /combat` : lance un combat simple

Body attendu :

```json
{
  "creature_1": 1,
  "creature_2": 2
}
```

Le gagnant est déterminé par la somme `(health_score + defense_score + attaque_score)` des deux créatures (égalité => `"draw"`).

## Pollinations

La classe `includes/polinations/Pollinations.class.php` se charge de :

- Construire les URLs d’images (`image.pollinations.ai`)
- Générer des descriptions/textes (`text.pollinations.ai`) avec fallback local si l’API n’est pas dispo
- Proposer un nom de créature à partir d’un prompt

Les templates de prompt texte/image sont dans :

- `includes/polinations/monster.description.prompt`
- `includes/polinations/monster.image.prompt`
