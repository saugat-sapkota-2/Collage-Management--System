<?php

declare(strict_types=1);

function dbConnection(array $config): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'] ?? [];
    $host = $db['host'] ?? '';
    $name = $db['name'] ?? '';
    $user = $db['user'] ?? '';
    $password = $db['password'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
