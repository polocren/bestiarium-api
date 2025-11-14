# Bestiarium – Documentation des endpoints

Base URL (développement) : `http://localhost:8000`

Tous les endpoints renvoient du JSON et utilisent l’en‑tête :

```http
Content-Type: application/json; charset=utf-8
```

---

## Santé

### GET /health

- **Description** : vérifie que l’API répond.
- **Réponse 200** :
  ```json
  { "db": "not_configured" }
  ```

---

## Authentification

### POST /auth/register

- **Description** : crée un utilisateur.
- **Body JSON** :
  ```json
  {
    "username": "mon_pseudo",
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }
  ```
- **Réponses** :
  - 201 :
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
  - 400 : champs manquants / email invalide
  - 409 : username ou email déjà utilisé

### POST /auth/login

- **Description** : authentifie un utilisateur et renvoie un JWT.
- **Body JSON** :
  ```json
  {
    "email": "moi@example.com",
    "password": "MonSuperMotDePasse123!"
  }
  ```
  ou :
  ```json
  {
    "username": "mon_pseudo",
    "password": "MonSuperMotDePasse123!"
  }
  ```
- **Réponses** :
  - 200 :
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
  - 401 : identifiants invalides

Le JWT est optionnel pour le reste de l’API : sans token, l’API utilise l’utilisateur de démo (id = 1).

---

## Types

### GET /types

- **Description** : liste les types de créatures.
- **Réponse 200** :
  ```json
  {
    "items": [
      { "uuid": 1, "name": "Dragon", "created_by": 1 }
    ]
  }
  ```

### GET /types/{id}

- **Description** : détail d’un type.
- **Réponses** :
  - 200 : objet type
  - 404 : type inconnu

### GET /types/{id}/creatures

- **Description** : liste les créatures d’un type donné.
- **Réponses** :
  - 200 :
    ```json
    {
      "type": { "uuid": 1, "name": "Dragon", "created_by": 1 },
      "items": [
        { "uuid": 3, "name": "Griffon du Nord", "created_at": "..." }
      ]
    }
    ```
  - 404 : type inconnu

### POST /types

- **Description** : crée un type.
- **Body JSON** :
  ```json
  {
    "name": "Tortue"
  }
  ```
  L’auteur (`created_by`) est déduit du JWT si présent, sinon user de démo id = 1.

- **Réponses** :
  - 201 :
    ```json
    { "uuid": 2, "name": "Tortue", "created_by": 1 }
    ```
  - 409 : nom de type déjà existant

---

## Créatures

### GET /creatures

- **Description** : liste toutes les créatures (simples et hybrides).
- **Réponse 200** :
  ```json
  {
    "items": [
      {
        "uuid": 3,
        "name": "Hydre des Marais",
        "created_at": "...",
        "type_uuid": 1,
        "type_name": "Dragon"
      }
    ]
  }
  ```

### GET /creatures/{id}

- **Description** : détail complet d’une créature.
- **Réponses** :
  - 200 : objet créature (avec description, scores, type, isHybride…)
  - 404 : créature inconnue

### POST /creatures

- **Description** : crée une créature “classique” (semi‑manuelle).
- **Body JSON** :
  ```json
  {
    "name": "Hydre des Marais",
    "type": "Dragon",
    "description": "Une hydre sombre tapie dans les marais.",
    "heads": 5
  }
  ```
  - `type` peut être un id (`1`) ou un nom (`"Dragon"`).
  - `created_by` est déduit automatiquement (JWT ou user de démo).
  - Si `description` est vide, une description est générée via l’IA.

- **Réponses** :
  - 201 : créature créée (avec scores + image)
  - 400 : type inconnu, champs obligatoires manquants
  - 409 : nom déjà utilisé

### PUT /creatures/{id}

- **Description** : met à jour une créature.
- **Body JSON** : partiel (seuls les champs fournis sont modifiés) :
  ```json
  {
    "name": "Hydre des Marais Renforcée",
    "health_score": 95
  }
  ```
- **Réponses** :
  - 200 : créature mise à jour
  - 404 : créature inconnue
  - 400 / 409 : validations métier

### DELETE /creatures/{id}

- **Description** : supprime une créature.
- **Réponses** :
  - 204 : supprimée
  - 404 : créature inconnue
  - 409 : créature utilisée dans un combat (erreur SQL / contrainte)

### GET /creatures/{id}/image

- **Description** : redirige vers l’URL Pollinations de la créature.
- **Réponses** :
  - 302 + header `Location: https://image.pollinations.ai/...`
  - 404 : créature inconnue

### GET /creatures/{id}/image?proxy=1

- **Description** : télécharge l’image distante et la renvoie directement.
- **Réponses** :
  - 200 + binaire image (`Content-Type: image/*`)
  - 404 / 502 en cas d’erreur

### POST /creatures/{id}/image

- **Description** : régénère l’URL d’image et la met à jour en base.
- **Réponses** :
  - 200 : créature mise à jour
  - 404 : créature inconnue

### POST /creatures/generate

- **Description** : crée une créature à partir d’un prompt IA.
- **Body JSON** :
  ```json
  {
    "prompt": "une tortue géante qui porte une ville sur son dos"
  }
  ```
  - Optionnellement, on peut ajouter `"type": "Tortue"` ou `"type": 2`.
  - Si `type` est absent, l’API :
    - essaie de reconnaître un type existant dans le prompt,
    - sinon extrait un nom de type (ex. "Tortue") et crée le type au besoin,
    - sinon utilise/crée le type `"Hybride"`.

- **Réponses** :
  - 201 : créature complète (nom, description, scores, image…)
  - 400 : `prompt` manquant ou type id/nom invalide

---

## Hybrides

### GET /hybrids

- **Description** : liste les créatures hybrides (`isHybride = 1`).
- **Réponse 200** :
  ```json
  {
    "items": [
      {
        "uuid": 10,
        "name": "Chimère Tortue-Licorne",
        "created_at": "...",
        "type_uuid": 3,
        "type_name": "Hybride"
      }
    ]
  }
  ```

### GET /hybrids/{id}

- **Description** : détail d’une créature hybride.
- **Réponses** :
  - 200 : objet créature avec `isHybride = 1`
  - 404 : hybride inconnu (ou créature non hybride)

### POST /hybrids

- **Description** : fusionne deux créatures pour créer un hybride.
- **Body JSON** :
  ```json
  {
    "creature_1": 1,
    "creature_2": 2,
    "name": "optionnel",
    "heads": 3
  }
  ```
- **Réponses** :
  - 201 :
    ```json
    {
      "uuid": 10,
      "name": "Chimère Tortue-Licorne",
      "type_uuid": 3,
      "type_name": "Hybride",
      "description": "...",
      "image": "https://image.pollinations.ai/prompt/...",
      "health_score": 80,
      "defense_score": 60,
      "attaque_score": 75,
      "heads": 3,
      "isHybride": 1,
      "parent_1": 1,
      "parent_2": 2
    }
    ```
  - 400 : ids invalides ou identiques
  - 404 : au moins une des créatures n’existe pas

---

## Combats

### GET /combat

- **Description** : liste les combats enregistrés.
- **Réponse 200** :
  ```json
  {
    "items": [
      {
        "uuid": 1,
        "result": "6",
        "creature_1": 6,
        "creature_2": 7,
        "created_at": "2024-11-03 10:12:00"
      }
    ]
  }
  ```

### GET /combat/{id}

- **Description** : détail d’un combat.
- **Réponses** :
  - 200 : combat
  - 404 : combat inconnu

### POST /combat

- **Description** : lance un combat entre deux créatures.
- **Body JSON** :
  ```json
  {
    "creature_1": 6,
    "creature_2": 7
  }
  ```
- **Réponses** :
  - 201 :
    ```json
    {
      "uuid": 1,
      "result": "6",
      "creature_1": 6,
      "creature_2": 7
    }
    ```
  - 400 : ids invalides ou identiques
  - 404 : au moins une des créatures n’existe pas

