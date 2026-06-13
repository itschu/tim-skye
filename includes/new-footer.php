<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Load required functions if not already loaded
if (!function_exists('get_setting') || !function_exists('e')) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}

$site_name = get_setting('site_name', 'Investment Platform');
?>

    </main><!-- End main content wrapper opened in new-header.php -->

    <?php require_once __DIR__ . '/new-mobile-menu.php'; ?>

    <!-- Minimal footer -->
    <footer class="mt-auto py-4 border-t border-zinc-800/60 bg-brand-dark/60 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-zinc-500 text-xs mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo e($site_name); ?>. <?php echo e(__('All rights reserved')); ?>.
            </p>
        </div>
    </footer>

</div><!-- End root Alpine wrapper opened in new-head.php -->

<!-- Extra scripts -->
<?php if (!empty($extra_scripts)) echo $extra_scripts; ?>

<?php
// Inject instant messaging/chat snippet for authenticated/user pages
$im = get_setting('instant_message_code', '');
if (!empty($im)) {
    echo $im;
}
?>

</body>
</html>
