<?php
/**
 * Administrator — System Notification Records
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('admin');
$pdo = get_pdo();

$channelFilter = (string) ($_GET['channel'] ?? '');
$statusFilter = (string) ($_GET['status'] ?? '');

$where = 'WHERE 1=1';
$params = [];

if (in_array($channelFilter, ['in_app', 'email', 'sms', 'mock'], true)) {
    $where .= ' AND n.channel = :ch';
    $params['ch'] = $channelFilter;
}
if (in_array($statusFilter, ['pending', 'sent', 'simulated', 'queued', 'failed'], true)) {
    $where .= ' AND n.delivery_status = :st';
    $params['st'] = $statusFilter;
}

$stmt = $pdo->prepare(
    "SELECT n.*, stu.full_name AS student_name, stu.email AS student_email
     FROM notifications n
     INNER JOIN appointments a ON a.appointment_id = n.appointment_id
     INNER JOIN users stu ON stu.user_id = a.student_id
     $where
     ORDER BY n.created_at DESC
     LIMIT 100"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$pageTitle = 'Notification Records';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('admin/notifications.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Channel</label>
            <select name="channel" class="form-select form-select-sm">
                <option value="">All Channels</option>
                <?php foreach (['in_app', 'mock', 'sms', 'email'] as $ch): ?>
                    <option value="<?php echo e($ch); ?>" <?php echo $channelFilter === $ch ? 'selected' : ''; ?>>
                        <?php echo e(strtoupper($ch)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Delivery Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (['sent', 'simulated', 'queued', 'pending', 'failed'] as $st): ?>
                    <option value="<?php echo e($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
                        <?php echo e(ucfirst($st)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter Logs</button>
            <a href="<?php echo e(app_url('admin/notifications.php')); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="ccams-card p-4">
    <?php if (empty($records)): ?>
        <p class="small text-muted mb-0">No notification records matched your filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Appt #</th>
                        <th>Student</th>
                        <th>Channel</th>
                        <th>Message</th>
                        <th>Delivery Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int) $r['notification_id']; ?></td>
                            <td>#<?php echo (int) $r['appointment_id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e($r['student_name']); ?></div>
                                <small class="text-muted"><?php echo e($r['student_email']); ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo e(strtoupper((string) $r['channel'])); ?></span></td>
                            <td><?php echo e($r['message']); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $r['delivery_status']); ?>">
                                    <?php echo e(ucfirst((string) $r['delivery_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo e(format_datetime((string) $r['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
