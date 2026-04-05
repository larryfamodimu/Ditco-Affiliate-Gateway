<?php

declare(strict_types=1);


$srcDir = dirname(__DIR__) . '/src';
require_once $srcDir . '/Env.php';
require_once $srcDir . '/Database.php';
require_once $srcDir . '/Jwt.php';
require_once $srcDir . '/Helpers.php';
require_once $srcDir . '/NotificationClient.php';

Env::load(dirname(__DIR__) . '/.env');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = '/' . trim($uri, '/');

if ($method === 'POST' && $uri === '/admin/login') {
    $body = Helpers::requireBody();

    $username = Helpers::sanitize($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        Helpers::respond(422, ['error' => 'username and password are required.']);
    }

    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        Helpers::respond(401, ['error' => 'Invalid credentials.']);
    }

    $token = Jwt::encode(['sub' => $admin['id'], 'role' => 'admin']);
    Helpers::respond(200, ['token' => $token]);
}


if ($method === 'POST' && $uri === '/admin/affiliate') {
    Helpers::requireJwt();

    $body = Helpers::requireBody();

    $businessName    = Helpers::sanitize($body['business_name']    ?? '');
    $email           = Helpers::sanitize($body['email']            ?? '');
    $slug            = Helpers::sanitize($body['slug']             ?? '');
    $phone           = Helpers::sanitize($body['phone']            ?? '');
    $logoUrl         = Helpers::sanitize($body['logo_url']         ?? '');
    $plainPassword   = $body['password'] ?? '';

    
    $errors = [];
    if ($businessName === '') $errors[] = 'business_name is required.';
    if ($email        === '') $errors[] = 'email is required.';
    if ($slug         === '') $errors[] = 'slug is required.';
    if ($plainPassword === '') $errors[] = 'password is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email is invalid.';
    // Slug must be lowercase alphanumeric + hyphens only
    if ($slug !== '' && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $errors[] = 'slug may only contain lowercase letters, numbers, and hyphens.';
    }
    if ($errors) {
        Helpers::respond(422, ['errors' => $errors]);
    }

    $pdo  = Database::getInstance();


    $dup = $pdo->prepare('SELECT id FROM affiliates WHERE slug = :slug OR email = :email');
    $dup->execute([':slug' => $slug, ':email' => $email]);
    if ($dup->fetch()) {
        Helpers::respond(409, ['error' => 'An affiliate with that slug or email already exists.']);
    }

    $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

    $ins = $pdo->prepare(
        'INSERT INTO affiliates (business_name, logo_url, phone, email, slug, password)
         VALUES (:business_name, :logo_url, :phone, :email, :slug, :password)
         RETURNING id, business_name, slug, email, phone, logo_url, created_at'
    );
    $ins->execute([
        ':business_name' => $businessName,
        ':logo_url'      => $logoUrl  ?: null,
        ':phone'         => $phone    ?: null,
        ':email'         => $email,
        ':slug'          => $slug,
        ':password'      => $hash,
    ]);
    $affiliate = $ins->fetch();

    
    NotificationClient::send([
        'affiliate_id'  => $affiliate['id'],
        'business_name' => $affiliate['business_name'],
        'email'         => $affiliate['email'],
    ]);

    Helpers::respond(201, ['data' => $affiliate]);
}


if ($method === 'GET' && preg_match('#^/affiliate/([a-z0-9\-]+)$#i', $uri, $m)) {
    $slug = Helpers::sanitize($m[1]);

    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare(
        'SELECT id, business_name, logo_url, phone, email, slug, created_at
         FROM affiliates WHERE slug = :slug'
    );
    $stmt->execute([':slug' => $slug]);
    $affiliate = $stmt->fetch();

    if (!$affiliate) {
        Helpers::respond(404, ['error' => 'Affiliate not found.']);
    }

    $pStmt = $pdo->prepare(
        'SELECT id, name, description, price, destination_url, created_at
         FROM products WHERE affiliate_id = :id ORDER BY id'
    );
    $pStmt->execute([':id' => $affiliate['id']]);
    $products = $pStmt->fetchAll();

    
    $affiliate['business_name'] = Helpers::sanitize($affiliate['business_name']);
    $affiliate['phone']         = Helpers::sanitize((string)($affiliate['phone'] ?? ''));
    $affiliate['email']         = Helpers::sanitize($affiliate['email']);

    Helpers::respond(200, [
        'data' => array_merge($affiliate, ['products' => $products]),
    ]);
}

if ($method === 'PUT' && preg_match('#^/affiliate/([a-z0-9\-]+)$#i', $uri, $m)) {
    Helpers::requireJwt();

    $slug = Helpers::sanitize($m[1]);
    $body = Helpers::requireBody();

    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare('SELECT id FROM affiliates WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $affiliate = $stmt->fetch();

    if (!$affiliate) {
        Helpers::respond(404, ['error' => 'Affiliate not found.']);
    }

    $allowed = ['business_name', 'logo_url', 'phone', 'email'];
    $sets    = [];
    $params  = [':id' => $affiliate['id']];

    foreach ($allowed as $field) {
        if (!array_key_exists($field, $body)) {
            continue;
        }
        $value = Helpers::sanitize((string) $body[$field]);

        if ($field === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                Helpers::respond(422, ['error' => 'email is invalid.']);
            }

            $chk = $pdo->prepare(
                'SELECT id FROM affiliates WHERE email = :email AND id != :id'
            );
            $chk->execute([':email' => $value, ':id' => $affiliate['id']]);
            if ($chk->fetch()) {
                Helpers::respond(409, ['error' => 'That email is already in use.']);
            }
        }

        $sets[]         = "{$field} = :{$field}";
        $params[":{$field}"] = $value;
    }

    if (empty($sets)) {
        Helpers::respond(422, ['error' => 'No updatable fields provided.']);
    }

    $sets[] = 'updated_at = NOW()';

    $upd = $pdo->prepare(
        'UPDATE affiliates SET ' . implode(', ', $sets) .
        ' WHERE id = :id
         RETURNING id, business_name, logo_url, phone, email, slug, updated_at'
    );
    $upd->execute($params);
    $updated = $upd->fetch();

    Helpers::respond(200, ['data' => $updated]);
}


if ($method === 'POST' && $uri === '/click-track') {
    $body        = Helpers::requireBody();
    $affiliateId = filter_var($body['affiliate_id'] ?? null, FILTER_VALIDATE_INT);

    if ($affiliateId === false || $affiliateId === null) {
        Helpers::respond(422, ['error' => 'affiliate_id must be a valid integer.']);
    }

    $pdo = Database::getInstance();

    $chk = $pdo->prepare('SELECT id FROM affiliates WHERE id = :id');
    $chk->execute([':id' => $affiliateId]);
    if (!$chk->fetch()) {
        Helpers::respond(404, ['error' => 'Affiliate not found.']);
    }

    $ip     = Helpers::getClientIp();
    $window = (int) Env::get('RATE_LIMIT_WINDOW', '60');
    $limit  = (int) Env::get('RATE_LIMIT_MAX',    '10');

    $rate = $pdo->prepare(
        "SELECT COUNT(*) AS cnt FROM click_logs
         WHERE ip_address = :ip
           AND timestamp  > NOW() - (:window * INTERVAL '1 second')"
    );
    $rate->execute([':ip' => $ip, ':window' => $window]);
    $count = (int) $rate->fetch()['cnt'];

    if ($count >= $limit) {
        http_response_code(429);
        header('Retry-After: ' . $window);
        Helpers::respond(429, [
            'error' => "Rate limit exceeded. Maximum {$limit} requests per {$window} seconds.",
        ]);
    }


    $ua   = Helpers::sanitize(
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
    );

    $ins = $pdo->prepare(
        'INSERT INTO click_logs (affiliate_id, ip_address, user_agent)
         VALUES (:affiliate_id, :ip, :ua)
         RETURNING id, timestamp'
    );
    $ins->execute([
        ':affiliate_id' => $affiliateId,
        ':ip'           => $ip,
        ':ua'           => $ua ?: null,
    ]);
    $log = $ins->fetch();

    // Remaining requests before the rate limit kicks in
    $remaining = max(0, $limit - $count - 1);
    header('X-RateLimit-Limit: '     . $limit);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Window: '    . $window);

    Helpers::respond(201, [
        'message'         => 'Click recorded.',
        'click_id'        => $log['id'],
        'timestamp'       => $log['timestamp'],
        'rate_limit'      => ['limit' => $limit, 'remaining' => $remaining, 'window_seconds' => $window],
    ]);
}

Helpers::respond(404, ['error' => "Route {$method} {$uri} not found."]);
