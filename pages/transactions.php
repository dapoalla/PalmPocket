<?php
declare(strict_types=1);

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'type' => $_GET['tx_type'] ?? 'all',
    'category' => $_GET['tx_category'] ?? 'all',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'duration' => $_GET['duration'] ?? 'all',
];
$perPage = 25;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$result = filtered_transactions($filters, $perPage, ($pageNum - 1) * $perPage);
$filteredTx = $result['rows'];
$totalCount = $result['total'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
?>
<div class="top"><div class="hello"><h1>History</h1><p>Search, filter, and manage every transaction.</p></div></div>
<div class="card full">
<h2>Search &amp; filter</h2>
<form method="get" class="row">
<input type="hidden" name="page" value="transactions">
<div><label>Search</label><input name="search" value="<?= h($filters['search']) ?>" placeholder="e.g. lunch, food..."></div>
<div><label>Type</label><select name="tx_type">
<option value="all" <?= $filters['type'] === 'all' ? 'selected' : '' ?>>All</option>
<option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
<option value="inflow" <?= $filters['type'] === 'inflow' ? 'selected' : '' ?>>Inflow</option>
</select></div>
<div><label>Category</label><select name="tx_category">
<option value="all" <?= $filters['category'] === 'all' ? 'selected' : '' ?>>All categories</option>
<?php foreach ($categories as $c): ?><option value="<?= h((string)$c['id']) ?>" <?= $filters['category'] === (string)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
</select></div>
<div><label>Duration</label><select name="duration">
<option value="all" <?= $filters['duration'] === 'all' ? 'selected' : '' ?>>All time</option>
<option value="today" <?= $filters['duration'] === 'today' ? 'selected' : '' ?>>Today</option>
<option value="week" <?= $filters['duration'] === 'week' ? 'selected' : '' ?>>This week</option>
<option value="month" <?= $filters['duration'] === 'month' ? 'selected' : '' ?>>This month</option>
<option value="year" <?= $filters['duration'] === 'year' ? 'selected' : '' ?>>This year</option>
</select></div>
<div><label>From</label><input name="date_from" type="date" value="<?= h($filters['date_from']) ?>"></div>
<div><label>To</label><input name="date_to" type="date" value="<?= h($filters['date_to']) ?>"></div>
<button class="btn" type="submit">Filter</button>
<a class="btn ghost" href="?page=transactions">Reset</a>
</form>
</div>
<div class="card full table">
<h2>Transactions (<?= (int)$totalCount ?>)</h2>
<table>
<thead><tr><th>Actions</th><th>Date</th><th>Type</th><th>Category</th><th>Purse</th><th>Qty</th><th>Note</th><th>Amount</th></tr></thead>
<tbody>
<?php foreach ($filteredTx as $tx): ?>
<tr>
<td style="white-space:nowrap">
<div class="stack-actions">
<a class="btn ghost sm" href="?page=edit&id=<?= (int)$tx['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="transaction_id" value="<?= (int)$tx['id'] ?>"><?= csrf_field() ?><button class="btn red sm" type="submit">Delete</button></form>
</div>
</td>
<td><?= h($tx['date']) ?></td>
<td><span class="pill"><?= h($tx['type']) ?><?= !empty($tx['is_loan']) ? ' (loan)' : '' ?></span></td>
<td><?= h(find_name($categories, $tx['category_id'])) ?></td>
<td><?= h(find_name($purses, $tx['purse_id'])) ?></td>
<td><?= (int)($tx['quantity'] ?? 1) ?></td>
<td><?= h($tx['note']) ?></td>
<td><?= money($tx['amount'], $currency) ?></td>
</tr>
<?php endforeach; if (!$filteredTx): ?><tr><td colspan="8" class="muted">No transactions match your filters.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php if ($pageNum > 1): ?><a href="<?= h(current_page_query(['p' => $pageNum - 1])) ?>">&larr; Prev</a><?php endif; ?>
<span class="current">Page <?= $pageNum ?> of <?= $totalPages ?></span>
<?php if ($pageNum < $totalPages): ?><a href="<?= h(current_page_query(['p' => $pageNum + 1])) ?>">Next &rarr;</a><?php endif; ?>
</div>
<?php endif; ?>
