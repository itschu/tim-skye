<?php
if (!defined('ROOT')) {
    die('Direct access denied');
}

if (!defined('INCLUDES_PATH') || !function_exists('get_setting')) {
    require_once __DIR__ . '/bootstrap.php';
}

?>

<!-- FOOTER -->
<footer>
    <div class="ft-logo"><?php echo e(get_setting('site_name', 'Investment Platform')); ?></div>
    <p class="ft-copy">&copy; <?php echo date('Y'); ?> <?php echo e(get_setting('site_name', 'Investment Platform')); ?>. <?php echo __('All rights reserved'); ?>.</p>
    <div class="ft-links">
        <a href="/terms"><?php echo __('Terms'); ?></a>
        <a href="/privacy"><?php echo __('Privacy'); ?></a>
        <a href="/disclaimer"><?php echo __('Risk Disclosure'); ?></a>
        <a href="/contact"><?php echo __('Contact'); ?></a>
    </div>
</footer>

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
    // Scroll reveal
    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((en) => {
                if (en.isIntersecting) en.target.classList.add('shown');
            });
        },
        { threshold: 0.08 },
    );
    document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

    // Stagger children in grids
    document.querySelectorAll('.testi-grid .tcard, .svc-grid .svc-card, .pkg-grid .pkg, .reasons .reason').forEach((el, i) => {
        el.style.transitionDelay = (i % 3) * 0.12 + 's';
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
