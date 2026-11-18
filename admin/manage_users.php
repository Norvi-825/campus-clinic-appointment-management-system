<?php
/**
 * Administrator — Manage Student & System User Accounts
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_auth('admin');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    require_valid_csrf('admin/manage_users.php');
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';

    if ($targetUserId === (int) $admin['user_id']) {
        set_flash('warning', 'You cannot deactivate your own active administrator account.');
        redirect('admin/manage_users.php');
    }

    $upd = $pdo->prepare('UPDATE users SET account_status = :status WHERE user_id = :uid');
    $upd->execute(['status' => $newStatus, 'uid' => $targetUserId]);
    set_flash('success', 'User account status updated to ' . $newStatus . '.');
    redirect('admin/manage_users.php');
}

$search = trim((string) ($_GET['search'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? 'student');

$where = 'WHERE 1=1';
$params = [];

if (in_array($roleFilter, ['student', 'doctor', 'admin'], true)) {
    $where .= ' AND role = :role';
    $params['role'] = $roleFilter;
}
if ($search !== '') {
    $where .= ' AND (full_name LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$stmt = $pdo->prepare(
    "SELECT user_id, full_name, email, phone, role, account_status, created_at
     FROM users
     $where
     ORDER BY created_at DESC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage User Accounts';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('admin/manage_users.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold">Search Users</label>
            <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Search by name, email, or phone...">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Account Role</label>
            <select name="role" class="form-select">
                <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="doctor" <?php echo $roleFilter === 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Administrators</option>
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
            <a href="<?php echo e(app_url('admin/manage_users.php')); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="ccams-card p-4">
    <?php if (empty($users)): ?>
        <p class="small text-muted mb-0">No user accounts matched your filter.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $isActive = $u['account_status'] === 'active'; ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($u['full_name']); ?></td>
                            <td><?php echo e($u['email']); ?></td>
                            <td><?php echo e($u['phone'] ?: '—'); ?></td>
                            <td><span class="badge bg-light text-dark border text-capitalize"><?php echo e($u['role']); ?></span></td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $u['account_status']); ?>">
                                    <?php echo e(ucfirst((string) $u['account_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo e(format_datetime((string) $u['created_at'], 'd M Y')); ?></td>
                            <td class="text-end">
                                <?php if ((int) $u['user_id'] !== (int) $admin['user_id']): ?>
                                    <form method="post" action="<?php echo e(app_url('admin/manage_users.php')); ?>"
                                          data-confirm="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?> user <?php echo e($u['full_name']); ?>?"
                                          data-confirm-title="Update User Account"
                                          data-confirm-btn-class="<?php echo $isActive ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                            <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Current Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
