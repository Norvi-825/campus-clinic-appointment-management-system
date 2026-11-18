<?php
/**
 * Administrator Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_auth('admin');
$pdo = get_pdo();

// Live system statistics queried directly from MySQL
$totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "student"')->fetchColumn();
$totalDoctors = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
$availableSlots = (int) $pdo->query('SELECT COUNT(*) FROM appointment_slots WHERE status = "available" AND start_time > NOW()')->fetchColumn();

$apptCounts = $pdo->query(
    'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled
     FROM appointments'
)->fetch() ?: ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];

$recentAppointments = $pdo->query(
    'SELECT a.appointment_id, a.status, a.created_at,
            s.start_time, s.end_time,
            stu.full_name AS student_name,
            doc_u.full_name AS doctor_name, d.specialization
     FROM appointments a
     INNER JOIN users stu ON stu.user_id = a.student_id
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users doc_u ON doc_u.user_id = d.user_id
     ORDER BY a.created_at DESC
     LIMIT 8'
)->fetchAll();

$pageTitle = 'Administrator Dashboard';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h4 fw-bold mb-1 text-primary-dark">Clinic Administration Overview</h2>
            <p class="small text-muted mb-0">
                Welcome, <?php echo e($admin['full_name']); ?>. Manage USTED University doctors, appointment slots, bookings, and user accounts.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo e(app_url('admin/add_doctor.php')); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i> Add Doctor
            </a>
            <a href="<?php echo e(app_url('admin/manage_slots.php')); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-clock-history me-1"></i> Create Slots
            </a>
        </div>
    </div>
</div>

<!-- Real System Statistics Grid -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Registered Students</div>
                <div class="stat-value"><?php echo $totalStudents; ?></div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Clinic Doctors</div>
                <div class="stat-value"><?php echo $totalDoctors; ?></div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-hospital"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?php echo (int) $apptCounts['total']; ?></div>
            </div>
            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-calendar2-week"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Available Future Slots</div>
                <div class="stat-value"><?php echo $availableSlots; ?></div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-clock"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div>
                <div class="stat-label">Pending Appointments</div>
                <div class="stat-value"><?php echo (int) $apptCounts['pending']; ?></div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div>
                <div class="stat-label">Confirmed Appointments</div>
                <div class="stat-value"><?php echo (int) $apptCounts['confirmed']; ?></div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div>
                <div class="stat-label">Cancelled Appointments</div>
                <div class="stat-value"><?php echo (int) $apptCounts['cancelled']; ?></div>
            </div>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
        </div>
    </div>
</div>

<!-- Recent Appointments -->
<div class="ccams-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0">Recent Clinic Appointments</h3>
        <a href="<?php echo e(app_url('admin/manage_appointments.php')); ?>" class="small text-decoration-none fw-semibold">Manage all appointments</a>
    </div>

    <?php if (empty($recentAppointments)): ?>
        <p class="small text-muted mb-0">No appointments recorded in the system yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Doctor</th>
                        <th>Schedule</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAppointments as $appt): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int) $appt['appointment_id']; ?></td>
                            <td><?php echo e($appt['student_name']); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e($appt['doctor_name']); ?></div>
                                <small class="text-muted"><?php echo e($appt['specialization']); ?></small>
                            </td>
                            <td><?php echo e(format_slot_range((string) $appt['start_time'], (string) $appt['end_time'])); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $appt['status']); ?>">
                                    <?php echo e(ucfirst((string) $appt['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
