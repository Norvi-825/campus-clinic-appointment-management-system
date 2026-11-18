/**
 * CCAMS Frontend Behavior (Vanilla JavaScript)
 * Handles mobile sidebar navigation, confirmation dialogs, loading indicators,
 * password visibility toggles, and client-side form/slot UX helpers.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Dashboard Sidebar Toggle
    const sidebar = document.getElementById('dashboardSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    const closeSidebar = () => {
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
    };

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('show');
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // 2. Reusable Confirmation Dialog for forms with [data-confirm]
    const confirmModalEl = document.getElementById('ccamsConfirmModal');
    const confirmBodyEl = document.getElementById('ccamsConfirmBody');
    const confirmTitleEl = document.getElementById('ccamsConfirmTitle');
    const confirmBtnEl = document.getElementById('ccamsConfirmBtn');
    const loadingOverlay = document.getElementById('ccamsLoadingOverlay');

    let pendingForm = null;

    if (confirmModalEl && typeof bootstrap !== 'undefined') {
        const confirmModal = new bootstrap.Modal(confirmModalEl);

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') {
                    return;
                }
                event.preventDefault();
                pendingForm = form;

                if (confirmTitleEl) {
                    confirmTitleEl.textContent = form.dataset.confirmTitle || 'Confirm Action';
                }
                if (confirmBodyEl) {
                    confirmBodyEl.textContent = form.dataset.confirm || 'Are you sure you want to proceed?';
                }
                if (confirmBtnEl) {
                    confirmBtnEl.className = 'btn ' + (form.dataset.confirmBtnClass || 'btn-primary');
                    confirmBtnEl.textContent = form.dataset.confirmBtnText || 'Yes, Proceed';
                }

                confirmModal.show();
            });
        });

        if (confirmBtnEl) {
            confirmBtnEl.addEventListener('click', () => {
                if (pendingForm) {
                    pendingForm.dataset.confirmed = 'true';
                    confirmModal.hide();
                    if (loadingOverlay) loadingOverlay.classList.remove('d-none');
                    pendingForm.submit();
                }
            });
        }
    }

    // 3. Loading Indicator on Regular Forms with [data-loading]
    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (!form.dataset.confirm || form.dataset.confirmed === 'true') {
                if (form.checkValidity() && loadingOverlay) {
                    loadingOverlay.classList.remove('d-none');
                }
            }
        });
    });

    // 4. Password Visibility Toggle
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            if (!input) return;

            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    });
});
