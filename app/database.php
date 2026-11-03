<?php
declare(strict_types=1);

function databaseConfig(): array
{
    $path = dirname(__DIR__) . '/config/local.php';
    $local = is_file($path) ? require $path : [];
    $defaults = [
        'host' => '127.0.0.1', 'port' => 3307, 'database' => 'ecotrade',
        'username' => 'ecotrade_app', 'password' => '',
    ];
    $environment = [
        'host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_NAME',
        'username' => 'DB_USER', 'password' => 'DB_PASSWORD',
    ];
    $config = array_replace($defaults, $local);
    foreach ($environment as $key => $name) {
        $value = getenv($name);
        if ($value !== false) {
            $config[$key] = $value;
        }
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['database'])) {
        throw new RuntimeException('DB_NAME hanya boleh mengandungi huruf, nombor dan underscore.');
    }
    $port = filter_var($config['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false || strpbrk($config['host'], ";\r\n") !== false) {
        throw new RuntimeException('DB_HOST atau DB_PORT tidak sah.');
    }
    $config['port'] = $port;
    return $config;
}

function connectDatabase(?array $config = null, bool $selectDatabase = true): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Extension pdo_mysql belum aktif dalam PHP.');
    }
    $config ??= databaseConfig();
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
    if ($selectDatabase) {
        $dsn .= ';dbname=' . $config['database'];
    }
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("SET SESSION time_zone = '+00:00'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}
