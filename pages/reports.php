<?php
declare(strict_types=1);

$reportMonth = $_GET['month'] ?? date('Y-m');
$reportView = $_GET['view'] ?? 'month';
$report = report_data($reportMonth);
$reportChartData = array_slice($report['byCategory'], 0, 6, true);
$reportChartMax = max($reportChartData ?: [1]);
?>
<div class="top"><div class="hello"><h1>Reports</h1><p>Understand where the month went.</p></div></div>
<section class="grid">
<div class="card full">
<h2>Expense report</h2>
<form method="get" class="row">
<input type="hidden" name="page" value="reports">
<div><label>Month</label><input type="month" name="month" value="<?= h($reportMonth) ?>"></div>
<div><label>Group</label><select name="view">
<option value="month" <?= $reportView === 'month' ? 'selected' : '' ?>>Monthly categories</option>
<option value="week" <?= $reportView === 'week' ? 'selected' : '' ?>>Weekly categories</option>
</select></div>
<button class="btn" type="submit">View report</button>
</form>
<div style="margin-top:12px" class="row">
<a class="btn ghost" href="?page=reports&month=<?= h($reportMonth) ?>&view=<?= h($reportView) ?>&export=list">Export full list</a>
<a class="btn ghost" href="?page=reports&month=<?= h($reportMonth) ?>&view=<?= h($reportView) ?>&export=summary">Export summary</a>
</div>
<div class="item" style="margin-top:12px;padding:16px;background:rgba(139,92,246,.1)"><span class="muted">Total for <?= h($reportMonth) ?></span><strong style="color:var(--red);display:block;font-size:24px;margin-top:6px"><?= money($report['total'], $currency) ?></strong></div>
</div>

<div class="card full">
<h2>Chart by category</h2>
<?php if ($reportChartData): ?>
<div class="chart-bars">
<?php foreach ($reportChartData as $catId => $amount): $barH = max(8, ($amount / $reportChartMax) * 140); ?>
<div>
<div class="bar-fill" title="<?= h(find_name($categories, $catId)) ?>: <?= money($amount, $currency) ?>" style="height:<?= $barH ?>px;background:linear-gradient(180deg,<?= h(category_color($categories, $catId)) ?>,rgba(255,255,255,.15))"></div>
<div><?= h(substr(find_name($categories, $catId), 0, 10)) ?></div>
</div>
<?php endforeach; ?>
</div>
<div class="list" style="margin-top:14px">
<?php foreach ($reportChartData as $catId => $amount): ?>
<div class="item"><span><i class="dot" style="background:<?= h(category_color($categories, $catId)) ?>"></i><?= h(find_name($categories, $catId)) ?></span><strong><?= money($amount, $currency) ?></strong></div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="muted">No data for chart yet. Add expenses for this month.</p>
<?php endif; ?>
</div>

<div class="card full table">
<h2>Expenses in <?= h($reportMonth) ?></h2>
<table>
<thead><tr><th>Date</th><th>Category</th><th>Purse</th><th>User</th><th>Note</th><th>Amount</th></tr></thead>
<tbody>
<?php foreach ($report['expenses'] as $tx): ?>
<tr><td><?= h($tx['date']) ?></td><td><?= h(find_name($categories, $tx['category_id'])) ?></td><td><?= h(find_name($purses, $tx['purse_id'])) ?></td><td><?= h(find_name($users, $tx['user_id'])) ?></td><td><?= h($tx['note']) ?></td><td><?= money($tx['amount'], $currency) ?></td></tr>
<?php endforeach; if (!$report['expenses']): ?><tr><td colspan="6" class="muted">No expenses for this month.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="card full">
<h2><?= $reportView === 'week' ? 'Weekly category totals' : 'Category totals' ?></h2>
<div class="list">
<?php if ($reportView === 'week'):
    foreach ($report['byWeek'] as $week => $cats): ?>
<div class="card"><h3>Week <?= h((string)$week) ?></h3><?php foreach ($cats as $catId => $amount): ?><div class="item"><span><?= h(find_name($categories, $catId)) ?></span><strong><?= money($amount, $currency) ?></strong></div><?php endforeach; ?></div>
<?php endforeach;
else:
    foreach ($report['byCategory'] as $catId => $amount): ?>
<div class="item"><span><i class="dot" style="background:<?= h(category_color($categories, $catId)) ?>"></i><?= h(find_name($categories, $catId)) ?></span><strong><?= money($amount, $currency) ?></strong></div>
<?php endforeach;
endif;
if (!$report['byCategory']): ?><p class="muted">Nothing to group yet.</p><?php endif; ?>
</div>
</div>
</section>
