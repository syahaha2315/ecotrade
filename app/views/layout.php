<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> | EcoTrade</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#content">Langkau ke kandungan</a>
<header class="site-header">
    <div class="header-inner">
        <a class="wordmark" href="<?= e(url()) ?>">EcoTrade<span>Bahan belajar, sambung guna.</span></a>
        <nav aria-label="Navigasi utama">
            <a href="<?= e(url()) ?>" <?= (input($_GET, 'page') ?: 'browse') === 'browse' ? 'aria-current="page"' : '' ?>>Cari barang</a>
            <?php if ($user !== null): ?>
                <a href="<?= e(url('mine')) ?>" <?= input($_GET, 'page') === 'mine' ? 'aria-current="page"' : '' ?>>Barang saya</a>
                <a class="button button-primary" href="<?= e(url('create')) ?>">Tambah barang</a>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button class="button-text" type="submit">Log keluar</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('login')) ?>">Log masuk</a>
                <a class="button button-primary" href="<?= e(url('register')) ?>">Daftar akaun</a>
            <?php endif; ?>
            <button class="theme-toggle" type="button" id="theme-toggle" hidden aria-pressed="false">Mod gelap</button>
        </nav>
    </div>
</header>
<main id="content" class="container" tabindex="-1">
    <?php
    $notices = [
        'registered' => 'Akaun berjaya didaftarkan. Log masuk menggunakan email dan password kau.',
        'logged_in' => 'Log masuk berjaya.',
        'logged_out' => 'Kau sudah log keluar.',
        'login_required' => 'Log masuk dahulu untuk mengurus barang.',
        'created' => 'Barang berjaya ditambah dan disimpan dalam database.',
        'updated' => 'Maklumat barang berjaya dikemas kini.',
        'archived' => 'Barang sudah diarkibkan. Rekodnya masih ada dalam Barang saya.',
    ];
    $notice = $notices[input($_GET, 'notice')] ?? null;
    ?>
    <?php if ($notice !== null): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php require __DIR__ . '/' . $template . '.php'; ?>
</main>
<footer class="site-footer container">
    <p>EcoTrade <span>Perkongsian bahan belajar antara pelajar.</span></p>
    <?php if ($user !== null): ?><p>Log masuk sebagai <?= e($user['full_name']) ?></p><?php endif; ?>
</footer>
<p class="loading-message" id="loading-message" role="status" hidden>Memuatkan halaman...</p>
</body>
</html>
