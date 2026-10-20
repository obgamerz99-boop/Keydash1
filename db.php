<?php
function database(): PDO {
    $host = getenv('KEYDASH_DB_HOST') ?: '127.0.0.1';
    $name = getenv('KEYDASH_DB_NAME') ?: 'keydash';
    $user = getenv('KEYDASH_DB_USER') ?: 'root';
    $pass = getenv('KEYDASH_DB_PASSWORD') ?: '';
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $connection = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Add the profile avatar field automatically for databases created by older Keydash versions.
    $column = $connection->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch();
    if (!$column) $connection->exec("ALTER TABLE users ADD avatar VARCHAR(16) NOT NULL DEFAULT '⌨️'");
    return $connection;
}
