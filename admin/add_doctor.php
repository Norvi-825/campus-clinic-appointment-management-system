<?php
/**
 * Administrator — Register New Doctor
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('admin');
$pdo = get_pdo();

$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'specialization' => '',
    'availability_status' => 'available',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('admin/add_doctor.php');

    $form['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $form['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $form['specialization'] = trim((string) ($_POST['specialization'] ?? ''));
    $form['availability_status'] = ($_POST['availability_status'] ?? 'available') === 'unavailable' ? 'unavailable' : 'available';
    $password = (string) ($_POST['password'] ?? '');

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) < 3) {
        $errors['full_name'] = 'Doctor full name is required (min 3 characters).';
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    if ($form['specialization'] === '') {
        $errors['specialization'] = 'Specialization is required.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Initial password must be at least 8 characters.';
    }

    if (empty($errors)) {
        $dup = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $dup->execute(['email' => $form['email']]);
        if ($dup->fetch()) {
            $errors['email'] = 'This email address is already in use.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $uStmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, phone, role, account_status)
                 VALUES (:full_name, :email, :hash, :phone, "doctor", "active")'
            );
            $uStmt->execute([
                'full_name' => $form['full_name'],
                'email' => $form['email'],
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $form['phone'] !== '' ? $form['phone'] : null,
            ]);
            $newUserId = (int) $pdo->lastInsertId();

            $dStmt = $pdo->prepare(
                'INSERT INTO doctors (user_id, specialization, availability_status)
                 VALUES (:uid, :spec, :avail)'
            );
            $dStmt->execute([
                'uid' => $newUserId,
                'spec' => $form['specialization'],
                'avail' => $form['availability_status'],
            ]);

            $pdo->commit();
            set_flash('success', 'Doctor account created for ' . $form['full_name'] . ' (' . $form['specialization'] . ').');
            redirect('admin/manage_doctors.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add doctor error: ' . $e->getMessage());
            $errors['general'] = 'Could not register doctor. Please try again.';
        }
    }
}

$pageTitle = 'Add New Doctor';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Doctors List
    </a>
</div>

<div class="ccams-card p-4" style="max-width: 720px;">
    <h2 class="h5 fw-bold mb-3">Register Campus Clinic Doctor</h2>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger small"><?php echo e($errors['general']); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(app_url('admin/add_doctor.php')); ?>" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="docFullName" class="form-label small fw-semibold">Full Name <span class="required-star">*</span></label>
                <input type="text" id="docFullName" name="full_name"
                       class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo e($form['full_name']); ?>" placeholder="e.g. Dr. Kofi Mensah" required>
                <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="docEmail" class="form-label small fw-semibold">Email Address <span class="required-star">*</span></label>
                <input type="email" id="docEmail" name="email"
                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo e($form['email']); ?>" placeholder="e.g. kofi.mensah@usted.edu.gh" required>
                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo e($errors['email']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="docPhone" class="form-label small fw-semibold">Phone Number</label>
                <input type="tel" id="docPhone" name="phone" class="form-control"
                       value="<?php echo e($form['phone']); ?>" placeholder="+233 24 000 0000">
            </div>
            <div class="col-md-6">
                <label for="docSpec" class="form-label small fw-semibold">Specialization <span class="required-star">*</span></label>
                <input type="text" id="docSpec" name="specialization"
                       class="form-control <?php echo isset($errors['specialization']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo e($form['specialization']); ?>" placeholder="e.g. General Medicine" required>
                <?php if (isset($errors['specialization'])): ?><div class="invalid-feedback"><?php echo e($errors['specialization']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="docAvail" class="form-label small fw-semibold">Initial Availability</label>
                <select id="docAvail" name="availability_status" class="form-select">
                    <option value="available" <?php echo $form['availability_status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $form['availability_status'] === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="docPassword" class="form-label small fw-semibold">Initial Password <span class="required-star">*</span></label>
                <input type="password" id="docPassword" name="password"
                       class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                       minlength="8" required>
                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo e($errors['password']); ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">Create Doctor Account</button>
            <a href="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
