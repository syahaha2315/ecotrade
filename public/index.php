<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/http.php';
require dirname(__DIR__) . '/app/auth.php';
require dirname(__DIR__) . '/app/listings.php';
require dirname(__DIR__) . '/app/controller.php';

$user = null;
$csrfToken = '';
try {
    $pdo = connectDatabase();
    $user = currentUser($pdo);
    $csrfToken = csrfToken();
    runApplication($pdo);
} catch (HttpError $error) {
    http_response_code($error->status);
    renderPage('Permintaan tidak dapat diteruskan', 'error', ['message' => $error->getMessage(), 'status' => $error->status]);
} catch (Throwable $error) {
    error_log('EcoTrade: ' . $error->getMessage());
    http_response_code(503);
    renderPage('EcoTrade belum dapat dicapai', 'error', [
        'message' => 'Database belum dapat dicapai. Pastikan terminal database masih berjalan, kemudian muat semula halaman ini.',
        'status' => 503,
    ]);
}
