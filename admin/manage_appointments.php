<?php
/**
 * Administrator — View, Search, Filter & Manage All Appointments
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$admin = require_auth('admin');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_valid_csrf('admin/manage_appointments.php');
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $service = new AppointmentService($pdo);
    $res = $service->updateStatus($appointmentId, $newStatus, (int) $admin['user_id'], 'admin');
    set_flash($res['success'] ? 'success' : 'danger', $res['message']);
    redirect('admin/manage_appointments.php');
}

$search = trim((string) ($_GET['search'] ?? ''));
$filterDate = trim((string) ($_GET['date'] ?? ''));
if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = '';
}
$filterDoctor = (int) ($_GET['doctor_id'] ?? 0);
$filterStatus = (string) ($_GET['status'] ?? '');

$doctors = $pdo->query(
    'SELECT d.doctor_id, u.full_name FROM doctors d INNER JOIN users u ON u.user_id = d.user_id ORDER BY u.full_name ASC'
)->fetchAll();

$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (stu.full_name LIKE :q1 OR doc_u.full_name LIKE :q2 OR a.reason LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
if ($filterDate !== '') {
    $where .= ' AND DATE(s.start_time) = :fdate';
    $params['fdate'] = $filterDate;
}
if ($filterDoctor > 0) {
    $where .= ' AND d.doctor_id = :did';
    $params['did'] = $filterDoctor;
}
if (in_array($filterStatus, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where .= ' AND a.status = :fst';
    $params['fst'] = $filterStatus;
}

$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.status, a.reason, s.start_time, s.end_time,
            stu.full_name AS student_name, stu.email AS student_email,
            doc_u.full_name AS doctor_name, d.specialization
     FROM appointments a
     INNER JOIN users stu ON stu.user_id = a.student_id
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users doc_u ON doc_u.user_id = d.user_id
     $where
     ORDER BY s.start_time DESC"
);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'All Appointments';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('admin/manage_appointments.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" value="<?php echo e($search); ?>" placeholder="Student, doctor, reason...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo e($filterDate); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Doctor</label>
            <select name="doctor_id" class="form-select form-select-sm">
                <option value="0">All Doctors</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?php echo (int) $doc['doctor_id']; ?>" <?php echo $filterDoctor === (int) $doc['doctor_id'] ? 'selected' : ''; ?>>
                        <?php echo e($doc['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $st): ?>
                    <option value="<?php echo e($st); ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo e(ucfirst($st)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
            <a href="<?php echo e(app_url('admin/manage_appointments.php')); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="ccams-card p-4">
    <?php if (empty($appointments)): ?>
        <p class="small text-muted mb-0">No appointments found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Doctor</th>
                        <th>Date &amp; Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Update Status</th>
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
                            <td>
                                <div class="fw-semibold"><?php echo e($appt['doctor_name']); ?></div>
                                <small class="text-muted"><?php echo e($appt['specialization']); ?></small>
                            </td>
                            <td><?php echo e(format_slot_range((string) $appt['start_time'], (string) $appt['end_time'])); ?></td>
                            <td><?php echo e($appt['reason'] ?: '—'); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $appt['status']); ?>">
                                    <?php echo e(ucfirst((string) $appt['status'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="post" action="<?php echo e(app_url('admin/manage_appointments.php')); ?>" class="d-inline-flex gap-1">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int) $appt['appointment_id']; ?>">
                                    <select name="status" class="form-select form-select-sm" style="width: auto;">
                                        <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $st): ?>
                                            <option value="<?php echo e($st); ?>" <?php echo $appt['status'] === $st ? 'selected' : ''; ?>>
                                                <?php echo e(ucfirst($st)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
