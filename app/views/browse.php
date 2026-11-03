<section class="page-heading">
    <div>
        <p class="section-label"><?= $page === 'mine' ? 'Ruang milik kau' : 'Buku / Kalkulator / Learning tools' ?></p>
        <h1><?= $page === 'mine' ? 'Barang saya' : 'Bahan belajar untuk tangan seterusnya.' ?></h1>
        <p class="muted"><?= $page === 'mine' ? 'Urus barang yang kau kongsi. Barang diarkibkan kekal dalam rekod kau.' : 'Cari barang yang masih elok untuk digunakan semula. Senarai ini menunjukkan barang yang tersedia.' ?></p>
    </div>
    <?php if ($page === 'mine'): ?><a class="button button-primary" href="<?= e(url('create')) ?>">Tambah barang</a><?php endif; ?>
</section>
<form class="search-form" method="get" action="/" role="search" aria-label="Cari dan tapis barang">
    <input type="hidden" name="page" value="<?= e($page) ?>">
    <div class="search-query"><label for="q">Cari tajuk atau penerangan</label><input type="search" id="q" name="q" placeholder="Contoh: buku pengaturcaraan" maxlength="150" value="<?= e($filters['q']) ?>"></div>
    <div><label for="category_id">Kategori</label><select id="category_id" name="category_id"><option value="">Semua kategori</option><?php foreach ($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= (string) $filters['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="item_condition">Keadaan</label><select id="item_condition" name="item_condition"><option value="">Semua keadaan</option><?php foreach (CONDITIONS as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['item_condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label for="listing_type">Jenis</label><select id="listing_type" name="listing_type"><option value="">Semua jenis</option><?php foreach (LISTING_TYPES as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['listing_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <?php if ($page === 'mine'): ?><div><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><?php foreach (LISTING_STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="search-actions"><button type="submit" class="button button-primary">Cari barang</button><a href="<?= e(url($page)) ?>">Reset carian</a></div>
</form>
<section class="results" aria-labelledby="results-heading">
    <div class="results-heading"><h2 id="results-heading"><?= $page === 'mine' ? 'Senarai barang kau' : 'Barang tersedia' ?></h2><p><?= e($results['total']) ?> barang ditemui</p></div>
    <?php if ($results['items'] === []): ?>
        <div class="empty-state"><h3><?= $page === 'mine' && $filters['q'] === '' && $filters['status'] === '' ? 'Belum ada barang di sini.' : 'Tiada barang sepadan.' ?></h3><p>Cuba kata kunci atau penapis lain. <?= $page === 'mine' ? 'Kau juga boleh tambah barang pertama kau.' : 'Barang baharu akan muncul di sini apabila disenaraikan.' ?></p><?php if ($page === 'mine'): ?><a class="button" href="<?= e(url('create')) ?>">Tambah barang</a><?php endif; ?></div>
    <?php else: ?>
        <div class="listing-list">
            <?php foreach ($results['items'] as $item): ?>
                <article class="listing-row">
                    <div class="listing-category"><?= e($item['category_name']) ?></div>
                    <div class="listing-copy"><h3><a href="<?= e(url('view', ['id' => $item['listing_id']])) ?>"><?= e($item['title']) ?></a></h3><p class="listing-excerpt"><?= e(mb_strimwidth(preg_replace('/\s+/u', ' ', $item['description']), 0, 150, '...')) ?></p><p class="item-meta"><?= e(CONDITIONS[$item['item_condition']]) ?> <span aria-hidden="true">/</span> <?= e($item['owner_name']) ?></p></div>
                    <div class="listing-end"><span class="type-label"><?= e(LISTING_TYPES[$item['listing_type']]) ?></span><?php if ($page === 'mine'): ?><span class="muted"><?= e(LISTING_STATUSES[$item['status']]) ?></span><?php endif; ?><a class="detail-link" href="<?= e(url('view', ['id' => $item['listing_id']])) ?>" aria-label="Lihat barang: <?= e($item['title']) ?>">Lihat barang</a></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($results['pages'] > 1): ?>
        <nav class="pagination" aria-label="Halaman senarai">
            <?php if ($results['number'] > 1): ?><a class="button" href="<?= e(url($page, array_replace($filters, ['p' => $results['number'] - 1]))) ?>">Halaman sebelumnya</a><?php endif; ?>
            <p>Halaman <?= e($results['number']) ?> daripada <?= e($results['pages']) ?></p>
            <?php if ($results['number'] < $results['pages']): ?><a class="button" href="<?= e(url($page, array_replace($filters, ['p' => $results['number'] + 1]))) ?>">Halaman seterusnya</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
