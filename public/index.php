<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob:; object-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/http.php';
require dirname(__DIR__) . '/app/auth.php';
require dirname(__DIR__) . '/app/listings.php';
require dirname(__DIR__) . '/app/photos.php';
require dirname(__DIR__) . '/app/profile.php';
require dirname(__DIR__) . '/app/chat.php';
require dirname(__DIR__) . '/app/admin.php';
require dirname(__DIR__) . '/app/google.php';
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
    renderPage('Request failed', 'error', ['message' => $error->getMessage(), 'status' => $error->status]);
} catch (Throwable $error) {
    error_log('EcoTrade: ' . $error->getMessage());
    http_response_code(503);
    renderPage('EcoTrade is unavailable', 'error', [
        'message' => 'Start the database and reload this page.',
        'status' => 503,
    ]);
}
