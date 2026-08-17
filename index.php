<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/includes/db.php';

if (!app_is_installed()) {
    header('Location: setup.php');
    exit;
}

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/repo.php';

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:40px;max-width:640px;margin:auto">'
        . '<h1>Database connection failed</h1><p>' . h($e->getMessage()) . '</p>'
        . '<p>Check <code>includes/config.local.php</code>, or delete it to re-run <a href="setup.php">setup</a>.</p></body>';
    exit;
}

$isLoggedIn = is_logged_in();
$currentUser = current_user();

if (!$isLoggedIn && ($_GET['page'] ?? 'dashboard') !== 'login') {
    redirect('?page=login');
}
if ($isLoggedIn && ($_GET['page'] ?? '') === 'login') {
    redirect('?page=dashboard');
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if (($_GET['action'] ?? '') === 'backup' && $isLoggedIn) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="palmpocket-backup-' . date('Y-m-d-His') . '.json"');
    echo json_encode(export_backup_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $needsAuth = $action !== 'login';

    if ($needsAuth && !$isLoggedIn) {
        redirect('?page=login');
    }

    if (!csrf_verify()) {
        flash('Security check failed. Please try again.');
        redirect(current_page_query());
    }

    try {
        if ($action === 'add_transaction') {
            $type = ($_POST['type'] ?? '') === 'inflow' ? 'inflow' : 'expense';
            $amount = max(0, (float)($_POST['amount'] ?? 0));
            if ($amount > 0) {
                insert_transaction([
                    'type' => $type,
                    'amount' => $amount,
                    'quantity' => max(1, (int)($_POST['quantity'] ?? 1)),
                    'is_loan' => $type === 'inflow' && isset($_POST['is_loan']),
                    'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                    'purse_id' => (int)($_POST['purse_id'] ?? 0) ?: null,
                    'user_id' => (int)($_POST['user_id'] ?? 0) ?: ($currentUser['id'] ?? null),
                    'note' => trim($_POST['note'] ?? ''),
                    'date' => $_POST['date'] ?: date('Y-m-d'),
                ]);
                flash('Transaction saved.');
            }
        }

        if ($action === 'edit_transaction') {
            $txId = (int)($_POST['transaction_id'] ?? 0);
            $existing = get_transaction($txId);
            if ($existing) {
                update_transaction($txId, [
                    'amount' => max(0, (float)($_POST['amount'] ?? $existing['amount'])),
                    'quantity' => max(1, (int)($_POST['quantity'] ?? $existing['quantity'])),
                    'is_loan' => $existing['type'] === 'inflow' && isset($_POST['is_loan']),
                    'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                    'purse_id' => (int)($_POST['purse_id'] ?? 0) ?: null,
                    'user_id' => (int)($_POST['user_id'] ?? 0) ?: null,
                    'note' => trim($_POST['note'] ?? $existing['note']),
                    'date' => $_POST['date'] ?: $existing['date'],
                ]);
                flash('Transaction updated.');
            }
        }

        if ($action === 'delete_transaction') {
            delete_transaction((int)($_POST['transaction_id'] ?? 0));
            flash('Transaction deleted.');
        }

        if ($action === 'add_category') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                insert_category($name, $_POST['color'] ?: '#8b5cf6');
                flash('Category added.');
            }
        }

        if ($action === 'update_categories') {
            update_categories_bulk($_POST['categories'] ?? []);
            flash('Categories updated.');
        }

        if ($action === 'delete_category') {
            $ok = delete_category((int)($_POST['category_id'] ?? 0));
            flash($ok ? 'Category deleted.' : 'Category is used by transactions and cannot be deleted.');
        }

        if ($action === 'add_purse') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                insert_purse($name, (float)($_POST['balance'] ?? 0));
                flash('Purse added.');
            }
        }

        if ($action === 'edit_purse') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                update_purse((int)($_POST['purse_id'] ?? 0), $name, (float)($_POST['balance'] ?? 0));
                flash('Purse updated.');
            }
        }

        if ($action === 'delete_purse') {
            $ok = delete_purse((int)($_POST['purse_id'] ?? 0));
            flash($ok ? 'Purse deleted.' : 'Purse is used by transactions and cannot be deleted.');
        }

        if ($action === 'add_user') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($name !== '' && $username !== '' && strlen($password) >= 4) {
                $ok = insert_user($name, $username, $password, trim($_POST['email'] ?? ''), trim($_POST['role'] ?? 'Member'));
                flash($ok ? 'User added.' : 'Username already taken.');
            } else {
                flash('Please provide name, username, and password (min 4 chars).');
            }
        }

        if ($action === 'delete_user') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            if ($targetId === (int)($currentUser['id'] ?? 0)) {
                flash('You cannot delete your own account while logged in.');
            } else {
                $ok = delete_user($targetId);
                flash($ok ? 'User removed.' : 'At least one user account must remain.');
            }
        }

        if ($action === 'save_budgets') {
            save_budgets($_POST['budgets'] ?? []);
            flash('Budgets saved.');
        }

        if ($action === 'save_settings') {
            save_settings([
                'currency' => trim($_POST['currency'] ?? '$') ?: '$',
                'monthly_expense_threshold' => (float)($_POST['monthly_expense_threshold'] ?? 0),
                'low_purse_threshold' => (float)($_POST['low_purse_threshold'] ?? 0),
                'alert_email' => trim($_POST['alert_email'] ?? ''),
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_user' => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass' => (string)($_POST['smtp_pass'] ?? ''),
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_secure' => trim($_POST['smtp_secure'] ?? 'tls'),
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name' => trim($_POST['smtp_from_name'] ?? 'PalmPocket'),
                'threshold_emails_enabled' => isset($_POST['threshold_emails_enabled']),
                'theme' => in_array($_POST['theme'] ?? 'dark', ['dark', 'light'], true) ? $_POST['theme'] : 'dark',
                'quotes' => $_POST['quotes'] ?? '',
            ]);
            flash('Settings saved.');
        }

        if ($action === 'restore_backup') {
            if (isset($_FILES['backup_file']) && is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
                $restored = json_decode((string)file_get_contents($_FILES['backup_file']['tmp_name']), true);
                if (is_array($restored) && isset($restored['categories'], $restored['transactions'], $restored['settings'])) {
                    require __DIR__ . '/includes/importer.php';
                    import_legacy_json($pdo, $restored);
                    flash('Backup restored.');
                } else {
                    flash('Invalid backup file.');
                }
            }
        }

        if ($action === 'test_email') {
            $settings = get_settings();
            $content = '<p>Your PalmPocket SMTP/mail setup is working.</p><p>Please mark this sender as trusted to improve deliverability.</p>';
            $result = send_mail_smtp($settings, trim($_POST['test_to'] ?? $settings['alert_email']), 'PalmPocket test email', get_email_template('Test Email', $content));
            flash($result['message']);
        }

        if ($action === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $user = attempt_login($username, $password);
            if ($user) {
                log_in_user($user);
                flash('Welcome back, ' . $user['name'] . '!');
                redirect('?page=dashboard');
            } else {
                flash('Invalid username or password.');
            }
        }

        if ($action === 'logout') {
            log_out_user();
            redirect('?page=login');
        }

        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($new !== $confirm || strlen($new) < 4) {
                flash('New password too short or passwords do not match.');
            } else {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->execute([$currentUser['id']]);
                $hash = $stmt->fetch()['password'] ?? '';
                if (password_verify($current, $hash)) {
                    update_user_password((int)$currentUser['id'], $new);
                    flash('Password changed successfully.');
                } else {
                    flash('Current password is incorrect.');
                }
            }
        }

        if ($action === 'update_user_password') {
            $newPassword = $_POST['new_password'] ?? '';
            if (strlen($newPassword) < 4) {
                flash('Password must be at least 4 characters.');
            } else {
                update_user_password((int)($_POST['user_id'] ?? 0), $newPassword);
                flash('User password updated.');
            }
        }

        if ($action === 'update_username') {
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $newUsername = trim($_POST['username'] ?? '');
            if (strlen($newUsername) < 3) {
                flash('Username must be at least 3 characters.');
            } elseif (username_taken($newUsername, $targetUserId)) {
                flash('Username already taken.');
            } else {
                update_username($targetUserId, $newUsername);
                flash('Username updated.');
            }
        }

        if ($action === 'add_wishlist') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                insert_wishlist($name, max(0, (float)($_POST['amount'] ?? 0)));
                flash('Wishlist item added.');
            }
        }

        if ($action === 'edit_wishlist') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                update_wishlist((int)($_POST['wish_id'] ?? 0), $name, max(0, (float)($_POST['amount'] ?? 0)));
                flash('Wishlist item updated.');
            }
        }

        if ($action === 'delete_wishlist') {
            delete_wishlist((int)($_POST['wish_id'] ?? 0));
            flash('Wishlist item deleted.');
        }

        if ($action === 'toggle_wishlist_purchased') {
            $purchased = toggle_wishlist_purchased((int)($_POST['wish_id'] ?? 0));
            flash($purchased ? 'Item marked as purchased.' : 'Item marked as not purchased.');
        }

        if ($action === 'move_wishlist_up') {
            move_wishlist((int)($_POST['wish_id'] ?? 0), 'up');
        }

        if ($action === 'move_wishlist_down') {
            move_wishlist((int)($_POST['wish_id'] ?? 0), 'down');
        }
    } catch (Throwable $e) {
        flash('Something went wrong: ' . $e->getMessage());
    }

    redirect(current_page_query());
}

$page = $_GET['page'] ?? 'dashboard';
$categories = $isLoggedIn ? all_categories() : [];
$purses = $isLoggedIn ? all_purses() : [];
$users = $isLoggedIn ? all_users() : [];
$settings = $isLoggedIn ? get_settings() : ['currency' => '$', 'theme' => 'dark', 'quotes' => ''];
$currency = $settings['currency'];

if ($page === 'reports' && $isLoggedIn && in_array($_GET['export'] ?? '', ['list', 'summary'], true)) {
    require __DIR__ . '/pages/reports_export.php';
    exit;
}

$navItems = [
    'dashboard' => ['label' => 'Home', 'icon' => 'home'],
    'add' => ['label' => 'Add', 'icon' => 'plus'],
    'transactions' => ['label' => 'History', 'icon' => 'list'],
    'reports' => ['label' => 'Reports', 'icon' => 'chart'],
    'wishlist' => ['label' => 'Wishlist', 'icon' => 'heart'],
    'settings' => ['label' => 'Settings', 'icon' => 'gear'],
];

function nav_icon(string $name): string {
    $paths = [
        'home' => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9h12v-9"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'list' => '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
        'heart' => '<path d="M12 20s-7-4.4-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 5c-2.5 4.6-9.5 9-9.5 9Z"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#7c3aed">
<title>PalmPocket</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-theme="<?= h($settings['theme'] ?? 'dark') ?>">
<?php if ($isLoggedIn): ?>
<header class="topbar">
<div class="brand">Palm<span>Pocket</span></div>
<div class="top-actions">
<div class="theme-toggle"><button type="button" data-theme-btn="light">Light</button><button type="button" data-theme-btn="dark">Dark</button></div>
</div>
</header>
<?php endif; ?>
<div class="app <?= $isLoggedIn ? '' : 'no-sidebar' ?>">
<?php if ($isLoggedIn): ?>
<aside class="side">
<div class="brand">Palm<span>Pocket</span></div>
<nav class="nav">
<?php foreach ($navItems as $key => $item): ?>
<a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= $key ?>"><?= nav_icon($item['icon']) ?> <?= h($item['label'] === 'Home' ? 'Dashboard' : ($item['label'] === 'History' ? 'History' : $item['label'])) ?></a>
<?php endforeach; ?>
</nav>
<div class="side-footer">
<div class="theme-toggle" style="margin-bottom:14px;width:100%;justify-content:center"><button type="button" data-theme-btn="light">Light</button><button type="button" data-theme-btn="dark">Dark</button></div>
<div class="side-user">Logged in as<br><strong><?= h($currentUser['name'] ?? 'User') ?></strong></div>
<form method="post" style="margin-top:10px"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button class="btn ghost sm" style="width:100%">Logout</button></form>
</div>
</aside>
<?php endif; ?>
<main class="main">
<?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
<?php
$pageFile = __DIR__ . '/pages/' . preg_replace('/[^a-z_]/', '', $page) . '.php';
if ($isLoggedIn || $page === 'login') {
    if (is_file($pageFile)) {
        require $pageFile;
    } else {
        require __DIR__ . '/pages/not_found.php';
    }
}
?>
</main>
</div>
<?php if ($isLoggedIn): ?>
<a class="fab" href="?page=add" aria-label="Quick add">+</a>
<nav class="mobile-nav">
<?php foreach ($navItems as $key => $item): ?>
<a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= $key ?>"><?= nav_icon($item['icon']) ?><span><?= h($item['label']) ?></span></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
