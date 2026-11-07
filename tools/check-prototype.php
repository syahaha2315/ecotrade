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
$emails = ["ecotrade.qa.$suffix@gmail.com", 'ecotrade.qa.' . bin2hex(random_bytes(8)) . '@outlook.com'];
$categoryId = 0;
$adminId = 0;
$requestIds = [];
$failed = false;
try {
    $a = new TestClient(); $b = new TestClient(); $guest = new TestClient();
    $admin = new TestClient();
    $adminEmail = 'qa.admin.' . $suffix . '@ecotrade.test';
    $adminPassword = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES ('QA Admin', ?, ?, 'admin')")
        ->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    $adminLogin = $admin->request('?page=login', [
        'csrf_token' => $admin->token('?page=login'),
        'email' => $adminEmail,
        'password' => $adminPassword,
    ], true);
    check($adminLogin['status'] === 303 && str_contains($adminLogin['headers']['location'], 'page=admin'), 'Admin login opens the dashboard.');
    $overview = $admin->request('?page=admin');
    check($overview['status'] === 200 && str_contains($overview['body'], 'href="/?page=admin-reports"'), 'Admin overview loads with the Reports link.');
    check($guest->request('?page=create')['status'] === 303, 'Guest dihantar ke login untuk tambah barang.');
    check($guest->request('?page=register', ['full_name' => 'Forged'], true)['status'] === 403, 'POST tanpa CSRF ditolak.');
    $badEmail = $guest->request('?page=login', ['csrf_token' => $guest->token('?page=login'), 'email' => 'missing-domain@local', 'password' => 'anything'], true);
    check($badEmail['status'] === 422 && str_contains($badEmail['body'], 'Enter a valid email address'), 'Sign-in validates email format on the server.');
    $password = 'QaEcoTrade!2026';
    foreach ([$a, $b] as $index => $client) {
        $registration = ['csrf_token' => $client->token('?page=register'), 'full_name' => 'QA Student ' . $index, 'email' => $emails[$index], 'password' => $password, 'password_confirmation' => $password, 'role' => 'admin'];
        if ($index === 0) {
            $badDomain = $client->request('?page=register', array_replace($registration, ['email' => 'ahmad@iwjdoq.com']), true);
            check($badDomain['status'] === 422 && str_contains($badDomain['body'], 'supported email provider'), 'Registration rejects an unsupported email domain.');
            $weak = 'alllowercase2026';
            check($client->request('?page=register', array_replace($registration, ['password' => $weak, 'password_confirmation' => $weak]), true)['status'] === 422, 'Registration rejects a long password without the required character types.');
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
    $profileToken = $a->token('?page=profile');
    check($a->request('?page=profile', ['csrf_token' => $profileToken, 'action' => 'credentials', 'email' => $emails[0], 'current_password' => 'wrong'], true)['status'] === 422, 'Profile sign-in changes require the current password.');
    check($a->request('?page=profile', ['csrf_token' => $profileToken, 'action' => 'credentials', 'email' => $emails[1], 'current_password' => $password], true)['status'] === 422, 'Profile cannot take another account email.');
    $categoryName = '[QA] ' . $suffix;
    $adminToken = $admin->token('?page=admin-categories');
    $createdCategory = $admin->request('?page=admin-categories', ['csrf_token' => $adminToken, 'name' => $categoryName, 'active' => '1'], true);
    $findCategory = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
    $findCategory->execute([$categoryName]);
    $categoryId = (int) $findCategory->fetchColumn();
    check($createdCategory['status'] === 303 && $categoryId > 0, 'Admin adds a category.');
    $title = '[QA] Buku ' . $suffix;
    $values = ['csrf_token' => $a->token('?page=create'), 'title' => $title, 'description' => 'Buku ujian untuk pembelajaran. <script>alert("xss")</script>', 'category_id' => $categoryId, 'item_condition' => 'good', 'listing_type' => 'donation', 'source_language' => 'ms', 'owner_id' => $bId];
    check($a->request('?page=create', array_replace($values, ['category_id' => 0]), true)['status'] === 422, 'Kategori tidak wujud ditolak.');
    check($a->request('?page=create', array_replace($values, ['item_condition' => 'invalid']), true)['status'] === 422, 'Keadaan barang tidak sah ditolak.');
    $categoryName .= ' edited';
    check($admin->request('?page=admin-categories', ['csrf_token' => $adminToken, 'id' => $categoryId, 'name' => $categoryName, 'active' => '0'], true)['status'] === 303, 'Admin renames and deactivates the category.');
    check($a->request('?page=create', $values, true)['status'] === 422, 'Kategori tidak aktif ditolak.');
    check($admin->request('?page=admin-categories', ['csrf_token' => $adminToken, 'id' => $categoryId, 'name' => $categoryName, 'active' => '1'], true)['status'] === 303, 'Admin reactivates the category.');
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
    foreach (['admin', 'admin-users', 'admin-categories', 'admin-reports', 'admin-export'] as $route) {
        check($b->request('?page=' . $route)['status'] === 403, 'Student is denied access to ' . $route . '.');
    }
    check($b->request('?page=admin-users', ['csrf_token' => $bToken, 'id' => $aId, 'active' => '0'], true)['status'] === 403, 'A student cannot deactivate another student.');
    $thread = '?page=chat&id=' . $id . '&buyer=' . $bId;
    check($a->request($thread)['status'] === 404, 'Owner cannot initiate a conversation on behalf of another student.');
    check($b->request($thread, ['csrf_token' => $bToken, 'message' => 'Can I collect the book tomorrow?'], true)['status'] === 303, 'Student sends a message about a listing.');
    check(str_contains($a->request('?page=inbox')['body'], 'Can I collect the book tomorrow?'), 'Owner sees the conversation in their inbox.');
    check($a->request($thread, ['csrf_token' => $a->token($thread), 'message' => 'Yes, after class.'], true)['status'] === 303, 'Owner replies to the same conversation.');
    check(str_contains($b->request($thread)['body'], 'Yes, after class.'), 'Student sees the reply.');
    check($b->request('?page=chat&id=' . $id . '&buyer=' . $aId)['status'] === 404, 'Forged buyer ID cannot read a thread.');
    check($guest->request($thread)['status'] === 303, 'Guest cannot access private messages.');
    check($admin->request($thread)['status'] === 403, 'Admin cannot read private student chat.');
    check($admin->request('?page=admin-users', ['csrf_token' => $adminToken, 'id' => $bId, 'active' => '0'], true)['status'] === 303 &&
        $b->request('?page=mine')['status'] === 303, 'Deactivating a student revokes their session.');
    check($admin->request('?page=admin-users', ['csrf_token' => $adminToken, 'id' => $bId, 'active' => '1'], true)['status'] === 303, 'Admin reactivates the student.');
    check($b->request('?page=login', ['csrf_token' => $b->token('?page=login'), 'email' => $emails[1], 'password' => $password], true)['status'] === 303, 'Reactivated student can sign in again.');
    $bToken = $b->token('?page=mine');
    $request = $pdo->prepare('INSERT INTO requests (requester_id, target_listing_id, status, created_at, completed_at) VALUES (?, ?, ?, ?, ?)');
    $request->execute([$bId, $id, 'completed', '2026-02-03 12:00:00', '2026-03-02 12:00:00']);
    $requestIds[] = (int) $pdo->lastInsertId();
    $request->execute([$bId, $id, 'pending', '2026-02-04 12:00:00', null]);
    $requestIds[] = (int) $pdo->lastInsertId();
    $report = '&from=2026-02-01&to=2026-03-31&category=' . $categoryId . '&type=donation';
    $reportPage = $admin->request('?page=admin-reports' . $report);
    check($reportPage['status'] === 200 && str_contains($reportPage['body'], '50.0%'), 'Report shows the completion rate for the February request cohort.');
    $csv = $admin->request('?page=admin-export' . $report);
    check($csv['status'] === 200 && str_contains($csv['headers']['content-type'], 'text/csv') &&
        str_contains($csv['body'], ',Donation,0,2,1,0,50.0%') && str_contains($csv['body'], ',Donation,0,0,0,1,N/A'), 'CSV separates request creation and completion months.');
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
    check($b->request($thread)['status'] === 200 && $b->request($thread, ['csrf_token' => $bToken, 'message' => 'Is it available?'], true)['status'] === 409, 'Archived listing preserves chat history and closes sending.');
    check($a->request('?page=edit&id=' . $id)['status'] === 409, 'Barang archived tidak boleh diedit.');
    $new = $a->request('?page=create', $values, true);
    parse_str(parse_url($new['headers']['location'] ?? '', PHP_URL_QUERY) ?? '', $newLocation);
    $deletedId = (int) ($newLocation['id'] ?? 0);
    check($new['status'] === 303 && $deletedId > 0, 'Create a separate listing to test deletion.');
    $delete = '?page=delete&id=' . $deletedId;
    $deletedThread = '?page=chat&id=' . $deletedId . '&buyer=' . $bId;
    check($b->request($deletedThread, ['csrf_token' => $bToken, 'message' => 'Message before deletion.'], true)['status'] === 303, 'Create a chat before deletion.');
    check($a->request($delete)['status'] === 200 && $a->request('?page=view&id=' . $deletedId)['status'] === 200, 'The delete confirmation GET leaves the listing intact.');
    check($b->request($delete, ['csrf_token' => $bToken], true)['status'] === 403 && $a->request($delete, [], true)['status'] === 403, 'Delete requires owner permission and a valid CSRF token.');
    check($a->request($delete, ['csrf_token' => $a->token($delete)], true)['status'] === 303, 'Owner can delete the listing.');
    check($a->request('?page=view&id=' . $deletedId)['status'] === 404 &&
        !str_contains($a->request('?page=mine')['body'], 'page=view&amp;id=' . $deletedId), 'Deleted listing disappears from detail and My listings.');
    check(str_contains($b->request($deletedThread)['body'], 'Message before deletion.') &&
        $b->request($deletedThread, ['csrf_token' => $bToken, 'message' => 'Message after deletion.'], true)['status'] === 409, 'Deleted listing keeps its chat history and closes sending.');
    $pdo->prepare('UPDATE user_sessions SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR), expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE user_id = ?')->execute([$bId]);
    check($b->request('?page=mine')['status'] === 303, 'Session tamat tidak boleh membuka halaman dilindungi.');
    check($a->request('?page=logout', ['csrf_token' => $a->token('?page=mine')], true)['status'] === 303, 'Log keluar berjaya.');
    $sessionQuery->execute([hash('sha256', $session), $aId]);
    check((int) $sessionQuery->fetchColumn() === 0 && $a->session() === '', 'Log keluar memadam session database dan cookie.');
    $replay = new TestClient(); $replay->replay($session);
    check($replay->request('?page=mine')['status'] === 303, 'Token lama tidak boleh digunakan semula selepas logout.');
    check($a->request('?page=logout')['status'] === 405, 'GET logout ditolak untuk elak perubahan melalui link.');
    $admin->request('?page=logout', ['csrf_token' => $adminToken], true);
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
} finally {
    if ($adminId > 0) {
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$adminId]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$adminId]);
    }
    foreach ($requestIds as $requestId) {
        $pdo->prepare('DELETE FROM requests WHERE request_id = ?')->execute([$requestId]);
    }
    $owned = $pdo->prepare('SELECT user_id FROM users WHERE email IN (?, ?)'); $owned->execute($emails); $ids = $owned->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $userId) {
        $pdo->prepare('DELETE m FROM listing_messages m JOIN listings l ON l.listing_id = m.listing_id WHERE l.owner_id = ? OR m.buyer_id = ?')->execute([$userId, $userId]);
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
