<?php
/**
 * Safe Logout Endpoint
 *
 * Uses POST + CSRF verification to prevent cross-site forced logout.
 * Renders a confirmation form if visited directly via GET while logged in.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('index.php');
    logout_user();
    init_secure_session();
    set_flash('info', 'You have been safely logged out.');
    redirect('login.php');
}

if (!is_logged_in()) {
    redirect('login.php');
}

$pageTitle = 'Confirm Log Out';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="ccams-card p-4 text-center">
                    <h1 class="h4 fw-bold mb-2">Sign Out of CCAMS?</h1>
                    <p class="text-muted small mb-4">Click below to securely end your current campus clinic session.</p>
                    <form method="post" action="<?php echo e(app_url('logout.php')); ?>" class="d-flex justify-content-center gap-2">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-danger px-4">Yes, Log Out</button>
                        <a href="<?php echo e(app_url(role_dashboard_path((string) ($_SESSION['role'] ?? 'student')))); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
