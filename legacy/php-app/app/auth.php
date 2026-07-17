<?php
// ============================================================
// UMKC VSA - Session & auth helpers
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, first_name, last_name, full_name, email, profile_pic, points, role, created_at
         FROM app_users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        header('Location: /app/login.php');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if (!has_role($user, 'admin')) {
        http_response_code(403);
        exit('Access denied. Admins only.');
    }
    return $user;
}

// Return the user's roles as a lowercase array (role is a SET column, comma-separated).
function user_roles(?array $user): array {
    if ($user === null || empty($user['role'])) {
        return [];
    }
    $roles = array_map('trim', explode(',', strtolower((string)$user['role'])));
    return array_values(array_filter($roles, fn($r) => $r !== ''));
}

// True if the user has the given role.
function has_role(?array $user, string $role): bool {
    return in_array(strtolower($role), user_roles($user), true);
}

// Require a logged-in user who holds the Officer role (admins allowed too).
function require_officer(): array {
    $user = require_login();
    if (!has_role($user, 'officer') && !has_role($user, 'admin')) {
        http_response_code(403);
        exit('Access denied. Officers only.');
    }
    return $user;
}

function login_user(int $userId): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
