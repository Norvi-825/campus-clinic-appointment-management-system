<?php
/**
 * Contact Page — USTED University Campus Clinic
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$submitted = false;
$errors = [];
$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('contact.php');

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter your name.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($subject === '') {
        $errors['subject'] = 'Please enter a subject.';
    }
    if ($message === '' || mb_strlen($message) < 10) {
        $errors['message'] = 'Please enter a message of at least 10 characters.';
    }

    if (empty($errors)) {
        $submitted = true;
        set_flash('success', 'Thank you, ' . $name . '. Your clinic inquiry has been logged for the USTED Clinic Desk.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact Clinic';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <span class="text-primary fw-bold text-uppercase small">Get in Touch</span>
                <h1 class="fw-bold mt-1 mb-3">USTED University Campus Clinic</h1>
                <p class="text-muted mb-4">
                    Reach out to the clinic reception desk for scheduling inquiries, doctor office hours,
                    or student health orientation support.
                </p>

                <div class="ccams-card p-4 mb-3">
                    <div class="d-flex gap-3 mb-3">
                        <div class="service-icon-box"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <h2 class="h6 fw-bold mb-1">Campus Location</h2>
                            <p class="small text-muted mb-0">Health Directorate Block, USTED University, Tanoso Campus, Kumasi, Ghana</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="service-icon-box"><i class="bi bi-telephone"></i></div>
                        <div>
                            <h2 class="h6 fw-bold mb-1">Phone Lines</h2>
                            <p class="small text-muted mb-0">Reception: +233 (0) 32 200 0199<br>Urgent Triage Desk: +233 (0) 24 000 0911</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="service-icon-box"><i class="bi bi-envelope"></i></div>
                        <div>
                            <h2 class="h6 fw-bold mb-1">Email Address</h2>
                            <p class="small text-muted mb-0">clinic@usted.edu.gh</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="ccams-card p-4 p-md-5">
                    <h2 class="h4 fw-bold mb-3">Send an Inquiry to the Clinic Desk</h2>
                    <form method="post" action="<?php echo e(app_url('contact.php')); ?>" data-loading novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contactName" class="form-label fw-semibold">Full Name <span class="required-star">*</span></label>
                                <input type="text" id="contactName" name="name"
                                       class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($name); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="contactEmail" class="form-label fw-semibold">Email Address <span class="required-star">*</span></label>
                                <input type="email" id="contactEmail" name="email"
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($email); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label for="contactSubject" class="form-label fw-semibold">Subject <span class="required-star">*</span></label>
                                <input type="text" id="contactSubject" name="subject"
                                       class="form-control <?php echo isset($errors['subject']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($subject); ?>" required>
                                <?php if (isset($errors['subject'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['subject']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label for="contactMessage" class="form-label fw-semibold">Message <span class="required-star">*</span></label>
                                <textarea id="contactMessage" name="message" rows="4"
                                          class="form-control <?php echo isset($errors['message']) ? 'is-invalid' : ''; ?>"
                                          required><?php echo e($message); ?></textarea>
                                <?php if (isset($errors['message'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['message']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-send me-1"></i> Send Inquiry
                                </button>
                                <a href="<?php echo e(app_url('index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
