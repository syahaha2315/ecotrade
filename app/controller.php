<?php
declare(strict_types=1);

function runApplication(PDO $pdo): void
{
    global $user, $csrfToken;
    $page = input($_GET, 'page') ?: 'browse';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $postPages = ['login', 'register', 'create', 'edit', 'archive', 'delete', 'logout', 'chat', 'profile', 'admin-users', 'admin-categories'];
    if (!in_array($method, ['GET', 'POST'], true) || ($method === 'POST' && !in_array($page, $postPages, true)) || ($page === 'logout' && $method !== 'POST')) {
        header('Allow: ' . ($page === 'logout' ? 'POST' : 'GET'));
        throw new HttpError(405, 'This request method is not allowed.');
    }
    if ($method === 'POST') {
        requireCsrf($csrfToken);
    }

    if (in_array($page, ['admin', 'admin-users', 'admin-categories', 'admin-reports', 'admin-export'], true)) {
        runAdmin($pdo, $page, $method, $user);
        return;
    }
    if ($page === 'photo') {
        serveListingPhoto($pdo, positiveId($_GET), $user);
        return;
    }
    if ($page === 'avatar') {
        serveAvatar($pdo, positiveId($_GET), $user);
        return;
    }
    if ($page === 'profile') {
        runProfile($pdo, $method, $user);
        return;
    }
    if (in_array($page, ['inbox', 'chat'], true)) {
        runChat($pdo, $page, $method, $user);
        return;
    }
    if (in_array($page, ['google', 'google-callback'], true)) {
        runGoogle($pdo, $page, $user);
        return;
    }

    if ($page === 'logout') {
        requireUser($user);
        endLogin($pdo);
        redirect(url('login', ['notice' => 'logged_out']));
    }

    if ($page === 'register' || $page === 'login') {
        if ($user !== null) {
            redirect(url($user['role'] === 'admin' ? 'admin' : 'mine'));
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
            } elseif (!validAccountEmail($values['email'])) {
                $errors['email'] = 'Enter a valid email address, such as name@gmail.com.';
                http_response_code(422);
            } else {
                $account = authenticate($pdo, $values['email'], $password);
                if ($account !== null) {
                    startLogin($pdo, (int) $account['user_id']);
                    redirect(url($account['role'] === 'admin' ? 'admin' : 'mine', ['notice' => 'logged_in']));
                }
                $errors['login'] = 'The email or password is incorrect, or the account is inactive.';
                http_response_code(422);
            }
        }
        renderPage($page === 'register' ? 'Create account' : 'Sign in', 'auth', compact('page', 'values', 'errors'));
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
            'listing_type' => 'donation', 'source_language' => 'en',
        ]);
        $errors = [];
        if ($method === 'POST') {
            $errors = validateListing($pdo, $values);
            $photos = [];
            try {
                $photos = uploadedPhotos();
            } catch (HttpError $error) {
                $errors['photos'] = $error->getMessage();
            }
            if ($errors === []) {
                if ($listing !== null) {
                    changeListing($pdo, (int) $listing['listing_id'], $owner, $values, $photos, input($_POST, 'remove_photos') === '1');
                    redirect(url('view', ['id' => $listing['listing_id'], 'notice' => 'updated']));
                }
                $id = createListing($pdo, $values, $owner, $photos);
                redirect(url('view', ['id' => $id, 'notice' => 'created']));
            }
            http_response_code(422);
        }
        $categories = activeCategories($pdo);
        $savedPhotos = $listing ? listingPhotos($pdo, (int) $listing['listing_id']) : [];
        renderPage($page === 'create' ? 'Add listing' : 'Edit listing', 'listing-form', compact('page', 'values', 'errors', 'categories', 'listing', 'savedPhotos'));
        return;
    }

    if (in_array($page, ['view', 'archive', 'delete'], true)) {
        $listing = listingById($pdo, positiveId($_GET));
        if ($page !== 'view') {
            $owner = requireUser($user);
            requireOwner($listing, $owner);
            requireEditable($listing);
            if ($method === 'POST') {
                if ($page === 'delete') {
                    deleteListing($pdo, (int) $listing['listing_id'], $owner);
                    redirect(url('mine', ['notice' => 'deleted']));
                }
                changeListing($pdo, (int) $listing['listing_id'], $owner, null);
                redirect(url('mine', ['notice' => 'archived']));
            }
        } elseif (!canViewListing($listing, $user)) {
            throw new HttpError(404, 'Listing not found.');
        }
        $photos = $page === 'view' ? listingPhotos($pdo, (int) $listing['listing_id']) : [];
        $title = $page === 'view' ? $listing['title'] : ucfirst($page) . ' listing';
        renderPage($title, 'listing-detail', compact('page', 'listing', 'photos'));
        return;
    }

    if ($page === 'browse' || $page === 'mine') {
        $owner = $page === 'mine' ? requireUser($user) : null;
        $filters = [];
        foreach (['q', 'category_id', 'item_condition', 'listing_type', 'status'] as $field) {
            $filters[$field] = input($_GET, $field);
        }
        if (mb_strlen($filters['q']) > 150 || ($filters['category_id'] !== '' && filter_var($filters['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) || ($filters['item_condition'] !== '' && !array_key_exists($filters['item_condition'], CONDITIONS)) || ($filters['listing_type'] !== '' && !array_key_exists($filters['listing_type'], LISTING_TYPES)) || ($filters['status'] !== '' && !array_key_exists($filters['status'], LISTING_STATUSES))) {
            throw new HttpError(400, 'Choose valid search filters and try again.');
        }
        $filters['p'] = max(1, min(100000, (int) input($_GET, 'p')));
        $results = searchListings($pdo, $filters, $owner);
        $categories = activeCategories($pdo);
        renderPage($page === 'mine' ? 'My listings' : 'Browse listings', 'browse', compact('page', 'filters', 'results', 'categories'));
        return;
    }

    throw new HttpError(404, 'Page not found.');
}
