<?php
/**
 * Doctor — Profile, Specialization & Availability Settings
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AuthService.php';

$user = require_auth('doctor');
$doctor = require_doctor_record((int) $user['user_id']);
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('doctor/profile.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $res = AuthService::updateProfile(
            $pdo,
            (int) $user['user_id'],
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['phone'] ?? '')
        );

        $availability = (string) ($_POST['availability_status'] ?? 'available');
        if (in_array($availability, ['available', 'unavailable'], true)) {
            $stmt = $pdo->prepare('UPDATE doctors SET availability_status = :avail WHERE doctor_id = :did');
            $stmt->execute([
                'avail' => $availability,
                'did' => (int) $doctor['doctor_id'],
            ]);
        }

        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect('doctor/profile.php');
    }

    if ($action === 'change_password') {
        $res = AuthService::changePassword(
            $pdo,
            (int) $user['user_id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect('doctor/profile.php');
    }
}

$pageTitle = 'Doctor Profile & Availability';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Doctor Profile &amp; Availability</h2>
            <form method="post" action="<?php echo e(app_url('doctor/profile.php')); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label for="docName" class="form-label small fw-semibold">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="docName" name="full_name" class="form-control" value="<?php echo e($doctor['full_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="docSpec" class="form-label small fw-semibold">Specialization (Managed by Clinic Admin)</label>
                    <input type="text" id="docSpec" class="form-control bg-light" value="<?php echo e($doctor['specialization']); ?>" disabled>
                </div>

                <div class="mb-3">
                    <label for="docPhone" class="form-label small fw-semibold">Phone Number</label>
                    <input type="tel" id="docPhone" name="phone" class="form-control" value="<?php echo e((string) ($doctor['phone'] ?? '')); ?>">
                </div>

                <div class="mb-4">
                    <label for="docAvail" class="form-label small fw-semibold">Consultation Availability</label>
                    <select id="docAvail" name="availability_status" class="form-select">
                        <option value="available" <?php echo $doctor['availability_status'] === 'available' ? 'selected' : ''; ?>>Available for Appointments</option>
                        <option value="unavailable" <?php echo $doctor['availability_status'] === 'unavailable' ? 'selected' : ''; ?>>Temporarily Unavailable</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Change Password</h2>
            <form method="post" action="<?php echo e(app_url('doctor/profile.php')); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label for="docCurrPw" class="form-label small fw-semibold">Current Password <span class="required-star">*</span></label>
                    <input type="password" id="docCurrPw" name="current_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="docNewPw" class="form-label small fw-semibold">New Password <span class="required-star">*</span></label>
                    <input type="password" id="docNewPw" name="new_password" class="form-control" minlength="8" required>
                </div>

                <div class="mb-4">
                    <label for="docConfPw" class="form-label small fw-semibold">Confirm New Password <span class="required-star">*</span></label>
                    <input type="password" id="docConfPw" name="confirm_password" class="form-control" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-outline-primary px-4">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
