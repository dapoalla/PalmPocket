<?php
declare(strict_types=1);
?>
<div class="login-wrap">
<div class="login-card card">
<div class="brand">Palm<span>Pocket</span></div>
<h2>Sign in</h2>
<form method="post">
<input type="hidden" name="action" value="login">
<?= csrf_field() ?>
<label>Username</label>
<input name="username" autocomplete="username" required autofocus>
<label>Password</label>
<input name="password" type="password" autocomplete="current-password" required>
<button class="btn" type="submit">Login</button>
</form>
<p class="hint">Track expenses, purses, budgets, and wishlists in one calm place.</p>
</div>
</div>
