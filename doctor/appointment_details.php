<?php
/**
 * Doctor — Appointment Details & Status Management
 * Strictly enforces doctor ownership so doctors cannot view or edit another doctor's patients.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$user = require_auth('doctor');
$doctor = require_doctor_record((int) $user['user_id']);
$doctorId = (int) $doctor['doctor_id'];
$pdo = get_pdo();

$appointmentId = (int) ($_GET['id'] ?? $_POST['appointment_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    require_valid_csrf('doctor/my_appointments.php');
    $newStatus = (string) ($_POST['status'] ?? '');
    $service = new AppointmentService($pdo);
    $res = $service->updateStatus($appointmentId, $newStatus, (int) $user['user_id'], 'doctor');
    set_flash($res['success'] ? 'success' : 'danger', $res['message']);
    redirect('doctor/appointment_details.php?id=' . $appointmentId);
}

$stmt = $pdo->prepare(
    'SELECT a.*, s.start_time, s.end_time,
            stu.full_name AS student_name, stu.email AS student_email, stu.phone AS student_phone
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN users stu ON stu.user_id = a.student_id
     WHERE a.appointment_id = :aid AND s.doctor_id = :did
     LIMIT 1'
);
$stmt->execute(['aid' => $appointmentId, 'did' => $doctorId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    set_flash('danger', 'Appointment not found or not assigned to you.');
    redirect('doctor/my_appointments.php');
}

$notifStmt = $pdo->prepare(
    'SELECT channel, message, delivery_status, created_at
     FROM notifications WHERE appointment_id = :aid ORDER BY created_at DESC'
);
$notifStmt->execute(['aid' => $appointmentId]);
$notifications = $notifStmt->fetchAll();

$pageTitle = 'Assigned Appointment #' . $appointmentId;
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="<?php echo e(app_url('doctor/my_appointments.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Assigned Appointments
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Student &amp; Slot Information</h2>
                <span class="badge <?php echo status_badge_class((string) $appointment['status']); ?> px-3 py-2">
                    <?php echo e(ucfirst((string) $appointment['status'])); ?>
                </span>
            </div>

            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">Student Name</dt>
                <dd class="col-sm-8 fw-semibold"><?php echo e($appointment['student_name']); ?></dd>

                <dt class="col-sm-4 text-muted">Student Email</dt>
                <dd class="col-sm-8"><?php echo e($appointment['student_email']); ?></dd>

                <dt class="col-sm-4 text-muted">Phone Number</dt>
                <dd class="col-sm-8"><?php echo e($appointment['student_phone'] ?: 'Not provided'); ?></dd>

                <dt class="col-sm-4 text-muted">Schedule</dt>
                <dd class="col-sm-8 fw-semibold"><?php echo e(format_slot_range((string) $appointment['start_time'], (string) $appointment['end_time'])); ?></dd>

                <dt class="col-sm-4 text-muted">Visit Reason</dt>
                <dd class="col-sm-8"><?php echo e($appointment['reason'] ?: 'No reason provided'); ?></dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Update Appointment Status</h2>
            <p class="small text-muted">
                Updating status triggers an automatic notification to the student. Rejecting frees the slot if still in the future.
            </p>
            <form method="post" action="<?php echo e(app_url('doctor/appointment_details.php?id=' . $appointmentId)); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">

                <div class="mb-3">
                    <label for="docStatusSelect" class="form-label small fw-semibold">New Status</label>
                    <select id="docStatusSelect" name="status" class="form-select" required>
                        <?php foreach (['pending', 'confirmed', 'completed', 'rejected'] as $st): ?>
                            <option value="<?php echo e($st); ?>" <?php echo $appointment['status'] === $st ? 'selected' : ''; ?>>
                                <?php echo e(ucfirst($st)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary px-4">Save Status Update</button>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="ccams-card p-4">
            <h2 class="h6 fw-bold mb-3">Appointment Notification Log</h2>
            <?php if (empty($notifications)): ?>
                <p class="small text-muted mb-0">No notifications recorded yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Channel</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $n): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?php echo e(strtoupper((string) $n['channel'])); ?></span></td>
                                    <td><?php echo e($n['message']); ?></td>
                                    <td><span class="badge <?php echo status_badge_class((string) $n['delivery_status']); ?>"><?php echo e(ucfirst((string) $n['delivery_status'])); ?></span></td>
                                    <td><?php echo e(format_datetime((string) $n['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
