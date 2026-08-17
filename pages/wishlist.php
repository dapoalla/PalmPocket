<?php
declare(strict_types=1);

$wishlist = all_wishlist();
$activeWishlist = array_values(array_filter($wishlist, fn($w) => !$w['purchased']));
$purchasedWishlist = array_values(array_filter($wishlist, fn($w) => $w['purchased']));

$editWishId = (int)($_GET['edit'] ?? 0);
$editWish = null;
foreach ($wishlist as $w) {
    if ((int)$w['id'] === $editWishId) {
        $editWish = $w;
        break;
    }
}
$activeTotal = array_sum(array_map(fn($w) => $w['amount'], $activeWishlist));
$highPriorityCount = max(1, (int)ceil(count($activeWishlist) * 0.33));
$highPriorityTotal = array_sum(array_map(fn($w) => $w['amount'], array_slice($activeWishlist, 0, $highPriorityCount)));
?>
<div class="top"><div class="hello"><h1>Wishlist</h1><p>Prioritize what matters most next.</p></div></div>
<section class="grid">
<div class="card tile" style="grid-column:span 6"><b>Wishlist Total</b><strong><?= money($activeTotal, $currency) ?></strong><span class="muted"><?= count($activeWishlist) ?> pending &middot; <?= count($purchasedWishlist) ?> purchased</span></div>
<div class="card tile" style="grid-column:span 6;background:linear-gradient(135deg,rgba(34,197,94,.16),rgba(34,197,94,.03))"><b>High Priority</b><strong><?= money($highPriorityTotal, $currency) ?></strong><span class="muted">Top <?= $highPriorityCount ?> items</span></div>

<div class="card wide">
<h2><?= $editWish ? 'Edit wishlist item' : 'Add to wishlist' ?></h2>
<form method="post">
<input type="hidden" name="action" value="<?= $editWish ? 'edit_wishlist' : 'add_wishlist' ?>">
<?php if ($editWish): ?><input type="hidden" name="wish_id" value="<?= (int)$editWish['id'] ?>"><?php endif; ?>
<?= csrf_field() ?>
<div class="row">
<div><label>Item name</label><input name="name" value="<?= h($editWish['name'] ?? '') ?>" placeholder="e.g. New shoes" required></div>
<div><label>Amount</label><input name="amount" type="number" step="0.01" min="0" value="<?= h((string)($editWish['amount'] ?? '')) ?>" placeholder="0.00"></div>
</div>
<div class="actions">
<button class="btn" type="submit"><?= $editWish ? 'Update' : 'Add' ?> item</button>
<?php if ($editWish): ?><a class="btn ghost" href="?page=wishlist">Cancel</a><?php endif; ?>
</div>
</form>
</div>

<div class="card full">
<h2>Wishlist (<?= count($activeWishlist) ?>)</h2>
<div class="list">
<?php foreach ($activeWishlist as $index => $w): ?>
<div class="priority-item">
<div class="priority-bar" style="background:<?= h(wishlist_priority_color($index, max(1, count($activeWishlist)))) ?>"></div>
<div style="flex:1">
<strong><?= h($w['name']) ?></strong>
<div class="muted"><?= $w['amount'] > 0 ? money($w['amount'], $currency) : 'No price set' ?> &middot; <?= wishlist_priority_label($index, max(1, count($activeWishlist))) ?></div>
</div>
<div class="stack-actions">
<?php if ($index > 0): ?><form method="post"><input type="hidden" name="action" value="move_wishlist_up"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn ghost sm" title="Move up">&uarr;</button></form><?php endif; ?>
<?php if ($index < count($activeWishlist) - 1): ?><form method="post"><input type="hidden" name="action" value="move_wishlist_down"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn ghost sm" title="Move down">&darr;</button></form><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="toggle_wishlist_purchased"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn ghost sm" title="Mark purchased">&check;</button></form>
<a class="btn ghost sm" href="?page=wishlist&edit=<?= (int)$w['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this item?')"><input type="hidden" name="action" value="delete_wishlist"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn red sm">Delete</button></form>
</div>
</div>
<?php endforeach; if (!$activeWishlist): ?><p class="muted">Your wishlist is empty. Add items above.</p><?php endif; ?>
</div>
</div>

<?php if ($purchasedWishlist): ?>
<div class="card full" style="opacity:.7">
<h2>Purchased (<?= count($purchasedWishlist) ?>)</h2>
<div class="list">
<?php foreach ($purchasedWishlist as $w): ?>
<div class="priority-item">
<div class="priority-bar" style="background:#6b7280"></div>
<div style="flex:1;text-decoration:line-through;opacity:.6">
<strong><?= h($w['name']) ?></strong>
<div class="muted"><?= $w['amount'] > 0 ? money($w['amount'], $currency) : 'No price set' ?> &middot; Purchased</div>
</div>
<div class="stack-actions">
<form method="post"><input type="hidden" name="action" value="toggle_wishlist_purchased"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn ghost sm" title="Undo purchase">&#8617;</button></form>
<form method="post" onsubmit="return confirm('Delete this item?')"><input type="hidden" name="action" value="delete_wishlist"><input type="hidden" name="wish_id" value="<?= (int)$w['id'] ?>"><?= csrf_field() ?><button class="btn red sm">Delete</button></form>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</section>
