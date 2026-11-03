<?php
declare(strict_types=1);

const CONDITIONS = ['new' => 'Baru', 'like_new' => 'Seperti baru', 'good' => 'Baik', 'fair' => 'Masih boleh digunakan'];
const LISTING_TYPES = ['donation' => 'Sumbangan', 'exchange' => 'Pertukaran'];
const LISTING_STATUSES = ['available' => 'Tersedia', 'reserved' => 'Ditempah', 'completed' => 'Selesai', 'archived' => 'Diarkibkan'];

function activeCategories(PDO $pdo): array
{
    return $pdo->query('SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name')->fetchAll();
}

function listingById(PDO $pdo, int $id, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT l.*, c.category_name, c.is_active AS category_active, u.full_name AS owner_name FROM listings l JOIN categories c ON c.category_id = l.category_id JOIN users u ON u.user_id = l.owner_id WHERE l.listing_id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $listing = $query->fetch();
    if (!$listing) {
        throw new HttpError(404, 'Barang ini tidak ditemui.');
    }
    return $listing;
}

function requireOwner(array $listing, array $user): void
{
    if ((int) $listing['owner_id'] !== (int) $user['user_id']) {
        throw new HttpError(403, 'Hanya pemilik boleh mengubah atau mengarkibkan barang ini.');
    }
}

function requireEditable(array $listing): void
{
    if ($listing['status'] !== 'available') {
        throw new HttpError(409, 'Barang ini sudah ditempah, selesai atau diarkibkan. Maklumatnya tidak boleh diubah.');
    }
}

function listingValues(array $source): array
{
    $values = [];
    foreach (['title', 'description', 'category_id', 'item_condition', 'listing_type', 'source_language'] as $field) {
        $values[$field] = input($source, $field);
    }
    return $values;
}

function validateListing(PDO $pdo, array $values): array
{
    $errors = [];
    if (mb_strlen($values['title']) < 5 || mb_strlen($values['title']) > 180) {
        $errors['title'] = 'Tajuk perlu antara 5 hingga 180 aksara.';
    }
    if (mb_strlen($values['description']) < 20 || mb_strlen($values['description']) > 5000) {
        $errors['description'] = 'Penerangan perlu antara 20 hingga 5,000 aksara.';
    }
    $category = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = ? AND is_active = 1');
    $id = filter_var($values['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $category->execute([$id === false ? 0 : $id]);
    if (!$category->fetchColumn()) {
        $errors['category_id'] = 'Pilih kategori yang masih aktif.';
    }
    if (!array_key_exists($values['item_condition'], CONDITIONS)) {
        $errors['item_condition'] = 'Pilih keadaan barang yang sah.';
    }
    if (!array_key_exists($values['listing_type'], LISTING_TYPES)) {
        $errors['listing_type'] = 'Pilih sumbangan atau pertukaran.';
    }
    if (!in_array($values['source_language'], ['ms', 'en'], true)) {
        $errors['source_language'] = 'Pilih bahasa penerangan.';
    }
    return $errors;
}

function createListing(PDO $pdo, array $values, array $user): int
{
    $pdo->prepare('INSERT INTO listings (owner_id, category_id, title, description, source_language, item_condition, listing_type) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $user['user_id'], $values['category_id'], $values['title'], $values['description'],
        $values['source_language'], $values['item_condition'], $values['listing_type'],
    ]);
    return (int) $pdo->lastInsertId();
}

function changeListing(PDO $pdo, int $id, array $user, ?array $values): void
{
    $pdo->beginTransaction();
    try {
        $listing = listingById($pdo, $id, true);
        requireOwner($listing, $user);
        requireEditable($listing);
        if ($values === null) {
            $pdo->prepare("UPDATE listings SET status = 'archived' WHERE listing_id = ? AND owner_id = ? AND status = 'available'")->execute([$id, $user['user_id']]);
        } else {
            $pdo->prepare("UPDATE listings SET category_id = ?, title = ?, description = ?, source_language = ?, item_condition = ?, listing_type = ? WHERE listing_id = ? AND owner_id = ? AND status = 'available'")->execute([
                $values['category_id'], $values['title'], $values['description'], $values['source_language'],
                $values['item_condition'], $values['listing_type'], $id, $user['user_id'],
            ]);
            if ($listing['description'] !== $values['description'] || $listing['source_language'] !== $values['source_language']) {
                $pdo->prepare('DELETE FROM translations WHERE listing_id = ?')->execute([$id]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function searchListings(PDO $pdo, array $filters, ?array $owner): array
{
    $where = [];
    $parameters = [];
    if ($owner !== null) {
        $where[] = 'l.owner_id = ?';
        $parameters[] = $owner['user_id'];
        if ($filters['status'] !== '') {
            $where[] = 'l.status = ?';
            $parameters[] = $filters['status'];
        }
    } else {
        $where[] = "l.status = 'available' AND c.is_active = 1 AND u.is_active = 1";
    }
    if ($filters['q'] !== '') {
        $where[] = "(l.title LIKE ? ESCAPE '!' OR l.description LIKE ? ESCAPE '!')";
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        array_push($parameters, $needle, $needle);
    }
    foreach (['category_id', 'item_condition', 'listing_type'] as $field) {
        if ($filters[$field] !== '') {
            $where[] = "l.$field = ?";
            $parameters[] = $filters[$field];
        }
    }
    $from = ' FROM listings l JOIN categories c ON c.category_id = l.category_id JOIN users u ON u.user_id = l.owner_id WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $from);
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 12));
    $number = min($filters['p'], $pages);
    $offset = ($number - 1) * 12;
    $query = $pdo->prepare('SELECT l.*, c.category_name, u.full_name AS owner_name' . $from . " ORDER BY l.created_at DESC, l.listing_id DESC LIMIT 12 OFFSET $offset");
    $query->execute($parameters);
    return ['items' => $query->fetchAll(), 'total' => $total, 'pages' => $pages, 'number' => $number];
}
