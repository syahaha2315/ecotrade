<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/database.php';

final class TestClient
{
    private CurlHandle $curl;
    public function __construct()
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
    }
    public function request(string $page, array $data = [], bool $post = false): array
    {
        $headers = [];
        curl_setopt($this->curl, CURLOPT_URL, 'http://127.0.0.1:8000/' . $page);
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower($key)] = trim($value); }
            return strlen($line);
        });
        if ($post) {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }
        $body = curl_exec($this->curl);
        if ($body === false) { throw new RuntimeException(curl_error($this->curl)); }
        return ['status' => curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $page): string
    {
        $response = $this->request($page);
        if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $matches)) {
            throw new RuntimeException('CSRF form not found: ' . $page);
        }
        return $matches[1];
    }
    public function session(): string
    {
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'ecotrade_session') { return $parts[6]; }
        }
        return '';
    }
    public function replay(string $token): void { curl_setopt($this->curl, CURLOPT_COOKIE, 'ecotrade_session=' . $token); }
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . "\n";
    $checks++;
}

$pdo = connectDatabase();
$suffix = bin2hex(random_bytes(8));
$emails = ["qa.$suffix@ecotrade.test", 'qa.' . bin2hex(random_bytes(8)) . '@ecotrade.test'];
$categoryId = 0;
$failed = false;
try {
    $a = new TestClient(); $b = new TestClient(); $guest = new TestClient();
    check($guest->request('?page=create')['status'] === 303, 'Guest dihantar ke login untuk tambah barang.');
    check($guest->request('?page=register', ['full_name' => 'Forged'], true)['status'] === 403, 'POST tanpa CSRF ditolak.');
    $password = 'QaEcoTrade!2026';
    foreach ([$a, $b] as $index => $client) {
        $registration = ['csrf_token' => $client->token('?page=register'), 'full_name' => 'QA Student ' . $index, 'email' => $emails[$index], 'password' => $password, 'password_confirmation' => $password, 'role' => 'admin'];
        if ($index === 0) {
            check($client->request('?page=register', array_replace($registration, ['password_confirmation' => 'different']), true)['status'] === 422, 'Password confirmation salah ditolak.');
            check($client->request('?page=register', array_replace($registration, ['password' => 'short', 'password_confirmation' => 'short']), true)['status'] === 422, 'Password pendek ditolak di server.');
        }
        check($client->request('?page=register', $registration, true)['status'] === 303, 'Pendaftaran akaun ' . ($index + 1) . ' berjaya.');
    }
    $accounts = $pdo->prepare('SELECT user_id, role, password_hash FROM users WHERE email IN (?, ?) ORDER BY email');
    $accounts->execute($emails);
    $rows = $accounts->fetchAll();
    check(count($rows) === 2 && count(array_filter($rows, fn ($r) => $r['role'] === 'student' && password_verify($password, $r['password_hash']))) === 2, 'Role admin palsu diabaikan dan password dihash.');
    $duplicate = ['csrf_token' => $a->token('?page=register'), 'full_name' => 'Duplicate', 'email' => $emails[0], 'password' => $password, 'password_confirmation' => $password];
    check($a->request('?page=register', $duplicate, true)['status'] === 422, 'Email pendua ditolak.');
    check($a->request('?page=login', ['csrf_token' => $a->token('?page=login'), 'email' => $emails[0], 'password' => 'wrong'], true)['status'] === 422, 'Password login salah ditolak.');
    $find = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
    $find->execute([$emails[0]]); $aId = (int) $find->fetchColumn();
    $find->execute([$emails[1]]); $bId = (int) $find->fetchColumn();
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$aId]);
    check($a->request('?page=login', ['csrf_token' => $a->token('?page=login'), 'email' => $emails[0], 'password' => $password], true)['status'] === 422, 'Akaun tidak aktif tidak boleh login.');
    $pdo->prepare('UPDATE users SET is_active = 1 WHERE user_id = ?')->execute([$aId]);
    $login = $a->request('?page=login', ['csrf_token' => $a->token('?page=login'), 'email' => $emails[0], 'password' => $password], true);
    check($login['status'] === 303, 'Login sah berjaya.');
    $session = $a->session();
    $sessionQuery = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE session_hash = ? AND user_id = ?');
    $sessionQuery->execute([hash('sha256', $session), $aId]);
    check(strlen($session) === 64 && (int) $sessionQuery->fetchColumn() === 1 && str_contains($login['headers']['set-cookie'], 'HttpOnly') && str_contains($login['headers']['set-cookie'], 'SameSite=Lax'), 'Session rawak dihash dalam database; cookie HttpOnly dan SameSite.');
    check($a->request('?page=mine')['status'] === 200, 'Session membolehkan halaman Barang saya dibuka.');
    $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute(['[QA] ' . $suffix]);
    $categoryId = (int) $pdo->lastInsertId();
    $title = '[QA] Buku ' . $suffix;
    $values = ['csrf_token' => $a->token('?page=create'), 'title' => $title, 'description' => 'Buku ujian untuk pembelajaran. <script>alert("xss")</script>', 'category_id' => $categoryId, 'item_condition' => 'good', 'listing_type' => 'donation', 'source_language' => 'ms', 'owner_id' => $bId];
    check($a->request('?page=create', array_replace($values, ['category_id' => 0]), true)['status'] === 422, 'Kategori tidak wujud ditolak.');
    check($a->request('?page=create', array_replace($values, ['item_condition' => 'invalid']), true)['status'] === 422, 'Keadaan barang tidak sah ditolak.');
    $pdo->prepare('UPDATE categories SET is_active = 0 WHERE category_id = ?')->execute([$categoryId]);
    check($a->request('?page=create', $values, true)['status'] === 422, 'Kategori tidak aktif ditolak.');
    $pdo->prepare('UPDATE categories SET is_active = 1 WHERE category_id = ?')->execute([$categoryId]);
    $created = $a->request('?page=create', $values, true);
    parse_str(parse_url($created['headers']['location'] ?? '', PHP_URL_QUERY) ?? '', $location);
    $id = (int) ($location['id'] ?? 0);
    check($created['status'] === 303 && $id > 0, 'Tambah barang menyimpan rekod dan membuka detail.');
    $read = $pdo->prepare('SELECT * FROM listings WHERE listing_id = ?'); $read->execute([$id]); $listing = $read->fetch();
    check((int) $listing['owner_id'] === $aId && $listing['image_s3_key'] === null, 'Owner ID palsu diabaikan; listing tanpa gambar disimpan.');
    $detail = $a->request('?page=view&id=' . $id);
    check($detail['status'] === 200 && str_contains($detail['body'], '&lt;script&gt;alert'), 'Detail memaparkan data dengan HTML escaping.');
    $needle = 'page=view&amp;id=' . $id;
    check(str_contains($guest->request('?page=browse&q=' . urlencode($suffix))['body'], $needle), 'Carian kata kunci menemui barang yang dicipta.');
    check(str_contains($guest->request('?page=browse&category_id=' . $categoryId . '&item_condition=good&listing_type=donation')['body'], $needle), 'Penapis kategori, keadaan dan jenis berfungsi bersama.');
    check(!str_contains($guest->request('?page=browse&q=' . urlencode($suffix) . '&listing_type=exchange')['body'], $needle), 'Penapis yang tidak sepadan menghasilkan senarai kosong.');
    check($guest->request('?page=browse&q=' . urlencode("' OR 1=1 --"))['status'] === 200, 'Input berbentuk SQL diproses sebagai teks carian.');
    check($b->request('?page=login', ['csrf_token' => $b->token('?page=login'), 'email' => $emails[1], 'password' => $password], true)['status'] === 303, 'Student B login dalam session berasingan.');
    $bToken = $b->token('?page=mine');
    check($b->request('?page=edit&id=' . $id)['status'] === 403, 'Student B ditolak untuk GET edit barang A.');
    check($b->request('?page=edit&id=' . $id, array_replace($values, ['csrf_token' => $bToken, 'title' => 'Forged update']), true)['status'] === 403, 'Student B ditolak untuk POST edit barang A dengan CSRF sah.');
    check($b->request('?page=archive&id=' . $id)['status'] === 403, 'Student B ditolak untuk halaman archive barang A.');
    check($b->request('?page=archive&id=' . $id, ['csrf_token' => $bToken], true)['status'] === 403, 'Student B ditolak untuk POST archive barang A.');
    $read->execute([$id]); check($read->fetch()['title'] === $title, 'Percubaan Student B tidak mengubah data A.');
    check(!str_contains($b->request('?page=mine')['body'], $needle), 'Barang saya B tidak memaparkan barang A.');
    check(!str_contains($b->request('?page=view&id=' . $id)['body'], 'page=edit&amp;id=' . $id), 'Butang pengurusan tidak dipaparkan kepada bukan pemilik.');
    $pdo->prepare('INSERT INTO translations (listing_id, field_name, source_hash, target_language, translated_text) VALUES (?, ?, ?, ?, ?)')->execute([$id, 'description', hash('sha256', 'old'), 'en', 'Old text']);
    $editedValues = array_replace($values, ['csrf_token' => $a->token('?page=edit&id=' . $id), 'title' => $title . ' updated', 'description' => 'Penerangan baharu untuk buku ujian yang masih dalam keadaan baik.']);
    check($a->request('?page=edit&id=' . $id, $editedValues, true)['status'] === 303, 'Pemilik boleh mengedit barang sendiri.');
    $read->execute([$id]); check($read->fetch()['title'] === $editedValues['title'], 'Perubahan edit tersimpan dalam database.');
    $cache = $pdo->prepare('SELECT COUNT(*) FROM translations WHERE listing_id = ?'); $cache->execute([$id]);
    check((int) $cache->fetchColumn() === 0, 'Cache terjemahan lama dibatalkan selepas penerangan diedit.');
    $pdo->prepare("UPDATE listings SET status = 'reserved' WHERE listing_id = ?")->execute([$id]);
    check($a->request('?page=edit&id=' . $id, $editedValues, true)['status'] === 409 && $a->request('?page=archive&id=' . $id, ['csrf_token' => $editedValues['csrf_token']], true)['status'] === 409, 'Barang reserved tidak boleh diedit atau diarkibkan.');
    $pdo->prepare("UPDATE listings SET status = 'available' WHERE listing_id = ?")->execute([$id]);
    check($a->request('?page=archive&id=' . $id)['status'] === 200, 'Archive membuka halaman pengesahan dahulu.');
    $read->execute([$id]); check($read->fetch()['status'] === 'available', 'GET pengesahan archive tidak mengubah data.');
    check($a->request('?page=archive&id=' . $id, [], true)['status'] === 403, 'Archive tanpa CSRF ditolak.');
    check($a->request('?page=archive&id=' . $id, ['csrf_token' => $a->token('?page=archive&id=' . $id)], true)['status'] === 303, 'Pemilik boleh mengarkibkan barang.');
    $read->execute([$id]); check($read->fetch()['status'] === 'archived', 'Archive mengekalkan rekod dengan status archived.');
    check(!str_contains($guest->request('?page=browse&q=' . urlencode($suffix))['body'], $needle), 'Barang archived hilang daripada carian umum.');
    check(str_contains($a->request('?page=mine&status=archived')['body'], $needle), 'Pemilik boleh melihat rekod archived sendiri.');
    check($b->request('?page=view&id=' . $id)['status'] === 404, 'Barang archived tidak didedahkan kepada Student B.');
    check($a->request('?page=edit&id=' . $id)['status'] === 409, 'Barang archived tidak boleh diedit.');
    $pdo->prepare('UPDATE user_sessions SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE user_id = ?')->execute([$bId]);
    check($b->request('?page=mine')['status'] === 303, 'Session tamat tidak boleh membuka halaman dilindungi.');
    check($a->request('?page=logout', ['csrf_token' => $a->token('?page=mine')], true)['status'] === 303, 'Log keluar berjaya.');
    $sessionQuery->execute([hash('sha256', $session), $aId]);
    check((int) $sessionQuery->fetchColumn() === 0 && $a->session() === '', 'Log keluar memadam session database dan cookie.');
    $replay = new TestClient(); $replay->replay($session);
    check($replay->request('?page=mine')['status'] === 303, 'Token lama tidak boleh digunakan semula selepas logout.');
    check($a->request('?page=logout')['status'] === 405, 'GET logout ditolak untuk elak perubahan melalui link.');
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
} finally {
    $owned = $pdo->prepare('SELECT user_id FROM users WHERE email IN (?, ?)'); $owned->execute($emails); $ids = $owned->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $userId) {
        $pdo->prepare('DELETE t FROM translations t JOIN listings l ON l.listing_id = t.listing_id WHERE l.owner_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM listings WHERE owner_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);
    }
    if ($categoryId > 0) { $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$categoryId]); }
    echo "Rekod QA sementara telah dibersihkan; rekod demo asal dikekalkan.\n";
}
echo "$checks semakan prototype lulus.\n";
exit($failed ? 1 : 0);
