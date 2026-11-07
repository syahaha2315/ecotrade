<?php
declare(strict_types=1);

function uploadedPhotos(): array
{
    $files = $_FILES['photos'] ?? null;
    if ($files === null) {
        return [];
    }
    if (!is_array($files['error']) || count($files['error']) > 4) {
        throw new HttpError(422, 'Choose up to four photos.');
    }
    $photos = [];
    foreach ($files['error'] as $index => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $path = $files['tmp_name'][$index] ?? '';
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ||
            ($error === UPLOAD_ERR_OK && is_string($path) && is_uploaded_file($path) && filesize($path) >= 10000000)) {
            throw new HttpError(422, 'Each listing photo must be under 10 MB.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
            throw new HttpError(422, 'This photo could not be uploaded. Choose it again.');
        }
        $info = @getimagesize($path);
        $formats = [
            IMAGETYPE_JPEG => ['image/jpeg'], IMAGETYPE_PNG => ['image/png'], IMAGETYPE_WEBP => ['image/webp'],
            IMAGETYPE_GIF => ['image/gif'], IMAGETYPE_AVIF => ['image/avif'], IMAGETYPE_BMP => ['image/bmp', 'image/x-ms-bmp'],
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!$info || !in_array($mime, $formats[$info[2]] ?? [], true) || $info[0] < 1 || $info[1] < 1) {
            throw new HttpError(422, 'Choose a valid JPEG, PNG, WebP, GIF, AVIF or BMP image.');
        }
        if ($info[0] * $info[1] > 60000000 || max($info[0], $info[1]) > 65535) {
            throw new HttpError(422, 'Choose a photo up to 60 megapixels.');
        }
        $data = file_get_contents($path);
        if ($data === false) {
            throw new HttpError(422, 'This photo could not be read. Choose it again.');
        }
        $photos[] = [$info['mime'], $info[0], $info[1], $data];
    }
    return $photos;
}

function readUploadedImage(mixed $error, mixed $path, int $maxSide): array
{
    if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path) || filesize($path) > 1048576) {
        throw new HttpError(422, 'Choose an image up to 1 MB.');
    }
    $info = @getimagesize($path);
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) ||
        $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > $maxSide ||
        (new finfo(FILEINFO_MIME_TYPE))->file($path) !== $info['mime']) {
        throw new HttpError(422, 'Use a JPEG, PNG or WebP image up to ' . number_format($maxSide) . ' pixels on each side.');
    }
    $data = file_get_contents($path);
    if ($data === false) {
        throw new HttpError(422, 'This image could not be read. Choose it again.');
    }
    return [$info['mime'], $info[0], $info[1], $data];
}

function saveListingPhotos(PDO $pdo, int $id, array $photos, bool $remove = false): void
{
    if ($photos === [] && !$remove) {
        return;
    }
    $pdo->prepare('DELETE FROM listing_photos WHERE listing_id = ?')->execute([$id]);
    $insert = $pdo->prepare('INSERT INTO listing_photos (listing_id, mime_type, image_width, image_height, image_data) VALUES (?, ?, ?, ?, ?)');
    foreach ($photos as $photo) {
        $insert->execute([$id, ...$photo]);
    }
}

function listingPhotos(PDO $pdo, int $id): array
{
    $query = $pdo->prepare('SELECT photo_id, image_width, image_height FROM listing_photos WHERE listing_id = ? ORDER BY photo_id');
    $query->execute([$id]);
    return $query->fetchAll();
}

function canViewListing(array $listing, ?array $viewer): bool
{
    return $listing['deleted_at'] === null && (
        ($viewer !== null && (int) $viewer['user_id'] === (int) $listing['owner_id']) ||
        ($listing['status'] !== 'archived' && (int) $listing['category_active'] === 1 && (int) $listing['owner_active'] === 1)
    );
}

function serveListingPhoto(PDO $pdo, int $id, ?array $viewer): void
{
    $query = $pdo->prepare('SELECT listing_id, mime_type, image_data FROM listing_photos WHERE photo_id = ?');
    $query->execute([$id]);
    $photo = $query->fetch();
    if (!$photo || !canViewListing(listingById($pdo, (int) $photo['listing_id']), $viewer)) {
        throw new HttpError(404, 'Photo not found.');
    }
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Length: ' . strlen($photo['image_data']));
    echo $photo['image_data'];
}
