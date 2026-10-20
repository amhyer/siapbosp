// SiapBOSP — interaksi shell aplikasi (sidebar drawer, dropdown, grup menu).
(function () {
    function sidebar(open) {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sidebar-overlay');
        if (!sb) return;
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (isDesktop) {
            sb.classList.toggle('lg:-ml-72', open === false);
            return;
        }
        const willOpen = open === undefined ? sb.classList.contains('-translate-x-full') : open;
        sb.classList.toggle('-translate-x-full', !willOpen);
        if (ov) {
            ov.classList.toggle('hidden', !willOpen);
            document.body.classList.toggle('overflow-hidden', willOpen);
        }
    }

    function closeNotif() {
        document.querySelectorAll('details[data-dropdown][open]').forEach((d) => d.removeAttribute('open'));
    }

    window.toggleSidebar = function (force) {
        sidebar(force);
    };

    window.toggleGrp = function (btn) {
        const grp = btn.closest('.grp');
        if (!grp) return;
        grp.classList.toggle('open');
        const sub = grp.querySelector('.sub');
        if (sub) sub.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', grp.classList.contains('open') ? 'true' : 'false');
    };

    document.addEventListener('click', (e) => {
        if (!e.target.closest('details[data-dropdown]')) closeNotif();
        const t = e.target.closest('[data-close-sidebar]');
        if (t) sidebar(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeNotif();
            sidebar(false);
        }
    });

    window.addEventListener('resize', () => {
        const ov = document.getElementById('sidebar-overlay');
        if (window.matchMedia('(min-width: 1024px)').matches && ov) {
            ov.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            document.getElementById('sidebar')?.classList.remove('-translate-x-full');
        }
    });
})();
