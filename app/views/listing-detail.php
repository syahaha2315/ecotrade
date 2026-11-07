<?php $isOwner = $user !== null && (int) $user['user_id'] === (int) $listing['owner_id']; ?>
<div class="detail-page">
    <a class="back-link" href="<?= e(url($isOwner ? 'mine' : 'browse')) ?>">Back to <?= $isOwner ? 'my listings' : 'listings' ?></a>
    <div class="detail-layout">
        <article class="detail-main">
            <p class="section-label"><?= e($listing['category_name']) ?></p>
            <h1><?= $page === 'view' ? e($listing['title']) : e(ucfirst($page)) . ' this listing?' ?></h1>
            <?php if ($page === 'archive'): ?><h2><?= e($listing['title']) ?></h2><p>Archiving removes this listing from search. You can still find it in My listings.</p><?php endif; ?>
            <?php if ($page === 'delete'): ?><h2><?= e($listing['title']) ?></h2><p>The listing and its photos will be removed. Existing conversations will be kept.</p><?php endif; ?>
            <?php if ($photos !== []): ?>
                <section class="photo-gallery" aria-label="Listing photos">
                    <div class="photo-track" tabindex="0" aria-label="Swipe or scroll to browse photos" role="region">
                        <?php foreach ($photos as $index => $photo): ?>
                            <figure id="photo-<?= e($photo['photo_id']) ?>">
                                <img src="<?= e(url('photo', ['id' => $photo['photo_id']])) ?>"
                                     width="<?= e($photo['image_width']) ?>" height="<?= e($photo['image_height']) ?>"
                                     alt="<?= e($listing['title']) ?>, photo <?= $index + 1 ?>"
                                     loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                            </figure>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($photos) > 1): ?>
                        <nav class="photo-links" aria-label="Choose a photo">
                            <?php foreach ($photos as $index => $photo): ?>
                                <a href="#photo-<?= e($photo['photo_id']) ?>" aria-label="Photo <?= $index + 1 ?>"><?= $index + 1 ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <div class="detail-description"><?= nl2br(e($listing['description'])) ?></div>
        </article>
        <aside class="detail-aside" aria-label="Listing details">
            <span class="type-label"><?= e(LISTING_TYPES[$listing['listing_type']]) ?></span>
            <dl><div><dt>Status</dt><dd><?= e(LISTING_STATUSES[$listing['status']]) ?></dd></div><div><dt>Condition</dt><dd><?= e(CONDITIONS[$listing['item_condition']]) ?></dd></div><div><dt>Owner</dt><dd><?= e($listing['owner_name']) ?></dd></div><div><dt>Description language</dt><dd><?= $listing['source_language'] === 'ms' ? 'Malay' : 'English' ?></dd></div></dl>
            <?php if ($page !== 'view'): ?>
                <form method="post" action="<?= e(url($page, ['id' => $listing['listing_id']])) ?>" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="button button-danger" type="submit">Confirm <?= e($page) ?></button><a class="button" href="<?= e(url($page === 'delete' ? 'edit' : 'view', ['id' => $listing['listing_id']])) ?>">Cancel</a></form>
            <?php elseif ($isOwner && $listing['status'] === 'available'): ?>
                <div class="stack-form"><a class="button button-primary" href="<?= e(url('edit', ['id' => $listing['listing_id']])) ?>">Edit listing</a><a class="button" data-confirm-form="archive-listing" href="<?= e(url('archive', ['id' => $listing['listing_id']])) ?>">Archive listing</a></div>
                <form id="archive-listing" method="post" action="<?= e(url('archive', ['id' => $listing['listing_id']])) ?>" hidden
                      data-confirm="Archive this listing?" data-confirm-action="Archive listing"
                      data-confirm-detail="The listing will leave search. You can still find it in My listings.">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                </form>
            <?php elseif ($isOwner): ?><p class="field-help">This listing is read-only at its current status.</p>
            <?php elseif ($listing['status'] === 'available' && $listing['owner_role'] === 'student' && ($user === null || $user['role'] === 'student')): ?>
                <a class="button button-primary" href="<?= e(url('chat', ['id' => $listing['listing_id']])) ?>">Message owner</a>
            <?php endif; ?>
        </aside>
    </div>
</div>
