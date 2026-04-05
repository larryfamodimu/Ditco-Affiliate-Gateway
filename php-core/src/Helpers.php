<?php

declare(strict_types=1);

/**
 * Helpers — shared utility functions.
 *
 * sanitize()      : strips XSS vectors from string input.
 * respond()       : sends a JSON response and terminates.
 * getClientIp()   : resolves the real client IP address.
 * requireJwt()    : extracts + validates Bearer token; aborts on failure.
 * requireBody()   : decodes JSON request body; aborts on malformed input.
 */
class Helpers
{
    /**
     * Sanitize a string against XSS.
     *
     * htmlspecialchars encodes <, >, ", ', & so they cannot be rendered
     * as HTML tags or event handlers if the value is ever embedded in HTML.
     * ENT_QUOTES covers both single and double quote contexts.
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Emit a JSON response with the given HTTP status code and exit.
     */
    public static function respond(int $status, array $data): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Resolve the real client IP, accounting for reverse proxies.
     * The first entry in X-Forwarded-For is the originating client.
     */
    public static function getClientIp(): string
    {
        $raw = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';

        // X-Forwarded-For can be a comma-separated list; take the first
        $ip = trim(explode(',', $raw)[0]);

        // Basic validation — fall back to REMOTE_ADDR if it looks wrong
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Validate the Authorization: Bearer <token> header and return the
     * decoded JWT payload. Responds 401 and exits on any failure.
     */
    public static function requireJwt(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            self::respond(401, ['error' => 'Missing or malformed Authorization header.']);
        }

        $token   = substr($header, 7);
        $payload = Jwt::decode($token);

        if ($payload === null) {
            self::respond(401, ['error' => 'Invalid or expired token.']);
        }

        return $payload;
    }

    /**
     * Decode the JSON request body. Responds 400 and exits on failure.
     */
    public static function requireBody(): array
    {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!is_array($body)) {
            self::respond(400, ['error' => 'Request body must be valid JSON.']);
        }

        return $body;
    }
}
