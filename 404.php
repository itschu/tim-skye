<?php

/**
 * 404 Not Found Error Page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Initialize translations
init_translation();

$is_logged_in = isset($_SESSION['user_id']);
$page_title = __('Page Not Found');

// SEO - Set noindex for error pages
$page_description = __('The page you are looking for does not exist or has been moved.');
$page_keywords = '';

// Send 404 HTTP status header
http_response_code(404);

require_once ROOT . '/includes/public-header.php';
?>

<main class="error-page">
    <div>
        <div class="error-code">404</div>

        <div class="error-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
        </div>

        <h1 class="section-h"><?php echo __('Page Not Found'); ?></h1>

        <p><?php echo __('The page you are looking for does not exist or has been moved. Please check the URL or navigate back to the homepage.'); ?></p>

        <div class="error-actions">
            <a href="/" class="btn-gold">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <?php echo __('Back to Home'); ?>
            </a>
            <?php if ($is_logged_in): ?>
                <a href="/user/dashboard" class="btn-outline-sm">
                    <?php echo __('Dashboard'); ?>
                </a>
            <?php else: ?>
                <a href="/contact" class="btn-outline-sm">
                    <?php echo __('Contact Support'); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="error-links">
            <p><?php echo __('Popular pages you might be looking for:'); ?></p>
            <div class="link-row">
                <a href="/about"><?php echo __('About Us'); ?></a>
                <a href="/contact"><?php echo __('Contact'); ?></a>
                <a href="/terms"><?php echo __('Terms'); ?></a>
                <a href="/privacy"><?php echo __('Privacy'); ?></a>
            </div>
        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
