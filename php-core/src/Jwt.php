<?php

declare(strict_types=1);

/**
 * Jwt — pure-PHP HS256 JSON Web Token implementation.
 *
 * No external libraries required.
 *
 * Security notes:
 *  - Uses hash_equals() for timing-safe signature comparison,
 *    preventing timing-based attacks on the HMAC.
 *  - Validates the 'exp' claim to reject expired tokens.
 *  - Secret is loaded from the JWT_SECRET env variable.
 */
class Jwt
{
    private static int $ttl = 3600; // 1 hour

    // ── Encode ────────────────────────────────────────────────────────────────

    public static function encode(array $payload): string
    {
        $secret = Env::get('JWT_SECRET');

        $header  = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$ttl;
        $body    = self::b64url(json_encode($payload));

        $signature = self::b64url(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    // ── Decode ────────────────────────────────────────────────────────────────

    /**
     * Returns the decoded payload array, or null if the token is
     * invalid, tampered with, or expired.
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $secret = Env::get('JWT_SECRET');

        // Timing-safe signature verification
        $expected = self::b64url(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::b64urlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        // Reject expired tokens
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
