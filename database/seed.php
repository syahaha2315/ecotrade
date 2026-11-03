<?php
declare(strict_types=1);

function seedDatabase(PDO $pdo): void
{
    // Existing accounts, passwords and listings are never reset by a setup rerun.
    $pdo->beginTransaction();
    try {
        $findUser = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $insertUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $users = [];
        foreach ([
            ['Admin Demo', 'admin@ecotrade.test', 'admin'],
            ['Student A Demo', 'student.a@ecotrade.test', 'student'],
            ['Student B Demo', 'student.b@ecotrade.test', 'student'],
        ] as [$name, $email, $role]) {
            $findUser->execute([$email]);
            $id = $findUser->fetchColumn();
            if ($id === false) {
                $insertUser->execute([$name, $email, password_hash('EcoTradeDemo!2026', PASSWORD_DEFAULT), $role]);
                $id = $pdo->lastInsertId();
            }
            $users[$email] = (int) $id;
        }
        $findCategory = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
        $insertCategory = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)');
        $categories = [];
        foreach (['Buku', 'Kalkulator', 'Learning Tools'] as $name) {
            $findCategory->execute([$name]);
            $id = $findCategory->fetchColumn();
            if ($id === false) {
                $insertCategory->execute([$name]);
                $id = $pdo->lastInsertId();
            }
            $categories[$name] = (int) $id;
        }
        $findListing = $pdo->prepare('SELECT listing_id FROM listings WHERE owner_id = ? AND title = ?');
        $insertListing = $pdo->prepare('INSERT INTO listings (owner_id, category_id, title, description, listing_type) VALUES (?, ?, ?, ?, ?)');
        foreach ([
            ['student.a@ecotrade.test', 'Buku', '[DEMO] Buku Asas Pengaturcaraan', 'Data ujian: buku terpakai untuk latihan EcoTrade.', 'donation'],
            ['student.b@ecotrade.test', 'Kalkulator', '[DEMO] Kalkulator Saintifik', 'Data ujian: kalkulator untuk latihan pertukaran.', 'exchange'],
        ] as [$email, $category, $title, $description, $type]) {
            $findListing->execute([$users[$email], $title]);
            if ($findListing->fetchColumn() === false) {
                $insertListing->execute([$users[$email], $categories[$category], $title, $description, $type]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
