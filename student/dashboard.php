<?php
/**
 * Student Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$student = require_auth('student');
$pdo = get_pdo();
$studentId = (int) $student['user_id'];

// Real DB statistics scoped strictly to the logged-in student
$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN a.status IN ("pending", "confirmed") AND s.start_time >= NOW() THEN 1 ELSE 0 END) AS upcoming_appointments,
        SUM(CASE WHEN a.status = "completed" THEN 1 ELSE 0 END) AS completed_appointments,
        SUM(CASE WHEN a.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_appointments
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     WHERE a.student_id = :sid'
);
$statsStmt->execute(['sid' => $studentId]);
$stats = $statsStmt->fetch() ?: [
    'total_appointments' => 0,
    'upcoming_appointments' => 0,
    'completed_appointments' => 0,
    'cancelled_appointments' => 0,
];

// Upcoming appointments preview
$upcomingStmt = $pdo->prepare(
    'SELECT a.appointment_id, a.status, a.reason, s.start_time, s.end_time,
            doc_u.full_name AS doctor_name, d.specialization
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users doc_u ON doc_u.user_id = d.user_id
     WHERE a.student_id = :sid
       AND a.status IN ("pending", "confirmed")
       AND s.start_time >= NOW()
     ORDER BY s.start_time ASC
     LIMIT 5'
);
$upcomingStmt->execute(['sid' => $studentId]);
$upcomingList = $upcomingStmt->fetchAll();

// Available doctors
$doctorsStmt = $pdo->query(
    'SELECT d.doctor_id, d.specialization, u.full_name,
            (SELECT COUNT(*) FROM appointment_slots s
             WHERE s.doctor_id = d.doctor_id AND s.status = "available" AND s.start_time > NOW()) AS open_slots
     FROM doctors d
     INNER JOIN users u ON u.user_id = d.user_id
     WHERE u.account_status = "active" AND d.availability_status = "available"
     ORDER BY open_slots DESC, u.full_name ASC
     LIMIT 5'
);
$availableDoctors = $doctorsStmt->fetchAll();

// Recent in-app notifications
$notifStmt = $pdo->prepare(
    'SELECT n.notification_id, n.appointment_id, n.channel, n.message, n.delivery_status, n.is_read, n.created_at
     FROM notifications n
     INNER JOIN appointments a ON a.appointment_id = n.appointment_id
     WHERE a.student_id = :sid
     ORDER BY n.created_at DESC
     LIMIT 5'
);
$notifStmt->execute(['sid' => $studentId]);
$recentNotifications = $notifStmt->fetchAll();

$pageTitle = 'Student Dashboard';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="ccams-card p-4 mb-4 bg-white">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h4 fw-bold mb-1 text-primary-dark">Welcome, <?php echo e($student['full_name']); ?></h2>
            <p class="text-muted small mb-0">
                Manage your USTED University Campus Clinic appointments, view available doctors, and track updates.
            </p>
        </div>
        <a href="<?php echo e(app_url('student/book_appointment.php')); ?>" class="btn btn-primary fw-semibold px-4">
            <i class="bi bi-calendar-plus me-2"></i>Book Appointment
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?php echo (int) $stats['total_appointments']; ?></div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar2-check"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Upcoming</div>
                <div class="stat-value"><?php echo (int) $stats['upcoming_appointments']; ?></div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo (int) $stats['completed_appointments']; ?></div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?php echo (int) $stats['cancelled_appointments']; ?></div>
            </div>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Upcoming Appointments -->
    <div class="col-lg-7">
        <div class="ccams-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Upcoming Appointments</h3>
                <a href="<?php echo e(app_url('student/my_appointments.php')); ?>" class="small text-decoration-none fw-semibold">View all</a>
            </div>

            <?php if (empty($upcomingList)): ?>
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                    You have no upcoming clinic appointments.
                    <div class="mt-2">
                        <a href="<?php echo e(app_url('student/book_appointment.php')); ?>" class="btn btn-sm btn-outline-primary">Book One Now</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Doctor</th>
                                <th>Date &amp; Time</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingList as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($item['doctor_name']); ?></div>
                                        <small class="text-muted"><?php echo e($item['specialization']); ?></small>
                                    </td>
                                    <td><?php echo e(format_slot_range((string) $item['start_time'], (string) $item['end_time'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo status_badge_class((string) $item['status']); ?>">
                                            <?php echo e(ucfirst((string) $item['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo e(app_url('student/appointment_details.php?id=' . (int) $item['appointment_id'])); ?>" class="btn btn-sm btn-outline-primary">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Doctors -->
    <div class="col-lg-5">
        <div class="ccams-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Available Campus Doctors</h3>
                <a href="<?php echo e(app_url('student/doctors.php')); ?>" class="small text-decoration-none fw-semibold">Browse all</a>
            </div>

            <?php if (empty($availableDoctors)): ?>
                <p class="small text-muted mb-0">No doctors are currently marked available.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($availableDoctors as $doc): ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-semibold small"><?php echo e($doc['full_name']); ?></div>
                                <small class="text-muted"><?php echo e($doc['specialization']); ?> &bull; <?php echo (int) $doc['open_slots']; ?> open slots</small>
                            </div>
                            <a href="<?php echo e(app_url('student/book_appointment.php?doctor_id=' . (int) $doc['doctor_id'])); ?>" class="btn btn-sm btn-outline-primary">
                                Book
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="col-12">
        <div class="ccams-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Recent Notifications</h3>
                <a href="<?php echo e(app_url('student/notifications.php')); ?>" class="small text-decoration-none fw-semibold">View notification history</a>
            </div>
            <?php if (empty($recentNotifications)): ?>
                <p class="small text-muted mb-0">No notifications recorded yet.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentNotifications as $n): ?>
                        <div class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <span class="badge <?php echo status_badge_class((string) $n['delivery_status']); ?> me-2">
                                    <?php echo e(strtoupper((string) $n['channel'])); ?> &bull; <?php echo e(ucfirst((string) $n['delivery_status'])); ?>
                                </span>
                                <span class="small"><?php echo e($n['message']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo e(format_datetime((string) $n['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
