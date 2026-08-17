<?php
declare(strict_types=1);
$editTxId = (int)($_GET['id'] ?? 0);
$editTx = $editTxId ? get_transaction($editTxId) : null;
?>
<div class="top"><div class="hello"><h1>Edit Entry</h1><p>Update this transaction.</p></div></div>
<?php if (!$editTx): ?>
<section class="grid"><div class="card wide"><h2>Transaction not found</h2><p class="muted">The transaction you are trying to edit does not exist.</p><a class="btn" href="?page=transactions">Back to transactions</a></div></section>
<?php else: ?>
<section class="grid">
<div class="card wide">
<h2>Edit transaction</h2>
<form method="post">
<input type="hidden" name="action" value="edit_transaction">
<input type="hidden" name="transaction_id" value="<?= (int)$editTx['id'] ?>">
<?= csrf_field() ?>
<div class="row">
<div><label>Type</label><input type="text" value="<?= h($editTx['type']) ?>" disabled></div>
<div><label>Amount</label><input name="amount" type="number" step="0.01" min="0" value="<?= h((string)$editTx['amount']) ?>" required></div>
</div>
<div class="row">
<div id="qtyWrap" style="display:<?= $editTx['type'] === 'expense' ? 'block' : 'none' ?>"><label>Quantity</label><input name="quantity" type="number" min="1" value="<?= h((string)($editTx['quantity'] ?? 1)) ?>"></div>
<div id="loanWrap" style="display:<?= $editTx['type'] === 'inflow' ? 'flex' : 'none' ?>;align-items:center;padding-top:6px">
<label class="field-inline"><input type="checkbox" name="is_loan" value="1" <?= !empty($editTx['is_loan']) ? 'checked' : '' ?>> This is a loan</label>
</div>
</div>
<div class="row">
<div><label>Category</label><select name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= h((string)$c['id']) ?>" <?= (string)($editTx['category_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Purse</label><select name="purse_id" required><?php foreach ($purses as $p): ?><option value="<?= h((string)$p['id']) ?>" <?= (string)($editTx['purse_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?> &middot; <?= money($p['balance'], $currency) ?></option><?php endforeach; ?></select></div>
</div>
<div class="row">
<div><label>User</label><select name="user_id"><?php foreach ($users as $u): ?><option value="<?= h((string)$u['id']) ?>" <?= (string)($editTx['user_id'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Date</label><input name="date" type="date" value="<?= h($editTx['date']) ?>"></div>
</div>
<label>Note</label>
<textarea name="note" rows="3" placeholder="e.g. lunch with friends"><?= h($editTx['note'] ?? '') ?></textarea>
<div class="actions" style="margin-top:4px">
<button class="btn" type="submit">Update entry</button>
<a class="btn ghost" href="?page=transactions">Cancel</a>
</div>
</form>
</div>
</section>
<?php endif; ?>
