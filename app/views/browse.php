<section class="page-heading">
    <div>
        <p class="section-label"><?= $page === 'mine' ? 'Your listings' : 'Books, calculators and learning tools' ?></p>
        <h1><?= $page === 'mine' ? 'My listings' : 'Find study materials' ?></h1>
        <p class="muted"><?= $page === 'mine' ? 'Manage your available and archived listings.' : 'Find materials other students are offering for donation or exchange.' ?></p>
    </div>
    <?php if ($page === 'mine'): ?><a class="button button-primary" href="<?= e(url('create')) ?>">Add listing</a><?php endif; ?>
</section>
<?php if ($page === 'mine' && $user['role'] === 'student' && googleSettings() !== null): ?>
    <p><a class="button" href="<?= e(url('google')) ?>">Connect Google account</a></p>
<?php endif; ?>
<form class="search-form" method="get" action="/" role="search" aria-label="Search and filter listings">
    <input type="hidden" name="page" value="<?= e($page) ?>">
    <div class="search-query"><label for="q">Title or description</label><input type="search" id="q" name="q" placeholder="Try programming textbook" maxlength="150" value="<?= e($filters['q']) ?>"></div>
    <div><label for="category_id">Category</label><select id="category_id" name="category_id"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= (string) $filters['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="item_condition">Condition</label><select id="item_condition" name="item_condition"><option value="">All conditions</option><?php foreach (CONDITIONS as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['item_condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label for="listing_type">Type</label><select id="listing_type" name="listing_type"><option value="">All types</option><?php foreach (LISTING_TYPES as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['listing_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <?php if ($page === 'mine'): ?><div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach (LISTING_STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="search-actions"><button type="submit" class="button button-primary">Search</button><a href="<?= e(url($page)) ?>">Clear filters</a></div>
</form>
<section class="results" aria-labelledby="results-heading">
    <div class="results-heading"><h2 id="results-heading"><?= $page === 'mine' ? 'Your listings' : 'Available listings' ?></h2><p><?= e($results['total']) ?> results</p></div>
    <?php if ($results['items'] === []): ?>
        <div class="empty-state"><h3><?= $page === 'mine' && $filters['q'] === '' && $filters['status'] === '' ? 'No listings yet' : 'No matching listings' ?></h3><p>Try another keyword or filter. <?= $page === 'mine' ? 'You can also post your first listing.' : 'New listings appear here as students post them.' ?></p><?php if ($page === 'mine'): ?><a class="button" href="<?= e(url('create')) ?>">Add listing</a><?php endif; ?></div>
    <?php else: ?>
        <div class="listing-list">
            <?php foreach ($results['items'] as $item): ?>
                <article class="listing-row">
                    <div class="listing-category"><?= e($item['category_name']) ?></div>
                    <div class="listing-copy"><h3><a href="<?= e(url('view', ['id' => $item['listing_id']])) ?>"><?= e($item['title']) ?></a></h3><p class="listing-excerpt"><?= e(mb_strimwidth(preg_replace('/\s+/u', ' ', $item['description']), 0, 150, '...')) ?></p><p class="item-meta"><?= e(CONDITIONS[$item['item_condition']]) ?> <span aria-hidden="true">/</span> <?= e($item['owner_name']) ?></p></div>
                    <div class="listing-end"><span class="type-label"><?= e(LISTING_TYPES[$item['listing_type']]) ?></span><?php if ($page === 'mine'): ?><span class="muted"><?= e(LISTING_STATUSES[$item['status']]) ?></span><?php endif; ?><a class="detail-link" href="<?= e(url('view', ['id' => $item['listing_id']])) ?>" aria-label="View listing <?= e($item['title']) ?>">View listing</a></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($results['pages'] > 1): ?>
        <nav class="pagination" aria-label="Listing pages">
            <?php if ($results['number'] > 1): ?><a class="button" href="<?= e(url($page, array_replace($filters, ['p' => $results['number'] - 1]))) ?>">Previous page</a><?php endif; ?>
            <p>Page <?= e($results['number']) ?> of <?= e($results['pages']) ?></p>
            <?php if ($results['number'] < $results['pages']): ?><a class="button" href="<?= e(url($page, array_replace($filters, ['p' => $results['number'] + 1]))) ?>">Next page</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
