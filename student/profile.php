<?php
/**
 * Student — Profile & Password Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AuthService.php';

$student = require_auth('student');
$pdo = get_pdo();
$studentId = (int) $student['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('student/profile.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $res = AuthService::updateProfile(
            $pdo,
            $studentId,
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['phone'] ?? '')
        );
        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect('student/profile.php');
    }

    if ($action === 'change_password') {
        $res = AuthService::changePassword(
            $pdo,
            $studentId,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect('student/profile.php');
    }
}

$pageTitle = 'My Profile & Password';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Profile Information</h2>
            <form method="post" action="<?php echo e(app_url('student/profile.php')); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label for="stuName" class="form-label small fw-semibold">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="stuName" name="full_name" class="form-control"
                           value="<?php echo e($student['full_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="stuEmail" class="form-label small fw-semibold">University Email (Read-only)</label>
                    <input type="email" id="stuEmail" class="form-control bg-light" value="<?php echo e($student['email']); ?>" disabled>
                </div>

                <div class="mb-4">
                    <label for="stuPhone" class="form-label small fw-semibold">Phone Number</label>
                    <input type="tel" id="stuPhone" name="phone" class="form-control"
                           value="<?php echo e((string) ($student['phone'] ?? '')); ?>">
                </div>

                <button type="submit" class="btn btn-primary px-4">Save Profile Changes</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Change Password</h2>
            <form method="post" action="<?php echo e(app_url('student/profile.php')); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label for="currPw" class="form-label small fw-semibold">Current Password <span class="required-star">*</span></label>
                    <input type="password" id="currPw" name="current_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="newPw" class="form-label small fw-semibold">New Password <span class="required-star">*</span></label>
                    <input type="password" id="newPw" name="new_password" class="form-control" minlength="8" required>
                    <div class="form-text">Minimum 8 characters.</div>
                </div>

                <div class="mb-4">
                    <label for="confPw" class="form-label small fw-semibold">Confirm New Password <span class="required-star">*</span></label>
                    <input type="password" id="confPw" name="confirm_password" class="form-control" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-outline-primary px-4">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
