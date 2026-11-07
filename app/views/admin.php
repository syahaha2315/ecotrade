<section class="page-heading">
    <div>
        <p class="section-label">EcoTrade administration</p>
        <h1><?= e($title) ?></h1>
    </div>
</section>
<nav class="admin-nav" aria-label="Administration">
    <?php foreach (['admin' => 'Overview', 'admin-users' => 'Users', 'admin-categories' => 'Categories', 'admin-reports' => 'Reports'] as $route => $label): ?>
        <a class="button" href="<?= e(url($route)) ?>" <?= $page === $route ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($page === 'admin'): ?>
    <h2>Marketplace activity</h2>
    <dl class="admin-summary">
        <?php foreach (['users' => 'Accounts', 'listings' => 'Listings', 'requests' => 'Requests', 'completed' => 'Completed requests'] as $key => $label): ?>
            <div><dt><?= e($label) ?></dt><dd><?= e($counts[$key]) ?></dd></div>
        <?php endforeach; ?>
    </dl>
<?php elseif ($page === 'admin-users'): ?>
    <form method="get" action="/" class="form-panel compact-form" role="search" aria-label="Search accounts">
        <input type="hidden" name="page" value="admin-users">
        <div><label for="account-query">Name or email</label><input id="account-query" name="q" type="search" maxlength="120" value="<?= e($q) ?>"></div>
        <button type="submit" class="button-primary">Find accounts</button>
    </form>
    <?php if ($accounts === []): ?><p class="empty-state">No accounts match your search.</p><?php endif; ?>
    <ul class="admin-list">
        <?php foreach (array_slice($accounts, 0, 30) as $account): ?>
            <li class="admin-row">
                <div>
                    <strong><?= e($account['full_name']) ?></strong>
                    <p class="muted"><?= e($account['email']) ?></p>
                    <p><?= e(ucfirst($account['role'])) ?> · <?= $account['is_active'] ? 'Active' : 'Inactive' ?></p>
                </div>
                <?php if ($account['role'] === 'student'): ?>
                    <form method="post" action="<?= e(url($page)) ?>" data-confirm="<?= $account['is_active'] ? 'Deactivate' : 'Activate' ?> this account?"
                          data-confirm-action="<?= $account['is_active'] ? 'Deactivate' : 'Activate' ?> account" data-confirm-detail="<?= e($account['email']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e($account['user_id']) ?>">
                        <input type="hidden" name="active" value="<?= $account['is_active'] ? '0' : '1' ?>">
                        <button type="submit" aria-label="<?= $account['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($account['email']) ?>"><?= $account['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <nav class="pagination" aria-label="Account pages">
        <?php if ($number > 1): ?><a class="button" href="<?= e(url($page, ['q' => $q, 'p' => $number - 1])) ?>">Previous page</a><?php endif; ?>
        <?php if (count($accounts) > 30): ?><a class="button" href="<?= e(url($page, ['q' => $q, 'p' => $number + 1])) ?>">Next page</a><?php endif; ?>
    </nav>
<?php elseif ($page === 'admin-categories'): ?>
    <p class="muted">Only active categories appear in the catalogue and listing form. Existing listings stay in their owners' records.</p>
    <form method="post" action="<?= e(url($page)) ?>" class="form-panel compact-form" data-confirm="Add this category?" data-confirm-action="Add category">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="active" value="1">
        <div><label for="new-category">New category name</label><input id="new-category" name="name" required minlength="2" maxlength="80"></div>
        <button type="submit" class="button-primary">Add category</button>
    </form>
    <?php if ($categories === []): ?><p class="empty-state">Add a category to let students post listings.</p><?php endif; ?>
    <?php foreach ($categories as $category): ?>
        <?php $id = (int) $category['category_id']; ?>
        <form method="post" action="<?= e(url($page)) ?>" class="category-editor compact-form" data-confirm="Save changes to this category?" data-confirm-action="Save category">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div><label for="category-<?= e($id) ?>">Category name</label><input id="category-<?= e($id) ?>" name="name" required minlength="2" maxlength="80" value="<?= e($category['category_name']) ?>"></div>
            <div><label for="active-<?= e($id) ?>">Status</label><select id="active-<?= e($id) ?>" name="active">
                <option value="1" <?= $category['is_active'] ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $category['is_active'] ? '' : 'selected' ?>>Inactive</option>
            </select></div>
            <button type="submit" aria-label="Save <?= e($category['category_name']) ?>">Save</button>
        </form>
    <?php endforeach; ?>
<?php else: ?>
    <form method="get" action="/" class="search-form" aria-label="Report filters">
        <input type="hidden" name="page" value="admin-reports">
        <div><label for="from">From (UTC)</label><input id="from" name="from" type="date" required value="<?= e($filters['from']) ?>"></div>
        <div><label for="to">Through (UTC)</label><input id="to" name="to" type="date" required value="<?= e($filters['to']) ?>"></div>
        <div><label for="report-category">Category</label><select id="report-category" name="category"><option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category['category_id']) ?>" <?= (string) $category['category_id'] === $filters['category'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div><label for="report-type">Type</label><select id="report-type" name="type"><option value="">All types</option>
            <?php foreach (LISTING_TYPES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $value === $filters['type'] ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="search-actions">
            <button type="submit" class="button-primary">Apply filters</button>
            <a class="button" href="<?= e(url('admin-export', $filters)) ?>" download>Download CSV</a>
        </div>
    </form>
    <p class="muted">Completion rate tracks the share of requests created in each month that are now complete. Completed in month counts completions by their completion date.</p>
    <?php if ($rows === []): ?>
        <p class="empty-state">No listings or requests match these dates and filters.</p>
    <?php else: ?>
        <div class="table-scroll" tabindex="0" aria-label="Scrollable monthly report" role="region">
            <table>
                <caption>Monthly activity by category and type</caption>
                <thead><tr><th scope="col">Month</th><th scope="col">Category</th><th scope="col">Type</th><th scope="col">Listings created</th><th scope="col">Requests created</th><th scope="col">Completed in month</th><th scope="col">Completion rate</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr><td><?= e($row['month']) ?></td><td><?= e($row['category_name']) ?></td><td><?= e(LISTING_TYPES[$row['listing_type']]) ?></td><td><?= e($row['listings']) ?></td><td><?= e($row['requests']) ?></td><td><?= e($row['completed']) ?></td><td><?= e(completionRate($row)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
