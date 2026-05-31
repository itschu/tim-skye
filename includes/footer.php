<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Footer Template
 *
 * Minimal footer template for authenticated pages with copyright only.
 *
 * Usage: require_once 'includes/footer.php';
 */

// Load required functions if not already loaded
if (!function_exists('get_setting')) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}
?>

</div><!-- End fade-in wrapper -->
</main><!-- End main-content -->

<footer class="mt-auto py-3 d-none d-md-block sidebar-width-left-margin" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-top: 1px solid rgba(0, 0, 0, 0.05);">
    <div class="container-fluid">
        <!-- <div class="d-flex justify-content-center gap-4 mb-2">
            <small class="text-muted">
                <i class="fas fa-envelope"></i> <a href="mailto:<?php echo e(get_setting('contact_email', 'support@example.com')); ?>"><?php echo e(get_setting('contact_email', 'support@example.com')); ?></a>
            </small>
            <small class="text-muted">
                <i class="fas fa-phone"></i> <a href="tel:<?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?>"><?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?></a>
            </small>
            <small class="text-muted">
                <i class="fas fa-map-marker-alt"></i> <?php echo e(get_setting('contact_address', '123 Business St, Suite 100')); ?>
            </small>
        </div> -->
        <p class="text-center text-muted mb-0 small">
            &copy; <?php echo date('Y'); ?> <?php echo e(get_setting('site_name', 'Investment Platform')); ?>. <?php echo __('All rights reserved'); ?>.
        </p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Alpine.js -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>

<!-- User Scripts -->
<script src="/assets/js/user-scripts.js?hash=<?php echo filemtime(ROOT . '/assets/js/user-scripts.js'); ?>"></script>
<?php
// Inject instant messaging/chat snippet for authenticated/user pages
$im = get_setting('instant_message_code', '');
if (!empty($im)) {
    echo $im;
}
?>
</body>

</html>