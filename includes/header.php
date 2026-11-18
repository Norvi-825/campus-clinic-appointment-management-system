<?php
/**
 * Shared Header Layout (Public & Role-Based Dashboard Navigation)
 *
 * Expected variables before including:
 *   $pageTitle (string) - Document title
 *   $layoutMode (string) - 'public' or 'dashboard' (default: 'public')
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'USTED University — Campus Clinic Appointment Management System';
$layoutMode = $layoutMode ?? 'public';
$authUser = current_user();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | CCAMS — USTED University</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo e(app_url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="<?php echo $layoutMode === 'dashboard' ? 'ccams-dashboard-body' : 'ccams-public-body'; ?>">
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a>

<?php if ($layoutMode === 'dashboard' && $authUser): ?>
    <div class="dashboard-wrapper">
        <!-- Mobile Backdrop -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Sidebar Navigation -->
        <aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Dashboard Navigation">
            <div class="sidebar-brand">
                <a href="<?php echo e(app_url('index.php')); ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white">
                    <span class="brand-emblem">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </span>
                    <div>
                        <div class="fw-bold lh-1 fs-6">CCAMS</div>
                        <small class="text-white-50" style="font-size: 0.72rem;">USTED University Clinic</small>
                    </div>
                </a>
                <button type="button" class="btn btn-sm text-white d-lg-none ms-auto" id="sidebarCloseBtn" aria-label="Close navigation menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="sidebar-role-pill">
                <i class="bi bi-shield-check me-1"></i>
                <?php echo e(ucfirst($authUser['role'])); ?> Portal
            </div>

            <nav class="sidebar-nav">
                <?php if ($authUser['role'] === 'student'): ?>
                    <a class="sidebar-link <?php echo is_active_nav('student/dashboard.php'); ?>" href="<?php echo e(app_url('student/dashboard.php')); ?>">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('student/doctors.php'); ?>" href="<?php echo e(app_url('student/doctors.php')); ?>">
                        <i class="bi bi-person-badge"></i><span>Available Doctors</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('student/book_appointment.php'); ?>" href="<?php echo e(app_url('student/book_appointment.php')); ?>">
                        <i class="bi bi-calendar-plus"></i><span>Book Appointment</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('student/my_appointments.php') || is_active_nav('student/appointment_details.php') ? 'active' : ''; ?>" href="<?php echo e(app_url('student/my_appointments.php')); ?>">
                        <i class="bi bi-calendar2-check"></i><span>My Appointments</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('student/notifications.php'); ?>" href="<?php echo e(app_url('student/notifications.php')); ?>">
                        <i class="bi bi-bell"></i><span>Notifications</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('student/profile.php'); ?>" href="<?php echo e(app_url('student/profile.php')); ?>">
                        <i class="bi bi-person-gear"></i><span>Profile &amp; Password</span>
                    </a>
                <?php elseif ($authUser['role'] === 'doctor'): ?>
                    <a class="sidebar-link <?php echo is_active_nav('doctor/dashboard.php'); ?>" href="<?php echo e(app_url('doctor/dashboard.php')); ?>">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('doctor/my_appointments.php') || is_active_nav('doctor/appointment_details.php') ? 'active' : ''; ?>" href="<?php echo e(app_url('doctor/my_appointments.php')); ?>">
                        <i class="bi bi-clipboard2-pulse"></i><span>Assigned Appointments</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('doctor/profile.php'); ?>" href="<?php echo e(app_url('doctor/profile.php')); ?>">
                        <i class="bi bi-person-vcard"></i><span>My Profile &amp; Availability</span>
                    </a>
                <?php elseif ($authUser['role'] === 'admin'): ?>
                    <a class="sidebar-link <?php echo is_active_nav('admin/dashboard.php'); ?>" href="<?php echo e(app_url('admin/dashboard.php')); ?>">
                        <i class="bi bi-speedometer2"></i><span>Admin Dashboard</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('admin/manage_doctors.php') || is_active_nav('admin/add_doctor.php') || is_active_nav('admin/edit_doctor.php') ? 'active' : ''; ?>" href="<?php echo e(app_url('admin/manage_doctors.php')); ?>">
                        <i class="bi bi-hospital"></i><span>Manage Doctors</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('admin/manage_slots.php'); ?>" href="<?php echo e(app_url('admin/manage_slots.php')); ?>">
                        <i class="bi bi-clock-history"></i><span>Appointment Slots</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('admin/manage_appointments.php'); ?>" href="<?php echo e(app_url('admin/manage_appointments.php')); ?>">
                        <i class="bi bi-calendar2-week"></i><span>All Appointments</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('admin/manage_users.php'); ?>" href="<?php echo e(app_url('admin/manage_users.php')); ?>">
                        <i class="bi bi-people"></i><span>User Accounts</span>
                    </a>
                    <a class="sidebar-link <?php echo is_active_nav('admin/notifications.php'); ?>" href="<?php echo e(app_url('admin/notifications.php')); ?>">
                        <i class="bi bi-bell-fill"></i><span>Notification Logs</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <a href="<?php echo e(app_url('index.php')); ?>" class="sidebar-link">
                    <i class="bi bi-globe2"></i><span>Clinic Homepage</span>
                </a>
                <form method="post" action="<?php echo e(app_url('logout.php')); ?>" class="mt-1">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="sidebar-link w-100 border-0 bg-transparent text-start text-danger-soft">
                        <i class="bi bi-box-arrow-right"></i><span>Log Out</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Dashboard Main Content Column -->
        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-outline-primary btn-sm d-lg-none" id="sidebarToggleBtn" aria-label="Open navigation menu">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0 fw-bold text-dark"><?php echo e($pageTitle); ?></h1>
                        <small class="text-muted">USTED University Campus Clinic</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="user-profile-chip">
                        <div class="avatar-circle">
                            <?php echo e(strtoupper(substr((string) $authUser['full_name'], 0, 1))); ?>
                        </div>
                        <div class="d-none d-sm-block text-end">
                            <div class="fw-semibold small lh-1"><?php echo e($authUser['full_name']); ?></div>
                            <small class="text-muted text-capitalize"><?php echo e($authUser['role']); ?></small>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main-content" class="dashboard-content">
                <?php if (!empty($flashes)): ?>
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show shadow-sm" role="alert">
                            <?php echo e($flash['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
<?php else: ?>
    <!-- Public Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-xs">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo e(app_url('index.php')); ?>">
                <span class="brand-emblem brand-emblem-blue">
                    <i class="bi bi-heart-pulse-fill"></i>
                </span>
                <div>
                    <span class="fw-bold text-primary-dark d-block lh-1">CCAMS</span>
                    <small class="text-muted d-block" style="font-size: 0.72rem;">USTED University Clinic</small>
                </div>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar"
                    aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_active_nav('index.php'); ?>" href="<?php echo e(app_url('index.php')); ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_active_nav('about.php'); ?>" href="<?php echo e(app_url('about.php')); ?>">About Clinic</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_active_nav('contact.php'); ?>" href="<?php echo e(app_url('contact.php')); ?>">Contact</a>
                    </li>
                </ul>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($authUser): ?>
                        <a href="<?php echo e(app_url(role_dashboard_path($authUser['role']))); ?>" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-speedometer2 me-1"></i> My Dashboard
                        </a>
                        <form method="post" action="<?php echo e(app_url('logout.php')); ?>" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log Out</button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo e(app_url('login.php')); ?>" class="btn btn-outline-primary btn-sm px-3">Log In</a>
                        <a href="<?php echo e(app_url('register.php')); ?>" class="btn btn-primary btn-sm px-3">Student Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content">
        <?php if (!empty($flashes)): ?>
            <div class="container pt-3">
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show shadow-sm" role="alert">
                        <?php echo e($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php endif; ?>
