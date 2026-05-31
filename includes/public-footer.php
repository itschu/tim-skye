<?php
if (!defined('ROOT')) {
    die('Direct access denied');
}

if (!defined('INCLUDES_PATH') || !function_exists('get_setting')) {
    require_once __DIR__ . '/bootstrap.php';
}

?>

<footer class="bg-black py-16 border-t border-gray-900 text-sm">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12">
        <div class="mb-4">
            <h5 class="text-2xl font-bold text-white mb-4">
                <?php echo e(get_setting('site_name', 'Investment Platform')); ?>
            </h5>
            <p class="text-gray-500 mb-6"><?php echo e(get_setting('site_description', __('Your trusted investment partner'))); ?></p>
            <div class="flex gap-4">
                <?php
                $social_setting = get_setting('social_links', '');
                $social = [];
                if ($social_setting) {
                    $decoded = json_decode($social_setting, true);
                    if (is_array($decoded)) $social = $decoded;
                }

                $platforms = [
                    'facebook' => ['icon' => 'fab fa-facebook-f', 'color' => 'hover:bg-neon-purple'],
                    'twitter' => ['icon' => 'fab fa-twitter', 'color' => 'hover:bg-neon-cyan'],
                    'linkedin' => ['icon' => 'fab fa-linkedin-in', 'color' => 'hover:bg-blue-600'],
                    'instagram' => ['icon' => 'fab fa-instagram', 'color' => 'hover:bg-neon-pink'],
                    'youtube' => ['icon' => 'fab fa-youtube', 'color' => 'hover:bg-red-600'],
                    'telegram' => ['icon' => 'fab fa-telegram', 'color' => 'hover:bg-sky-500']
                ];

                foreach ($platforms as $key => $meta) {
                    if (!empty($social[$key])) {
                        $url = e($social[$key]);
                        echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center ' . $meta['color'] . ' transition-colors text-white" aria-label="' . ucfirst($key) . '"><i class="' . $meta['icon'] . '"></i></a>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="text-white font-bold mb-4 uppercase tracking-wider"><?php echo __('Quick Links'); ?></h6>
            <ul class="space-y-2 text-gray-500">
                <li><a href="/" class="hover:text-neon-purple transition-colors"><?php echo __('Home'); ?></a></li>
                <li><a href="/about" class="hover:text-neon-purple transition-colors"><?php echo __('About'); ?></a></li>
                <li><a href="/contact" class="hover:text-neon-purple transition-colors"><?php echo __('Contact'); ?></a></li>
                <li><a href="/./#faq" class="hover:text-neon-purple transition-colors"><?php echo __('FAQ'); ?></a></li>
            </ul>
        </div>

        <div class="mb-4">
            <h6 class="text-white font-bold mb-4 uppercase tracking-wider"><?php echo __('Legal'); ?></h6>
            <ul class="space-y-2 text-gray-500">
                <li><a href="/terms" class="hover:text-neon-purple transition-colors"><?php echo __('Terms of Service'); ?></a></li>
                <li><a href="/privacy" class="hover:text-neon-purple transition-colors"><?php echo __('Privacy Policy'); ?></a></li>
                <li><a href="/disclaimer" class="hover:text-neon-purple transition-colors"><?php echo __('Risk Disclaimer'); ?></a></li>
            </ul>
        </div>

        <div class="mb-4">
            <h6 class="text-white font-bold mb-4 uppercase tracking-wider"><?php echo __('Contact Us'); ?></h6>
            <ul class="space-y-2 text-gray-500">
                <li class="flex items-center gap-3"><i class="fas fa-envelope text-neon-purple"></i> <a href="mailto:<?php echo e(get_setting('contact_email', 'support@example.com')); ?>" class="hover:text-white transition-colors"><?php echo e(get_setting('contact_email', 'support@example.com')); ?></a></li>
                <li class="flex items-center gap-3"><i class="fas fa-phone text-neon-purple"></i> <a href="tel:<?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?>" class="hover:text-white transition-colors"><?php echo e(get_setting('contact_phone', '+1 (555) 123-4567')); ?></a></li>
                <li class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-neon-purple"></i> <?php echo nl2br(e(get_setting('contact_address', "123 Business St, Suite 100\nFinancial District, NY 10001"))); ?></li>
            </ul>
        </div>
    </div>

    <div class="border-top border-gray-900 mt-12 pt-8 text-center text-gray-600">
        <small>&copy; <?php echo date('Y'); ?> <?php echo e(get_setting('site_name', 'Investment Platform')); ?>. <?php echo __('All rights reserved'); ?>.</small>
    </div>
</footer>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="/assets/js/public-scripts.js?hash=<?php echo filemtime(ROOT . '/assets/js/public-scripts.js'); ?>"></script>
<?php
// Structured data for SEO - Organization + WebSite
$org_name = get_setting('site_name', 'Investment Platform');
$org_description = get_setting('site_description', '');
$site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>
<script type="application/ld+json">
    <?php
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'url' => $site_url,
        'name' => $org_name,
        'description' => $org_description
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ?>
</script>
<script>
    // Initialize AOS after library is loaded
    document.addEventListener('DOMContentLoaded', function() {
        AOS.init({
            once: true,
            offset: 50, // Triggers sooner on scroll
            duration: 800,
            easing: 'ease-in-out',
        });
    });
</script>

<?php
// Inject instant messaging/chat snippet if set
$im = get_setting('instant_message_code', '');
if (!empty($im)) {
    echo $im;
}
?>

</body>

</html>