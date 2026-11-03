<?php
declare(strict_types=1);

function runApplication(PDO $pdo): void
{
    global $user, $csrfToken;
    $page = input($_GET, 'page') ?: 'browse';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true) || ($method === 'POST' && !in_array($page, ['login', 'register', 'create', 'edit', 'archive', 'logout'], true)) || ($page === 'logout' && $method !== 'POST')) {
        header('Allow: ' . ($page === 'logout' ? 'POST' : 'GET'));
        throw new HttpError(405, 'Kaedah permintaan ini tidak dibenarkan.');
    }
    if ($method === 'POST') {
        requireCsrf($csrfToken);
    }

    if ($page === 'logout') {
        requireUser($user);
        endLogin($pdo);
        redirect(url('login', ['notice' => 'logged_out']));
    }

    if ($page === 'register' || $page === 'login') {
        if ($user !== null) {
            redirect(url('mine'));
        }
        $values = ['full_name' => input($_POST, 'full_name'), 'email' => mb_strtolower(input($_POST, 'email'))];
        $errors = [];
        if ($method === 'POST') {
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if ($page === 'register') {
                $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
                $errors = registerUser($pdo, $values + ['password' => $password, 'password_confirmation' => $confirmation]);
                if ($errors === []) {
                    redirect(url('login', ['notice' => 'registered']));
                }
                http_response_code(422);
            } else {
                $account = authenticate($pdo, $values['email'], $password);
                if ($account !== null) {
                    startLogin($pdo, (int) $account['user_id']);
                    redirect(url('mine', ['notice' => 'logged_in']));
                }
                $errors['login'] = 'Email atau password tidak betul, atau akaun tidak aktif.';
                http_response_code(422);
            }
        }
        renderPage($page === 'register' ? 'Daftar akaun' : 'Log masuk', 'auth', compact('page', 'values', 'errors'));
        return;
    }

    if ($page === 'create' || $page === 'edit') {
        $owner = requireUser($user);
        $listing = null;
        if ($page === 'edit') {
            $listing = listingById($pdo, positiveId($_GET));
            requireOwner($listing, $owner);
            requireEditable($listing);
        }
        $values = $method === 'POST' ? listingValues($_POST) : ($listing ?? [
            'title' => '', 'description' => '', 'category_id' => '', 'item_condition' => 'good',
            'listing_type' => 'donation', 'source_language' => 'ms',
        ]);
        $errors = [];
        if ($method === 'POST') {
            $errors = validateListing($pdo, $values);
            if ($errors === []) {
                if ($listing !== null) {
                    changeListing($pdo, (int) $listing['listing_id'], $owner, $values);
                    redirect(url('view', ['id' => $listing['listing_id'], 'notice' => 'updated']));
                }
                $id = createListing($pdo, $values, $owner);
                redirect(url('view', ['id' => $id, 'notice' => 'created']));
            }
            http_response_code(422);
        }
        $categories = activeCategories($pdo);
        renderPage($page === 'create' ? 'Tambah barang' : 'Edit barang', 'listing-form', compact('page', 'values', 'errors', 'categories', 'listing'));
        return;
    }

    if ($page === 'view' || $page === 'archive') {
        $listing = listingById($pdo, positiveId($_GET));
        if ($page === 'archive') {
            $owner = requireUser($user);
            requireOwner($listing, $owner);
            requireEditable($listing);
            if ($method === 'POST') {
                changeListing($pdo, (int) $listing['listing_id'], $owner, null);
                redirect(url('mine', ['notice' => 'archived']));
            }
        } elseif ($listing['status'] === 'archived' && ($user === null || (int) $listing['owner_id'] !== (int) $user['user_id'])) {
            throw new HttpError(404, 'Barang ini tidak ditemui.');
        }
        renderPage($page === 'archive' ? 'Arkibkan barang' : $listing['title'], 'listing-detail', compact('page', 'listing'));
        return;
    }

    if ($page === 'browse' || $page === 'mine') {
        $owner = $page === 'mine' ? requireUser($user) : null;
        $filters = [];
        foreach (['q', 'category_id', 'item_condition', 'listing_type', 'status'] as $field) {
            $filters[$field] = input($_GET, $field);
        }
        if (mb_strlen($filters['q']) > 150 || ($filters['category_id'] !== '' && filter_var($filters['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) || ($filters['item_condition'] !== '' && !array_key_exists($filters['item_condition'], CONDITIONS)) || ($filters['listing_type'] !== '' && !array_key_exists($filters['listing_type'], LISTING_TYPES)) || ($filters['status'] !== '' && !array_key_exists($filters['status'], LISTING_STATUSES))) {
            throw new HttpError(400, 'Penapis carian tidak sah. Kembali ke senarai barang dan pilih semula.');
        }
        $filters['p'] = max(1, min(100000, (int) input($_GET, 'p')));
        $results = searchListings($pdo, $filters, $owner);
        $categories = activeCategories($pdo);
        renderPage($page === 'mine' ? 'Barang saya' : 'Cari bahan belajar', 'browse', compact('page', 'filters', 'results', 'categories'));
        return;
    }

    throw new HttpError(404, 'Halaman ini tidak ditemui.');
}
