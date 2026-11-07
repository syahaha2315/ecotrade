<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/auth.php';

$email = strtolower(trim($argv[1] ?? ''));
$option = $argv[2] ?? '';
if (!validAdminEmail($email) || !in_array($option, ['', '--retire-demo'], true) || $argc > 3) {
    fwrite(STDERR, "Use: php tools/create-admin.php <email address> [--retire-demo]\n");
    exit(1);
}

$pdo = null;
try {
    $pdo = connectDatabase();
    $password = bin2hex(random_bytes(12));
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, preferred_language) VALUES ('EcoTrade Admin', ?, ?, 'admin', 'en')")
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    if ($option === '--retire-demo') {
        $pdo->exec("UPDATE users SET is_active = 0 WHERE email = 'admin@ecotrade.test' AND role = 'admin'");
        $pdo->exec("DELETE s FROM user_sessions s JOIN users u ON u.user_id = s.user_id WHERE u.email = 'admin@ecotrade.test' AND u.role = 'admin'");
    }
    $pdo->commit();
    echo "Admin email: $email\nEcoTrade password: $password\n";
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062
        ? 'This email already belongs to an EcoTrade account.'
        : 'Could not create the admin. Check the database connection.';
    fwrite(STDERR, $message . "\n");
    exit(1);
}
