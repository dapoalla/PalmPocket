<?php
declare(strict_types=1);

function current_user(): ?array {
    static $user = false;
    if ($user !== false) {
        return $user ?: null;
    }
    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }
    $stmt = get_pdo()->prepare('SELECT id, name, email, role, username FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $user = $row ?: null;
    return $user;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function attempt_login(string $username, string $password): ?array {
    $stmt = get_pdo()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}

function log_in_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function log_out_user(): void {
    $_SESSION = [];
    session_regenerate_id(true);
}
