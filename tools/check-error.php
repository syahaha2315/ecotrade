<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// A closed local port exercises the error response without stopping the real database.
putenv('DB_PORT=1');
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require dirname(__DIR__) . '/public/health.php';
$body = ob_get_clean();
$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
if (http_response_code() !== 503 || ($data['status'] ?? '') !== 'error' || str_contains($body, 'SQLSTATE')) {
    fwrite(STDERR, "FAIL: Respons kegagalan database tidak seperti dijangka.\n");
    exit(1);
}
echo "PASS: Sambungan gagal memberi status HTTP 503 dan JSON bantuan tanpa butiran SQL.\n";
