<?php
/**
 * Portal Login Page (Students, Doctors, Administrators)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/AuthService.php';

if ($existing = current_user()) {
    redirect_by_role($existing['role']);
}

$email = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('login.php');

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $result = AuthService::authenticate(get_pdo(), $email, $password);
    if ($result['success'] && isset($result['user'])) {
        login_user($result['user']);
        set_flash('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
        redirect_by_role($result['user']['role']);
    }

    $errorMsg = $result['message'];
}

$pageTitle = 'Portal Login';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center g-4">
            <div class="col-md-7 col-lg-5">
                <div class="ccams-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="brand-emblem brand-emblem-blue mb-3" style="width: 50px; height: 50px; font-size: 1.4rem;">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </span>
                        <h1 class="h4 fw-bold mb-1">Log In to CCAMS</h1>
                        <p class="small text-muted mb-0">USTED University Campus Clinic Portal</p>
                    </div>

                    <?php if ($errorMsg !== ''): ?>
                        <div class="alert alert-danger small" role="alert">
                            <i class="bi bi-exclamation-circle me-1"></i> <?php echo e($errorMsg); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo e(app_url('login.php')); ?>" data-loading novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label fw-semibold">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="loginEmail" name="email" class="form-control"
                                   value="<?php echo e($email); ?>" placeholder="e.g. kwabena.asare@st.usted.edu.gh" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="loginPassword" class="form-label fw-semibold">Password <span class="required-star">*</span></label>
                            <div class="input-group">
                                <input type="password" id="loginPassword" name="password" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="loginPassword" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary fw-semibold py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </button>
                            <a href="<?php echo e(app_url('index.php')); ?>" class="btn btn-outline-secondary">Back to Homepage</a>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top small">
                        New USTED student? <a href="<?php echo e(app_url('register.php')); ?>" class="fw-semibold">Create a student account</a>
                    </div>
                </div>
            </div>

            <div class="col-md-7 col-lg-5">
                <div class="ccams-card p-4 bg-white">
                    <h2 class="h6 fw-bold text-primary-dark mb-3">
                        <i class="bi bi-info-circle me-1"></i> Demonstration Accounts (Fictional USTED Data)
                    </h2>
                    <div class="small text-muted mb-3">
                        Use these seeded accounts after importing <code>database/campus_clinic.sql</code> into MySQL:
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-soft-primary">Student</span></td>
                                    <td><code>kwabena.asare@st.usted.edu.gh</code></td>
                                    <td><code>Student@2026</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-soft-success">Doctor</span></td>
                                    <td><code>kwame.mensah@usted.edu.gh</code></td>
                                    <td><code>Doctor@2026</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-soft-warning">Admin</span></td>
                                    <td><code>admin@usted.edu.gh</code></td>
                                    <td><code>Admin@2026</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
