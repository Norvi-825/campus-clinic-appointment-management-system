<?php
/**
 * Administrator — Manage Doctors
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('admin');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_account') {
    require_valid_csrf('admin/manage_doctors.php');
    $userId = (int) ($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';

    $stmt = $pdo->prepare('UPDATE users SET account_status = :status WHERE user_id = :uid AND role = "doctor"');
    $stmt->execute(['status' => $newStatus, 'uid' => $userId]);

    set_flash('success', 'Doctor account status updated to ' . $newStatus . '.');
    redirect('admin/manage_doctors.php');
}

$search = trim((string) ($_GET['search'] ?? ''));

$sql = 'SELECT d.doctor_id, d.user_id, d.specialization, d.availability_status,
               u.full_name, u.email, u.phone, u.account_status
        FROM doctors d
        INNER JOIN users u ON u.user_id = d.user_id
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE :q1 OR d.specialization LIKE :q2 OR u.email LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql .= ' ORDER BY d.doctor_id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$pageTitle = 'Manage Doctors';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-8">
            <form method="get" action="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="d-flex gap-2">
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>"
                       placeholder="Search doctors by name, email, or specialization...">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?php echo e(app_url('admin/manage_doctors.php')); ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="<?php echo e(app_url('admin/add_doctor.php')); ?>" class="btn btn-success">
                <i class="bi bi-person-plus me-1"></i> Register New Doctor
            </a>
        </div>
    </div>
</div>

<div class="ccams-card p-4">
    <?php if (empty($doctors)): ?>
        <p class="small text-muted mb-0">No doctor accounts matched your search.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Email &amp; Phone</th>
                        <th>Availability</th>
                        <th>Account</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doc): ?>
                        <?php $isActive = $doc['account_status'] === 'active'; ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($doc['full_name']); ?></td>
                            <td><?php echo e($doc['specialization']); ?></td>
                            <td>
                                <div><?php echo e($doc['email']); ?></div>
                                <small class="text-muted"><?php echo e($doc['phone'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $doc['availability_status']); ?>">
                                    <?php echo e(ucfirst((string) $doc['availability_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo status_badge_class((string) $doc['account_status']); ?>">
                                    <?php echo e(ucfirst((string) $doc['account_status'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?php echo e(app_url('admin/edit_doctor.php?id=' . (int) $doc['doctor_id'])); ?>" class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>
                                    <form method="post" action="<?php echo e(app_url('admin/manage_doctors.php')); ?>"
                                          data-confirm="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?> account for <?php echo e($doc['full_name']); ?>?"
                                          data-confirm-title="Update Doctor Account"
                                          data-confirm-btn-class="<?php echo $isActive ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_account">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $doc['user_id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                            <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
