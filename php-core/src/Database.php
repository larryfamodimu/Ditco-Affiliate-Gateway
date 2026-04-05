<?php

declare(strict_types=1);

/**
 * Database — PDO singleton for PostgreSQL.
 *
 * Security notes:
 *  - ATTR_EMULATE_PREPARES = false  → forces real server-side prepared
 *    statements, preventing SQL injection via emulation quirks.
 *  - ATTR_ERRMODE = ERRMODE_EXCEPTION → surfaces DB errors as catchable
 *    exceptions rather than silent failures.
 *  - Credentials are read from environment variables, never hardcoded.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = Env::get('DB_HOST', 'postgres');
            $port = Env::get('DB_PORT', '5432');
            $name = Env::get('DB_NAME');
            $user = Env::get('DB_USER');
            $pass = Env::get('DB_PASS');

            $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--client_encoding=UTF8'";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // real server-side prepared statements
            ]);
        }

        return self::$instance;
    }

    // Prevent instantiation and cloning
    private function __construct() {}
    private function __clone()     {}
}
