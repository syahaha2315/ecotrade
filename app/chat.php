<?php
declare(strict_types=1);

function chatTime(string $utc): DateTimeImmutable
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
}

function runChat(PDO $pdo, string $page, string $method, ?array $viewer): void
{
    $student = requireUser($viewer);
    if ($student['role'] !== 'student') {
        throw new HttpError(403, 'Messaging is available to student accounts.');
    }
    $studentId = (int) $student['user_id'];
    if ($page === 'inbox') {
        $query = $pdo->prepare(
            "SELECT m.listing_id, m.buyer_id, l.title, m.body, m.created_at, l.status, l.deleted_at,
                    other.user_id, other.full_name, other.is_active AND a.user_id IS NOT NULL AS has_avatar,
                    CASE WHEN l.deleted_at IS NULL AND (l.owner_id = ? OR (l.status <> 'archived' AND c.is_active = 1 AND owner.is_active = 1))
                         THEN (SELECT MIN(photo_id) FROM listing_photos p WHERE p.listing_id = l.listing_id) END AS cover_id
             FROM (SELECT MAX(m.message_id) AS last_id FROM listing_messages m
                   JOIN listings l ON l.listing_id = m.listing_id
                   WHERE m.buyer_id = ? OR l.owner_id = ?
                   GROUP BY m.listing_id, m.buyer_id) threads
             JOIN listing_messages m ON m.message_id = threads.last_id
             JOIN listings l ON l.listing_id = m.listing_id
             JOIN categories c ON c.category_id = l.category_id
             JOIN users owner ON owner.user_id = l.owner_id
             JOIN users other ON other.user_id = CASE WHEN m.buyer_id = ? THEN l.owner_id ELSE m.buyer_id END
             LEFT JOIN user_avatars a ON a.user_id = other.user_id
             ORDER BY m.message_id DESC LIMIT 31 OFFSET " . (30 * max(0, min(10000, (int) input($_GET, 'p') - 1)))
        );
        $query->execute([$studentId, $studentId, $studentId, $studentId]);
        renderPage('Messages', 'chat', ['threads' => $query->fetchAll()]);
        return;
    }

    $id = positiveId($_GET);
    if ($method === 'POST') {
        $pdo->beginTransaction();
    }
    try {
        $listing = listingById($pdo, $id, $method === 'POST');
        $ownerId = (int) $listing['owner_id'];
        $buyerId = $ownerId === $studentId ? positiveId($_GET, 'buyer') : $studentId;
        if ($buyerId === $ownerId || (input($_GET, 'buyer') !== '' && positiveId($_GET, 'buyer') !== $buyerId)) {
            throw new HttpError(404, 'Conversation not found.');
        }
        $otherId = $studentId === $ownerId ? $buyerId : $ownerId;
        $account = $pdo->prepare("SELECT u.user_id, u.full_name, u.is_active, a.user_id IS NOT NULL AS has_avatar
            FROM users u LEFT JOIN user_avatars a ON a.user_id = u.user_id WHERE u.user_id = ? AND u.role = 'student'");
        $account->execute([$otherId]);
        $other = $account->fetch();
        $existing = $pdo->prepare('SELECT 1 FROM listing_messages WHERE listing_id = ? AND buyer_id = ? LIMIT 1');
        $existing->execute([$id, $buyerId]);
        $hasMessages = (bool) $existing->fetchColumn();
        $open = $other && $listing['deleted_at'] === null && (int) $other['is_active'] === 1 && (int) $listing['category_active'] === 1 &&
            in_array($listing['status'], ['available', 'reserved'], true);
        if (!$other || (!$hasMessages && ($studentId === $ownerId || !$open || $listing['status'] !== 'available'))) {
            throw new HttpError(404, 'Conversation not found.');
        }
        $threadUrl = url('chat', ['id' => $id, 'buyer' => $buyerId]);
        if ($method === 'POST') {
            if (!$open) {
                throw new HttpError(409, 'This conversation is closed.');
            }
            $body = input($_POST, 'message');
            if ($body === '' || mb_strlen($body) > 2000) {
                throw new HttpError(422, 'Write a message between 1 and 2,000 characters.');
            }
            $pdo->prepare('INSERT INTO listing_messages (listing_id, buyer_id, sender_id, body) VALUES (?, ?, ?, ?)')
                ->execute([$id, $buyerId, $studentId, $body]);
            $pdo->commit();
            redirect($threadUrl . '#latest');
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    $before = input($_GET, 'before') === '' ? null : positiveId($_GET, 'before');
    $query = $pdo->prepare('SELECT message_id, sender_id, body, created_at FROM listing_messages WHERE listing_id = ? AND buyer_id = ?' .
        ($before === null ? '' : ' AND message_id < ?') . ' ORDER BY message_id DESC LIMIT 31');
    $query->execute($before === null ? [$id, $buyerId] : [$id, $buyerId, $before]);
    $rows = $query->fetchAll();
    $messages = array_reverse(array_slice($rows, 0, 30));
    $older = count($rows) > 30 ? $messages[0]['message_id'] : null;
    $showListingLink = canViewListing($listing, $student);
    $photos = $showListingLink ? listingPhotos($pdo, $id) : [];
    $cover = $photos[0] ?? null;
    renderPage('Conversation', 'chat', compact('listing', 'other', 'messages', 'older', 'open', 'threadUrl', 'studentId', 'buyerId', 'showListingLink', 'cover'));
}
