<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(array $source, string $name): string
{
    return isset($source[$name]) && is_string($source[$name]) ? trim($source[$name]) : '';
}

function url(string $page = 'browse', array $parameters = []): string
{
    return '/?' . http_build_query(['page' => $page] + $parameters);
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function positiveId(array $source, string $key = 'id'): int
{
    $value = filter_var(input($source, $key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) {
        throw new HttpError(400, 'Invalid record ID.');
    }
    return $value;
}

function renderPage(string $title, string $template, array $data = []): void
{
    global $user, $csrfToken;
    extract($data, EXTR_SKIP);
    require __DIR__ . '/views/layout.php';
}

function fieldError(string $name, array $errors): void
{
    if (isset($errors[$name])) {
        echo '<p class="field-error" id="error-' . e($name) . '">' . e($errors[$name]) . '</p>';
    }
}

function fieldState(string $name, array $errors): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . e($name) . '"' : '';
}

function passwordField(string $field, string $label, string $autocomplete, array $errors, bool $required = true, bool $new = false): void
{
    require __DIR__ . '/views/password-field.php';
}

function avatar(int $id, string $name, bool $hasPhoto): void
{
    if ($hasPhoto): ?>
        <img class="avatar" src="<?= e(url('avatar', ['id' => $id])) ?>" width="48" height="48" alt="">
    <?php else: ?>
        <span class="avatar avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
    <?php endif;
}
