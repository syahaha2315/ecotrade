<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/database.php';

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo "PASS: $message\n";
}

function expectRejected(PDO $pdo, string $sql, array $values, array $codes, string $message): void
{
    try {
        $pdo->prepare($sql)->execute($values);
    } catch (PDOException $error) {
        if (!in_array((int) ($error->errorInfo[1] ?? 0), $codes, true)) {
            throw $error;
        }
        verify(true, $message);
        return;
    }
    throw new RuntimeException('Database tidak menolak: ' . $message);
}

$pdo = null;
try {
    $pdo = connectDatabase();
    verify($pdo->query('SELECT 1')->fetchColumn() == 1, 'PHP boleh membaca database melalui PDO.');
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    verify($tables === ['categories', 'google_accounts', 'listing_messages', 'listing_photos', 'listings', 'requests', 'translations', 'user_avatars', 'user_sessions', 'users'], 'Ten tables are available, including profile photos.');
    $foreignKeys = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
    verify((int) $foreignKeys === 14, 'Fourteen foreign keys protect table relationships.');
    $categories = $pdo->query('SELECT category_name FROM categories WHERE is_active = 1 ORDER BY category_id')->fetchAll(PDO::FETCH_COLUMN);
    verify(count($categories) >= 3, 'Kategori aktif dibaca daripada database: ' . implode(', ', $categories));
    verify(!str_starts_with($pdo->query('SELECT CURRENT_USER()')->fetchColumn(), 'root@'), 'Aplikasi bersambung dengan akaun selain root.');
    $hashes = $pdo->query('SELECT password_hash FROM users')->fetchAll(PDO::FETCH_COLUMN);
    verify(count($hashes) >= 3 && array_all_hashes_valid($hashes), 'Akaun demo tersedia dan password disimpan sebagai hash.');

    $pdo->beginTransaction();
    $suffix = bin2hex(random_bytes(8));
    $insertUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
    $insertUser->execute(['Ujian Sementara', "check-$suffix@ecotrade.test", password_hash('test-only', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    expectRejected($pdo, 'INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)', ['Duplicate', "check-$suffix@ecotrade.test", 'test'], [1062], 'Email pendua ditolak.');
    $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute(["Ujian $suffix"]);
    $categoryId = (int) $pdo->lastInsertId();
    $listingSql = 'INSERT INTO listings (owner_id, category_id, title, description, listing_type) VALUES (?, ?, ?, ?, ?)';
    $text = "Buku pelajar: nota ‘pengaturcaraan’ 📚";
    $pdo->prepare($listingSql)->execute([$userId, $categoryId, 'Ujian UTF-8', $text, 'donation']);
    $listingId = (int) $pdo->lastInsertId();
    $readText = $pdo->prepare('SELECT description, image_s3_key FROM listings WHERE listing_id = ?');
    $readText->execute([$listingId]);
    $row = $readText->fetch();
    verify($row['description'] === $text && $row['image_s3_key'] === null, 'Teks UTF-8 disimpan betul dan gambar boleh NULL.');
    expectRejected($pdo, $listingSql, [$userId, 0, 'Orphan', 'Invalid category', 'donation'], [1452], 'Listing dengan kategori yang tidak wujud ditolak.');
    expectRejected($pdo, 'DELETE FROM users WHERE user_id = ?', [$userId], [1451], 'Pemilik listing yang masih dirujuk tidak boleh dipadam.');
    expectRejected($pdo, 'INSERT INTO requests (requester_id, target_listing_id, offered_listing_id) VALUES (?, ?, ?)', [$userId, $listingId, $listingId], [4025, 3819], 'Target dan offered listing yang sama ditolak.');

    $pdo->prepare('INSERT INTO requests (requester_id, target_listing_id) VALUES (?, ?)')->execute([$userId, $listingId]);
    $requestId = (int) $pdo->lastInsertId();
    $translationSql = 'INSERT INTO translations (listing_id, request_id, field_name, source_hash, target_language, translated_text) VALUES (?, ?, ?, ?, ?, ?)';
    $hash = hash('sha256', 'ms:' . $text);
    $pdo->prepare($translationSql)->execute([$listingId, null, 'description', $hash, 'en', 'Student book']);
    expectRejected($pdo, $translationSql, [$listingId, null, 'description', $hash, 'en', 'Duplicate'], [1062], 'Cache terjemahan pendua untuk parent, field dan bahasa ditolak.');
    expectRejected($pdo, $translationSql, [$listingId, $requestId, 'description', $hash, 'ms', 'Both'], [4025, 3819], 'Terjemahan yang merujuk listing dan request serentak ditolak.');
    expectRejected($pdo, $translationSql, [null, null, 'description', $hash, 'en', 'Neither'], [4025, 3819], 'Terjemahan tanpa parent ditolak.');
    expectRejected($pdo, $translationSql, [null, $requestId, 'description', $hash, 'en', 'Wrong field'], [4025, 3819], 'Field terjemahan yang tidak sepadan dengan jenis parent ditolak.');
    $pdo->prepare($translationSql)->execute([null, $requestId, 'exchange_note', $hash, 'en', 'Test note']);
    expectRejected($pdo, $translationSql, [null, $requestId, 'exchange_note', $hash, 'en', 'Duplicate note'], [1062], 'Cache terjemahan request pendua ditolak.');
    expectRejected($pdo, 'INSERT INTO user_sessions (session_hash, user_id, created_at, expires_at) VALUES (?, ?, ?, ?)', [hash('sha256', $suffix), $userId, '2026-01-02 00:00:00', '2026-01-01 00:00:00'], [4025, 3819], 'Session yang tamat sebelum dicipta ditolak.');
    $pdo->rollBack();
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $remaining->execute(["check-$suffix@ecotrade.test"]);
    verify((int) $remaining->fetchColumn() === 0, 'Rekod ujian sementara di-rollback; data demo dikekalkan.');
    echo "\nSIAP: $checks semakan lulus.\n";
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}

function array_all_hashes_valid(array $hashes): bool
{
    foreach ($hashes as $hash) {
        if (password_get_info($hash)['algoName'] === 'unknown') {
            return false;
        }
    }
    return true;
}
