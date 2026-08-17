<?php
declare(strict_types=1);

/* ---------- Simple lookups ---------- */

function all_categories(): array {
    return get_pdo()->query('SELECT * FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function all_purses(): array {
    $rows = get_pdo()->query('SELECT * FROM purses ORDER BY id ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['balance'] = (float)$row['balance'];
    }
    return $rows;
}

function all_users(): array {
    return get_pdo()->query('SELECT id, name, email, role, username FROM users ORDER BY id ASC')->fetchAll();
}

function get_settings(): array {
    $row = get_pdo()->query('SELECT * FROM settings WHERE id = 1')->fetch();
    if (!$row) {
        throw new RuntimeException('Settings row missing. Re-run the installer.');
    }
    $row['threshold_emails_enabled'] = (bool)$row['threshold_emails_enabled'];
    $row['monthly_expense_threshold'] = (float)$row['monthly_expense_threshold'];
    $row['low_purse_threshold'] = (float)$row['low_purse_threshold'];
    return $row;
}

function all_budgets(): array {
    $rows = get_pdo()->query('SELECT category_id, amount FROM budgets')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[(string)$row['category_id']] = (float)$row['amount'];
    }
    return $out;
}

function all_wishlist(): array {
    $rows = get_pdo()->query('SELECT * FROM wishlist ORDER BY purchased ASC, sort_order ASC, id ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['amount'] = (float)$row['amount'];
        $row['purchased'] = (bool)$row['purchased'];
    }
    return $rows;
}

/* ---------- Transactions ---------- */

function get_transaction(int $id): ?array {
    $stmt = get_pdo()->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['amount'] = (float)$row['amount'];
        $row['date'] = $row['txn_date'];
    }
    return $row ?: null;
}

function adjust_purse_balance(PDO $pdo, ?int $purseId, float $delta): void {
    if (!$purseId || $delta == 0.0) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE purses SET balance = balance + ? WHERE id = ?');
    $stmt->execute([$delta, $purseId]);
}

function insert_transaction(array $tx): int {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO transactions (type, amount, quantity, is_loan, category_id, purse_id, user_id, note, txn_date) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $tx['type'], $tx['amount'], $tx['quantity'], $tx['is_loan'] ? 1 : 0,
            $tx['category_id'] ?: null, $tx['purse_id'] ?: null, $tx['user_id'] ?: null,
            $tx['note'], $tx['date'],
        ]);
        $id = (int)$pdo->lastInsertId();
        $delta = $tx['type'] === 'inflow' ? (float)$tx['amount'] : -(float)$tx['amount'];
        adjust_purse_balance($pdo, $tx['purse_id'] ?: null, $delta);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function update_transaction(int $id, array $tx): void {
    $pdo = get_pdo();
    $old = get_transaction($id);
    if (!$old) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE transactions SET amount=?, quantity=?, is_loan=?, category_id=?, purse_id=?, user_id=?, note=?, txn_date=? WHERE id=?');
        $stmt->execute([
            $tx['amount'], $tx['quantity'], $tx['is_loan'] ? 1 : 0,
            $tx['category_id'] ?: null, $tx['purse_id'] ?: null, $tx['user_id'] ?: null,
            $tx['note'], $tx['date'], $id,
        ]);
        // reverse the old purse effect, then apply the new one
        $oldDelta = $old['type'] === 'inflow' ? -(float)$old['amount'] : (float)$old['amount'];
        adjust_purse_balance($pdo, $old['purse_id'] ? (int)$old['purse_id'] : null, $oldDelta);
        $newDelta = $old['type'] === 'inflow' ? (float)$tx['amount'] : -(float)$tx['amount'];
        adjust_purse_balance($pdo, $tx['purse_id'] ?: null, $newDelta);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_transaction(int $id): void {
    $pdo = get_pdo();
    $tx = get_transaction($id);
    if (!$tx) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
        $stmt->execute([$id]);
        $delta = $tx['type'] === 'inflow' ? -(float)$tx['amount'] : (float)$tx['amount'];
        adjust_purse_balance($pdo, $tx['purse_id'] ? (int)$tx['purse_id'] : null, $delta);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function month_totals(string $month): array {
    $pdo = get_pdo();
    $expense = 0.0;
    $inflow = 0.0;
    $loans = 0.0;
    $byCategory = [];

    $stmt = $pdo->prepare("SELECT category_id, SUM(amount) total FROM transactions WHERE type='expense' AND DATE_FORMAT(txn_date,'%Y-%m')=? GROUP BY category_id");
    $stmt->execute([$month]);
    foreach ($stmt->fetchAll() as $row) {
        $amount = (float)$row['total'];
        $expense += $amount;
        $byCategory[(string)$row['category_id']] = $amount;
    }
    arsort($byCategory);

    $stmt = $pdo->prepare("SELECT is_loan, SUM(amount) total FROM transactions WHERE type='inflow' AND DATE_FORMAT(txn_date,'%Y-%m')=? GROUP BY is_loan");
    $stmt->execute([$month]);
    foreach ($stmt->fetchAll() as $row) {
        $amount = (float)$row['total'];
        $inflow += $amount;
        if ((int)$row['is_loan'] === 1) {
            $loans += $amount;
        }
    }

    return compact('expense', 'inflow', 'loans', 'byCategory');
}

function total_purse_balance(): float {
    return (float)(get_pdo()->query('SELECT COALESCE(SUM(balance),0) t FROM purses')->fetch()['t']);
}

function recent_transactions(int $limit = 8): array {
    $stmt = get_pdo()->prepare('SELECT * FROM transactions ORDER BY txn_date DESC, id DESC LIMIT ' . (int)$limit);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['amount'] = (float)$row['amount'];
        $row['date'] = $row['txn_date'];
    }
    return $rows;
}

function filtered_transactions(array $filters, int $limit, int $offset): array {
    $where = [];
    $params = [];

    if (($filters['search'] ?? '') !== '') {
        $where[] = '(t.note LIKE ? OR c.name LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (($filters['type'] ?? 'all') !== 'all') {
        $where[] = 't.type = ?';
        $params[] = $filters['type'];
    }
    if (($filters['category'] ?? 'all') !== 'all') {
        $where[] = 't.category_id = ?';
        $params[] = (int)$filters['category'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 't.txn_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (($filters['date_to'] ?? '') !== '') {
        $where[] = 't.txn_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (($filters['duration'] ?? 'all') === 'today') {
        $where[] = 't.txn_date = CURDATE()';
    } elseif (($filters['duration'] ?? 'all') === 'week') {
        $where[] = 'YEARWEEK(t.txn_date, 3) = YEARWEEK(CURDATE(), 3)';
    } elseif (($filters['duration'] ?? 'all') === 'month') {
        $where[] = "DATE_FORMAT(t.txn_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')";
    } elseif (($filters['duration'] ?? 'all') === 'year') {
        $where[] = 'YEAR(t.txn_date) = YEAR(CURDATE())';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $pdo = get_pdo();

    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM transactions t LEFT JOIN categories c ON c.id = t.category_id $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['c'];

    $stmt = $pdo->prepare("SELECT t.* FROM transactions t LEFT JOIN categories c ON c.id = t.category_id $whereSql ORDER BY t.txn_date DESC, t.id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['amount'] = (float)$row['amount'];
        $row['date'] = $row['txn_date'];
    }

    return ['rows' => $rows, 'total' => $total];
}

function report_data(string $month): array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE type='expense' AND DATE_FORMAT(txn_date,'%Y-%m')=? ORDER BY txn_date ASC, id ASC");
    $stmt->execute([$month]);
    $expenses = $stmt->fetchAll();
    foreach ($expenses as &$row) {
        $row['amount'] = (float)$row['amount'];
        $row['date'] = $row['txn_date'];
    }
    unset($row);

    $byCategory = [];
    $byWeek = [];
    foreach ($expenses as $tx) {
        $byCategory[(string)$tx['category_id']] = ($byCategory[(string)$tx['category_id']] ?? 0) + $tx['amount'];
        $week = date('W', strtotime($tx['date']));
        $byWeek[$week][(string)$tx['category_id']] = ($byWeek[$week][(string)$tx['category_id']] ?? 0) + $tx['amount'];
    }
    arsort($byCategory);
    ksort($byWeek);
    foreach ($byWeek as &$cats) {
        arsort($cats);
    }
    unset($cats);

    $total = array_sum($byCategory);
    return compact('expenses', 'byCategory', 'byWeek', 'total');
}

/* ---------- Categories ---------- */

function insert_category(string $name, string $color): int {
    $pdo = get_pdo();
    $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 n FROM categories')->fetch()['n'];
    $stmt = $pdo->prepare('INSERT INTO categories (name, color, sort_order) VALUES (?,?,?)');
    $stmt->execute([$name, $color ?: '#8b5cf6', $order]);
    return (int)$pdo->lastInsertId();
}

function update_categories_bulk(array $categories): void {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('UPDATE categories SET name=?, color=? WHERE id=?');
    foreach ($categories as $id => $fields) {
        $name = trim((string)($fields['name'] ?? ''));
        $color = (string)($fields['color'] ?? '#8b5cf6');
        if ($name === '') {
            continue;
        }
        $stmt->execute([$name, $color, (int)$id]);
    }
}

function category_in_use(int $id): bool {
    $stmt = get_pdo()->prepare('SELECT COUNT(*) c FROM transactions WHERE category_id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetch()['c'] > 0;
}

function delete_category(int $id): bool {
    if (category_in_use($id)) {
        return false;
    }
    $pdo = get_pdo();
    $pdo->prepare('DELETE FROM budgets WHERE category_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    return true;
}

/* ---------- Purses ---------- */

function insert_purse(string $name, float $balance): int {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO purses (name, balance) VALUES (?,?)');
    $stmt->execute([$name, $balance]);
    return (int)$pdo->lastInsertId();
}

function update_purse(int $id, string $name, float $balance): void {
    $stmt = get_pdo()->prepare('UPDATE purses SET name=?, balance=? WHERE id=?');
    $stmt->execute([$name, $balance, $id]);
}

function purse_in_use(int $id): bool {
    $stmt = get_pdo()->prepare('SELECT COUNT(*) c FROM transactions WHERE purse_id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetch()['c'] > 0;
}

function delete_purse(int $id): bool {
    if (purse_in_use($id)) {
        return false;
    }
    get_pdo()->prepare('DELETE FROM purses WHERE id = ?')->execute([$id]);
    return true;
}

/* ---------- Users ---------- */

function insert_user(string $name, string $username, string $password, string $email, string $role): bool {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ((int)$stmt->fetch()['c'] > 0) {
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, email, role, username, password) VALUES (?,?,?,?,?)');
    $stmt->execute([$name, $email, $role ?: 'Member', $username, password_hash($password, PASSWORD_DEFAULT)]);
    return true;
}

function username_taken(string $username, int $exceptUserId = 0): bool {
    $stmt = get_pdo()->prepare('SELECT COUNT(*) c FROM users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $exceptUserId]);
    return (int)$stmt->fetch()['c'] > 0;
}

function update_username(int $userId, string $username): void {
    $stmt = get_pdo()->prepare('UPDATE users SET username=? WHERE id=?');
    $stmt->execute([$username, $userId]);
}

function update_user_password(int $userId, string $password): void {
    $stmt = get_pdo()->prepare('UPDATE users SET password=? WHERE id=?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
}

function user_count(): int {
    return (int)get_pdo()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
}

function delete_user(int $id): bool {
    if (user_count() <= 1) {
        return false;
    }
    get_pdo()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    return true;
}

/* ---------- Budgets ---------- */

function save_budgets(array $budgets): void {
    $pdo = get_pdo();
    $pdo->exec('DELETE FROM budgets');
    $stmt = $pdo->prepare('INSERT INTO budgets (category_id, amount) VALUES (?,?)');
    foreach ($budgets as $categoryId => $amount) {
        $amount = max(0, (float)$amount);
        if ($amount > 0) {
            $stmt->execute([(int)$categoryId, $amount]);
        }
    }
}

/* ---------- Settings ---------- */

function save_settings(array $settings): void {
    $stmt = get_pdo()->prepare(
        'UPDATE settings SET currency=?, monthly_expense_threshold=?, low_purse_threshold=?, alert_email=?, smtp_host=?, smtp_user=?, smtp_pass=?, smtp_port=?, smtp_secure=?, smtp_from_email=?, smtp_from_name=?, threshold_emails_enabled=?, theme=?, quotes=? WHERE id=1'
    );
    $stmt->execute([
        $settings['currency'], $settings['monthly_expense_threshold'], $settings['low_purse_threshold'],
        $settings['alert_email'], $settings['smtp_host'], $settings['smtp_user'], $settings['smtp_pass'],
        $settings['smtp_port'], $settings['smtp_secure'], $settings['smtp_from_email'], $settings['smtp_from_name'],
        $settings['threshold_emails_enabled'] ? 1 : 0, $settings['theme'] ?? 'dark', $settings['quotes'],
    ]);
}

/* ---------- Wishlist ---------- */

function insert_wishlist(string $name, float $amount): int {
    $pdo = get_pdo();
    $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 n FROM wishlist')->fetch()['n'];
    $stmt = $pdo->prepare('INSERT INTO wishlist (name, amount, purchased, sort_order, date_added) VALUES (?,?,0,?,CURDATE())');
    $stmt->execute([$name, $amount, $order]);
    return (int)$pdo->lastInsertId();
}

function update_wishlist(int $id, string $name, float $amount): void {
    $stmt = get_pdo()->prepare('UPDATE wishlist SET name=?, amount=? WHERE id=?');
    $stmt->execute([$name, $amount, $id]);
}

function delete_wishlist(int $id): void {
    get_pdo()->prepare('DELETE FROM wishlist WHERE id = ?')->execute([$id]);
}

function toggle_wishlist_purchased(int $id): bool {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT purchased FROM wishlist WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $newState = $row['purchased'] ? 0 : 1;
    $pdo->prepare('UPDATE wishlist SET purchased=? WHERE id=?')->execute([$newState, $id]);
    return (bool)$newState;
}

function move_wishlist(int $id, string $direction): void {
    $pdo = get_pdo();
    $items = $pdo->query('SELECT id, sort_order FROM wishlist WHERE purchased = 0 ORDER BY sort_order ASC, id ASC')->fetchAll();
    $index = null;
    foreach ($items as $i => $item) {
        if ((int)$item['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapWith < 0 || $swapWith >= count($items)) {
        return;
    }
    $a = $items[$index];
    $b = $items[$swapWith];
    $stmt = $pdo->prepare('UPDATE wishlist SET sort_order=? WHERE id=?');
    $stmt->execute([$b['sort_order'], $a['id']]);
    $stmt->execute([$a['sort_order'], $b['id']]);
}

/* ---------- Backup / restore (portable .sql dump) ---------- */

const BACKUP_TABLES = [
    'users' => ['id', 'name', 'email', 'role', 'username', 'password', 'created_at'],
    'categories' => ['id', 'name', 'color', 'sort_order', 'created_at'],
    'purses' => ['id', 'name', 'balance', 'created_at'],
    'settings' => ['id', 'currency', 'monthly_expense_threshold', 'low_purse_threshold', 'alert_email', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure', 'smtp_from_email', 'smtp_from_name', 'threshold_emails_enabled', 'theme', 'quotes'],
    'transactions' => ['id', 'type', 'amount', 'quantity', 'is_loan', 'category_id', 'purse_id', 'user_id', 'note', 'txn_date', 'created_at'],
    'budgets' => ['category_id', 'amount'],
    'wishlist' => ['id', 'name', 'amount', 'purchased', 'sort_order', 'date_added'],
    'notifications' => ['id', 'kind', 'ref_key', 'sent_on', 'created_at'],
];

function sql_dump_value(PDO $pdo, $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return $pdo->quote((string)$value);
}

function sql_dump_table(PDO $pdo, string $table, array $columns): string {
    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    $out = "-- Table: {$table}\nTRUNCATE TABLE `{$table}`;\n";
    if ($rows) {
        $columnList = '`' . implode('`, `', $columns) . '`';
        $valueLines = array_map(
            fn($row) => '(' . implode(', ', array_map(fn($c) => sql_dump_value($pdo, $row[$c]), $columns)) . ')',
            $rows
        );
        $out .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $valueLines) . ";\n";
        if (in_array('id', $columns, true)) {
            $maxId = (int)max(array_column($rows, 'id'));
            $out .= "ALTER TABLE `{$table}` AUTO_INCREMENT = " . ($maxId + 1) . ";\n";
        }
    }
    return $out . "\n";
}

function export_backup_sql(): string {
    $pdo = get_pdo();
    $sql = "-- PalmPocket SQL backup\n-- Generated " . date('c') . "\n\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach (BACKUP_TABLES as $table => $columns) {
        $sql .= sql_dump_table($pdo, $table, $columns);
    }
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

function import_backup_sql(string $sql): void {
    if (trim($sql) === '') {
        throw new InvalidArgumentException('The uploaded file is empty.');
    }
    $pdo = get_multi_statement_pdo();
    $pdo->exec($sql);
}
