<?php $isOwner = $user !== null && (int) $user['user_id'] === (int) $listing['owner_id']; ?>
<div class="detail-page">
    <a class="back-link" href="<?= e(url($isOwner ? 'mine' : 'browse')) ?>">Kembali ke <?= $isOwner ? 'barang saya' : 'senarai barang' ?></a>
    <div class="detail-layout">
        <article class="detail-main">
            <p class="section-label"><?= e($listing['category_name']) ?></p>
            <h1><?= $page === 'archive' ? 'Arkibkan barang ini?' : e($listing['title']) ?></h1>
            <?php if ($page === 'archive'): ?><h2><?= e($listing['title']) ?></h2><p>Barang ini akan dikeluarkan daripada carian umum. Rekodnya kekal dalam Barang saya dan tidak dipadam.</p><?php endif; ?>
            <div class="detail-description"><?= nl2br(e($listing['description'])) ?></div>
        </article>
        <aside class="detail-aside" aria-label="Maklumat barang">
            <span class="type-label"><?= e(LISTING_TYPES[$listing['listing_type']]) ?></span>
            <dl><div><dt>Status</dt><dd><?= e(LISTING_STATUSES[$listing['status']]) ?></dd></div><div><dt>Keadaan</dt><dd><?= e(CONDITIONS[$listing['item_condition']]) ?></dd></div><div><dt>Pemilik</dt><dd><?= e($listing['owner_name']) ?></dd></div><div><dt>Bahasa penerangan</dt><dd><?= $listing['source_language'] === 'ms' ? 'Bahasa Melayu' : 'English' ?></dd></div></dl>
            <?php if ($page === 'archive'): ?>
                <form method="post" action="<?= e(url('archive', ['id' => $listing['listing_id']])) ?>" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="button button-danger" type="submit">Ya, arkibkan barang</button><a class="button" href="<?= e(url('view', ['id' => $listing['listing_id']])) ?>">Batal</a></form>
            <?php elseif ($isOwner && $listing['status'] === 'available'): ?>
                <div class="stack-form"><a class="button button-primary" href="<?= e(url('edit', ['id' => $listing['listing_id']])) ?>">Edit barang</a><a class="button" href="<?= e(url('archive', ['id' => $listing['listing_id']])) ?>">Arkibkan barang</a></div>
            <?php elseif ($isOwner): ?><p class="field-help">Barang dengan status ini disimpan sebagai rekod dan tidak boleh diedit.</p>
            <?php else: ?><p class="field-help">Permintaan sumbangan dan pertukaran akan disambungkan dalam fasa seterusnya.</p><?php endif; ?>
        </aside>
    </div>
</div>
