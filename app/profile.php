<?php
declare(strict_types=1);

function serveAvatar(PDO $pdo, int $id, ?array $viewer): void
{
    requireUser($viewer);
    $query = $pdo->prepare('SELECT a.mime_type, a.image_data FROM user_avatars a JOIN users u ON u.user_id = a.user_id WHERE a.user_id = ? AND u.is_active = 1');
    $query->execute([$id]);
    $image = $query->fetch();
    if (!$image) {
        throw new HttpError(404, 'Profile photo not found.');
    }
    header('Content-Type: ' . $image['mime_type']);
    header('Content-Length: ' . strlen($image['image_data']));
    echo $image['image_data'];
}

function saveProfileDetails(PDO $pdo, int $id, string $name): array
{
    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['full_name'] = 'Enter a name between 2 and 120 characters.';
    }
    $photo = null;
    $file = $_FILES['avatar'] ?? null;
    if ($file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $photo = readUploadedImage($file['error'], $file['tmp_name'] ?? '', 1600);
        } catch (HttpError $error) {
            $errors['avatar'] = $error->getMessage();
        }
    }
    if ($errors !== []) {
        return $errors;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET full_name = ? WHERE user_id = ?')->execute([$name, $id]);
        if ($photo !== null) {
            $pdo->prepare('INSERT INTO user_avatars (user_id, mime_type, image_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), image_data = VALUES(image_data)')
                ->execute([$id, $photo[0], $photo[3]]);
        } elseif (input($_POST, 'remove_avatar') === '1') {
            $pdo->prepare('DELETE FROM user_avatars WHERE user_id = ?')->execute([$id]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    return [];
}

function saveProfileCredentials(PDO $pdo, array $viewer, string $email): array
{
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $errors = [];
    if (!validAccountEmail($email) || ($viewer['role'] === 'admin' && $email !== $viewer['email'] && !validAdminEmail($email))) {
        $errors['email'] = 'Enter a valid email address you can use to sign in.';
    }
    if ($password !== '' && !validNewPassword($password)) {
        $errors['new_password'] = 'Use at least 10 characters. Try a shorter password if it is too long.';
    }
    if ($password !== $confirmation) {
        $errors['password_confirmation'] = 'The passwords must match.';
    }
    if ($errors !== []) {
        return $errors;
    }
    $id = (int) $viewer['user_id'];
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT email, password_hash FROM users WHERE user_id = ? AND is_active = 1 FOR UPDATE');
        $query->execute([$id]);
        $account = $query->fetch();
        if (!$account || strlen($current) > 72 || str_contains($current, "\0") || !password_verify($current, $account['password_hash'])) {
            $pdo->rollBack();
            return ['current_password' => 'Enter your current EcoTrade password.'];
        }
        if ($account['email'] !== $email || $password !== '') {
            $hash = $password === '' ? $account['password_hash'] : password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET email = ?, password_hash = ? WHERE user_id = ?')->execute([$email, $hash, $id]);
            $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ? AND session_hash <> ?')
                ->execute([$id, hash('sha256', tokenCookie(SESSION_COOKIE))]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
            return ['email' => 'This email is already in use.'];
        }
        throw $error;
    }
    return [];
}

function runProfile(PDO $pdo, string $method, ?array $viewer): void
{
    $account = requireUser($viewer);
    $id = (int) $account['user_id'];
    $name = $account['full_name'];
    $email = $account['email'];
    $detailsErrors = [];
    $credentialsErrors = [];
    if ($method === 'POST') {
        $action = input($_POST, 'action');
        if ($action === 'details') {
            $name = input($_POST, 'full_name');
            $detailsErrors = saveProfileDetails($pdo, $id, $name);
        } elseif ($action === 'credentials') {
            $email = strtolower(input($_POST, 'email'));
            $credentialsErrors = saveProfileCredentials($pdo, $account, $email);
        } else {
            throw new HttpError(400, 'Choose a profile action.');
        }
        if ($detailsErrors === [] && $credentialsErrors === []) {
            redirect(url('profile', ['notice' => 'profile_saved']));
        }
        http_response_code(422);
    }
    $query = $pdo->prepare('SELECT 1 FROM user_avatars WHERE user_id = ?');
    $query->execute([$id]);
    $hasAvatar = (bool) $query->fetchColumn();
    renderPage('Profile', 'profile', compact('account', 'name', 'email', 'detailsErrors', 'credentialsErrors', 'hasAvatar'));
}
