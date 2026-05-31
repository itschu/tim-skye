<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Close content area and main content container opened in admin-header.php
?>

</div><!-- /.content-area -->
</main><!-- /.main-content -->
</div><!-- /.admin-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/medium-zoom@1.0.8/dist/medium-zoom.min.js"></script>
<script src="/assets/js/admin-scripts.js?hash=<?php echo filemtime(ROOT . '/assets/js/admin-scripts.js'); ?>"></script>
<?php
// Inject instant messaging/chat snippet into admin pages if configured
$im = get_setting('instant_message_code', '');
if (!empty($im)) {
    echo $im;
}
?>
</body>

</html>