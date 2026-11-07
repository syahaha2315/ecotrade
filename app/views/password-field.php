<div>
    <label for="<?= e($field) ?>"><?= e($label) ?></label>
    <div class="password-input">
        <input type="password" id="<?= e($field) ?>" name="<?= e($field) ?>" autocomplete="<?= e($autocomplete) ?>" maxlength="72"
            <?= $required ? 'required' : '' ?> <?= $new ? 'minlength="10"' : '' ?><?= fieldState($field, $errors) ?>
            <?php if ($field === 'password' && $new): ?> pattern="<?= e(SIGNUP_PASSWORD_PATTERN) ?>" title="<?= e(SIGNUP_PASSWORD_HELP) ?>" <?= isset($errors[$field]) ? '' : 'aria-describedby="signup-password-help"' ?><?php endif; ?>>
        <button class="password-toggle" type="button" hidden data-password-toggle aria-controls="<?= e($field) ?>"
                aria-label="Show <?= e(strtolower($label)) ?>" data-field-label="<?= e(strtolower($label)) ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
                <circle cx="12" cy="12" r="2.6"/>
                <path class="eye-slash" d="M4 4l16 16"/>
            </svg>
        </button>
    </div>
    <?php fieldError($field, $errors); ?>
</div>
