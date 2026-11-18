<?php
/**
 * Student — Notification History
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$student = require_auth('student');
$pdo = get_pdo();
$studentId = (int) $student['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    require_valid_csrf('student/notifications.php');
    $upd = $pdo->prepare(
        'UPDATE notifications n
         INNER JOIN appointments a ON a.appointment_id = n.appointment_id
         SET n.is_read = 1
         WHERE a.student_id = :sid'
    );
    $upd->execute(['sid' => $studentId]);
    set_flash('success', 'All notifications marked as read.');
    redirect('student/notifications.php');
}

$stmt = $pdo->prepare(
    'SELECT n.notification_id, n.appointment_id, n.channel, n.message, n.delivery_status, n.is_read, n.sent_at, n.created_at
     FROM notifications n
     INNER JOIN appointments a ON a.appointment_id = n.appointment_id
     WHERE a.student_id = :sid
     ORDER BY n.created_at DESC'
);
$stmt->execute(['sid' => $studentId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'My Notifications';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h2 class="h5 fw-bold mb-0">Notification History</h2>
            <small class="text-muted">In-app alerts and simulated/sandbox SMS dispatch logs for your appointments.</small>
        </div>
        <?php if (!empty($notifications)): ?>
            <form method="post" action="<?php echo e(app_url('student/notifications.php')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check2-all me-1"></i> Mark All Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <p class="text-muted small mb-0">You have not received any notifications yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Appointment</th>
                        <th>Channel</th>
                        <th>Message</th>
                        <th>Delivery Status</th>
                        <th>Read State</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr class="<?php echo !(int) $n['is_read'] ? 'table-primary bg-opacity-10' : ''; ?>">
                            <td>
                                <a href="<?php echo e(app_url('student/appointment_details.php?id=' . (int) $n['appointment_id'])); ?>" class="fw-semibold text-decoration-none">
                                    #<?php echo (int) $n['appointment_id']; ?>
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo e(strtoupper((string) $n['channel'])); ?></span></td>
                            <td><?php echo e($n['message']); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $n['delivery_status']); ?>">
                                    <?php echo e(ucfirst((string) $n['delivery_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo (int) $n['is_read'] ? 'Read' : '<strong>Unread</strong>'; ?></td>
                            <td><?php echo e(format_datetime((string) $n['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
