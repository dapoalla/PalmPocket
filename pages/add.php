<?php
declare(strict_types=1);
$selectedType = $_GET['type'] ?? 'expense';
?>
<div class="top"><div class="hello"><h1>Add Entry</h1><p>Log an expense or inflow in a few taps.</p></div></div>
<section class="grid">
<div class="card wide">
<h2>Add expense or inflow</h2>
<form method="post">
<input type="hidden" name="action" value="add_transaction">
<?= csrf_field() ?>
<div class="row">
<div><label>Type</label>
<select name="type" id="txType">
<option value="expense" <?= $selectedType === 'expense' ? 'selected' : '' ?>>Expense</option>
<option value="inflow" <?= $selectedType === 'inflow' ? 'selected' : '' ?>>Inflow</option>
</select></div>
<div><label>Amount</label><input name="amount" type="number" step="0.01" min="0" required></div>
</div>
<div class="row">
<div id="qtyWrap"><label>Quantity</label><input name="quantity" type="number" min="1" value="1"></div>
<div id="loanWrap" style="align-items:center;padding-top:6px">
<label class="field-inline"><input type="checkbox" name="is_loan" value="1"> This is a loan</label>
</div>
</div>
<div class="row">
<div><label>Category</label><select name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= h((string)$c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Purse</label><select name="purse_id" required><?php foreach ($purses as $p): ?><option value="<?= h((string)$p['id']) ?>"><?= h($p['name']) ?> &middot; <?= money($p['balance'], $currency) ?></option><?php endforeach; ?></select></div>
</div>
<div class="row">
<div><label>User</label><select name="user_id"><?php foreach ($users as $u): ?><option value="<?= h((string)$u['id']) ?>" <?= (int)$u['id'] === (int)($currentUser['id'] ?? 0) ? 'selected' : '' ?>><?= h($u['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Date</label><input name="date" type="date" value="<?= date('Y-m-d') ?>"></div>
</div>
<label>Note</label>
<textarea name="note" rows="3" placeholder="e.g. lunch with friends"></textarea>
<button class="btn" type="submit">Save entry</button>
</form>
</div>
</section>
