        </main>
    </div>

    <!-- Scripts -->
    <script src="<?= url('assets/js/main.js') ?>"></script>
    <script>
        // Initialize Lucide Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Mobile Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mainSidebar = document.getElementById('main-sidebar');
        if (sidebarToggle && mainSidebar) {
            sidebarToggle.addEventListener('click', () => {
                mainSidebar.classList.toggle('-translate-x-full');
            });
        }
    </script>
</body>
</html>
