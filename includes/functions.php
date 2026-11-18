<?php
/**
 * Shared Helper Functions for CCAMS
 */

declare(strict_types=1);

/**
 * Escapes output safely for HTML rendering to mitigate XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Builds an application-relative URL respecting base_path.
 */
function app_url(string $path = ''): string
{
    $cfg = ccams_config();
    $base = rtrim($cfg['app']['base_path'], '/');
    $path = ltrim($path, '/');
    return $base . ($path !== '' ? '/' . $path : '/');
}

/**
 * Redirects to an application path and terminates execution.
 */
function redirect(string $path): never
{
    $url = preg_match('#^https?://#i', $path) ? $path : app_url($path);
    header('Location: ' . $url);
    exit;
}

/**
 * Stores a one-time flash message in session.
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['flash_messages'][] = [
            'type' => $type, // success, warning, danger, info
            'message' => $message,
        ];
    }
}

/**
 * Retrieves and clears all pending flash messages.
 */
function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash_messages'])) {
        return [];
    }
    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Formats a MySQL DATETIME string for readable display.
 */
function format_datetime(string $datetime, string $format = 'D, d M Y • g:i A'): string
{
    try {
        $dt = new DateTimeImmutable($datetime);
        return $dt->format($format);
    } catch (Exception) {
        return $datetime;
    }
}

/**
 * Formats a time range from start and end DATETIME strings.
 */
function format_slot_range(string $start, string $end): string
{
    try {
        $s = new DateTimeImmutable($start);
        $e = new DateTimeImmutable($end);
        return $s->format('D, d M Y') . ' | ' . $s->format('g:i A') . ' - ' . $e->format('g:i A');
    } catch (Exception) {
        return $start . ' - ' . $end;
    }
}

/**
 * Returns Bootstrap badge color class for a status value.
 */
function status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'confirmed', 'available', 'active', 'sent' => 'badge-soft-success',
        'pending', 'booked', 'queued' => 'badge-soft-warning',
        'completed', 'simulated' => 'badge-soft-primary',
        'cancelled', 'rejected', 'inactive', 'unavailable', 'failed' => 'badge-soft-danger',
        default => 'badge-soft-secondary',
    };
}

/**
 * Checks whether an appointment is eligible for student cancellation/rescheduling
 * based on its status and the clinic cutoff window before slot start_time.
 */
function can_student_modify_appointment(array $appointment, ?int $cutoffHours = null): bool
{
    $status = strtolower((string) ($appointment['status'] ?? ''));
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        return false;
    }

    $cutoff = $cutoffHours ?? (int) (ccams_config()['app']['cancellation_cutoff_hours'] ?? 2);
    try {
        $slotStart = new DateTimeImmutable((string) $appointment['start_time']);
        $deadline = $slotStart->modify('-' . $cutoff . ' hours');
        return new DateTimeImmutable('now') < $deadline;
    } catch (Exception) {
        return false;
    }
}

/**
 * Checks if current script matches a given filename or subpath for active nav styling.
 */
function is_active_nav(string $target): string
{
    $current = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_ends_with($current, '/' . ltrim($target, '/')) ? 'active' : '';
}
