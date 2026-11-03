<div class="auth-layout">
    <aside class="auth-aside">
        <p class="section-label">Ruang pelajar</p>
        <p class="auth-copy">Buku yang dah habis dibaca,<br>masih boleh membantu.</p>
        <p>Kongsi buku, kalkulator dan alat pembelajaran yang masih elok dengan pelajar lain.</p>
        <p class="aside-note">Mulakan dengan akaun sendiri. Setiap barang kekal di bawah nama pemiliknya.</p>
    </aside>
    <section class="form-panel" aria-labelledby="auth-heading">
        <h1 id="auth-heading"><?= $page === 'register' ? 'Daftar akaun' : 'Log masuk' ?></h1>
        <p class="muted"><?= $page === 'register' ? 'Satu akaun untuk kongsi dan urus barang kau.' : 'Sambung mengurus barang yang kau kongsi.' ?></p>
        <?php if ($errors !== []): ?>
            <div class="error-summary" role="alert"><p><?= e($errors['login'] ?? 'Semak maklumat dalam borang di bawah.') ?></p></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url($page)) ?>" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <?php if ($page === 'register'): ?>
                <div><label for="full_name">Nama penuh</label><input id="full_name" name="full_name" autocomplete="name" required minlength="2" maxlength="120" value="<?= e($values['full_name']) ?>"<?= fieldState('full_name', $errors) ?>><?php fieldError('full_name', $errors); ?></div>
            <?php endif; ?>
            <div><label for="email">Email</label><input type="email" id="email" name="email" autocomplete="username" required maxlength="254" value="<?= e($values['email']) ?>"<?= fieldState('email', $errors) ?>><?php fieldError('email', $errors); ?></div>
            <div><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="<?= $page === 'register' ? 'new-password' : 'current-password' ?>" required <?= $page === 'register' ? 'minlength="10" maxlength="72"' : 'maxlength="72"' ?><?= fieldState('password', $errors) ?>><?php fieldError('password', $errors); ?><?php if ($page === 'register'): ?><p class="field-help">Sekurang-kurangnya 10 aksara.</p><?php endif; ?></div>
            <?php if ($page === 'register'): ?>
                <div><label for="password_confirmation">Ulang password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required maxlength="72"<?= fieldState('password_confirmation', $errors) ?>><?php fieldError('password_confirmation', $errors); ?></div>
            <?php endif; ?>
            <button class="button button-primary" type="submit"><?= $page === 'register' ? 'Daftar akaun' : 'Log masuk' ?></button>
        </form>
        <p class="form-switch"><?= $page === 'register' ? 'Dah ada akaun?' : 'Belum ada akaun?' ?> <a href="<?= e(url($page === 'register' ? 'login' : 'register')) ?>"><?= $page === 'register' ? 'Log masuk' : 'Daftar akaun' ?></a></p>
    </section>
</div>
