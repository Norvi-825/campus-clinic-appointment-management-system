<?php
/**
 * Administrator — Create, Edit & Deactivate Appointment Slots
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('admin');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('admin/manage_slots.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_slot' || $action === 'edit_slot') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $slotDate = trim((string) ($_POST['slot_date'] ?? ''));
        $startClock = trim((string) ($_POST['start_clock'] ?? ''));
        $endClock = trim((string) ($_POST['end_clock'] ?? ''));

        if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate) || $startClock === '' || $endClock === '') {
            set_flash('danger', 'Please select a valid doctor, date, start time, and end time.');
            redirect('admin/manage_slots.php');
        }

        $startTime = $slotDate . ' ' . $startClock . ':00';
        $endTime = $slotDate . ' ' . $endClock . ':00';

        if (strtotime($endTime) <= strtotime($startTime)) {
            set_flash('danger', 'Slot end time must be after the start time.');
            redirect('admin/manage_slots.php');
        }

        // Prevent overlapping slot for same doctor
        $overlapSql = 'SELECT slot_id FROM appointment_slots
                       WHERE doctor_id = :did
                         AND status != "inactive"
                         AND start_time < :end_t
                         AND end_time > :start_t';
        $overlapParams = ['did' => $doctorId, 'end_t' => $endTime, 'start_t' => $startTime];
        if ($action === 'edit_slot') {
            $overlapSql .= ' AND slot_id != :sid';
            $overlapParams['sid'] = $slotId;
        }
        $ovStmt = $pdo->prepare($overlapSql);
        $ovStmt->execute($overlapParams);
        if ($ovStmt->fetch()) {
            set_flash('danger', 'This time range overlaps with an existing active slot for the selected doctor.');
            redirect('admin/manage_slots.php');
        }

        if ($action === 'create_slot') {
            $ins = $pdo->prepare(
                'INSERT INTO appointment_slots (doctor_id, start_time, end_time, status)
                 VALUES (:did, :start_t, :end_t, "available")'
            );
            $ins->execute(['did' => $doctorId, 'start_t' => $startTime, 'end_t' => $endTime]);
            set_flash('success', 'New appointment slot created successfully.');
        } else {
            $upd = $pdo->prepare(
                'UPDATE appointment_slots
                 SET doctor_id = :did, start_time = :start_t, end_time = :end_t
                 WHERE slot_id = :sid AND status != "booked"'
            );
            $upd->execute(['did' => $doctorId, 'start_t' => $startTime, 'end_t' => $endTime, 'sid' => $slotId]);
            set_flash($upd->rowCount() > 0 ? 'success' : 'warning',
                $upd->rowCount() > 0 ? 'Slot updated successfully.' : 'Booked slots cannot be edited directly.');
        }
        redirect('admin/manage_slots.php');
    }

    if ($action === 'toggle_slot') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $targetStatus = ($_POST['target_status'] ?? '') === 'available' ? 'available' : 'inactive';

        $upd = $pdo->prepare(
            'UPDATE appointment_slots SET status = :target WHERE slot_id = :sid AND status != "booked"'
        );
        $upd->execute(['target' => $targetStatus, 'sid' => $slotId]);

        set_flash($upd->rowCount() > 0 ? 'success' : 'warning',
            $upd->rowCount() > 0 ? 'Slot status updated to ' . $targetStatus . '.' : 'Cannot deactivate an already booked slot.');
        redirect('admin/manage_slots.php');
    }
}

$doctors = $pdo->query(
    'SELECT d.doctor_id, d.specialization, u.full_name
     FROM doctors d INNER JOIN users u ON u.user_id = d.user_id
     ORDER BY u.full_name ASC'
)->fetchAll();

// Optional slot to edit
$editSlot = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM appointment_slots WHERE slot_id = :sid AND status != "booked" LIMIT 1');
    $editStmt->execute(['sid' => (int) $_GET['edit']]);
    $editSlot = $editStmt->fetch() ?: null;
}

$filterDoctor = (int) ($_GET['doctor_id'] ?? 0);
$filterStatus = (string) ($_GET['status'] ?? '');

$where = 'WHERE 1=1';
$params = [];
if ($filterDoctor > 0) {
    $where .= ' AND s.doctor_id = :did';
    $params['did'] = $filterDoctor;
}
if (in_array($filterStatus, ['available', 'booked', 'inactive'], true)) {
    $where .= ' AND s.status = :st';
    $params['st'] = $filterStatus;
}

$slotsStmt = $pdo->prepare(
    "SELECT s.*, u.full_name AS doctor_name, d.specialization
     FROM appointment_slots s
     INNER JOIN doctors d ON d.doctor_id = s.doctor_id
     INNER JOIN users u ON u.user_id = d.user_id
     $where
     ORDER BY s.start_time DESC
     LIMIT 60"
);
$slotsStmt->execute($params);
$slots = $slotsStmt->fetchAll();

$pageTitle = 'Manage Appointment Slots';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Create / Edit Slot Form -->
    <div class="col-lg-4">
        <div class="ccams-card p-4">
            <h2 class="h6 fw-bold text-primary-dark mb-3">
                <?php echo $editSlot ? 'Edit Slot #' . (int) $editSlot['slot_id'] : 'Create New Appointment Slot'; ?>
            </h2>
            <form method="post" action="<?php echo e(app_url('admin/manage_slots.php')); ?>" data-loading>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $editSlot ? 'edit_slot' : 'create_slot'; ?>">
                <?php if ($editSlot): ?>
                    <input type="hidden" name="slot_id" value="<?php echo (int) $editSlot['slot_id']; ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="slotDoctor" class="form-label small fw-semibold">Doctor <span class="required-star">*</span></label>
                    <select id="slotDoctor" name="doctor_id" class="form-select" required>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo (int) $doc['doctor_id']; ?>"
                                <?php echo ($editSlot && (int) $editSlot['doctor_id'] === (int) $doc['doctor_id']) ? 'selected' : ''; ?>>
                                <?php echo e($doc['full_name'] . ' (' . $doc['specialization'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="slotDate" class="form-label small fw-semibold">Date <span class="required-star">*</span></label>
                    <input type="date" id="slotDate" name="slot_date" class="form-control"
                           value="<?php echo $editSlot ? e(substr((string) $editSlot['start_time'], 0, 10)) : date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <label for="slotStart" class="form-label small fw-semibold">Start Time <span class="required-star">*</span></label>
                        <input type="time" id="slotStart" name="start_clock" class="form-control"
                               value="<?php echo $editSlot ? e(substr((string) $editSlot['start_time'], 11, 5)) : '09:00'; ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="slotEnd" class="form-label small fw-semibold">End Time <span class="required-star">*</span></label>
                        <input type="time" id="slotEnd" name="end_clock" class="form-control"
                               value="<?php echo $editSlot ? e(substr((string) $editSlot['end_time'], 11, 5)) : '09:30'; ?>" required>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <?php echo $editSlot ? 'Update Slot' : 'Create Slot'; ?>
                    </button>
                    <?php if ($editSlot): ?>
                        <a href="<?php echo e(app_url('admin/manage_slots.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Slot List & Filters -->
    <div class="col-lg-8">
        <div class="ccams-card p-4">
            <form method="get" action="<?php echo e(app_url('admin/manage_slots.php')); ?>" class="row g-2 align-items-end mb-3">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Filter by Doctor</label>
                    <select name="doctor_id" class="form-select form-select-sm">
                        <option value="0">All Doctors</option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo (int) $doc['doctor_id']; ?>" <?php echo $filterDoctor === (int) $doc['doctor_id'] ? 'selected' : ''; ?>>
                                <?php echo e($doc['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach (['available', 'booked', 'inactive'] as $st): ?>
                            <option value="<?php echo e($st); ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo e(ucfirst($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                    <a href="<?php echo e(app_url('admin/manage_slots.php')); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Doctor</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($slot['doctor_name']); ?></div>
                                    <small class="text-muted"><?php echo e($slot['specialization']); ?></small>
                                </td>
                                <td><?php echo e(format_slot_range((string) $slot['start_time'], (string) $slot['end_time'])); ?></td>
                                <td>
                                    <span class="badge <?php echo status_badge_class((string) $slot['status']); ?>">
                                        <?php echo e(ucfirst((string) $slot['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($slot['status'] !== 'booked'): ?>
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?php echo e(app_url('admin/manage_slots.php?edit=' . (int) $slot['slot_id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="post" action="<?php echo e(app_url('admin/manage_slots.php')); ?>">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_slot">
                                                <input type="hidden" name="slot_id" value="<?php echo (int) $slot['slot_id']; ?>">
                                                <input type="hidden" name="target_status" value="<?php echo $slot['status'] === 'available' ? 'inactive' : 'available'; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $slot['status'] === 'available' ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                    <?php echo $slot['status'] === 'available' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Locked (Booked)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
