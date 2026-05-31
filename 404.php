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

<main>
    <section class="relative pt-32 pb-20 lg:pt-10 lg:pb-32 overflow-hidden min-h-[70vh] flex items-center">
        <!-- Background Effects -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10">
            <div class="orb w-96 h-96 bg-neon-purple/10 top-[-100px] left-[-100px] animate-blob"></div>
            <div class="orb w-96 h-96 bg-neon-cyan/10 bottom-0 right-0 animate-blob animation-delay-2000"></div>
        </div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-neon-purple/5 rounded-full blur-[120px] -z-10"></div>

        <div class="max-w-7xl mx-auto px-6 text-center relative z-10">
            <div data-aos="fade-up" data-aos-duration="1000">
                <!-- 404 Icon/Number -->
                <div class="mb-8">
                    <span class="text-8xl md:text-9xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-neon-purple via-neon-cyan to-neon-pink opacity-80 select-none">
                        404
                    </span>
                </div>

                <!-- Error Icon -->
                <div class="w-24 h-24 mx-auto mb-8 rounded-3xl glass-panel border border-neon-purple/30 flex items-center justify-center">
                    <i class="fas fa-compass text-4xl text-neon-cyan"></i>
                </div>

                <h1 class="text-3xl md:text-5xl font-bold text-white mb-6">
                    <?php echo __('Page Not Found'); ?>
                </h1>

                <p class="text-lg text-gray-400 mb-10 max-w-xl mx-auto leading-relaxed">
                    <?php echo __('The page you are looking for does not exist or has been moved. Please check the URL or navigate back to the homepage.'); ?>
                </p>

                <div class="flex flex-col sm:flex-row gap-5 justify-center">
                    <a href="/" class="px-8 py-4 bg-gradient-to-r from-neon-purple to-neon-cyan text-white rounded-full font-bold shadow-lg hover:shadow-neon-cyan/30 transition-all hover:scale-105 flex items-center justify-center gap-2">
                        <i class="fas fa-home"></i>
                        <?php echo __('Back to Home'); ?>
                    </a>
                    <?php if ($is_logged_in): ?>
                        <a href="/user/dashboard" class="px-8 py-4 glass-panel text-white border border-gray-700 rounded-full font-bold hover:border-neon-purple hover:text-neon-purple transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-chart-pie"></i>
                            <?php echo __('Dashboard'); ?>
                        </a>
                    <?php else: ?>
                        <a href="/contact" class="px-8 py-4 glass-panel text-white border border-gray-700 rounded-full font-bold hover:border-neon-purple hover:text-neon-purple transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-envelope"></i>
                            <?php echo __('Contact Support'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Suggested Links -->
                <div class="mt-16 pt-8 border-t border-gray-800">
                    <p class="text-gray-500 text-sm mb-6"><?php echo __('Popular pages you might be looking for:'); ?></p>
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="/about" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-info-circle text-xs"></i> <?php echo __('About Us'); ?>
                        </a>
                        <a href="/contact" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-envelope text-xs"></i> <?php echo __('Contact'); ?>
                        </a>
                        <a href="/terms" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-file-contract text-xs"></i> <?php echo __('Terms'); ?>
                        </a>
                        <a href="/privacy" class="text-gray-400 hover:text-neon-cyan transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-shield-alt text-xs"></i> <?php echo __('Privacy'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT . '/includes/public-footer.php'; ?>