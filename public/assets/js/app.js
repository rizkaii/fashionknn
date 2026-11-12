document.addEventListener('DOMContentLoaded', function () {
    // ── Lucide icons init ────────────────────────────────────────────────────
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // ── Sidebar Slide Hidden & Responsive Toggle ─────────────────────────────
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const closeBtn  = document.getElementById('sidebarClose');

    const isDesktop = () => window.innerWidth > 992;

    // Restore desktop preference
    if (isDesktop() && localStorage.getItem('sidebar_hidden') === '1') {
        document.body.classList.add('sidebar-hidden');
    }

    function toggleSidebar() {
        if (isDesktop()) {
            document.body.classList.toggle('sidebar-hidden');
            const isHidden = document.body.classList.contains('sidebar-hidden');
            localStorage.setItem('sidebar_hidden', isHidden ? '1' : '0');
        } else {
            // Mobile drawer slide
            if (sidebar && sidebar.classList.contains('open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        }
    }

    function openMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (hamburger) {
        hamburger.addEventListener('click', function (e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isDesktop()) {
                document.body.classList.add('sidebar-hidden');
                localStorage.setItem('sidebar_hidden', '1');
            } else {
                closeMobileSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!isDesktop() && sidebar && sidebar.classList.contains('open')) {
                closeMobileSidebar();
            }
        }
    });

    // Handle Window Resize smoothly
    window.addEventListener('resize', function () {
        if (isDesktop()) {
            closeMobileSidebar();
            if (localStorage.getItem('sidebar_hidden') === '1') {
                document.body.classList.add('sidebar-hidden');
            } else {
                document.body.classList.remove('sidebar-hidden');
            }
        } else {
            document.body.classList.remove('sidebar-hidden');
        }
    });

    // ── Delete confirmations ─────────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.dataset.confirm || 'Yakin hapus data ini?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // ── Auto-dismiss alerts after 5 s ────────────────────────────────────────
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.style.display = 'none'; }, 500);
        }, 5000);
    });

    // ── Re-init Lucide after dynamic content ─────────────────────────────────
    window.reinitIcons = function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    // ── Generic form loading state ───────────────────────────────────────────
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = btn.dataset.loadingText || 'Memproses...';
            }
        });
    });
});
