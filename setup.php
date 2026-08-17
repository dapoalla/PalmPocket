<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

if (app_is_installed()) {
    redirect('index.php');
}

$step = (int)($_GET['step'] ?? 1);
if ($step === 2 && empty($_SESSION['setup_db'])) {
    $step = 1;
}

$error = '';

function run_schema(PDO $pdo): void {
    $sql = file_get_contents(__DIR__ . '/db/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    $pdo->exec("INSERT IGNORE INTO settings (id, currency, monthly_expense_threshold, low_purse_threshold, smtp_from_name, quotes) VALUES (1, '$', 100000, 5000, 'PalmPocket', 'Money, but make it calm.\nSpend softly, live loudly.\nYour money has a mood.')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postStep = (int)($_POST['step'] ?? 1);

    if ($postStep === 1) {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int)($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? 'palmpocket');
        $user = trim($_POST['user'] ?? 'root');
        $pass = (string)($_POST['pass'] ?? '');

        try {
            $server = get_server_pdo($host, $port, $user, $pass);
            $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            run_schema($pdo);
            $_SESSION['setup_db'] = compact('host', 'port', 'name', 'user', 'pass');
            redirect('setup.php?step=2');
        } catch (Throwable $e) {
            $error = 'Could not connect / set up database: ' . $e->getMessage();
            $step = 1;
        }
    }

    if ($postStep === 2 && !empty($_SESSION['setup_db'])) {
        $cfg = $_SESSION['setup_db'];
        $name = trim($_POST['admin_name'] ?? '');
        $username = trim($_POST['admin_username'] ?? '');
        $password = (string)($_POST['admin_password'] ?? '');
        $currency = trim($_POST['currency'] ?? '$') ?: '$';

        if ($name === '' || $username === '' || strlen($password) < 4) {
            $error = 'Please provide a name, username, and a password of at least 4 characters.';
            $step = 2;
        } else {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']),
                $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->prepare('INSERT INTO users (name, email, role, username, password) VALUES (?,?,?,?,?)')
                ->execute([$name, '', 'Owner', $username, password_hash($password, PASSWORD_DEFAULT)]);

            $defaultCategories = [
                ['Food', '#fb7185'], ['Transport', '#38bdf8'], ['Fuel', '#f97316'],
                ['Home', '#a78bfa'], ['Fun', '#f59e0b'], ['Salary', '#34d399'], ['Gifts', '#22c55e'],
            ];
            $catStmt = $pdo->prepare('INSERT INTO categories (name, color, sort_order) VALUES (?,?,?)');
            foreach ($defaultCategories as $i => [$catName, $color]) {
                $catStmt->execute([$catName, $color, $i]);
            }
            $purseStmt = $pdo->prepare('INSERT INTO purses (name, balance) VALUES (?,0)');
            foreach (['Cash', 'Bank', 'Mobile Wallet'] as $purseName) {
                $purseStmt->execute([$purseName]);
            }
            $pdo->prepare('UPDATE settings SET currency = ? WHERE id = 1')->execute([$currency]);

            write_db_config($cfg['host'], $cfg['port'], $cfg['name'], $cfg['user'], $cfg['pass']);
            unset($_SESSION['setup_db']);
            $step = 3;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up PalmPocket</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-theme="dark">
<div class="setup-wrap">
<div class="setup-card card">
<div class="brand">Palm<span>Pocket</span></div>
<div class="setup-steps">
<span class="<?= $step >= 1 ? 'on' : '' ?>">1. Database</span>
<span class="<?= $step >= 2 ? 'on' : '' ?>">2. Admin</span>
<span class="<?= $step >= 3 ? 'on' : '' ?>">3. Done</span>
</div>
<?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>

<?php if ($step === 1): ?>
<h2>Connect your database</h2>
<p class="muted">PalmPocket needs a MySQL/MariaDB database. On XAMPP the defaults below usually work as-is.</p>
<form method="post">
<input type="hidden" name="step" value="1">
<label>Host</label>
<input name="host" value="<?= h($_POST['host'] ?? 'localhost') ?>" required>
<div class="row">
<div><label>Port</label><input name="port" type="number" value="<?= h((string)($_POST['port'] ?? 3306)) ?>" required></div>
<div><label>Database name</label><input name="name" value="<?= h($_POST['name'] ?? 'palmpocket') ?>" required></div>
</div>
<div class="row">
<div><label>DB username</label><input name="user" value="<?= h($_POST['user'] ?? 'root') ?>" required></div>
<div><label>DB password</label><input name="pass" type="password" value=""></div>
</div>
<button class="btn" type="submit">Test connection & create database</button>
</form>

<?php elseif ($step === 2): ?>
<h2>Create your admin account</h2>
<form method="post">
<input type="hidden" name="step" value="2">
<label>Your name</label>
<input name="admin_name" required>
<label>Username</label>
<input name="admin_username" required>
<label>Password</label>
<input name="admin_password" type="password" minlength="4" required>
<label>Currency symbol</label>
<input name="currency" value="$" required>
<button class="btn" type="submit">Finish setup</button>
</form>

<?php elseif ($step === 3): ?>
<h2>You're all set</h2>
<p class="muted">Your database has been created with default categories and purses.</p>
<p class="muted">Have an existing PalmPocket backup (or an old flat-file export) you want to bring in? Log in, then use <strong>Settings &rarr; Backup &amp; Restore</strong> to upload it &mdash; no need to touch the installer again.</p>
<a class="btn" href="index.php">Go to login</a>
<?php endif; ?>

</div>
</div>
</body>
</html>
