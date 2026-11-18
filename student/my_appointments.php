<?php
/**
 * Student — My Appointments (Upcoming & Historical Records)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$student = require_auth('student');
$pdo = get_pdo();
$studentId = (int) $student['user_id'];

// Handle cancellation action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    require_valid_csrf('student/my_appointments.php');
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $service = new AppointmentService($pdo);
    $res = $service->cancelAppointment($appointmentId, $studentId, 'student');
    set_flash($res['success'] ? 'success' : 'danger', $res['message']);
    redirect('student/my_appointments.php');
}

$viewFilter = (string) ($_GET['view'] ?? 'all');
$statusFilter = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = 'WHERE a.student_id = :sid';
$params = ['sid' => $studentId];

if ($viewFilter === 'upcoming') {
    $where .= ' AND a.status IN ("pending", "confirmed") AND s.start_time >= NOW()';
} elseif ($viewFilter === 'previous') {
    $where .= ' AND (a.status IN ("completed", "cancelled", "rejected") OR s.start_time < NOW())';
}

if (in_array($statusFilter, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where .= ' AND a.status = :status';
    $params['status'] = $statusFilter;
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     $where"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$listStmt = $pdo->prepare(
    "SELECT a.appointment_id, a.status, a.reason, a.created_at,
            s.start_time, s.end_time,
            doc_u.full_name AS doctor_name, d.specialization
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users doc_u ON doc_u.user_id = d.user_id
     $where
     ORDER BY s.start_time DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue(':' . $k, $v);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$appointments = $listStmt->fetchAll();

$pageTitle = 'My Appointments';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('student/my_appointments.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="viewSelect" class="form-label small fw-semibold">Appointment View</label>
            <select id="viewSelect" name="view" class="form-select">
                <option value="all" <?php echo $viewFilter === 'all' ? 'selected' : ''; ?>>All Appointments</option>
                <option value="upcoming" <?php echo $viewFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Only</option>
                <option value="previous" <?php echo $viewFilter === 'previous' ? 'selected' : ''; ?>>Previous / History</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="statusSelect" class="form-label small fw-semibold">Status Filter</label>
            <select id="statusSelect" name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $st): ?>
                    <option value="<?php echo e($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
                        <?php echo e(ucfirst($st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
            <a href="<?php echo e(app_url('student/book_appointment.php')); ?>" class="btn btn-outline-primary">
                <i class="bi bi-plus-lg"></i> New
            </a>
        </div>
    </form>
</div>

<div class="ccams-card p-4">
    <?php if (empty($appointments)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar2 fs-1 d-block mb-2"></i>
            No appointment records found for the selected filter.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Doctor &amp; Specialization</th>
                        <th>Appointment Window</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appt): ?>
                        <?php $canModify = can_student_modify_appointment($appt); ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int) $appt['appointment_id']; ?></td>
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
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo e(app_url('student/appointment_details.php?id=' . (int) $appt['appointment_id'])); ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <?php echo $canModify ? 'Details / Reschedule' : 'Details'; ?>
                                    </a>
                                    <?php if ($canModify): ?>
                                        <form method="post" action="<?php echo e(app_url('student/my_appointments.php')); ?>"
                                              data-confirm="Cancel Appointment #<?php echo (int) $appt['appointment_id']; ?>? The record will remain in your history and the slot will be released."
                                              data-confirm-title="Cancel Appointment"
                                              data-confirm-btn-class="btn-danger"
                                              data-confirm-btn-text="Yes, Cancel Appointment">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int) $appt['appointment_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Appointments pagination" class="mt-3">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?view=<?php echo urlencode($viewFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $p; ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
