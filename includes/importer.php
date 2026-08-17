<?php
declare(strict_types=1);

/**
 * Imports data from the legacy flat-file JSON format (or a JSON backup
 * exported by this app) into the database, replacing everything currently
 * stored. String ids from the old format (c_food, p_cash, ...) are mapped
 * to freshly generated auto-increment ids.
 */
function import_legacy_json(PDO $pdo, array $json): array {
    $stats = ['users' => 0, 'categories' => 0, 'purses' => 0, 'transactions' => 0, 'budgets' => 0, 'wishlist' => 0];

    $pdo->beginTransaction();
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['transactions', 'budgets', 'wishlist', 'notifications', 'purses', 'categories', 'users'] as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $userMap = [];
        $stmt = $pdo->prepare('INSERT INTO users (name, email, role, username, password) VALUES (?,?,?,?,?)');
        foreach (($json['users'] ?? []) as $u) {
            $password = $u['password'] ?? '';
            if ($password === '' || !preg_match('/^\$2y\$/', $password)) {
                $password = password_hash('password', PASSWORD_DEFAULT);
            }
            $stmt->execute([
                $u['name'] ?? 'User', $u['email'] ?? '', $u['role'] ?? 'Member',
                $u['username'] ?? ('user' . (count($userMap) + 1)), $password,
            ]);
            $userMap[(string)($u['id'] ?? '')] = (int)$pdo->lastInsertId();
            $stats['users']++;
        }

        $categoryMap = [];
        $stmt = $pdo->prepare('INSERT INTO categories (name, color, sort_order) VALUES (?,?,?)');
        $order = 0;
        foreach (($json['categories'] ?? []) as $c) {
            $stmt->execute([$c['name'] ?? 'Category', $c['color'] ?? '#8b5cf6', $order++]);
            $categoryMap[(string)($c['id'] ?? '')] = (int)$pdo->lastInsertId();
            $stats['categories']++;
        }

        $purseMap = [];
        $stmt = $pdo->prepare('INSERT INTO purses (name, balance) VALUES (?,?)');
        foreach (($json['purses'] ?? []) as $p) {
            $stmt->execute([$p['name'] ?? 'Purse', (float)($p['balance'] ?? 0)]);
            $purseMap[(string)($p['id'] ?? '')] = (int)$pdo->lastInsertId();
            $stats['purses']++;
        }

        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, quantity, is_loan, category_id, purse_id, user_id, note, txn_date) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach (($json['transactions'] ?? []) as $t) {
            $type = ($t['type'] ?? 'expense') === 'inflow' ? 'inflow' : 'expense';
            $stmt->execute([
                $type,
                max(0, (float)($t['amount'] ?? 0)),
                max(1, (int)($t['quantity'] ?? 1)),
                !empty($t['is_loan']) ? 1 : 0,
                $categoryMap[(string)($t['category_id'] ?? '')] ?? null,
                $purseMap[(string)($t['purse_id'] ?? '')] ?? null,
                $userMap[(string)($t['user_id'] ?? '')] ?? null,
                $t['note'] ?? '',
                $t['date'] ?? date('Y-m-d'),
            ]);
            $stats['transactions']++;
        }

        $stmt = $pdo->prepare('INSERT INTO budgets (category_id, amount) VALUES (?,?)');
        foreach (($json['budgets'] ?? []) as $catId => $amount) {
            if (isset($categoryMap[(string)$catId]) && (float)$amount > 0) {
                $stmt->execute([$categoryMap[(string)$catId], (float)$amount]);
                $stats['budgets']++;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO wishlist (name, amount, purchased, sort_order, date_added) VALUES (?,?,?,?,?)');
        $order = 0;
        foreach (($json['wishlist'] ?? []) as $w) {
            $stmt->execute([
                $w['name'] ?? 'Item', (float)($w['amount'] ?? 0), !empty($w['purchased']) ? 1 : 0,
                $order++, $w['date_added'] ?? date('Y-m-d'),
            ]);
            $stats['wishlist']++;
        }

        if (!empty($json['settings']) && is_array($json['settings'])) {
            $s = $json['settings'];
            $stmt = $pdo->prepare(
                'UPDATE settings SET currency=?, monthly_expense_threshold=?, low_purse_threshold=?, alert_email=?, smtp_host=?, smtp_user=?, smtp_pass=?, smtp_port=?, smtp_secure=?, smtp_from_email=?, smtp_from_name=?, threshold_emails_enabled=?, quotes=? WHERE id=1'
            );
            $stmt->execute([
                $s['currency'] ?? '$',
                (float)($s['monthly_expense_threshold'] ?? 0),
                (float)($s['low_purse_threshold'] ?? 0),
                $s['alert_email'] ?? '',
                $s['smtp_host'] ?? '',
                $s['smtp_user'] ?? '',
                $s['smtp_pass'] ?? '',
                (int)($s['smtp_port'] ?? 587),
                $s['smtp_secure'] ?? 'tls',
                $s['smtp_from_email'] ?? '',
                $s['smtp_from_name'] ?? 'PalmPocket',
                !empty($s['threshold_emails_enabled']) ? 1 : 0,
                $s['quotes'] ?? '',
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $stats;
}
