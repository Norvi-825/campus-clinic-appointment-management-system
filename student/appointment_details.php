<?php
/**
 * Student — Appointment Details, Cancellation & Rescheduling
 * Strictly scoped so students can only inspect or modify their own appointments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$student = require_auth('student');
$pdo = get_pdo();
$studentId = (int) $student['user_id'];
$appointmentId = (int) ($_GET['id'] ?? $_POST['appointment_id'] ?? 0);

// Handle POST actions (cancel / reschedule)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('student/my_appointments.php');
    $action = (string) ($_POST['action'] ?? '');
    $service = new AppointmentService($pdo);

    if ($action === 'cancel') {
        $res = $service->cancelAppointment($appointmentId, $studentId, 'student');
        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect('student/appointment_details.php?id=' . $appointmentId);
    }

    if ($action === 'reschedule') {
        $newSlotId = (int) ($_POST['new_slot_id'] ?? 0);
        $newReason = trim((string) ($_POST['reason'] ?? ''));
        $res = $service->rescheduleAppointment($appointmentId, $studentId, $newSlotId, $newReason);
        if ($res['success'] && isset($res['new_appointment_id'])) {
            set_flash('success', $res['message']);
            redirect('student/appointment_details.php?id=' . (int) $res['new_appointment_id']);
        }
        set_flash('danger', $res['message']);
        redirect('student/appointment_details.php?id=' . $appointmentId);
    }
}

// Fetch appointment strictly belonging to this student
$stmt = $pdo->prepare(
    'SELECT a.*, s.slot_id, s.doctor_id, s.start_time, s.end_time,
            doc_u.full_name AS doctor_name, doc_u.email AS doctor_email, d.specialization
     FROM appointments a
     INNER JOIN appointment_slots s ON s.slot_id = a.slot_id
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users doc_u ON doc_u.user_id = d.user_id
     WHERE a.appointment_id = :aid AND a.student_id = :sid
     LIMIT 1'
);
$stmt->execute(['aid' => $appointmentId, 'sid' => $studentId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    set_flash('danger', 'Appointment record not found or you do not have permission to view it.');
    redirect('student/my_appointments.php');
}

$canModify = can_student_modify_appointment($appointment);

// Load alternative open slots for rescheduling
$openSlots = [];
if ($canModify) {
    $slotsStmt = $pdo->prepare(
        'SELECT s.slot_id, s.start_time, s.end_time, doc_u.full_name AS doctor_name, d.specialization
         FROM appointment_slots s
         INNER JOIN doctors d ON d.doctor_id = s.doctor_id
         INNER JOIN users doc_u ON doc_u.user_id = d.user_id
         WHERE s.status = "available"
           AND s.start_time > NOW()
           AND s.slot_id != :current_slot
           AND d.availability_status = "available"
           AND doc_u.account_status = "active"
         ORDER BY (d.doctor_id = :current_doc) DESC, s.start_time ASC
         LIMIT 20'
    );
    $slotsStmt->execute([
        'current_slot' => (int) $appointment['slot_id'],
        'current_doc' => (int) $appointment['doctor_id'],
    ]);
    $openSlots = $slotsStmt->fetchAll();
}

// Fetch notification trail for this appointment
$notifStmt = $pdo->prepare(
    'SELECT channel, message, delivery_status, created_at
     FROM notifications
     WHERE appointment_id = :aid
     ORDER BY created_at DESC'
);
$notifStmt->execute(['aid' => $appointmentId]);
$notifHistory = $notifStmt->fetchAll();

$pageTitle = 'Appointment #' . $appointmentId;
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a href="<?php echo e(app_url('student/my_appointments.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to My Appointments
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Appointment Summary</h2>
                <span class="badge <?php echo status_badge_class((string) $appointment['status']); ?> px-3 py-2">
                    <?php echo e(ucfirst((string) $appointment['status'])); ?>
                </span>
            </div>

            <dl class="row small mb-4">
                <dt class="col-sm-4 text-muted">Doctor</dt>
                <dd class="col-sm-8 fw-semibold"><?php echo e($appointment['doctor_name']); ?></dd>

                <dt class="col-sm-4 text-muted">Specialization</dt>
                <dd class="col-sm-8"><?php echo e($appointment['specialization']); ?></dd>

                <dt class="col-sm-4 text-muted">Date &amp; Time</dt>
                <dd class="col-sm-8 fw-semibold"><?php echo e(format_slot_range((string) $appointment['start_time'], (string) $appointment['end_time'])); ?></dd>

                <dt class="col-sm-4 text-muted">Reason</dt>
                <dd class="col-sm-8"><?php echo e($appointment['reason'] ?: 'No reason provided'); ?></dd>

                <dt class="col-sm-4 text-muted">Booked On</dt>
                <dd class="col-sm-8"><?php echo e(format_datetime((string) $appointment['created_at'])); ?></dd>
            </dl>

            <?php if ($canModify): ?>
                <form method="post" action="<?php echo e(app_url('student/appointment_details.php?id=' . $appointmentId)); ?>"
                      data-confirm="Cancel Appointment #<?php echo $appointmentId; ?>? This preserves your record in history and releases the slot."
                      data-confirm-title="Confirm Cancellation"
                      data-confirm-btn-class="btn-danger"
                      data-confirm-btn-text="Cancel Appointment">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Cancel This Appointment
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-light border small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    This appointment can no longer be cancelled or rescheduled (already closed or within the 2-hour clinic cutoff window).
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ccams-card p-4 h-100">
            <h2 class="h5 fw-bold mb-3">Reschedule Appointment</h2>
            <?php if (!$canModify): ?>
                <p class="small text-muted mb-0">Rescheduling is only available for upcoming pending or confirmed appointments outside the 2-hour cutoff window.</p>
            <?php elseif (empty($openSlots)): ?>
                <p class="small text-muted mb-0">There are currently no alternative open slots available.</p>
            <?php else: ?>
                <p class="small text-muted">
                    Choosing a new slot will verify availability inside a database transaction, preserve Appointment #<?php echo $appointmentId; ?> in your history, and create your rescheduled booking.
                </p>
                <form method="post" action="<?php echo e(app_url('student/appointment_details.php?id=' . $appointmentId)); ?>"
                      data-confirm="Reschedule this appointment to the newly selected time slot?"
                      data-confirm-title="Confirm Reschedule"
                      data-confirm-btn-text="Confirm Reschedule"
                      data-loading>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">

                    <div class="mb-3">
                        <label for="newSlotSelect" class="form-label small fw-semibold">Select New Available Slot <span class="required-star">*</span></label>
                        <select id="newSlotSelect" name="new_slot_id" class="form-select" required>
                            <option value="">-- Choose an open slot --</option>
                            <?php foreach ($openSlots as $slot): ?>
                                <option value="<?php echo (int) $slot['slot_id']; ?>">
                                    <?php echo e(format_slot_range((string) $slot['start_time'], (string) $slot['end_time']) . ' — ' . $slot['doctor_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="rescheduleReason" class="form-label small fw-semibold">Update Reason (Optional)</label>
                        <input type="text" id="rescheduleReason" name="reason" maxlength="255" class="form-control"
                               value="<?php echo e((string) ($appointment['reason'] ?? '')); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-arrow-repeat me-1"></i> Reschedule Now
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification History for this Appointment -->
    <div class="col-12">
        <div class="ccams-card p-4">
            <h2 class="h6 fw-bold mb-3">Notification History for Appointment #<?php echo $appointmentId; ?></h2>
            <?php if (empty($notifHistory)): ?>
                <p class="small text-muted mb-0">No notifications recorded for this appointment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Channel</th>
                                <th>Message</th>
                                <th>Delivery Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifHistory as $n): ?>
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
