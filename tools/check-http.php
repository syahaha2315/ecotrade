<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function request(string $path, string $method = 'GET'): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 5,
    ]]);
    $body = file_get_contents('http://127.0.0.1:8000' . $path, false, $context);
    if ($body === false) {
        throw new RuntimeException('Server PHP belum dapat dicapai. Jalankan scripts/serve.ps1 dahulu.');
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches);
    return [(int) $matches[1], $body];
}

try {
    [$status, $body] = request('/health.php');
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if ($status !== 200 || ($data['status'] ?? '') !== 'connected' || !isset($data['categories'])) {
        throw new RuntimeException('GET /health.php belum memulangkan sambungan berjaya dan kategori.');
    }
    echo "PASS: GET /health.php memberi HTTP 200, status connected dan data kategori.\n";
    [$status] = request('/health.php', 'POST');
    if ($status !== 405) {
        throw new RuntimeException('POST / sepatutnya ditolak dengan HTTP 405.');
    }
    echo "PASS: POST / ditolak dengan HTTP 405.\n";
    foreach (['/config/local.php', '/database/schema.sql', '/tools/setup.php'] as $path) {
        [$status] = request($path);
        if ($status !== 404) {
            throw new RuntimeException("Fail dalaman boleh dicapai: $path");
        }
        echo "PASS: $path memberi HTTP 404.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
