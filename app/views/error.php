<section class="message-page">
    <p class="section-label">Request <?= e($status) ?></p>
    <h1><?= $status === 503 ? 'EcoTrade is unavailable.' : 'We could not complete your request.' ?></h1>
    <p><?= e($message) ?></p>
    <a class="button button-primary" href="<?= e(url()) ?>">Back to listings</a>
</section>
