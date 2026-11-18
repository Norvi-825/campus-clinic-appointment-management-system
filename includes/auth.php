<?php
/**
 * Session Management, Authentication & Role-Based Access Control (RBAC)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

/**
 * Initializes a hardened PHP session if not already started.
 */
function init_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    if (PHP_SAPI !== 'cli') {
        session_start();
    }
}

/**
 * Logs in a verified user and regenerates the session ID to prevent fixation.
 */
function login_user(array $user): void
{
    init_secure_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = (string) $user['full_name'];
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['logged_in_at'] = time();

    // Rotate CSRF token on authentication boundary
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Safely terminates the current user session.
 */
function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Checks whether a user is currently authenticated.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']) && (int) $_SESSION['user_id'] > 0;
}

/**
 * Fetches fresh authenticated user record from database (or null if inactive/missing).
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    static $cached = null;
    $sessionUserId = (int) $_SESSION['user_id'];

    if ($cached !== null && (int) $cached['user_id'] === $sessionUserId) {
        return $cached;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT user_id, full_name, email, phone, role, account_status, created_at
         FROM users
         WHERE user_id = :id AND account_status = "active"
         LIMIT 1'
    );
    $stmt->execute(['id' => $sessionUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        return null;
    }

    $cached = $user;
    return $cached;
}

/**
 * Returns the dashboard path for a given role.
 */
function role_dashboard_path(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'doctor' => 'doctor/dashboard.php',
        default => 'student/dashboard.php',
    };
}

/**
 * Redirects logged-in user to their role-specific dashboard.
 */
function redirect_by_role(string $role): never
{
    redirect(role_dashboard_path($role));
}

/**
 * Enforces authentication and optionally restricts access to specific role(s).
 *
 * @param string|string[]|null $allowedRoles
 */
function require_auth(string|array|null $allowedRoles = null): array
{
    init_secure_session();

    $user = current_user();
    if (!$user) {
        set_flash('warning', 'Please log in to access that page.');
        redirect('login.php');
    }

    if ($allowedRoles !== null) {
        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        if (!in_array($user['role'], $roles, true)) {
            set_flash('danger', 'Access denied: you do not have permission to view that section.');
            redirect(role_dashboard_path($user['role']));
        }
    }

    return $user;
}

/**
 * Returns the doctor row linked to the authenticated doctor user.
 */
function require_doctor_record(int $userId): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT d.*, u.full_name, u.email, u.phone, u.account_status
         FROM doctors d
         INNER JOIN users u ON u.user_id = d.user_id
         WHERE d.user_id = :uid
         LIMIT 1'
    );
    $stmt->execute(['uid' => $userId]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        set_flash('danger', 'Doctor profile record could not be located. Please contact the clinic administrator.');
        redirect('logout.php');
    }

    return $doctor;
}

// Auto-initialize session for web requests
if (PHP_SAPI !== 'cli') {
    init_secure_session();
}
