# Bestiarium – Guide utilisateur

Ce guide explique comment installer l’API, la lancer en local et tester les principales fonctionnalités (créatures, hybrides, combats) avec quelques exemples simples.

## 1. Prérequis

- PHP 8.x installé (`php -v` pour vérifier)
- SQLite activé dans PHP (extension `pdo_sqlite`)
- Git (si clonage depuis GitHub)

## 2. Installation

1. Cloner le dépôt ou télécharger les sources dans un dossier :

   ```bash
   git clone <url-du-repo> bestiarium-api
   cd bestiarium-api
   ```

2. Initialiser la base SQLite :

   ```bash
   php scripts/db_init.php
   ```

   Ce script crée :

   - la base `data/bestiarium.db`
   - les tables `users`, `type`, `creatures`, `combat`
   - un utilisateur de démo :
     - email : `alice@example.com`
     - mot de passe : `Secret123!`
   - un type de démo : `Dragon`
   - quelques créatures de test

3. Lancer le serveur PHP interne :

   ```bash
   php -S localhost:8000 index.php
   ```

   L’API est maintenant accessible sur `http://localhost:8000`.

## 3. Authentification (optionnelle)

L’API peut fonctionner sans JWT (dans ce cas, les créations sont attribuées à l’utilisateur de démo id = 1), mais voici comment utiliser l’auth :

### 3.1. Créer un utilisateur

```bash
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "mon_pseudo",
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }'
```

### 3.2. Se connecter

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }'
```

Note le champ `access_token` si tu veux tester l’API avec un vrai utilisateur (via un header `Authorization: Bearer ...`). Sinon, tu peux ignorer cette partie : l’API utilisera l’utilisateur de démo.

## 4. Types

### 4.1. Lister les types

```bash
curl http://localhost:8000/types
```

### 4.2. Créer un type

```bash
curl -X POST http://localhost:8000/types \
  -H "Content-Type: application/json" \
  -d '{ "name": "Licorne" }'
```

## 5. Créatures

### 5.1. Créer une créature manuellement

```bash
curl -X POST http://localhost:8000/creatures \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tortue des Abysses",
    "type": "Tortue",
    "description": "Une tortue géante qui hante les profondeurs.",
    "heads": 1
  }'
```

L’API complète automatiquement :

- les scores (`health_score`, `defense_score`, `attaque_score`)
- l’URL de l’image Pollinations

### 5.2. Créer une créature depuis un prompt IA

```bash
curl -X POST http://localhost:8000/creatures/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "une licorne géante et dangereuse"
  }'
```

L’API :

- propose un nom de créature
- détermine ou crée un type adéquat (ex. `"Licorne"`)
- génère description, scores, image
- enregistre la créature dans la base

### 5.3. Voir la liste des créatures

```bash
curl http://localhost:8000/creatures
```

### 5.4. Voir le détail d’une créature

```bash
curl http://localhost:8000/creatures/1
```

### 5.5. Récupérer l’image d’une créature

Dans un navigateur :

- `http://localhost:8000/creatures/1/image`  
  (redirection vers l’URL Pollinations)

## 6. Hybrides

1. Créer ou récupérer deux créatures parents (via `/creatures`).
2. Lancer la fusion :

```bash
curl -X POST http://localhost:8000/hybrids \
  -H "Content-Type: application/json" \
  -d '{
    "creature_1": 1,
    "creature_2": 2
  }'
```

L’API :

- calcule des scores moyens à partir des parents
- génère un nom et une description d’hybride (en utilisant les descriptions des parents)
- crée une créature dans `creatures` avec `isHybride = 1`

Tu peux ensuite :

- lister les hybrides : `curl http://localhost:8000/hybrids`
- voir un hybride : `curl http://localhost:8000/hybrids/ID`

## 7. Combats

1. Choisis deux créatures (id différents).
2. Lance un combat :

```bash
curl -X POST http://localhost:8000/combat \
  -H "Content-Type: application/json" \
  -d '{
    "creature_1": 1,
    "creature_2": 2
  }'
```

La réponse indique :

- l’id du combat (`uuid`)
- le résultat (`result`) :
  - id du gagnant
  - `"draw"` si égalité

3. Historique des combats :

```bash
curl http://localhost:8000/combat
curl http://localhost:8000/combat/1
```

## 8. Résumé rapide pour la démo

Ordre de commandes minimal pour montrer le projet :

```bash
php scripts/db_init.php
php -S localhost:8000 index.php
# Nouveau terminal
curl http://localhost:8000/health
curl http://localhost:8000/types
curl -X POST http://localhost:8000/creatures/generate \
  -H "Content-Type: application/json" \
  -d '{ "prompt": "un dragon aux yeux bleus très dangereux" }'
curl -X POST http://localhost:8000/creatures/generate \
  -H "Content-Type: application/json" \
  -d '{ "prompt": "une licorne géante et dangereuse" }'
curl -X POST http://localhost:8000/hybrids \
  -H "Content-Type: application/json" \
  -d '{ "creature_1": 1, "creature_2": 2 }'
curl -X POST http://localhost:8000/combat \
  -H "Content-Type: application/json" \
  -d '{ "creature_1": 1, "creature_2": 3 }'
```

Ce guide utilisateur couvre l’installation, les principales routes et quelques scénarios de test pour que l’API soit facilement démontrable et exploitable.

