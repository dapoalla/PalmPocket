<?php
declare(strict_types=1);

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount, string $currency): string {
    $sign = $amount < 0 ? '-' : '';
    return $sign . $currency . number_format(abs($amount), 0);
}

function new_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = new_token(24);
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function find_name(array $items, $id, string $fallback = 'Uncategorized'): string {
    foreach ($items as $item) {
        if ((string)($item['id'] ?? '') === (string)$id) {
            return $item['name'] ?? $fallback;
        }
    }
    return $fallback;
}

function category_color(array $categories, $id): string {
    foreach ($categories as $category) {
        if ((string)($category['id'] ?? '') === (string)$id) {
            return $category['color'] ?? '#8b5cf6';
        }
    }
    return '#8b5cf6';
}

function wishlist_priority_color(int $index, int $total): string {
    if ($total <= 1) return '#ef4444';
    $ratio = $index / ($total - 1);
    if ($ratio < 0.33) return '#ef4444';
    if ($ratio < 0.66) return '#f59e0b';
    return '#22c55e';
}

function wishlist_priority_label(int $index, int $total): string {
    if ($total <= 1) return 'High priority';
    $ratio = $index / ($total - 1);
    if ($ratio < 0.33) return 'High priority';
    if ($ratio < 0.66) return 'Medium priority';
    return 'Low priority';
}

function flash(string $message): void {
    $_SESSION['flash'] = $message;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function current_page_query(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
