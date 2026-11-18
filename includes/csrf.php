<?php
/**
 * CSRF Protection Utilities
 *
 * Generates and verifies cryptographically random per-session CSRF tokens
 * for all state-changing POST forms.
 */

declare(strict_types=1);

/**
 * Returns the active session's CSRF token, creating one if absent.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Renders a hidden HTML input containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifies a submitted CSRF token in constant time.
 */
function verify_csrf_token(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * Enforces CSRF verification on POST requests and redirects cleanly on failure.
 */
function require_valid_csrf(string $redirectPath = ''): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($submitted) ? $submitted : null)) {
        set_flash('danger', 'Your security token expired or was invalid. Please try submitting the form again.');
        if ($redirectPath !== '') {
            redirect($redirectPath);
        }
        http_response_code(403);
        exit('Invalid CSRF security token.');
    }
}
