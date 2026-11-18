<?php

declare(strict_types=1);

/**
 * Environment-specific parameters (database connection). Secrets come from the environment only.
 */

$getEnvironment = static function (string $name, string $default = ''): string {
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

    return is_string($value) ? $value : $default;
};

$dbHost = $getEnvironment('DB_HOST', 'db');
$dbPort = $getEnvironment('DB_PORT', '3306');
$dbName = $getEnvironment('DB_NAME', 'app');
$dbCollation = $getEnvironment('DB_COLLATE', 'utf8mb4');
$dbLazy = filter_var($getEnvironment('DB_LAZY', 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;

return [
    'parameters' => [
        'db' => [
            'driver'        => $getEnvironment('DB_DRIVER', 'pdo'),
            'lazy'          => $dbLazy,
            'dsn'           => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCollation),
            'user'          => $getEnvironment('DB_USER'),
            'password'      => $getEnvironment('DB_PASSWORD'),
            'autoReconnect' => true,
        ],
    ],
];
