<?php
/**
 * Student — Appointment Booking Workflow
 *
 * Step 1: Select an available doctor
 * Step 2: Filter available dates/times
 * Step 3: Select slot, enter optional reason & confirm booking inside PDO transaction
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AppointmentService.php';

$student = require_auth('student');
$pdo = get_pdo();

$selectedDoctorId = (int) ($_REQUEST['doctor_id'] ?? 0);
$filterDate = trim((string) ($_GET['date'] ?? ''));

// Validate date filter format if provided
if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = '';
}

$bookingError = '';
$reasonInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('student/book_appointment.php');

    $doctorIdPost = (int) ($_POST['doctor_id'] ?? 0);
    $slotIdPost = (int) ($_POST['slot_id'] ?? 0);
    $reasonInput = trim((string) ($_POST['reason'] ?? ''));
    $selectedDoctorId = $doctorIdPost;

    $service = new AppointmentService($pdo);
    $result = $service->bookAppointment((int) $student['user_id'], $doctorIdPost, $slotIdPost, $reasonInput);

    if ($result['success']) {
        set_flash('success', $result['message']);
        redirect('student/appointment_details.php?id=' . (int) $result['appointment_id']);
    }

    $bookingError = $result['message'];
}

// Fetch available active doctors
$doctors = $pdo->query(
    'SELECT d.doctor_id, d.specialization, u.full_name,
            (SELECT COUNT(*) FROM appointment_slots s
             WHERE s.doctor_id = d.doctor_id AND s.status = "available" AND s.start_time > NOW()) AS open_slots
     FROM doctors d
     INNER JOIN users u ON u.user_id = d.user_id
     WHERE u.account_status = "active" AND d.availability_status = "available"
     ORDER BY u.full_name ASC'
)->fetchAll();

// Default to first available doctor if none chosen
if ($selectedDoctorId === 0 && !empty($doctors)) {
    $selectedDoctorId = (int) $doctors[0]['doctor_id'];
}

$selectedDoctor = null;
foreach ($doctors as $d) {
    if ((int) $d['doctor_id'] === $selectedDoctorId) {
        $selectedDoctor = $d;
        break;
    }
}

// Load available future slots for the chosen doctor
$availableSlots = [];
if ($selectedDoctor) {
    $slotSql = 'SELECT slot_id, start_time, end_time
                FROM appointment_slots
                WHERE doctor_id = :did
                  AND status = "available"
                  AND start_time > NOW()';
    $slotParams = ['did' => $selectedDoctorId];

    if ($filterDate !== '') {
        $slotSql .= ' AND DATE(start_time) = :slot_date';
        $slotParams['slot_date'] = $filterDate;
    }

    $slotSql .= ' ORDER BY start_time ASC';
    $slotStmt = $pdo->prepare($slotSql);
    $slotStmt->execute($slotParams);
    $availableSlots = $slotStmt->fetchAll();
}

$pageTitle = 'Book Appointment';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Step 1 & Date Filter -->
    <div class="col-lg-4">
        <div class="ccams-card p-4">
            <h2 class="h6 fw-bold text-primary-dark mb-3">
                <i class="bi bi-funnel me-1"></i> Step 1: Doctor &amp; Date Filter
            </h2>
            <form method="get" action="<?php echo e(app_url('student/book_appointment.php')); ?>">
                <div class="mb-3">
                    <label for="doctorSelect" class="form-label small fw-semibold">Select Doctor <span class="required-star">*</span></label>
                    <select id="doctorSelect" name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo (int) $doc['doctor_id']; ?>"
                                <?php echo (int) $doc['doctor_id'] === $selectedDoctorId ? 'selected' : ''; ?>>
                                <?php echo e($doc['full_name'] . ' — ' . $doc['specialization'] . ' (' . (int) $doc['open_slots'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="dateFilter" class="form-label small fw-semibold">Filter Slots by Date</label>
                    <input type="date" id="dateFilter" name="date" class="form-control"
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo e($filterDate); ?>">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1">Apply Filter</button>
                    <a href="<?php echo e(app_url('student/book_appointment.php?doctor_id=' . $selectedDoctorId)); ?>" class="btn btn-outline-secondary btn-sm">Clear Date</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2 & 3: Slot Selection & Booking Confirmation -->
    <div class="col-lg-8">
        <div class="ccams-card p-4">
            <h2 class="h5 fw-bold mb-1">
                Step 2: Choose an Available Time Slot
            </h2>
            <?php if ($selectedDoctor): ?>
                <p class="small text-muted mb-3">
                    Showing open consultation windows for <strong><?php echo e($selectedDoctor['full_name']); ?></strong>
                    (<?php echo e($selectedDoctor['specialization']); ?>).
                </p>
            <?php endif; ?>

            <?php if ($bookingError !== ''): ?>
                <div class="alert alert-danger small" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?php echo e($bookingError); ?>
                </div>
            <?php endif; ?>

            <?php if (!$selectedDoctor): ?>
                <div class="alert alert-warning mb-0">No active doctors are currently available for booking.</div>
            <?php elseif (empty($availableSlots)): ?>
                <div class="alert alert-info mb-0">
                    No open appointment slots found for this doctor<?php echo $filterDate !== '' ? ' on ' . e($filterDate) : ''; ?>.
                    Try clearing the date filter or choosing another doctor.
                </div>
            <?php else: ?>
                <form method="post" action="<?php echo e(app_url('student/book_appointment.php?doctor_id=' . $selectedDoctorId)); ?>"
                      data-confirm="Confirm booking this campus clinic appointment slot?"
                      data-confirm-title="Confirm Appointment Booking"
                      data-confirm-btn-text="Confirm Booking"
                      data-loading>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="doctor_id" value="<?php echo $selectedDoctorId; ?>">

                    <div class="row g-2 mb-4">
                        <?php foreach ($availableSlots as $slot): ?>
                            <?php $sid = (int) $slot['slot_id']; ?>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="slot_id" id="slot_<?php echo $sid; ?>"
                                       value="<?php echo $sid; ?>" required>
                                <label class="slot-option-label" for="slot_<?php echo $sid; ?>">
                                    <div class="fw-semibold small text-primary-dark">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?php echo e(format_datetime((string) $slot['start_time'], 'D, d M Y')); ?>
                                    </div>
                                    <div class="small text-dark mt-1">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo e(format_datetime((string) $slot['start_time'], 'g:i A')); ?>
                                        &ndash;
                                        <?php echo e(format_datetime((string) $slot['end_time'], 'g:i A')); ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-4">
                        <label for="visitReason" class="form-label fw-semibold">Brief Reason for Visit (Optional)</label>
                        <textarea id="visitReason" name="reason" rows="2" maxlength="255" class="form-control"
                                  placeholder="e.g. Routine consultation, eye checkup, or fever symptoms"><?php echo e($reasonInput); ?></textarea>
                        <div class="form-text">Maximum 255 characters. Avoid sharing sensitive medical records.</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Confirm Appointment
                        </button>
                        <a href="<?php echo e(app_url('student/dashboard.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
