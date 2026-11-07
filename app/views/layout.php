<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> | EcoTrade</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/assets/ecotrade-favicon.png">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<header class="site-header">
    <div class="header-inner">
        <a class="wordmark" href="<?= e(url()) ?>">
            <img src="/assets/ecotrade-wordmark.png" width="432" height="86" alt="EcoTrade">
            <span>Study materials for the next student.</span>
        </a>
        <nav aria-label="Main navigation">
            <a href="<?= e(url()) ?>" <?= (input($_GET, 'page') ?: 'browse') === 'browse' ? 'aria-current="page"' : '' ?>>Browse listings</a>
            <?php if ($user !== null): ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin')) ?>">Admin dashboard</a>
                <?php else: ?>
                    <a href="<?= e(url('inbox')) ?>">Messages</a>
                <?php endif; ?>
                <a href="<?= e(url('mine')) ?>" <?= input($_GET, 'page') === 'mine' ? 'aria-current="page"' : '' ?>>My listings</a>
                <a class="button button-primary" href="<?= e(url('create')) ?>">Add listing</a>
                <a href="<?= e(url('profile')) ?>" <?= input($_GET, 'page') === 'profile' ? 'aria-current="page"' : '' ?>>Profile</a>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button class="button-text" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login')) ?>">Sign in</a>
                <a class="button button-primary" href="<?= e(url('register')) ?>">Create account</a>
            <?php endif; ?>
            <button class="theme-toggle" type="button" id="theme-toggle" hidden aria-pressed="false">Dark mode</button>
        </nav>
    </div>
</header>
<main id="content" class="container" tabindex="-1">
    <?php
    $notices = [
        'registered' => 'Account created. Sign in with your email and password.',
        'logged_in' => 'You are signed in.',
        'logged_out' => 'You are signed out.',
        'login_required' => 'Sign in to continue.',
        'created' => 'Listing added.',
        'updated' => 'Listing updated.',
        'archived' => 'Listing archived. You can still find it in My listings.',
        'deleted' => 'Listing deleted.',
        'admin_saved' => 'Changes saved.',
        'google_connected' => 'Google account connected.',
        'profile_saved' => 'Profile updated.',
    ];
    $notice = $notices[input($_GET, 'notice')] ?? null;
    ?>
    <?php if ($notice !== null): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/' . $template . '.php'; ?>
</main>
<footer class="site-footer container">
    <p>EcoTrade <span>A student marketplace for study materials.</span></p>
    <?php if ($user !== null): ?><p>Signed in as <?= e($user['full_name']) ?></p><?php endif; ?>
</footer>
<dialog class="confirm-dialog" id="confirm-action" aria-labelledby="confirm-title" aria-describedby="confirm-description">
    <h2 id="confirm-title">Confirm changes</h2>
    <p id="confirm-description" hidden></p>
    <form method="dialog" class="form-actions">
        <button type="submit" value="cancel" autofocus>Cancel</button>
        <button type="submit" class="button-primary" value="confirm">Confirm</button>
    </form>
</dialog>
<p class="loading-message" id="loading-message" role="status" hidden>Loading…</p>
</body>
</html>
