<div class="form-page">
    <div class="page-heading"><div><p class="section-label">Account settings</p><h1>Profile</h1></div></div>
    <section class="form-panel">
        <header class="profile-heading">
            <?php avatar((int) $account['user_id'], $account['full_name'], $hasAvatar); ?>
            <div><h2><?= e($account['full_name']) ?></h2><p class="muted"><?= $account['role'] === 'admin' ? 'Administrator' : 'Student' ?></p></div>
        </header>
        <h3>Personal details</h3>
        <?php if ($detailsErrors !== []): ?><p class="error-summary" role="alert">Check the highlighted profile fields.</p><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('profile')) ?>" class="stack-form" data-confirm="Save changes to your profile?" data-confirm-action="Save profile">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="details">
            <div><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" required minlength="2" maxlength="120" value="<?= e($name) ?>"<?= fieldState('full_name', $detailsErrors) ?>><?php fieldError('full_name', $detailsErrors); ?></div>
            <div>
                <label for="avatar">Profile photo</label>
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                    <?= isset($detailsErrors['avatar']) ? 'aria-invalid="true" aria-describedby="avatar-help error-avatar"' : 'aria-describedby="avatar-help"' ?>>
                <?php fieldError('avatar', $detailsErrors); ?>
                <p id="avatar-help" class="field-help">JPEG, PNG or WebP. Up to 1 MB and 1,600 pixels per side. Your photo appears beside your name in conversations.</p>
                <?php if ($hasAvatar): ?><label class="check-option"><input name="remove_avatar" type="checkbox" value="1"> Remove profile photo</label><?php endif; ?>
            </div>
            <div class="form-actions"><button class="button-primary" type="submit">Save profile</button></div>
        </form>
    </section>
    <section class="form-panel profile-security">
        <h2>Email and password</h2>
        <p class="muted">Use your current EcoTrade password to save these changes. Leave the new password fields empty to keep your password.</p>
        <?php if ($credentialsErrors !== []): ?><p class="error-summary" role="alert">Check the highlighted sign-in fields.</p><?php endif; ?>
        <form method="post" action="<?= e(url('profile')) ?>" class="stack-form" data-confirm="Save your sign-in details?" data-confirm-action="Save sign-in details">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="credentials">
            <div><label for="email">Email</label><input type="email" id="email" name="email" autocomplete="email" required maxlength="254" pattern="[^@\s]+@[^@\s.]+(\.[^@\s.]+)+" title="Use a full email address such as name@gmail.com" value="<?= e($email) ?>"<?= fieldState('email', $credentialsErrors) ?>><?php fieldError('email', $credentialsErrors); ?></div>
            <?php passwordField('current_password', 'Current password', 'current-password', $credentialsErrors); ?>
            <?php passwordField('new_password', 'New password', 'new-password', $credentialsErrors, false, true); ?>
            <?php passwordField('password_confirmation', 'Confirm new password', 'new-password', $credentialsErrors, false, true); ?>
            <div class="form-actions"><button class="button-primary" type="submit">Save sign-in details</button></div>
        </form>
    </section>
</div>
