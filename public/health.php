<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['status' => 'error', 'message' => 'Endpoint ini menerima GET sahaja.']);
    exit;
}

require dirname(__DIR__) . '/app/database.php';

try {
    $pdo = connectDatabase();
    $query = $pdo->prepare('SELECT category_id, category_name FROM categories WHERE is_active = ? ORDER BY category_id');
    $query->execute([1]);
    $categories = $query->fetchAll();
    $result = [
        'application' => 'EcoTrade',
        'stage' => 'Semakan sambungan database EcoTrade',
        'status' => 'connected',
        'message' => $categories === [] ? 'Database bersambung. Belum ada kategori aktif.' : 'PHP berjaya membaca kategori daripada database.',
        'categories' => $categories,
    ];
} catch (Throwable $error) {
    http_response_code(503);
    error_log('EcoTrade database check: ' . $error->getMessage());
    $result = [
        'application' => 'EcoTrade',
        'status' => 'error',
        'message' => 'Database belum dapat dibaca. Jalankan scripts/start-db.ps1 dan pastikan setup sudah dijalankan.',
        'help' => 'Rujuk README.md dalam folder projek. Butiran ralat ada dalam terminal PHP.',
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
