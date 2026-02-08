    </div><!-- End Main Content -->
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    
    <!-- Custom App JS -->
    <script src="/assets/js/app.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>
