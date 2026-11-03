-- Run inside the ecotrade database. Existing tables and records are preserved.
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    preferred_language ENUM('en', 'ms') NOT NULL DEFAULT 'ms',
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT ck_users_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(80) NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT uq_categories_name UNIQUE (category_name),
    CONSTRAINT ck_categories_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listings (
    listing_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    source_language ENUM('en', 'ms') NOT NULL DEFAULT 'ms',
    item_condition ENUM('new', 'like_new', 'good', 'fair') NOT NULL DEFAULT 'good',
    listing_type ENUM('donation', 'exchange') NOT NULL,
    status ENUM('available', 'reserved', 'completed', 'archived') NOT NULL DEFAULT 'available',
    image_s3_key VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_owner FOREIGN KEY (owner_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE RESTRICT,
    INDEX idx_listings_browse (status, category_id, listing_type),
    INDEX idx_listings_owner (owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
    request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    target_listing_id INT UNSIGNED NOT NULL,
    offered_listing_id INT UNSIGNED NULL,
    status ENUM('pending', 'accepted', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    exchange_note TEXT NULL,
    note_language ENUM('en', 'ms') NOT NULL DEFAULT 'ms',
    pickup_location VARCHAR(255) NULL,
    pickup_at DATETIME NULL,
    pickup_instructions TEXT NULL,
    pickup_language ENUM('en', 'ms') NOT NULL DEFAULT 'ms',
    owner_confirmed_at DATETIME NULL,
    requester_confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_requests_requester FOREIGN KEY (requester_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_target FOREIGN KEY (target_listing_id) REFERENCES listings (listing_id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_offer FOREIGN KEY (offered_listing_id) REFERENCES listings (listing_id) ON DELETE RESTRICT,
    CONSTRAINT ck_requests_different_items CHECK (offered_listing_id IS NULL OR offered_listing_id <> target_listing_id),
    INDEX idx_requests_requester (requester_id, status),
    INDEX idx_requests_reporting (status, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
    translation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    field_name ENUM('description', 'exchange_note', 'pickup_instructions') NOT NULL,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_language ENUM('en', 'ms') NOT NULL,
    translated_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_translations_listing FOREIGN KEY (listing_id) REFERENCES listings (listing_id) ON DELETE RESTRICT,
    CONSTRAINT fk_translations_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT,
    CONSTRAINT ck_translations_one_parent CHECK (
        (listing_id IS NOT NULL AND request_id IS NULL AND field_name = 'description') OR
        (listing_id IS NULL AND request_id IS NOT NULL AND field_name IN ('exchange_note', 'pickup_instructions'))
    ),
    CONSTRAINT uq_translations_listing UNIQUE (listing_id, field_name, target_language),
    CONSTRAINT uq_translations_request UNIQUE (request_id, field_name, target_language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT ck_sessions_expiry CHECK (expires_at > created_at),
    INDEX idx_sessions_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
