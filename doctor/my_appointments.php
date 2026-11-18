<?php
/**
 * Doctor — Assigned Appointments & History (with Date & Status Filtering)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$user = require_auth('doctor');
$doctor = require_doctor_record((int) $user['user_id']);
$doctorId = (int) $doctor['doctor_id'];
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_valid_csrf('doctor/my_appointments.php');
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $service = new AppointmentService($pdo);
    $res = $service->updateStatus($appointmentId, $newStatus, (int) $user['user_id'], 'doctor');
    set_flash($res['success'] ? 'success' : 'danger', $res['message']);
    redirect('doctor/my_appointments.php');
}

$filterDate = trim((string) ($_GET['date'] ?? ''));
if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = '';
}
$filterStatus = (string) ($_GET['status'] ?? '');

$where = 'WHERE s.doctor_id = :did';
$params = ['did' => $doctorId];

if ($filterDate !== '') {
    $where .= ' AND DATE(s.start_time) = :filter_date';
    $params['filter_date'] = $filterDate;
}

if (in_array($filterStatus, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where .= ' AND a.status = :filter_status';
    $params['filter_status'] = $filterStatus;
}

$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.status, a.reason, a.created_at,
            s.start_time, s.end_time,
            stu.full_name AS student_name, stu.email AS student_email, stu.phone AS student_phone
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN users stu ON stu.user_id = a.student_id
     $where
     ORDER BY s.start_time DESC"
);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Assigned Appointments';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('doctor/my_appointments.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="docFilterDate" class="form-label small fw-semibold">Filter by Date</label>
            <input type="date" id="docFilterDate" name="date" class="form-control" value="<?php echo e($filterDate); ?>">
        </div>
        <div class="col-md-4">
            <label for="docFilterStatus" class="form-label small fw-semibold">Filter by Status</label>
            <select id="docFilterStatus" name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $st): ?>
                    <option value="<?php echo e($st); ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                        <?php echo e(ucfirst($st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filter Appointments</button>
            <a href="<?php echo e(app_url('doctor/my_appointments.php')); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="ccams-card p-4">
    <?php if (empty($appointments)): ?>
        <p class="text-muted small mb-0">No appointments matched your selected filters.</p>
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
                        <th class="text-end">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appt): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int) $appt['appointment_id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e($appt['student_name']); ?></div>
                                <small class="text-muted"><?php echo e($appt['student_email']); ?></small>
                            </td>
                            <td><?php echo e(format_slot_range((string) $appt['start_time'], (string) $appt['end_time'])); ?></td>
                            <td><?php echo e($appt['reason'] ?: '—'); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $appt['status']); ?>">
                                    <?php echo e(ucfirst((string) $appt['status'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo e(app_url('doctor/appointment_details.php?id=' . (int) $appt['appointment_id'])); ?>" class="btn btn-sm btn-outline-primary">
                                    Manage / Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
