<?php

/**
 * 500 Internal Server Error Page
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Initialize translations
init_translation();

$is_logged_in = isset($_SESSION['user_id']);
$page_title = __('Server Error');

// SEO - Set noindex for error pages
$page_description = __('Something went wrong on our end. Please try again later.');
$page_keywords = '';

// Send 500 HTTP status header
http_response_code(500);

require_once ROOT . '/includes/public-header.php';
?>

<main class="error-page">
    <div>
        <div class="error-code">500</div>

        <div class="error-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>

        <h1 class="section-h"><?php echo __('Something Went Wrong'); ?></h1>

        <p><?php echo __('We are experiencing a technical issue on our end. Our team has been notified and is working to fix the problem.'); ?></p>

        <div class="error-actions">
            <button onclick="window.location.reload()" class="btn-gold">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                <?php echo __('Refresh Page'); ?>
            </button>
            <a href="/" class="btn-outline-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <?php echo __('Back to Home'); ?>
            </a>
        </div>

        <div class="error-links">
            <p><?php echo __('Need immediate assistance?'); ?></p>
            <div class="link-row">
                <a href="/contact"><?php echo __('Contact Support'); ?></a>
                <?php if ($is_logged_in): ?>
                    <a href="/user/dashboard"><?php echo __('Go to Dashboard'); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <p style="font-size: 0.75rem; color: var(--muted); font-family: 'DM Mono', monospace;">
                <?php echo __('Error Code:'); ?> ERR_500_INTERNAL
                <?php
                $request_id = $_SERVER['REQUEST_ID'] ?? (defined('REQUEST_ID') ? constant('REQUEST_ID') : null);
                if ($request_id):
                ?>
                    | <?php echo __('Request ID:'); ?> <?php echo e($request_id); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>
