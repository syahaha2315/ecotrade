<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('DB_PORT=1');
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require dirname(__DIR__) . '/public/index.php';
$html = ob_get_clean();
if (http_response_code() !== 503 || !str_contains($html, 'EcoTrade is unavailable.') || str_contains($html, 'SQLSTATE')) {
    fwrite(STDERR, "FAIL: Halaman ralat database tidak seperti dijangka.\n"); exit(1);
}
echo "PASS: Halaman HTML kegagalan database memberi 503, mesej bantuan dan tiada butiran SQL.\n";
