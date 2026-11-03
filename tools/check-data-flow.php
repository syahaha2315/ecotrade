<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/database.php';

function readEndpoint(): array
{
    $body = file_get_contents('http://127.0.0.1:8000/health.php', false, stream_context_create(['http' => ['timeout' => 5]]));
    if ($body === false) {
        throw new RuntimeException('Hidupkan scripts/serve.ps1 sebelum ujian ini.');
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

$pdo = null;
$originals = [];
try {
    $pdo = connectDatabase();
    $originals = $pdo->query('SELECT category_id, category_name, is_active FROM categories')->fetchAll();
    if ($originals === []) {
        throw new RuntimeException('Ujian memerlukan sekurang-kurangnya satu kategori demo.');
    }
    $id = $originals[0]['category_id'];
    $temporaryName = '[CHECK] ' . bin2hex(random_bytes(8));
    $pdo->prepare('UPDATE categories SET category_name = ?, is_active = 1 WHERE category_id = ?')->execute([$temporaryName, $id]);
    $anotherConnection = connectDatabase();
    $query = $anotherConnection->prepare('SELECT category_name FROM categories WHERE category_id = ?');
    $query->execute([$id]);
    if ($query->fetchColumn() !== $temporaryName || !in_array($temporaryName, array_column(readEndpoint()['categories'], 'category_name'), true)) {
        throw new RuntimeException('Perubahan database belum dibaca oleh sambungan baru dan HTTP.');
    }
    echo "PASS: Perubahan kategori kekal pada sambungan baru dan muncul melalui HTTP.\n";
    $disable = $pdo->prepare('UPDATE categories SET is_active = 0 WHERE category_id = ?');
    foreach ($originals as $row) {
        $disable->execute([$row['category_id']]);
    }
    $empty = readEndpoint();
    if (($empty['status'] ?? '') !== 'connected' || $empty['categories'] !== [] || !str_contains($empty['message'], 'Belum ada kategori')) {
        throw new RuntimeException('Respons kategori kosong belum betul.');
    }
    echo "PASS: Tiada kategori aktif memberi senarai kosong dan mesej yang sesuai.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $failed = true;
} finally {
    // These changes must be committed for the HTTP server to see them, then restored.
    if ($pdo instanceof PDO && $originals !== []) {
        $restore = $pdo->prepare('UPDATE categories SET category_name = ?, is_active = ? WHERE category_id = ?');
        foreach ($originals as $row) {
            $restore->execute([$row['category_name'], $row['is_active'], $row['category_id']]);
        }
        echo "PASS: Nama dan status semua kategori ujian sudah dipulihkan.\n";
    }
}
exit(isset($failed) ? 1 : 0);
