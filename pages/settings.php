<?php
declare(strict_types=1);
?>
<div class="top"><div class="hello"><h1>Settings</h1><p>Tune categories, purses, people, and notifications.</p></div></div>
<section class="grid">

<div class="card wide">
<h2>Change your password</h2>
<form method="post">
<input type="hidden" name="action" value="change_password">
<?= csrf_field() ?>
<div class="row">
<div><label>Current password</label><input name="current_password" type="password" required></div>
<div><label>New password</label><input name="new_password" type="password" required></div>
</div>
<div class="row"><div><label>Confirm new password</label><input name="confirm_password" type="password" required></div></div>
<button class="btn" type="submit">Update password</button>
</form>
</div>

<div class="card wide">
<h2>App &amp; email settings</h2>
<form method="post">
<input type="hidden" name="action" value="save_settings">
<?= csrf_field() ?>
<div class="row">
<div><label>Currency</label><input name="currency" value="<?= h($settings['currency']) ?>"></div>
<div><label>Alert email</label><input name="alert_email" type="email" value="<?= h($settings['alert_email']) ?>"></div>
</div>
<div class="row">
<div><label>Monthly expense threshold</label><input name="monthly_expense_threshold" type="number" step="0.01" value="<?= h((string)$settings['monthly_expense_threshold']) ?>"></div>
<div><label>Low purse threshold</label><input name="low_purse_threshold" type="number" step="0.01" value="<?= h((string)$settings['low_purse_threshold']) ?>"></div>
</div>
<div class="row">
<div><label>Default theme</label><select name="theme">
<option value="dark" <?= ($settings['theme'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark</option>
<option value="light" <?= ($settings['theme'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Light</option>
</select></div>
<div style="display:flex;align-items:flex-end;padding-bottom:12px"><label class="field-inline"><input type="checkbox" name="threshold_emails_enabled" <?= $settings['threshold_emails_enabled'] ? 'checked' : '' ?>> Enable threshold emails</label></div>
</div>
<div class="row">
<div><label>SMTP host</label><input name="smtp_host" placeholder="mail.yourdomain.com" value="<?= h($settings['smtp_host']) ?>"></div>
<div><label>SMTP user</label><input name="smtp_user" value="<?= h($settings['smtp_user']) ?>"></div>
</div>
<div class="row">
<div><label>SMTP password</label><input name="smtp_pass" type="password" value="<?= h($settings['smtp_pass']) ?>"></div>
<div><label>SMTP port</label><input name="smtp_port" type="number" value="<?= h((string)$settings['smtp_port']) ?>"></div>
</div>
<div class="row">
<div><label>Security</label><select name="smtp_secure">
<option value="tls" <?= $settings['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS 587</option>
<option value="ssl" <?= $settings['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL 465</option>
</select></div>
<div><label>From name</label><input name="smtp_from_name" value="<?= h($settings['smtp_from_name']) ?>"></div>
</div>
<label>From email</label><input name="smtp_from_email" value="<?= h($settings['smtp_from_email']) ?>">
<label>Dashboard quotes, one per line</label>
<textarea name="quotes" rows="4"><?= h($settings['quotes']) ?></textarea>
<button class="btn" type="submit">Save settings</button>
</form>
<form method="post" style="margin-top:14px">
<input type="hidden" name="action" value="test_email">
<?= csrf_field() ?>
<label>Send test email to</label><input name="test_to" type="email" value="<?= h($settings['alert_email']) ?>">
<button class="btn ghost" type="submit">Test email</button>
</form>
</div>

<div class="card">
<h2>Add category</h2>
<form method="post">
<input type="hidden" name="action" value="add_category">
<?= csrf_field() ?>
<label>Name</label><input name="name" required>
<label>Color</label><input name="color" type="color" value="#8b5cf6">
<button class="btn" type="submit">Add</button>
</form>
</div>

<div class="card full">
<h2>Monthly category budgets</h2>
<form method="post">
<input type="hidden" name="action" value="save_budgets">
<?= csrf_field() ?>
<div class="list">
<?php $budgets = all_budgets(); foreach ($categories as $category): ?>
<div class="item"><span><i class="dot" style="background:<?= h($category['color']) ?>"></i><?= h($category['name']) ?></span><input style="max-width:180px" name="budgets[<?= (int)$category['id'] ?>]" type="number" step="0.01" min="0" value="<?= h((string)($budgets[(string)$category['id']] ?? '')) ?>" placeholder="0.00"></div>
<?php endforeach; ?>
</div>
<button class="btn" style="margin-top:12px" type="submit">Save budgets</button>
</form>
</div>

<div class="card full">
<h2>Edit categories</h2>
<form method="post">
<input type="hidden" name="action" value="update_categories">
<?= csrf_field() ?>
<div class="list">
<?php foreach ($categories as $category): ?>
<div class="item"><input name="categories[<?= (int)$category['id'] ?>][name]" value="<?= h($category['name']) ?>"><input style="max-width:90px" name="categories[<?= (int)$category['id'] ?>][color]" type="color" value="<?= h($category['color']) ?>"></div>
<?php endforeach; ?>
</div>
<button class="btn" style="margin-top:12px" type="submit">Update categories</button>
</form>
<div class="list" style="margin-top:12px">
<?php foreach ($categories as $category): ?>
<form method="post" class="item" onsubmit="return confirm('Delete this category?')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><?= csrf_field() ?><span><?= h($category['name']) ?></span><button class="btn red sm">Delete</button></form>
<?php endforeach; ?>
</div>
</div>

<div class="card full">
<h2>Purses</h2>
<div class="list">
<?php foreach ($purses as $purse): ?>
<form method="post" class="item">
<input type="hidden" name="action" value="edit_purse">
<input type="hidden" name="purse_id" value="<?= (int)$purse['id'] ?>">
<?= csrf_field() ?>
<input name="name" value="<?= h($purse['name']) ?>" style="max-width:200px">
<input name="balance" type="number" step="0.01" value="<?= h((string)$purse['balance']) ?>" style="max-width:140px">
<div class="stack-actions">
<button class="btn ghost sm" type="submit">Save</button>
</div>
</form>
<?php endforeach; ?>
</div>
<div class="list" style="margin-top:12px">
<?php foreach ($purses as $purse): ?>
<form method="post" class="item" onsubmit="return confirm('Delete this purse?')"><input type="hidden" name="action" value="delete_purse"><input type="hidden" name="purse_id" value="<?= (int)$purse['id'] ?>"><?= csrf_field() ?><span><?= h($purse['name']) ?> &middot; <?= money($purse['balance'], $currency) ?></span><button class="btn red sm">Delete</button></form>
<?php endforeach; ?>
</div>
<h3 style="margin-top:16px">Add purse</h3>
<form method="post">
<input type="hidden" name="action" value="add_purse">
<?= csrf_field() ?>
<div class="row">
<div><label>Name</label><input name="name" required></div>
<div><label>Opening balance</label><input name="balance" type="number" step="0.01" value="0"></div>
</div>
<button class="btn" type="submit">Add</button>
</form>
</div>

<div class="card full">
<h2>Backup &amp; restore</h2>
<div class="actions"><a class="btn ghost" href="?action=backup">Download backup</a></div>
<form method="post" enctype="multipart/form-data" style="margin-top:14px" onsubmit="return confirm('Restore will replace current data. Continue?')">
<input type="hidden" name="action" value="restore_backup">
<?= csrf_field() ?>
<label>Restore JSON backup</label>
<input name="backup_file" type="file" accept="application/json,.json" required>
<button class="btn red" type="submit">Restore backup</button>
</form>
</div>

<div class="card full">
<h2>Add user</h2>
<form method="post">
<input type="hidden" name="action" value="add_user">
<?= csrf_field() ?>
<div class="row">
<div><label>Name</label><input name="name" required></div>
<div><label>Username</label><input name="username" required></div>
</div>
<div class="row">
<div><label>Password</label><input name="password" type="password" required></div>
<div><label>Email</label><input name="email" type="email"></div>
</div>
<label>Role</label><input name="role" value="Member">
<button class="btn" type="submit">Add</button>
</form>
</div>

<div class="card full">
<h2>Manage users</h2>
<div class="list">
<?php foreach ($users as $u): ?>
<div class="item" style="flex-direction:column;align-items:stretch;gap:12px;padding:16px">
<div style="display:flex;align-items:center;gap:12px;justify-content:space-between">
<div><strong><?= h($u['name']) ?></strong> <span class="muted"><?= h($u['role'] ?: 'Member') ?></span></div>
<?php if (count($users) > 1): ?>
<form method="post" onsubmit="return confirm('Remove this user?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><?= csrf_field() ?><button class="btn red sm">Remove</button></form>
<?php endif; ?>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="post" style="display:flex;gap:8px;flex:1;align-items:center;min-width:200px">
<input type="hidden" name="action" value="update_username"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><?= csrf_field() ?>
<label style="text-transform:none;font-weight:600">Username</label>
<input name="username" value="<?= h($u['username'] ?? '') ?>" style="max-width:140px">
<button class="btn ghost sm" type="submit">Update</button>
</form>
<form method="post" style="display:flex;gap:8px;flex:1;align-items:center;min-width:200px">
<input type="hidden" name="action" value="update_user_password"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><?= csrf_field() ?>
<label style="text-transform:none;font-weight:600">Password</label>
<input name="new_password" type="password" placeholder="New password" style="max-width:140px">
<button class="btn ghost sm" type="submit">Update</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="card full">
<h2>Current setup</h2>
<p class="muted">Users: <?= count($users) ?> &middot; Categories: <?= count($categories) ?> &middot; Purses: <?= count($purses) ?>. Data is stored in a MySQL database via PDO.</p>
</div>

</section>
