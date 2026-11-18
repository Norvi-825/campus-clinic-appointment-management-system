<?php
/**
 * Student — Browse & Filter Available Clinic Doctors
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth('student');
$pdo = get_pdo();

$searchName = trim((string) ($_GET['search'] ?? ''));
$specialization = trim((string) ($_GET['specialization'] ?? ''));

// Fetch distinct specializations for filter dropdown
$specList = $pdo->query('SELECT DISTINCT specialization FROM doctors ORDER BY specialization ASC')->fetchAll(PDO::FETCH_COLUMN);

$sql = 'SELECT d.doctor_id, d.specialization, d.availability_status, u.full_name, u.email,
               (SELECT COUNT(*) FROM appointment_slots s
                WHERE s.doctor_id = d.doctor_id AND s.status = "available" AND s.start_time > NOW()) AS open_slots
        FROM doctors d
        INNER JOIN users u ON u.user_id = d.user_id
        WHERE u.account_status = "active"';
$params = [];

if ($searchName !== '') {
    $sql .= ' AND u.full_name LIKE :search';
    $params['search'] = '%' . $searchName . '%';
}

if ($specialization !== '') {
    $sql .= ' AND d.specialization = :spec';
    $params['spec'] = $specialization;
}

$sql .= ' ORDER BY d.availability_status ASC, open_slots DESC, u.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$pageTitle = 'Available Doctors';
$layoutMode = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ccams-card p-4 mb-4">
    <form method="get" action="<?php echo e(app_url('student/doctors.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label for="searchDoctor" class="form-label small fw-semibold">Search Doctor by Name</label>
            <input type="text" id="searchDoctor" name="search" class="form-control"
                   value="<?php echo e($searchName); ?>" placeholder="e.g. Dr. Kwame Mensah">
        </div>
        <div class="col-md-4">
            <label for="filterSpec" class="form-label small fw-semibold">Filter by Specialization</label>
            <select id="filterSpec" name="specialization" class="form-select">
                <option value="">All Specializations</option>
                <?php foreach ($specList as $spec): ?>
                    <option value="<?php echo e($spec); ?>" <?php echo $specialization === $spec ? 'selected' : ''; ?>>
                        <?php echo e($spec); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <a href="<?php echo e(app_url('student/doctors.php')); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<?php if (empty($doctors)): ?>
    <div class="ccams-card p-5 text-center text-muted">
        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
        No campus doctors matched your search criteria.
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($doctors as $doc): ?>
            <div class="col-md-6 col-xl-4">
                <div class="ccams-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge <?php echo status_badge_class((string) $doc['availability_status']); ?>">
                                <?php echo e(ucfirst((string) $doc['availability_status'])); ?>
                            </span>
                            <span class="small text-muted fw-semibold">
                                <?php echo (int) $doc['open_slots']; ?> open slot(s)
                            </span>
                        </div>
                        <h2 class="h5 fw-bold mb-1"><?php echo e($doc['full_name']); ?></h2>
                        <p class="text-primary small fw-semibold mb-2"><?php echo e($doc['specialization']); ?></p>
                        <p class="small text-muted mb-3">
                            <i class="bi bi-envelope me-1"></i> <?php echo e($doc['email']); ?>
                        </p>
                    </div>
                    <?php if ($doc['availability_status'] === 'available' && (int) $doc['open_slots'] > 0): ?>
                        <a href="<?php echo e(app_url('student/book_appointment.php?doctor_id=' . (int) $doc['doctor_id'])); ?>"
                           class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-calendar-plus me-1"></i> Select &amp; View Slots
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" disabled>
                            Currently No Open Slots
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
