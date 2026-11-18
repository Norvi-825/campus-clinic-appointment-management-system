<?php
/**
 * About the USTED University Campus Clinic
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'About Campus Clinic';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="text-primary fw-bold text-uppercase small">USTED University Health Directorate</span>
                <h1 class="fw-bold mt-1 mb-3">About the Campus Clinic Appointment System</h1>
                <p class="lead text-muted mb-0">
                    The USTED University Campus Clinic provides primary outpatient care, preventive screening,
                    and student wellness support on our Tanoso Campus in Kumasi. CCAMS modernizes appointment
                    scheduling so students spend less time waiting and doctors can plan consultations effectively.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?php echo e(app_url('register.php')); ?>" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-calendar-check me-2"></i>Register to Book
                </a>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="ccams-card p-4 h-100">
                    <h2 class="h5 fw-bold text-primary-dark mb-3">
                        <i class="bi bi-clock-history me-2"></i>Clinic Operating Hours
                    </h2>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between py-2 border-bottom">
                            <span class="fw-semibold">Monday – Friday</span>
                            <span class="text-muted">7:30 AM – 5:00 PM</span>
                        </li>
                        <li class="d-flex justify-content-between py-2 border-bottom">
                            <span class="fw-semibold">Saturday (Urgent Outpatient)</span>
                            <span class="text-muted">9:00 AM – 1:00 PM</span>
                        </li>
                        <li class="d-flex justify-content-between py-2">
                            <span class="fw-semibold">Sunday &amp; Public Holidays</span>
                            <span class="text-danger fw-semibold">Emergency On-Call Only</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="ccams-card p-4 h-100">
                    <h2 class="h5 fw-bold text-primary-dark mb-3">
                        <i class="bi bi-journal-check me-2"></i>Appointment &amp; Cancellation Rules
                    </h2>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-2"></i>Students may only book one active appointment per time window.</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-2"></i>Cancellations and rescheduling are permitted up to <strong>2 hours</strong> before slot start time.</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-2"></i>Cancelled appointments stay in your history log while freeing future slots for peers.</li>
                        <li><i class="bi bi-exclamation-triangle text-warning me-2"></i>For acute emergencies, visit the Triage Desk directly rather than waiting for an online slot.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
