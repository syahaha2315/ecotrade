<section class="message-page">
    <p class="section-label">Permintaan <?= e($status) ?></p>
    <h1><?= $status === 503 ? 'EcoTrade belum dapat dicapai.' : 'Permintaan ini tidak dapat diteruskan.' ?></h1>
    <p><?= e($message) ?></p>
    <a class="button button-primary" href="<?= e(url()) ?>">Kembali ke senarai barang</a>
</section>
