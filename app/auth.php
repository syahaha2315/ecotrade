<?php
declare(strict_types=1);

const SESSION_COOKIE = 'ecotrade_session';
const CSRF_COOKIE = 'ecotrade_csrf';
const SIGNUP_EMAIL_DOMAINS = [
    'gmail.com', 'googlemail.com', 'outlook.com', 'outlook.my', 'hotmail.com', 'live.com', 'msn.com',
    'yahoo.com', 'yahoo.com.my', 'ymail.com', 'icloud.com', 'me.com', 'mac.com',
    'proton.me', 'protonmail.com', 'pm.me', 'aol.com', 'fastmail.com', 'zoho.com', 'gmx.com', 'mail.com',
];
const SIGNUP_PASSWORD_PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\p{P}\p{S}]).{10,72}';
const SIGNUP_PASSWORD_HELP = 'Use 10 or more characters with an uppercase letter, a lowercase letter, a number and a symbol. The maximum is 72 bytes.';

function cookieOptions(int $expires): array
{
    return [
        'expires' => $expires, 'path' => '/',
        'secure' => getenv('COOKIE_SECURE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true, 'samesite' => 'Lax',
    ];
}

function tokenCookie(string $name): string
{
    $value = $_COOKIE[$name] ?? '';
    return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) ? $value : '';
}

function currentUser(PDO $pdo): ?array
{
    $token = tokenCookie(SESSION_COOKIE);
    if ($token === '') {
        return null;
    }
    $query = $pdo->prepare('SELECT u.user_id, u.full_name, u.email, u.role FROM user_sessions s JOIN users u ON u.user_id = s.user_id WHERE s.session_hash = ? AND s.expires_at > UTC_TIMESTAMP() AND u.is_active = 1');
    $query->execute([hash('sha256', $token)]);
    $user = $query->fetch();
    if (!$user) {
        setcookie(SESSION_COOKIE, '', cookieOptions(time() - 3600));
        unset($_COOKIE[SESSION_COOKIE]);
        return null;
    }
    return $user;
}

function csrfToken(): string
{
    $nonce = tokenCookie(CSRF_COOKIE);
    if ($nonce === '') {
        $nonce = bin2hex(random_bytes(32));
        setcookie(CSRF_COOKIE, $nonce, cookieOptions(0));
        $_COOKIE[CSRF_COOKIE] = $nonce;
    }
    // Bind the form token to the login token; the database password signs anonymous forms too.
    return hash_hmac('sha256', $nonce . ':' . tokenCookie(SESSION_COOKIE), databaseConfig()['password']);
}

function requireCsrf(string $expected): void
{
    if (!hash_equals($expected, input($_POST, 'csrf_token'))) {
        throw new HttpError(403, 'This form has expired or is invalid. Reload the page and try again.');
    }
}

function requireUser(?array $user): array
{
    if ($user === null) {
        redirect(url('login', ['notice' => 'login_required']));
    }
    return $user;
}

function validAccountEmail(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
        preg_match('/@[a-z0-9-]+(?:\.[a-z0-9-]+)+$/Di', $email) === 1;
}

function validAdminEmail(string $email): bool
{
    if (!validAccountEmail($email)) {
        return false;
    }
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    return !preg_match('/(?:^|\.)(test|example|invalid|localhost|local)$/D', $domain) &&
        !in_array($domain, ['example.com', 'example.net', 'example.org'], true) &&
        !str_starts_with($domain, '[');
}

function validNewPassword(string $password): bool
{
    return mb_strlen($password) >= 10 && strlen($password) <= 72 && !str_contains($password, "\0");
}

function startLogin(PDO $pdo, int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare('SELECT is_active FROM users WHERE user_id = ? FOR UPDATE');
        $account->execute([$userId]);
        if ((int) $account->fetchColumn() !== 1) {
            throw new HttpError(403, 'This account is inactive.');
        }
        $old = tokenCookie(SESSION_COOKIE);
        if ($old !== '') {
            $pdo->prepare('DELETE FROM user_sessions WHERE session_hash = ?')->execute([hash('sha256', $old)]);
        }
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ? AND expires_at <= UTC_TIMESTAMP()')->execute([$userId]);
        $pdo->prepare('INSERT INTO user_sessions (session_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 HOUR))')->execute([hash('sha256', $token), $userId]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    setcookie(SESSION_COOKIE, $token, cookieOptions(time() + 8 * 3600));
}

function endLogin(PDO $pdo): void
{
    $pdo->prepare('DELETE FROM user_sessions WHERE session_hash = ?')->execute([hash('sha256', tokenCookie(SESSION_COOKIE))]);
    setcookie(SESSION_COOKIE, '', cookieOptions(time() - 3600));
    setcookie(CSRF_COOKIE, '', cookieOptions(time() - 3600));
}

function registerUser(PDO $pdo, array $values): array
{
    $errors = [];
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 120) {
        $errors['full_name'] = 'Enter a name between 2 and 120 characters.';
    }
    if (!validAccountEmail($values['email']) ||
        !in_array(substr(strrchr($values['email'], '@'), 1), SIGNUP_EMAIL_DOMAINS, true)) {
        $errors['email'] = 'Use an address from a supported email provider, such as Gmail, Outlook, Yahoo, iCloud or Proton Mail.';
    }
    if (!validNewPassword($values['password']) || preg_match('/\A' . SIGNUP_PASSWORD_PATTERN . '\z/u', $values['password']) !== 1) {
        $errors['password'] = SIGNUP_PASSWORD_HELP;
    }
    if ($values['password'] !== $values['password_confirmation']) {
        $errors['password_confirmation'] = 'The passwords must match.';
    }
    if ($errors !== []) {
        return $errors;
    }
    try {
        // The client never chooses its account role.
        $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, preferred_language) VALUES (?, ?, ?, 'student', 'en')")->execute([
            $values['full_name'], $values['email'], password_hash($values['password'], PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) !== 1062) {
            throw $error;
        }
        $errors['email'] = 'This email is already registered. Sign in to continue.';
    }
    return $errors;
}

function authenticate(PDO $pdo, string $email, string $password): ?array
{
    if (strlen($password) > 72 || str_contains($password, "\0")) {
        return null;
    }
    $query = $pdo->prepare('SELECT user_id, password_hash, is_active, role FROM users WHERE email = ?');
    $query->execute([$email]);
    $account = $query->fetch();
    $hash = $account['password_hash'] ?? '$2y$10$XiBivttdkhTFSPe86AoaB.yjVeF15ISVbz01XM3rtMf5Wv9lI4dFK';
    $verified = password_verify($password, $hash);
    return $verified && $account && (int) $account['is_active'] === 1 ? $account : null;
}
