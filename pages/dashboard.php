<?php
declare(strict_types=1);

$stats = month_totals(date('Y-m'));
$budgets = all_budgets();
$totalBudget = array_sum($budgets);
$balance = total_purse_balance();
$recent = recent_transactions(8);
$wishlist = all_wishlist();
$wishlistPending = array_filter($wishlist, fn($w) => !$w['purchased']);
$wishlistTotal = array_sum(array_map(fn($w) => $w['purchased'] ? 0 : $w['amount'], $wishlist));

$quotes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $settings['quotes'] ?? ''))));
$heroQuote = $quotes ? $quotes[array_rand($quotes)] : 'Money, but make it calm.';
?>
<div class="top">
<div class="hello"><h1><?= h($heroQuote) ?></h1><p>Mobile-first personal budget tracking with categories, purses, and clear goals.</p></div>
<div class="top-actions"><a class="btn" href="?page=add">+ Quick Add</a></div>
</div>

<section class="grid">
<div class="card tile"><b>Total Balance</b><strong><?= money($balance, $currency) ?></strong><span class="muted">Across <?= count($purses) ?> purses</span></div>
<div class="card tile income"><b>This Month Inflow</b><strong><?= money($stats['inflow'], $currency) ?></strong><span class="muted">Income and other money in</span></div>
<div class="card tile expense"><b>This Month Expenses</b><strong><?= money($stats['expense'], $currency) ?></strong><span class="muted">Tracked by category</span></div>
<div class="card tile income"><b>This Month Loans</b><strong><?= money($stats['loans'], $currency) ?></strong><span class="muted">Inflow marked as loan</span></div>
<div class="card tile"><b>Wishlist Total</b><strong><?= money($wishlistTotal, $currency) ?></strong><span class="muted"><?= count($wishlistPending) ?> items pending</span></div>
<div class="card tile"><b>Total Monthly Budget</b><strong><?= money($totalBudget, $currency) ?></strong><span class="muted">Sum of all category limits</span></div>

<div class="card quick">
<h2>Quick actions</h2>
<div class="actions">
<a class="btn" href="?page=add&type=expense">Expense</a>
<a class="btn ghost" href="?page=add&type=inflow">Inflow</a>
<a class="btn ghost" href="?page=settings">Categories</a>
<a class="btn ghost" href="?page=transactions">History</a>
</div>
</div>

<div class="card wide">
<h2>Expenses per category</h2>
<div class="list">
<?php $max = max($stats['byCategory'] ?: [1]); foreach ($stats['byCategory'] as $catId => $amount): $pct = $max > 0 ? ($amount / $max * 100) : 0; ?>
<div>
<div class="item"><span><i class="dot" style="background:<?= h(category_color($categories, $catId)) ?>"></i><?= h(find_name($categories, $catId)) ?></span><strong><?= money($amount, $currency) ?></strong></div>
<div class="bar"><i style="width:<?= $pct ?>%;background:<?= h(category_color($categories, $catId)) ?>"></i></div>
</div>
<?php endforeach; if (!$stats['byCategory']): ?><p class="muted">Add expenses to see category tiles.</p><?php endif; ?>
</div>
</div>

<div class="card full">
<h2>Category budgets</h2>
<div class="list">
<?php foreach ($categories as $category): $budget = (float)($budgets[(string)$category['id']] ?? 0); if ($budget <= 0) continue; $spent = (float)($stats['byCategory'][(string)$category['id']] ?? 0); $pct = $budget > 0 ? min(100, ($spent / $budget * 100)) : 0; $over = $spent > $budget; ?>
<div>
<div class="item"><span><i class="dot" style="background:<?= h($category['color']) ?>"></i><?= h($category['name']) ?></span><strong style="color:<?= $over ? 'var(--red)' : 'var(--green)' ?>"><?= money($spent, $currency) ?> / <?= money($budget, $currency) ?></strong></div>
<div class="bar"><i style="width:<?= $pct ?>%;background:<?= $over ? 'var(--red)' : h($category['color']) ?>"></i></div>
</div>
<?php endforeach; if (empty($budgets)): ?><p class="muted">Set category budgets in Settings to track monthly limits.</p><?php endif; ?>
</div>
</div>

<div class="card full">
<h2>Recent entries</h2>
<div class="list">
<?php foreach ($recent as $tx): ?>
<div class="item">
<div><strong><?= h(find_name($categories, $tx['category_id'])) ?></strong><div class="muted"><?= h($tx['date']) ?> &middot; <?= h(find_name($purses, $tx['purse_id'])) ?><?= $tx['note'] ? ' &middot; ' . h($tx['note']) : '' ?></div></div>
<strong style="color:<?= $tx['type'] === 'expense' ? 'var(--red)' : 'var(--green)' ?>"><?= $tx['type'] === 'expense' ? '-' : '+' ?><?= money($tx['amount'], $currency) ?></strong>
</div>
<?php endforeach; if (!$recent): ?><p class="muted">No entries yet. Use quick add to start.</p><?php endif; ?>
</div>
</div>
</section>
