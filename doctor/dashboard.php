<?php
/**
 * Doctor Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$user = require_auth('doctor');
$doctor = require_doctor_record((int) $user['user_id']);
$doctorId = (int) $doctor['doctor_id'];
$pdo = get_pdo();

// Handle quick status update from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_valid_csrf('doctor/dashboard.php');
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $service = new AppointmentService($pdo);
    $res = $service->updateStatus($appointmentId, $newStatus, (int) $user['user_id'], 'doctor');
    set_flash($res['success'] ? 'success' : 'danger', $res['message']);
    redirect('doctor/dashboard.php');
}

// Real DB statistics scoped strictly to this doctor
$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_assigned,
        SUM(CASE WHEN DATE(s.start_time) = CURDATE() AND a.status IN ("pending", "confirmed") THEN 1 ELSE 0 END) AS today_count,
        SUM(CASE WHEN s.start_time >= NOW() AND a.status IN ("pending", "confirmed") THEN 1 ELSE 0 END) AS upcoming_count,
        SUM(CASE WHEN a.status = "completed" THEN 1 ELSE 0 END) AS completed_count
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     WHERE s.doctor_id = :did'
);
$statsStmt->execute(['did' => $doctorId]);
$stats = $statsStmt->fetch() ?: [
    'total_assigned' => 0,
    'today_count' => 0,
    'upcoming_count' => 0,
    'completed_count' => 0,
];

// Active queue for quick management
$queueStmt = $pdo->prepare(
    'SELECT a.appointment_id, a.status, a.reason, s.start_time, s.end_time,
            stu.full_name AS student_name, stu.phone AS student_phone
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN users stu ON stu.user_id = a.student_id
     WHERE s.doctor_id = :did
       AND a.status IN ("pending", "confirmed")
     ORDER BY s.start_time ASC
     LIMIT 8'
);
$queueStmt->execute(['did' => $doctorId]);
$activeQueue = $queueStmt->fetchAll();

$pageTitle = 'Doctor Dashboard';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h4 fw-bold mb-1 text-primary-dark">Welcome, <?php echo e($doctor['full_name']); ?></h2>
            <p class="small text-muted mb-0">
                Specialization: <strong><?php echo e($doctor['specialization']); ?></strong> &bull;
                Availability:
                <span class="badge <?php echo status_badge_class((string) $doctor['availability_status']); ?>">
                    <?php echo e(ucfirst((string) $doctor['availability_status'])); ?>
                </span>
            </p>
        </div>
        <a href="<?php echo e(app_url('doctor/my_appointments.php')); ?>" class="btn btn-primary btn-sm px-3">
            <i class="bi bi-clipboard2-pulse me-1"></i> Manage All Assigned Appointments
        </a>
    </div>
</div>

<!-- Live Statistics -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Assigned</div>
                <div class="stat-value"><?php echo (int) $stats['total_assigned']; ?></div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar2-check"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Today's Appointments</div>
                <div class="stat-value"><?php echo (int) $stats['today_count']; ?></div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Upcoming Active</div>
                <div class="stat-value"><?php echo (int) $stats['upcoming_count']; ?></div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo (int) $stats['completed_count']; ?></div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div>
        </div>
    </div>
</div>

<!-- Appointment Management Section -->
<div class="ccams-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0">Upcoming &amp; Pending Appointments Queue</h3>
        <a href="<?php echo e(app_url('doctor/my_appointments.php')); ?>" class="small text-decoration-none fw-semibold">Full History &amp; Filters</a>
    </div>

    <?php if (empty($activeQueue)): ?>
        <p class="small text-muted mb-0">No pending or confirmed appointments awaiting action.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Schedule</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeQueue as $appt): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int) $appt['appointment_id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e($appt['student_name']); ?></div>
                                <small class="text-muted"><?php echo e($appt['student_phone'] ?: 'No phone'); ?></small>
                            </td>
                            <td><?php echo e(format_slot_range((string) $appt['start_time'], (string) $appt['end_time'])); ?></td>
                            <td><?php echo e($appt['reason'] ?: '—'); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $appt['status']); ?>">
                                    <?php echo e(ucfirst((string) $appt['status'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <?php if ($appt['status'] === 'pending'): ?>
                                        <form method="post" action="<?php echo e(app_url('doctor/dashboard.php')); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $appt['appointment_id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($appt['status'], ['pending', 'confirmed'], true)): ?>
                                        <form method="post" action="<?php echo e(app_url('doctor/dashboard.php')); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $appt['appointment_id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-primary">Complete</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?php echo e(app_url('doctor/appointment_details.php?id=' . (int) $appt['appointment_id'])); ?>" class="btn btn-sm btn-outline-secondary">
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
