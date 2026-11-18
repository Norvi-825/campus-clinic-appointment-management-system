<?php
/**
 * Student Registration Page
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/AuthService.php';

if ($existing = current_user()) {
    redirect_by_role($existing['role']);
}

$formData = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
];
$errors = [];
$generalError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('register.php');

    $formData['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $formData['phone'] = trim((string) ($_POST['phone'] ?? ''));

    // Require phone number for campus appointment alerts
    if ($formData['phone'] === '') {
        $errors['phone'] = 'Phone number is required for clinic appointment notifications.';
    }

    $result = AuthService::registerStudent(get_pdo(), $_POST);

    if (empty($errors) && $result['success']) {
        set_flash('success', $result['message']);
        redirect('login.php');
    }

    $errors = array_merge($result['errors'] ?? [], $errors);
    $generalError = $result['message'];
}

$pageTitle = 'Student Registration';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="ccams-card p-4 p-md-5">
                    <div class="mb-4">
                        <span class="text-primary fw-bold text-uppercase small">USTED University Student Portal</span>
                        <h1 class="h3 fw-bold mt-1 mb-1">Create Your Student Account</h1>
                        <p class="small text-muted mb-0">Register once to book, reschedule, and manage your campus clinic visits.</p>
                    </div>

                    <?php if ($generalError !== ''): ?>
                        <div class="alert alert-danger small" role="alert">
                            <i class="bi bi-exclamation-circle me-1"></i> <?php echo e($generalError); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo e(app_url('register.php')); ?>" data-loading novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="regFullName" class="form-label fw-semibold">Full Name <span class="required-star">*</span></label>
                            <input type="text" id="regFullName" name="full_name"
                                   class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo e($formData['full_name']); ?>"
                                   placeholder="e.g. Nana Kofi Agyemang" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="regEmail" class="form-label fw-semibold">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="regEmail" name="email"
                                   class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo e($formData['email']); ?>"
                                   placeholder="e.g. nana.agyemang@st.usted.edu.gh" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="regPhone" class="form-label fw-semibold">Phone Number <span class="required-star">*</span></label>
                            <input type="tel" id="regPhone" name="phone"
                                   class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo e($formData['phone']); ?>"
                                   placeholder="e.g. +233 54 000 3009" required>
                            <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['phone']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="regPassword" class="form-label fw-semibold">Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="regPassword" name="password"
                                           class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                           minlength="8" required>
                                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="regPassword" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="regConfirmPassword" class="form-label fw-semibold">Confirm Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="regConfirmPassword" name="confirm_password"
                                           class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                           minlength="8" required>
                                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="regConfirmPassword" aria-label="Show confirm password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo e($errors['confirm_password']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                <i class="bi bi-person-check me-1"></i> Register Account
                            </button>
                            <a href="<?php echo e(app_url('login.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
