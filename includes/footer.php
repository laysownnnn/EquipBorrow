<?php
/**
 * includes/footer.php
 * ------------------------------------------------------------
 * Page bottom: footer bar, Bootstrap JS bundle, and the
 * dark/light theme switcher script.
 * ------------------------------------------------------------
 */
?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const html = document.documentElement;
    const btn  = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    if (!btn || !icon) return;

    function syncIcon(theme) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill';
    }
    syncIcon(html.getAttribute('data-bs-theme') || 'light');

    btn.addEventListener('click', function () {
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('eb_theme', next);
        syncIcon(next);
    });
})();
</script>
</body>
</html>
