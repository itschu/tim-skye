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

<main>
    <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden min-h-[70vh] flex items-center">
        <!-- Background Effects -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10">
            <div class="orb w-96 h-96 bg-red-500/10 top-[-100px] right-[-100px] animate-blob"></div>
            <div class="orb w-96 h-96 bg-neon-purple/10 bottom-0 left-0 animate-blob animation-delay-2000"></div>
        </div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-red-500/5 rounded-full blur-[120px] -z-10"></div>

        <div class="max-w-7xl mx-auto px-6 text-center relative z-10">
            <div data-aos="fade-up" data-aos-duration="1000">
                <!-- 500 Icon/Number -->
                <div class="mb-8">
                    <span class="text-8xl md:text-9xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-red-400 via-neon-purple to-red-400 opacity-80 select-none">
                        500
                    </span>
                </div>

                <!-- Error Icon -->
                <div class="w-24 h-24 mx-auto mb-8 rounded-3xl glass-panel border border-red-500/30 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-400"></i>
                </div>

                <h1 class="text-3xl md:text-5xl font-bold text-white mb-6">
                    <?php echo __('Something Went Wrong'); ?>
                </h1>

                <p class="text-lg text-gray-400 mb-6 max-w-xl mx-auto leading-relaxed">
                    <?php echo __('We are experiencing a technical issue on our end. Our team has been notified and is working to fix the problem.'); ?>
                </p>

                <div class="glass-panel border border-red-500/20 rounded-2xl p-6 max-w-lg mx-auto mb-10 bg-red-900/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-tools text-red-400"></i>
                        </div>
                        <div class="text-left">
                            <h3 class="text-white font-semibold mb-1"><?php echo __('What you can do:'); ?></h3>
                            <ul class="text-gray-400 text-sm space-y-1">
                                <li>• <?php echo __('Refresh the page and try again'); ?></li>
                                <li>• <?php echo __('Come back in a few minutes'); ?></li>
                                <li>• <?php echo __('Contact us if the problem persists'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-5 justify-center">
                    <button onclick="window.location.reload()" class="px-8 py-4 bg-gradient-to-r from-neon-purple to-neon-cyan text-white rounded-full font-bold shadow-lg hover:shadow-neon-cyan/30 transition-all hover:scale-105 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fas fa-sync-alt"></i>
                        <?php echo __('Refresh Page'); ?>
                    </button>
                    <a href="/" class="px-8 py-4 glass-panel text-white border border-gray-700 rounded-full font-bold hover:border-neon-purple hover:text-neon-purple transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-home"></i>
                        <?php echo __('Back to Home'); ?>
                    </a>
                </div>

                <!-- Support Contact -->
                <div class="mt-12 pt-8 border-t border-gray-800">
                    <p class="text-gray-500 text-sm mb-4"><?php echo __('Need immediate assistance?'); ?></p>
                    <div class="flex flex-wrap justify-center gap-6">
                        <a href="/contact" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-envelope"></i> <?php echo __('Contact Support'); ?>
                        </a>
                        <?php if ($is_logged_in): ?>
                            <a href="/user/dashboard" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                                <i class="fas fa-chart-pie"></i> <?php echo __('Go to Dashboard'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Error Code for Support -->
                <div class="mt-8">
                    <p class="text-gray-600 text-xs">
                        <?php echo __('Error Code:'); ?> <span class="font-mono">ERR_500_INTERNAL</span>
                        <?php
                        $request_id = $_SERVER['REQUEST_ID'] ?? (defined('REQUEST_ID') ? constant('REQUEST_ID') : null);
                        if ($request_id):
                        ?>
                            | <?php echo __('Request ID:'); ?> <span class="font-mono"><?php echo e($request_id); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>