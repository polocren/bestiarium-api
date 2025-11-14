<?php



function api_get_authorization_header(): ?string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    return null;
}

function api_base64url_decode(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64, true);
    return $decoded === false ? '' : $decoded;
}


function api_verify_jwt(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h64, $p64, $s64] = $parts;

    $headerJson = api_base64url_decode($h64);
    $payloadJson = api_base64url_decode($p64);
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
        return null;
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $secret = getenv('JWT_SECRET') ?: 'dev-secret-change-me';
    $expected = hash_hmac('sha256', $h64 . '.' . $p64, $secret, true);
    $sig = api_base64url_decode($s64);

    if (!hash_equals($expected, $sig)) {
        return null;
    }

    if (isset($payload['exp']) && time() >= (int)$payload['exp']) {
        return null;
    }

    return $payload;
}


function api_current_user_id(): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $header = api_get_authorization_header();
    if (!$header || stripos($header, 'bearer ') !== 0) {
        $cached = 0;
        return 0;
    }

    $token = trim(substr($header, 7));
    if ($token === '') {
        $cached = 0;
        return 0;
    }

    $payload = api_verify_jwt($token);
    if (!$payload || !isset($payload['sub'])) {
        $cached = 0;
        return 0;
    }

    $cached = (int)$payload['sub'];
    return $cached;
}


function api_effective_user_id(int $fallbackId = 1): int
{
    $uid = api_current_user_id();
    return $uid > 0 ? $uid : $fallbackId;
}
