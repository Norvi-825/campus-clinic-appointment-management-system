<?php
/**
 * Administrator — Edit Doctor Details & Specialization
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('admin');
$pdo = get_pdo();
$doctorId = (int) ($_GET['id'] ?? $_POST['doctor_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT d.doctor_id, d.user_id, d.specialization, d.availability_status,
            u.full_name, u.email, u.phone, u.account_status
     FROM doctors d
     INNER JOIN users u ON u.user_id = d.user_id
     WHERE d.doctor_id = :did
     LIMIT 1'
);
$stmt->execute(['did' => $doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    set_flash('danger', 'Doctor record not found.');
    redirect('admin/manage_doctors.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('admin/edit_doctor.php?id=' . $doctorId);

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $specialization = trim((string) ($_POST['specialization'] ?? ''));
    $availability = ($_POST['availability_status'] ?? 'available') === 'unavailable' ? 'unavailable' : 'available';
    $accountStatus = ($_POST['account_status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters.';
    }
    if ($specialization === '') {
        $errors['specialization'] = 'Specialization is required.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                'UPDATE users SET full_name = :name, phone = :phone, account_status = :status WHERE user_id = :uid'
            )->execute([
                'name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'status' => $accountStatus,
                'uid' => (int) $doctor['user_id'],
            ]);

            $pdo->prepare(
                'UPDATE doctors SET specialization = :spec, availability_status = :avail WHERE doctor_id = :did'
            )->execute([
                'spec' => $specialization,
                'avail' => $availability,
                'did' => $doctorId,
            ]);

            $pdo->commit();
            set_flash('success', 'Doctor details updated successfully.');
            redirect('admin/manage_doctors.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Edit doctor error: ' . $e->getMessage());
            $errors['general'] = 'Failed to update doctor details.';
        }
    }

    $doctor['full_name'] = $fullName;
    $doctor['phone'] = $phone;
    $doctor['specialization'] = $specialization;
    $doctor['availability_status'] = $availability;
    $doctor['account_status'] = $accountStatus;
}

$pageTitle = 'Edit Doctor';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Doctors List
    </a>
</div>

<div class="ccams-card p-4" style="max-width: 720px;">
    <h2 class="h5 fw-bold mb-3">Edit Doctor Profile: <?php echo e($doctor['full_name']); ?></h2>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger small"><?php echo e($errors['general']); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(app_url('admin/edit_doctor.php?id=' . $doctorId)); ?>" data-loading>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="doctor_id" value="<?php echo $doctorId; ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="editDocName" class="form-label small fw-semibold">Full Name <span class="required-star">*</span></label>
                <input type="text" id="editDocName" name="full_name"
                       class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo e($doctor['full_name']); ?>" required>
                <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Email (Read-only)</label>
                <input type="email" class="form-control bg-light" value="<?php echo e($doctor['email']); ?>" disabled>
            </div>
            <div class="col-md-6">
                <label for="editDocPhone" class="form-label small fw-semibold">Phone Number</label>
                <input type="tel" id="editDocPhone" name="phone" class="form-control"
                       value="<?php echo e((string) ($doctor['phone'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label for="editDocSpec" class="form-label small fw-semibold">Specialization <span class="required-star">*</span></label>
                <input type="text" id="editDocSpec" name="specialization"
                       class="form-control <?php echo isset($errors['specialization']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo e($doctor['specialization']); ?>" required>
                <?php if (isset($errors['specialization'])): ?><div class="invalid-feedback"><?php echo e($errors['specialization']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="editDocAvail" class="form-label small fw-semibold">Availability Status</label>
                <select id="editDocAvail" name="availability_status" class="form-select">
                    <option value="available" <?php echo $doctor['availability_status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $doctor['availability_status'] === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="editDocAcct" class="form-label small fw-semibold">Account Status</label>
                <select id="editDocAcct" name="account_status" class="form-select">
                    <option value="active" <?php echo $doctor['account_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $doctor['account_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
            <a href="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
