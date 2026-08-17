<?php
declare(strict_types=1);
// Run daily from cron, e.g.: php /path/to/palmpocket/cron_thresholds.php

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/repo.php';

if (!app_is_installed()) {
    fwrite(STDERR, "PalmPocket is not set up yet. Run setup.php first.\n");
    exit(1);
}

$pdo = get_pdo();
$settings = get_settings();

if (!$settings['threshold_emails_enabled']) {
    echo "Threshold emails disabled.\n";
    exit;
}

$currency = $settings['currency'] ?: '$';
$month = date('Y-m');
$today = date('Y-m-d');
$monthly = month_totals($month);
$monthlyExpense = $monthly['expense'];

$alerts = [];

$expenseThreshold = $settings['monthly_expense_threshold'];
if ($expenseThreshold > 0 && $monthlyExpense >= $expenseThreshold) {
    $alerts['monthly_expense'] = [
        'ref' => $month,
        'message' => 'Your expenses for ' . $month . ' reached ' . $currency . number_format($monthlyExpense, 2) . ', crossing your threshold of ' . $currency . number_format($expenseThreshold, 2) . '.',
    ];
}

$lowPurseThreshold = $settings['low_purse_threshold'];
foreach (all_purses() as $purse) {
    if ($lowPurseThreshold > 0 && $purse['balance'] <= $lowPurseThreshold) {
        $alerts['low_purse_' . $purse['id']] = [
            'ref' => (string)$purse['id'],
            'kind' => 'low_purse',
            'message' => 'Your purse "' . $purse['name'] . '" is low at ' . $currency . number_format($purse['balance'], 2) . '.',
        ];
    }
}

$checkStmt = $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE kind = ? AND ref_key = ? AND sent_on = ?');
$insertStmt = $pdo->prepare('INSERT INTO notifications (kind, ref_key, sent_on) VALUES (?,?,?)');

$sentAny = false;
foreach ($alerts as $key => $alert) {
    $kind = $alert['kind'] ?? $key;
    $checkStmt->execute([$kind, $alert['ref'], $today]);
    if ((int)$checkStmt->fetch()['c'] > 0) {
        continue;
    }
    $sentAny = true;
    $html = get_email_template('PalmPocket Alert', '<p>' . h($alert['message']) . '</p><p>Open your dashboard to review your spending and purse balances.</p>');
    $result = send_mail_smtp($settings, $settings['alert_email'], 'PalmPocket threshold alert', $html);
    $insertStmt->execute([$kind, $alert['ref'], $today]);
    echo $alert['message'] . ' ' . $result['message'] . "\n";
}

if (!$sentAny) {
    echo "No threshold alerts due.\n";
}
