<?php


declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Env.php';
require_once dirname(__DIR__) . '/src/Database.php';

Env::load(dirname(__DIR__) . '/.env');

$pdo      = Database::getInstance();
$username = Env::get('ADMIN_USERNAME', 'admin');
$password = Env::get('ADMIN_PASSWORD', 'admin123');
$hash     = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (username, password)
     VALUES (:username, :password)
     ON CONFLICT (username) DO UPDATE SET password = EXCLUDED.password'
);
$stmt->execute([':username' => $username, ':password' => $hash]);
echo "[OK] Admin '{$username}' seeded.\n";

$affiliatePass = password_hash('affiliate123', PASSWORD_BCRYPT);
$upd = $pdo->prepare(
    "UPDATE affiliates SET password = :hash WHERE slug = 'acme-tech'"
);
$upd->execute([':hash' => $affiliatePass]);
echo "[OK] Sample affiliate password set (password: affiliate123).\n";

echo "\nSetup complete. You can now log in:\n";
echo "  Admin  → POST /admin/login  { username: '{$username}', password: '{$password}' }\n";
