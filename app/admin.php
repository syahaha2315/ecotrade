<?php
declare(strict_types=1);

function requireAdmin(?array $viewer): array
{
    $admin = requireUser($viewer);
    if ($admin['role'] !== 'admin') {
        throw new HttpError(403, 'Administrator access is required.');
    }
    return $admin;
}

function manageStudent(PDO $pdo, int $id, bool $active): void
{
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'student' FOR UPDATE");
        $query->execute([$id]);
        if (!$query->fetchColumn()) {
            throw new HttpError(404, 'Student account not found.');
        }
        $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ? AND role = 'student'")->execute([(int) $active, $id]);
        if (!$active) {
            $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$id]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function saveCategory(PDO $pdo, ?int $id, string $name, bool $active): void
{
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        throw new HttpError(422, 'Enter a category name between 2 and 80 characters.');
    }
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO categories (category_name, is_active) VALUES (?, ?)')->execute([$name, (int) $active]);
        } else {
            $pdo->prepare('UPDATE categories SET category_name = ?, is_active = ? WHERE category_id = ?')->execute([$name, (int) $active, $id]);
        }
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
            throw new HttpError(422, 'A category with this name already exists.');
        }
        throw $error;
    }
}

function reportDate(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2099-12-31') {
        throw new HttpError(400, 'Choose valid report dates between 2000 and 2099.');
    }
    return $date;
}

function adminReport(PDO $pdo, array $filters): array
{
    $start = reportDate($filters['from']);
    $end = reportDate($filters['to']);
    if ($end < $start || $start->diff($end)->days > 731) {
        throw new HttpError(400, 'Choose a date range of up to two years, with the start before the end.');
    }
    $parameters = [$filters['from'], $end->modify('+1 day')->format('Y-m-d')];
    $where = '';
    if ($filters['category'] !== '') {
        $where .= ' AND l.category_id = ?';
        $parameters[] = positiveId($filters, 'category');
    }
    if ($filters['type'] !== '') {
        if (!isset(LISTING_TYPES[$filters['type']])) {
            throw new HttpError(400, 'Choose a valid listing type.');
        }
        $where .= ' AND l.listing_type = ?';
        $parameters[] = $filters['type'];
    }
    $query = $pdo->prepare(
        "SELECT month, category_name, listing_type, SUM(listings) AS listings, SUM(requests) AS requests,
                SUM(cohort_completed) AS cohort_completed, SUM(completed) AS completed
         FROM (
             SELECT DATE_FORMAT(l.created_at, '%Y-%m') AS month, c.category_name, l.listing_type,
                    1 AS listings, 0 AS requests, 0 AS cohort_completed, 0 AS completed
             FROM listings l JOIN categories c ON c.category_id = l.category_id
             WHERE l.created_at >= ? AND l.created_at < ? $where
             UNION ALL
             SELECT DATE_FORMAT(r.created_at, '%Y-%m'), c.category_name, l.listing_type,
                    0, 1, (r.status = 'completed'), 0
             FROM requests r JOIN listings l ON l.listing_id = r.target_listing_id JOIN categories c ON c.category_id = l.category_id
             WHERE r.created_at >= ? AND r.created_at < ? $where
             UNION ALL
             SELECT DATE_FORMAT(r.completed_at, '%Y-%m'), c.category_name, l.listing_type, 0, 0, 0, 1
             FROM requests r JOIN listings l ON l.listing_id = r.target_listing_id JOIN categories c ON c.category_id = l.category_id
             WHERE r.status = 'completed' AND r.completed_at >= ? AND r.completed_at < ? $where
         ) report GROUP BY month, category_name, listing_type ORDER BY month DESC, category_name, listing_type"
    );
    $query->execute([...$parameters, ...$parameters, ...$parameters]);
    return $query->fetchAll();
}

function completionRate(array $row): string
{
    return (int) $row['requests'] === 0 ? 'N/A' : number_format(100 * $row['cohort_completed'] / $row['requests'], 1) . '%';
}

function exportAdminReport(array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ecotrade-report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Month (UTC)', 'Category', 'Type', 'Listings created', 'Requests created', 'Created requests now completed', 'Completed in month', 'Completion rate'], ',', '"', '');
    foreach ($rows as $row) {
        $category = preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $row['category_name']) ? "'" . $row['category_name'] : $row['category_name'];
        fputcsv($output, [$row['month'], $category, LISTING_TYPES[$row['listing_type']], $row['listings'], $row['requests'], $row['cohort_completed'], $row['completed'], completionRate($row)], ',', '"', '');
    }
    fclose($output);
}

function runAdmin(PDO $pdo, string $page, string $method, ?array $viewer): void
{
    requireAdmin($viewer);
    if ($method === 'POST' && !in_array(input($_POST, 'active'), ['0', '1'], true)) {
        throw new HttpError(422, 'Choose an active or inactive status.');
    }
    if ($page === 'admin-users') {
        if ($method === 'POST') {
            manageStudent($pdo, positiveId($_POST), input($_POST, 'active') === '1');
            redirect(url($page, ['notice' => 'admin_saved']));
        }
        $q = mb_substr(input($_GET, 'q'), 0, 120);
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $number = max(1, min(10000, (int) input($_GET, 'p')));
        $offset = ($number - 1) * 30;
        $query = $pdo->prepare("SELECT user_id, full_name, email, role, is_active FROM users WHERE full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' ORDER BY user_id LIMIT 31 OFFSET $offset");
        $query->execute([$needle, $needle]);
        renderPage('Users', 'admin', ['page' => $page, 'accounts' => $query->fetchAll(), 'q' => $q, 'number' => $number]);
        return;
    }
    if ($page === 'admin-categories' && $method === 'POST') {
        $id = input($_POST, 'id') === '' ? null : positiveId($_POST);
        saveCategory($pdo, $id, input($_POST, 'name'), input($_POST, 'active') === '1');
        redirect(url($page, ['notice' => 'admin_saved']));
    }
    if ($page === 'admin-categories') {
        $categories = $pdo->query('SELECT category_id, category_name, is_active FROM categories ORDER BY category_name')->fetchAll();
        renderPage('Categories', 'admin', compact('page', 'categories'));
        return;
    }
    if ($page === 'admin-reports' || $page === 'admin-export') {
        $filters = [
            'from' => input($_GET, 'from') ?: gmdate('Y-01-01'),
            'to' => input($_GET, 'to') ?: gmdate('Y-m-d'),
            'category' => input($_GET, 'category'),
            'type' => input($_GET, 'type'),
        ];
        $rows = adminReport($pdo, $filters);
        if ($page === 'admin-export') {
            exportAdminReport($rows);
            return;
        }
        $categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
        renderPage('Reports', 'admin', compact('page', 'filters', 'rows', 'categories'));
        return;
    }
    $counts = $pdo->query("SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM listings) AS listings,
        (SELECT COUNT(*) FROM requests) AS requests, (SELECT COUNT(*) FROM requests WHERE status = 'completed') AS completed")->fetch();
    renderPage('Admin dashboard', 'admin', compact('page', 'counts'));
}
