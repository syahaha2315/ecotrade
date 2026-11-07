<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/database.php';
$email = $argv[1] ?? '';
if (!preg_match('/^(?:qa\.[a-f0-9]{16}@ecotrade\.test|ecotrade\.qa\.[a-f0-9]{16}@gmail\.com)$/D', $email)) {
    fwrite(STDERR, "Hanya email QA rawak yang dibenarkan.\n"); exit(1);
}
$pdo = connectDatabase();
$pdo->beginTransaction();
try {
    $query = $pdo->prepare('SELECT user_id FROM users WHERE email = ? FOR UPDATE');
    $query->execute([$email]);
    $id = $query->fetchColumn();
    if ($id !== false) {
        $pdo->prepare('DELETE m FROM listing_messages m JOIN listings l ON l.listing_id = m.listing_id WHERE l.owner_id = ? OR m.buyer_id = ?')
            ->execute([$id, $id]);
        $pdo->prepare('DELETE t FROM translations t JOIN listings l ON l.listing_id = t.listing_id WHERE l.owner_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM listings WHERE owner_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$id]);
    }
    $pdo->commit();
    echo "Rekod akaun QA browser dibersihkan.\n";
} catch (Throwable $error) {
    $pdo->rollBack(); throw $error;
}
