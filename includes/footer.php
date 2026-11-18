<?php
/**
 * Shared Footer Layout, Confirmation Dialog Modal & JavaScript Imports
 */

declare(strict_types=1);

$layoutMode = $layoutMode ?? 'public';
?>
<?php if ($layoutMode === 'dashboard' && current_user()): ?>
            </main>
            <footer class="dashboard-footer text-muted small">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>&copy; <?php echo date('Y'); ?> USTED University — Campus Clinic Appointment Management System (CCAMS)</span>
                    <span>Tanoso Campus, Kumasi, Ghana &bull; Demonstration System</span>
                </div>
            </footer>
        </div>
    </div>
<?php else: ?>
    </main>
    <footer class="public-footer mt-5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="brand-emblem">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </span>
                        <div>
                            <h5 class="mb-0 fw-bold text-white">CCAMS — USTED University</h5>
                            <small class="text-white-50">Campus Clinic Appointment Management System</small>
                        </div>
                    </div>
                    <p class="text-white-50 small mb-0">
                        Providing timely, organized, and accessible healthcare appointment scheduling for
                        students and clinical staff at USTED University, Kumasi.
                    </p>
                </div>
                <div class="col-6 col-lg-3">
                    <h6 class="text-white fw-semibold mb-3">Quick Links</h6>
                    <ul class="list-unstyled small footer-links">
                        <li><a href="<?php echo e(app_url('index.php')); ?>">Homepage</a></li>
                        <li><a href="<?php echo e(app_url('about.php')); ?>">About the Clinic</a></li>
                        <li><a href="<?php echo e(app_url('contact.php')); ?>">Contact &amp; Hours</a></li>
                        <li><a href="<?php echo e(app_url('login.php')); ?>">Portal Login</a></li>
                        <li><a href="<?php echo e(app_url('register.php')); ?>">Student Registration</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h6 class="text-white fw-semibold mb-3">Clinic Contact</h6>
                    <ul class="list-unstyled small text-white-50 mb-0">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>USTED University Health Directorate, Tanoso, Kumasi</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i>+233 (0) 32 200 0199 (Clinic Desk)</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i>clinic@usted.edu.gh</li>
                        <li><i class="bi bi-clock me-2"></i>Mon – Fri: 7:30 AM – 5:00 PM | Sat: 9:00 AM – 1:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr class="border-light border-opacity-10 my-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50 gap-2">
                <span>&copy; <?php echo date('Y'); ?> USTED University Campus Clinic (CCAMS). Academic Demonstration Project.</span>
                <span>Built with PHP 8, PDO MySQL, HTML5, CSS3 &amp; Bootstrap 5</span>
            </div>
        </div>
    </footer>
<?php endif; ?>

<!-- Reusable Confirmation Modal for Important Actions -->
<div class="modal fade" id="ccamsConfirmModal" tabindex="-1" aria-labelledby="ccamsConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="ccamsConfirmTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ccamsConfirmBody">
                Are you sure you want to proceed with this action?
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ccamsConfirmBtn">Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<!-- Global Loading Indicator -->
<div id="ccamsLoadingOverlay" class="ccams-loading-overlay d-none" aria-hidden="true">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Processing...</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo e(app_url('assets/js/script.js')); ?>"></script>
</body>
</html>
