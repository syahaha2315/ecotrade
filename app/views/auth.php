<div class="auth-layout">
    <aside class="auth-aside">
        <p class="section-label">Student marketplace</p>
        <p class="auth-copy">Pass on the books<br>you no longer need.</p>
        <p>Share books, calculators and learning tools with other students.</p>
        <p class="aside-note">List items you can pass on, or find materials for your next class.</p>
    </aside>
    <section class="form-panel" aria-labelledby="auth-heading">
        <h1 id="auth-heading"><?= $page === 'register' ? 'Create account' : 'Sign in' ?></h1>
        <p class="muted"><?= $page === 'register' ? 'Create a student account to post listings and message other students.' : 'Manage your listings and conversations.' ?></p>
        <?php if (googleSettings() !== null): ?>
            <a class="button google-button" href="<?= e(url('google')) ?>">Continue with Google</a>
            <p class="auth-divider muted">or use your email</p>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="error-summary" role="alert"><p><?= e($errors['login'] ?? 'Check the fields below.') ?></p></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url($page)) ?>" class="stack-form" <?= $page === 'register' ? 'data-confirm="Create this account?" data-confirm-action="Create account"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <?php if ($page === 'register'): ?>
                <div><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" required minlength="2" maxlength="120" value="<?= e($values['full_name']) ?>"<?= fieldState('full_name', $errors) ?>><?php fieldError('full_name', $errors); ?></div>
            <?php endif; ?>
            <div><label for="email">Email</label><input type="email" id="email" name="email" autocomplete="username" required maxlength="254" pattern="[^@\s]+@[^@\s.]+(\.[^@\s.]+)+" title="Use a full email address such as name@gmail.com" value="<?= e($values['email']) ?>"<?= fieldState('email', $errors) ?>
                <?php if ($page === 'register'): ?> data-email-domains="<?= e(implode(' ', SIGNUP_EMAIL_DOMAINS)) ?>" <?= isset($errors['email']) ? '' : 'aria-describedby="email-help"' ?><?php endif; ?>>
                <?php fieldError('email', $errors); ?>
                <?php if ($page === 'register'): ?><p class="field-help" id="email-help">Use a supported provider such as Gmail, Outlook, Yahoo, iCloud or Proton Mail.</p><?php endif; ?>
            </div>
            <?php passwordField('password', 'Password', $page === 'register' ? 'new-password' : 'current-password', $errors, true, $page === 'register'); ?>
            <?php if ($page === 'register'): ?>
                <p id="signup-password-help" class="field-help"><?= e(SIGNUP_PASSWORD_HELP) ?></p>
                <?php passwordField('password_confirmation', 'Confirm password', 'new-password', $errors, true, true); ?>
            <?php endif; ?>
            <button class="button button-primary" type="submit"><?= $page === 'register' ? 'Create account' : 'Sign in' ?></button>
        </form>
        <p class="form-switch"><?= $page === 'register' ? 'Already have an account?' : 'New to EcoTrade?' ?> <a href="<?= e(url($page === 'register' ? 'login' : 'register')) ?>"><?= $page === 'register' ? 'Sign in' : 'Create account' ?></a></p>
    </section>
</div>
