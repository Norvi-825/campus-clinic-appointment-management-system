<?php
/**
 * Public Homepage — Campus Clinic Appointment Management System (CCAMS)
 * USTED University, Kumasi
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = get_pdo();

// Fetch active doctors and upcoming available slot count for homepage display
$doctorsStmt = $pdo->query(
    'SELECT d.doctor_id, d.specialization, d.availability_status, u.full_name,
            (SELECT COUNT(*) FROM appointment_slots s
             WHERE s.doctor_id = d.doctor_id AND s.status = "available" AND s.start_time > NOW()) AS open_slots
     FROM doctors d
     INNER JOIN users u ON u.user_id = d.user_id
     WHERE u.account_status = "active"
     ORDER BY d.doctor_id ASC
     LIMIT 6'
);
$featuredDoctors = $doctorsStmt->fetchAll();

$totalOpenSlots = (int) $pdo->query(
    'SELECT COUNT(*) FROM appointment_slots WHERE status = "available" AND start_time > NOW()'
)->fetchColumn();

$activeDoctorsCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM doctors d INNER JOIN users u ON u.user_id = d.user_id
     WHERE u.account_status = "active" AND d.availability_status = "available"'
)->fetchColumn();

$pageTitle = 'Home';
$layoutMode = 'public';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <i class="bi bi-hospital"></i>
                    <span>USTED University Health Directorate &bull; Kumasi</span>
                </div>
                <h1 class="display-5 fw-bold mb-3">
                    Campus Clinic Appointment Management System (CCAMS)
                </h1>
                <p class="lead text-white-50 mb-4">
                    Skip long clinic queues on campus. Browse available USTED University doctors, view real-time
                    consultation slots, book or reschedule appointments online, and receive instant appointment updates.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <?php if ($authUser): ?>
                        <a href="<?php echo e(app_url(role_dashboard_path($authUser['role']))); ?>" class="btn btn-light btn-lg fw-semibold text-primary-dark px-4">
                            <i class="bi bi-speedometer2 me-2"></i>Go to My Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(app_url('register.php')); ?>" class="btn btn-light btn-lg fw-semibold text-primary-dark px-4">
                            <i class="bi bi-person-plus me-2"></i>Student Registration
                        </a>
                        <a href="<?php echo e(app_url('login.php')); ?>" class="btn btn-outline-light btn-lg fw-semibold px-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Portal Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold mb-0 text-primary-dark">Live Campus Clinic Snapshot</h2>
                        <span class="badge badge-soft-success px-2 py-1">Online Booking Open</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-light border">
                                <div class="small text-muted fw-semibold">Available Doctors</div>
                                <div class="h3 fw-bold text-primary-dark mb-0"><?php echo $activeDoctorsCount; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-light border">
                                <div class="small text-muted fw-semibold">Open Time Slots</div>
                                <div class="h3 fw-bold text-primary-dark mb-0"><?php echo $totalOpenSlots; ?></div>
                            </div>
                        </div>
                    </div>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Real-time slot locking prevents double bookings</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Cancel or reschedule up to 2 hours before visit</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Dedicated portals for Students, Doctors &amp; Admins</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Introduction & How It Works -->
<section class="py-5 bg-white border-bottom">
    <div class="container py-2">
        <div class="text-center mx-auto mb-5" style="max-width: 720px;">
            <span class="text-primary fw-bold text-uppercase small">How Appointment Booking Works</span>
            <h2 class="fw-bold mt-1 mb-3">Four Simple Steps to Visit the USTED Campus Clinic</h2>
            <p class="text-muted mb-0">
                CCAMS connects USTED University students directly with clinic doctors through a structured,
                transparent scheduling workflow designed to minimize waiting times.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="ccams-card p-4 h-100">
                    <div class="step-circle">1</div>
                    <h3 class="h6 fw-bold">Create Student Account</h3>
                    <p class="small text-muted mb-0">
                        Register using your name, USTED email address, phone number, and a secure password.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ccams-card p-4 h-100">
                    <div class="step-circle">2</div>
                    <h3 class="h6 fw-bold">Choose a Doctor</h3>
                    <p class="small text-muted mb-0">
                        Browse campus doctors by specialization, availability status, and consultation schedules.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ccams-card p-4 h-100">
                    <div class="step-circle">3</div>
                    <h3 class="h6 fw-bold">Select an Open Slot</h3>
                    <p class="small text-muted mb-0">
                        Filter available dates and times, enter a brief visit reason, and confirm your booking.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="ccams-card p-4 h-100">
                    <div class="step-circle">4</div>
                    <h3 class="h6 fw-bold">Track &amp; Get Notified</h3>
                    <p class="small text-muted mb-0">
                        Receive instant status alerts and manage cancellations or rescheduling anytime.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Available Clinic Services -->
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
            <div>
                <span class="text-primary fw-bold text-uppercase small">Campus Healthcare Units</span>
                <h2 class="fw-bold mt-1 mb-0">Available Clinic Services</h2>
            </div>
            <a href="<?php echo e(app_url('about.php')); ?>" class="btn btn-outline-primary btn-sm">
                View Clinic Policies <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-stethoscope"></i></div>
                    <h3 class="h5 fw-bold">General Medical Consultation</h3>
                    <p class="small text-muted mb-0">
                        Primary care consultations, fever &amp; malaria screening, blood pressure checks, and medical excuse referrals.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-emoji-smile"></i></div>
                    <h3 class="h5 fw-bold">Dental &amp; Oral Health</h3>
                    <p class="small text-muted mb-0">
                        Routine oral examinations, tooth sensitivity assessments, dental hygiene advice, and treatment scheduling.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-eye"></i></div>
                    <h3 class="h5 fw-bold">Optometry &amp; Eye Care</h3>
                    <p class="small text-muted mb-0">
                        Vision screening, digital eye-strain assessment for students, and prescription lens consultations.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-heart-pulse"></i></div>
                    <h3 class="h5 fw-bold">Women &amp; Reproductive Health</h3>
                    <p class="small text-muted mb-0">
                        Confidential wellness guidance, reproductive health consultations, and preventive campus health education.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-chat-heart"></i></div>
                    <h3 class="h5 fw-bold">Student Counseling &amp; Mental Health</h3>
                    <p class="small text-muted mb-0">
                        Stress management, academic wellness support, and private one-on-one counseling sessions.
                    </p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="ccams-card p-4 h-100">
                    <div class="service-icon-box mb-3"><i class="bi bi-shield-plus"></i></div>
                    <h3 class="h5 fw-bold">Preventive Screening &amp; Wellness</h3>
                    <p class="small text-muted mb-0">
                        Freshers' medical checkups, vaccination advisories, and campus-wide health outreach coordination.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Campus Doctors Directory Preview -->
<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
            <div>
                <span class="text-primary fw-bold text-uppercase small">Medical Team</span>
                <h2 class="fw-bold mt-1 mb-0">USTED University Clinic Doctors</h2>
            </div>
            <a href="<?php echo e(app_url($authUser && $authUser['role'] === 'student' ? 'student/doctors.php' : 'login.php')); ?>" class="btn btn-primary btn-sm">
                Book with a Doctor
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredDoctors as $doc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="ccams-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge <?php echo status_badge_class($doc['availability_status']); ?>">
                                    <?php echo e(ucfirst($doc['availability_status'])); ?>
                                </span>
                                <small class="text-muted fw-semibold">
                                    <?php echo (int) $doc['open_slots']; ?> open slots
                                </small>
                            </div>
                            <h3 class="h5 fw-bold mb-1"><?php echo e($doc['full_name']); ?></h3>
                            <p class="text-primary small fw-semibold mb-3"><?php echo e($doc['specialization']); ?></p>
                        </div>
                        <a href="<?php echo e(app_url($authUser && $authUser['role'] === 'student' ? 'student/book_appointment.php?doctor_id=' . (int) $doc['doctor_id'] : 'login.php')); ?>"
                           class="btn btn-outline-primary btn-sm w-100">
                            View Available Times
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
