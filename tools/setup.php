<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/database/seed.php';

try {
    $config = databaseConfig();
    if (!in_array($config['host'], ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Setup ini untuk database laptop sahaja. DB_HOST mesti localhost atau 127.0.0.1.');
    }
    if ($config['username'] !== 'ecotrade_app') {
        throw new RuntimeException('Setup tempatan ini menggunakan akaun ecotrade_app.');
    }
    $localPath = dirname(__DIR__) . '/config/local.php';
    if (!is_file($localPath) && getenv('DB_PASSWORD') === false) {
        $config['password'] = bin2hex(random_bytes(24));
    }
    $adminConfig = $config;
    $adminConfig['username'] = getenv('ECOTRADE_ADMIN_USER') ?: 'root';
    $adminConfig['password'] = getenv('ECOTRADE_ADMIN_PASSWORD') ?: '';
    $admin = connectDatabase($adminConfig, false);
    $name = $config['database'];
    $admin->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $admin->exec("USE `$name`");
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') {
            $admin->exec($statement);
        }
    }
    $deletedAt = $admin->query("SHOW COLUMNS FROM listings LIKE 'deleted_at'")->fetch();
    if (!$deletedAt) {
        $admin->exec('ALTER TABLE listings ADD COLUMN deleted_at DATETIME NULL');
    }
    $account = $admin->quote($config['username']) . "@'localhost'";
    $admin->exec('CREATE USER IF NOT EXISTS ' . $account . ' IDENTIFIED BY ' . $admin->quote($config['password']));
    $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `$name`.* TO " . $account);
    $app = connectDatabase($config);
    if (!is_file($localPath)) {
        $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($localPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis config/local.php.');
        }
    }
    seedDatabase($app);
    echo "Ready: database $name, schema and demo accounts are available.\n";
    echo "Config aplikasi: config/local.php (password tidak dipaparkan).\n";
    echo "Aplikasi menggunakan ecotrade_app, bukan akaun root.\n";
    echo "Seterusnya: jalankan scripts/check.ps1, kemudian scripts/serve.ps1.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Setup gagal: " . $error->getMessage() . "\n");
    fwrite(STDERR, "Pastikan scripts/start-db.ps1 sedang berjalan. Semak README.md untuk bantuan.\n");
    exit(1);
}
