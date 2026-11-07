<?php
declare(strict_types=1);

const GOOGLE_FLOW_COOKIE = 'ecotrade_google_flow';

function googleSettings(): ?array
{
    $path = dirname(__DIR__) . '/config/google.local.php';
    $local = is_file($path) ? require $path : [];
    $settings = [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: ($local['client_id'] ?? ''),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: ($local['client_secret'] ?? ''),
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: ($local['redirect_uri'] ?? ''),
    ];
    return in_array('', $settings, true) ? null : $settings;
}

function googleState(string $verifier): string
{
    return hash_hmac('sha256', 'ecotrade-google:' . tokenCookie(SESSION_COOKIE), $verifier);
}

function googleResponse(string $endpoint, array $options): array
{
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $response = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($response)) {
        throw new HttpError(502, 'Google sign-in could not be verified. Try again or sign in with email.');
    }
    return $response;
}

function googleUser(PDO $pdo, array $profile, ?array $viewer): int
{
    $subject = input($profile, 'sub');
    $email = strtolower(input($profile, 'email'));
    if (!preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $subject) || !validAccountEmail($email) ||
        ($profile['email_verified'] ?? false) !== true) {
        throw new HttpError(403, 'Use a Google account with a verified email address.');
    }
    $pdo->beginTransaction();
    try {
        $linked = $pdo->prepare('SELECT u.user_id, u.role, u.is_active FROM google_accounts g JOIN users u ON u.user_id = g.user_id WHERE g.google_subject = ? FOR UPDATE');
        $linked->execute([$subject]);
        $account = $linked->fetch();
        if ($account) {
            if ($account['role'] !== 'student' || (int) $account['is_active'] !== 1 ||
                ($viewer && (int) $viewer['user_id'] !== (int) $account['user_id'])) {
                throw new HttpError(403, 'This Google account cannot sign in here.');
            }
            $id = (int) $account['user_id'];
        } else {
            $find = $pdo->prepare('SELECT user_id, role, is_active FROM users WHERE email = ? FOR UPDATE');
            $find->execute([$email]);
            $existing = $find->fetch();
            if ($existing) {
                if (!$viewer || (int) $viewer['user_id'] !== (int) $existing['user_id'] ||
                    $existing['role'] !== 'student' || (int) $existing['is_active'] !== 1) {
                    throw new HttpError(409, 'Sign in with your EcoTrade password first, then connect Google from My listings.');
                }
                $id = (int) $existing['user_id'];
            } else {
                if ($viewer) {
                    throw new HttpError(409, 'Choose the Google account with the same email as your EcoTrade account.');
                }
                $name = mb_substr(input($profile, 'name') ?: 'EcoTrade student', 0, 120);
                $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, preferred_language) VALUES (?, ?, ?, 'student', 'en')")
                    ->execute([$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('INSERT INTO google_accounts (google_subject, user_id) VALUES (?, ?)')->execute([$subject, $id]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        $pdo->rollBack();
        if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
            throw new HttpError(409, 'This account already has a Google connection. Sign in with the connected account.');
        }
        throw $error;
    }
}

function runGoogle(PDO $pdo, string $page, ?array $viewer): void
{
    $settings = googleSettings();
    if ($settings === null) {
        throw new HttpError(503, 'Google sign-in is not configured.');
    }
    if ($viewer && $viewer['role'] !== 'student') {
        throw new HttpError(403, 'Use your administrator email and password to sign in.');
    }
    if ($page === 'google') {
        $verifier = bin2hex(random_bytes(32));
        setcookie(GOOGLE_FLOW_COOKIE, $verifier, cookieOptions(time() + 600));
        redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $settings['client_id'],
            'redirect_uri' => $settings['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => googleState($verifier),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986));
    }
    $verifier = tokenCookie(GOOGLE_FLOW_COOKIE);
    setcookie(GOOGLE_FLOW_COOKIE, '', cookieOptions(time() - 3600));
    if ($verifier === '' || !hash_equals(googleState($verifier), input($_GET, 'state'))) {
        throw new HttpError(403, 'This Google sign-in attempt expired. Start again from Sign in.');
    }
    $code = input($_GET, 'code');
    if ($code === '' || strlen($code) > 2048 || input($_GET, 'error') !== '') {
        throw new HttpError(400, 'Google sign-in was cancelled. Return to Sign in to try again.');
    }
    $token = googleResponse('https://oauth2.googleapis.com/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($settings + [
            'code' => $code, 'grant_type' => 'authorization_code', 'code_verifier' => $verifier,
        ]),
    ]);
    $access = input($token, 'access_token');
    if ($access === '' || preg_match('/[\r\n]/', $access)) {
        throw new HttpError(502, 'Google did not return a usable sign-in token.');
    }
    $profile = googleResponse('https://openidconnect.googleapis.com/v1/userinfo', [
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $access],
    ]);
    startLogin($pdo, googleUser($pdo, $profile, $viewer));
    redirect(url('mine', ['notice' => $viewer ? 'google_connected' : 'logged_in']));
}
