<?php

require_once __DIR__ . '/../models/User.class.php';

class AuthController
{
    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function jwt(array $payload, string $secret, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string
    {
        $h = self::base64url_encode(json_encode($header));
        $p = self::base64url_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        $s = self::base64url_encode($sig);
        return $h . '.' . $p . '.' . $s;
    }

    public static function login(array $data): void
    {
        try {
            $identifier = isset($data['email']) ? trim((string)$data['email']) : (isset($data['username']) ? trim((string)$data['username']) : '');
            $password = isset($data['password']) ? (string)$data['password'] : '';
            if ($identifier === '' || $password === '') {
                http_response_code(400);
                echo json_encode(['error' => ['message' => 'email (ou username) et password requis']], JSON_UNESCAPED_UNICODE);
                return;
            }

            // pour trouver u tilisateur par email ou username
            $user = null;
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $user = UserModel::findByEmail($identifier);
            } else {
                $user = UserModel::findByUsername($identifier);
            }

            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => ['message' => 'Identifiants invalides']], JSON_UNESCAPED_UNICODE);
                return;
            }

            $now = time();
            $exp = $now + 3600; // 1h
            $secret = getenv('JWT_SECRET') ?: 'dev-secret-change-me';
            $token = self::jwt([
                'sub' => (int)$user['uuid'], 
                'username' => $user['username'], // pas besoin 
                'email' => $user['email'], // pas besoin 
                'iat' => $now,
                'exp' => $exp,
            ], $secret);

            echo json_encode([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'user' => UserModel::publicInfo($user),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function register(array $data): void
    {
        try {
            $username = isset($data['username']) ? (string)$data['username'] : '';
            $email = isset($data['email']) ? (string)$data['email'] : '';
            $password = isset($data['password']) ? (string)$data['password'] : '';

            // Création utilisateur
            $id = UserModel::create($username, $email, $password);
            $user = UserModel::findByEmail($email);

            http_response_code(201);
            echo json_encode(['user' => UserModel::publicInfo($user)], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (DomainException $e) {
            http_response_code(409);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
    }
}
