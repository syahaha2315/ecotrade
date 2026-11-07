<div class="form-page">
    <div class="page-heading"><div><p class="section-label">My listings</p><h1><?= $page === 'create' ? 'Add listing' : 'Edit listing' ?></h1><p class="muted">Describe the item and its condition so other students know what to expect.</p></div></div>
    <?php if ($errors !== []): ?><div class="error-summary" role="alert"><p>Check the highlighted fields before saving.</p></div><?php endif; ?>
    <?php if ($categories === []): ?><p class="error-summary" role="alert">Ask an administrator to activate a category before posting a listing.</p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url($page, $listing !== null ? ['id' => $listing['listing_id']] : [])) ?>" class="stack-form form-panel"
          data-confirm="<?= $page === 'create' ? 'Publish this listing?' : 'Save changes to this listing?' ?>" data-confirm-action="<?= $page === 'create' ? 'Publish listing' : 'Save changes' ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <fieldset class="listing-fields">
            <legend>Item details</legend>
            <div class="stack-form">
                <div><label for="category_id">Category</label><select id="category_id" name="category_id" required<?= fieldState('category_id', $errors) ?>><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?= e($category['category_id']) ?>" <?= (string) $values['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option><?php endforeach; ?></select><?php fieldError('category_id', $errors); ?></div>
                <div><label for="title">Title</label><input id="title" name="title" required minlength="5" maxlength="180" placeholder="Example: Introductory programming textbook" value="<?= e($values['title']) ?>"<?= fieldState('title', $errors) ?>><?php fieldError('title', $errors); ?></div>
                <fieldset class="choice-field"<?= fieldState('item_condition', $errors) ?>>
                    <legend>Condition</legend>
                    <div class="choice-list">
                        <?php foreach (CONDITIONS as $value => $label): ?>
                            <label><input type="radio" name="item_condition" value="<?= e($value) ?>" required <?= $values['item_condition'] === $value ? 'checked' : '' ?>> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?php fieldError('item_condition', $errors); ?>
                </fieldset>
                <div><label for="description">Description</label><textarea id="description" name="description" rows="6" required minlength="20" maxlength="5000" placeholder="Describe what is included, its condition and any defects."<?= fieldState('description', $errors) ?>><?= e($values['description']) ?></textarea><?php fieldError('description', $errors); ?><p class="field-help">20 to 5,000 characters.</p>
                    <button type="button" id="description-draft" aria-describedby="draft-help" hidden>Generate draft</button>
                    <p class="field-help" id="draft-help" hidden>Uses your listing details and the main colour in the first photo, when it can be detected.</p>
                    <p class="field-help" id="description-feedback" role="status" hidden></p>
                </div>
                <div><label for="source_language">Description language</label><select id="source_language" name="source_language" required<?= fieldState('source_language', $errors) ?>><option value="en" <?= $values['source_language'] === 'en' ? 'selected' : '' ?>>English</option><option value="ms" <?= $values['source_language'] === 'ms' ? 'selected' : '' ?>>Malay</option></select><?php fieldError('source_language', $errors); ?></div>
            </div>
        </fieldset>
        <fieldset class="listing-fields">
            <legend>Photos</legend>
            <label for="photos">Listing photos (optional)</label>
            <input id="photos" name="photos[]" type="file" accept="image/*" multiple <?= isset($errors['photos']) ? 'aria-invalid="true" aria-describedby="photo-help error-photos"' : 'aria-describedby="photo-help"' ?>>
            <?php fieldError('photos', $errors); ?>
            <p id="photo-help" class="field-help">Up to four JPEG, PNG, WebP, GIF, AVIF or BMP photos. Each must be under 10 MB. Choose images again if a form error prevents saving.</p>
            <?php if ($savedPhotos !== []): ?>
                <div class="saved-photos">
                    <?php foreach ($savedPhotos as $index => $photo): ?>
                        <img src="<?= e(url('photo', ['id' => $photo['photo_id']])) ?>" width="64" height="64" loading="lazy" alt="Saved photo <?= $index + 1 ?>">
                    <?php endforeach; ?>
                </div>
                <p class="field-help"><?= count($savedPhotos) ?> saved. Selecting new files replaces this set.</p>
                <label class="check-option"><input name="remove_photos" type="checkbox" value="1"> Remove saved photos</label>
            <?php endif; ?>
        </fieldset>
        <fieldset class="listing-fields">
            <legend>How to share</legend>
            <fieldset class="choice-field"<?= fieldState('listing_type', $errors) ?>>
                <legend>Listing type</legend>
                <div class="choice-list">
                    <?php foreach (LISTING_TYPES as $value => $label): ?>
                        <label><input type="radio" name="listing_type" value="<?= e($value) ?>" required <?= $values['listing_type'] === $value ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="field-help">Donate the item for free or exchange it for another study item. Students can message you to arrange collection.</p>
                <?php fieldError('listing_type', $errors); ?>
            </fieldset>
        </fieldset>
        <div class="form-actions"><button type="submit" class="button button-primary" <?= $categories === [] ? 'disabled data-permanent-disabled' : '' ?>><?= $page === 'create' ? 'Save listing' : 'Save changes' ?></button><a class="button" href="<?= e($listing !== null ? url('view', ['id' => $listing['listing_id']]) : url('mine')) ?>">Cancel</a>
            <?php if ($listing !== null): ?><a class="button button-danger" data-confirm-form="delete-listing" href="<?= e(url('delete', ['id' => $listing['listing_id']])) ?>">Delete listing</a><?php endif; ?>
        </div>
    </form>
    <?php if ($listing !== null): ?>
        <form id="delete-listing" method="post" action="<?= e(url('delete', ['id' => $listing['listing_id']])) ?>" hidden
              data-confirm="Delete this listing?" data-confirm-action="Delete listing" data-confirm-danger
              data-confirm-detail="The listing and its photos will be removed. Existing conversations will be kept.">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        </form>
    <?php endif; ?>
</div>
