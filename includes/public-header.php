<?php

/**
 * includes/public-header.php
 * Public-facing header for unauthenticated pages.
 * Initializes translation, loads dependencies and renders a horizontal sticky nav.
 */

// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Load required functions if not already loaded
if (!function_exists('get_setting')) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}

if (!function_exists('get_available_languages')) {
    require_once __DIR__ . '/translation-functions.php';
}

// Initialize translation system BEFORE any output (guest/public context)
init_translation();

// Retrieve site settings
$site_logo = get_setting('site_logo', '');
$site_name = get_setting('site_name', 'Investment Platform');
$site_description = get_setting('site_description', '');
$site_keywords = get_setting('site_keywords', '');
$default_mode = get_setting('default_mode', 'system');

// Session state detection
$is_logged_in = isset($_SESSION['user_id']);

// Get available languages
$languages = get_available_languages();
$current_lang = get_current_language();
$current_lang_label = $languages[$current_lang] ?? 'English';

// Get current page for redirect after language switch
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

// Determine favicon path
$favicon_path = SITE_ICON;

// Page title helper
$full_title = isset($page_title) ? e($page_title) . ' - ' . e($site_name) : e($site_name);
// Meta description/keywords - allow page override via $page_description / $page_keywords
$meta_description = isset($page_description) && $page_description ? $page_description : $site_description;
$meta_keywords = isset($page_keywords) && $page_keywords ? $page_keywords : $site_keywords;

// Compute theme color by default mode
if ($default_mode === 'light') {
    $theme_color = '#ffffff';
} else {
    // dark or system fallback to dark color
    $theme_color = '#07090d';
}

?>
<!doctype html>
<html lang="en" class="scroll-smooth" data-default-mode="<?php echo e($default_mode); ?>">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="<?php echo e($theme_color); ?>" />
    <?php if (!empty($meta_description)): ?>
        <meta name="description" content="<?php echo e($meta_description); ?>" />
    <?php endif; ?>
    <?php if (!empty($meta_keywords)): ?>
        <meta name="keywords" content="<?php echo e($meta_keywords); ?>" />
    <?php endif; ?>
    <title><?php echo $full_title; ?></title>

    <?php
    // Build current URL and select an image for social previews
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $current_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    $og_image = '';
    if (!empty($favicon_path)) {
        // If the favicon path is already an absolute URL, use it directly.
        if (filter_var($favicon_path, FILTER_VALIDATE_URL) || preg_match('#^https?://#i', $favicon_path)) {
            $og_image = $favicon_path;
        } else {
            $og_image = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . ltrim($favicon_path, '/');
        }
    }
    ?>

    <!-- SEO: canonical and robots -->
    <link rel="canonical" href="<?php echo e($current_url); ?>" />
    <meta name="robots" content="index, follow" />

    <!-- Open Graph / Facebook -->
    <meta property="og:site_name" content="<?php echo e($site_name); ?>" />
    <meta property="og:title" content="<?php echo e($full_title); ?>" />
    <?php if (!empty($meta_description)): ?>
        <meta property="og:description" content="<?php echo e($meta_description); ?>" /><?php endif; ?>
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo e($current_url); ?>" />
    <?php if (!empty($og_image)): ?>
        <meta property="og:image" content="<?php echo e($og_image); ?>" /><?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo e($full_title); ?>" />
    <?php if (!empty($meta_description)): ?>
        <meta name="twitter:description" content="<?php echo e($meta_description); ?>" /><?php endif; ?>
    <?php if (!empty($og_image)): ?>
        <meta name="twitter:image" content="<?php echo e($og_image); ?>" /><?php endif; ?>

    <?php if (!empty($favicon_path)): ?>
        <link rel="icon" type="image/png" href="<?php echo e($favicon_path); ?>">
    <?php endif; ?>

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Mono:wght@300;400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link href="/assets/css/public-style.css?hash=<?php echo filemtime(ROOT . '/assets/css/public-style.css'); ?>" rel="stylesheet" />

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
</head>

<body x-data="{ mobileMenuOpen: false }">

    <!-- NAV -->
    <nav>
        <a href="/" class="logo">
            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" style="height:42px; width:auto; display:block;">
            <?php else: ?>
                <?php echo e($site_name); ?><em>.</em>
            <?php endif; ?>
        </a>

        <ul class="nav-links">
            <li><a href="/"><?php echo __('Home'); ?></a></li>
            <li><a href="/about"><?php echo __('About'); ?></a></li>
            <li><a href="/#packages"><?php echo __('Plans'); ?></a></li>
            <li><a href="/#testimonials"><?php echo __('Testimonials'); ?></a></li>
            <li><a href="/#faq"><?php echo __('FAQs'); ?></a></li>
            <li><a href="/contact"><?php echo __('Contact'); ?></a></li>
        </ul>

        <div class="nav-auth">
            <?php if (is_google_translate_enabled()): ?>
                <div style="display:none;"><?php render_google_translate_widget('navbar'); ?></div>
            <?php else: ?>
                <div class="relative" x-data="{ langOpen: false }" @click.away="langOpen = false">
                    <form method="POST" action="/actions/switch-language-public.php" id="publicLangForm">
                        <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                        <input type="hidden" name="lang" id="publicSelectedLang" value="<?php echo e($current_lang); ?>">
                        <button type="button"
                            style="background:none;border:none;color:var(--muted-light);font-family:'DM Mono',monospace;font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;gap:0.4rem;"
                            @click="langOpen = !langOpen">
                            <span><?php echo e($current_lang_label); ?></span>
                            <span style="font-size:0.5rem;transition:transform 0.2s;" :style="langOpen ? 'transform:rotate(180deg)' : ''">▼</span>
                        </button>
                        <div x-show="langOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            style="display:none;position:absolute;right:0;top:120%;min-width:140px;background:var(--bg-card);border:0.5px solid var(--border);padding:0.4rem 0;z-index:300;">
                            <?php foreach ($languages as $code => $label): ?>
                                <button type="button"
                                    style="display:block;width:100%;text-align:left;padding:0.4rem 0.8rem;background:none;border:none;color:<?php echo $code === $current_lang ? 'var(--gold)' : 'var(--muted-light)'; ?>;font-family:'Outfit',sans-serif;font-size:0.8rem;cursor:pointer;"
                                    @click="document.getElementById('publicSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('publicLangForm').submit();">
                                    <?php echo e($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <a href="/login" style="font-size:0.72rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted-light);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--gold-light)'" onmouseout="this.style.color='var(--muted-light)'"><?php echo __('Login'); ?></a>
                <a href="/register" class="btn-gold-sm"><?php echo __('Register'); ?></a>
            <?php else: ?>
                <a href="/user/dashboard" class="btn-gold-sm"><?php echo __('Dashboard'); ?></a>
            <?php endif; ?>
        </div>

        <div class="mobile-toggle">
            <button @click="mobileMenuOpen = !mobileMenuOpen" style="background:none;border:none;color:var(--txt);font-size:1.2rem;cursor:pointer;">☰</button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen"
        x-transition:enter="transition-opacity duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileMenuOpen = false"
        style="display:none;position:fixed;inset:0;background:rgba(7,9,13,0.85);backdrop-filter:blur(12px);z-index:150;top:64px;">
    </div>
    <div x-show="mobileMenuOpen"
        x-collapse
        style="display:none;position:fixed;left:0;right:0;top:64px;background:var(--bg-card);border-top:0.5px solid var(--border);z-index:160;padding:1.5rem;max-height:calc(100vh - 64px);overflow-y:auto;">
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <a href="/" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('Home'); ?></a>
            <a href="/about" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('About'); ?></a>
            <a href="/#packages" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('Plans'); ?></a>
            <a href="/#testimonials" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('Testimonials'); ?></a>
            <a href="/#faq" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('FAQs'); ?></a>
            <a href="/contact" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);text-decoration:none;"><?php echo __('Contact'); ?></a>
            <div style="border-top:0.5px solid var(--border);padding-top:1rem;margin-top:0.5rem;display:flex;flex-direction:column;gap:0.75rem;">
                <?php if (!$is_logged_in): ?>
                    <a href="/login" @click="mobileMenuOpen = false" style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted-light);text-decoration:none;"><?php echo __('Login'); ?></a>
                    <a href="/register" @click="mobileMenuOpen = false" class="btn-gold-sm" style="text-align:center;"><?php echo __('Register'); ?></a>
                <?php else: ?>
                    <a href="/user/dashboard" @click="mobileMenuOpen = false" class="btn-gold-sm" style="text-align:center;"><?php echo __('Dashboard'); ?></a>
                <?php endif; ?>
            </div>
            <?php if (!is_google_translate_enabled()): ?>
                <div style="border-top:0.5px solid var(--border);padding-top:1rem;margin-top:0.5rem;">
                    <p style="font-size:0.6rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--muted);margin-bottom:0.5rem;"><?php echo __('Language'); ?></p>
                    <form method="POST" action="/actions/switch-language-public.php" id="mobileLangForm">
                        <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                        <input type="hidden" name="lang" id="mobileSelectedLang" value="<?php echo e($current_lang); ?>">
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                            <?php foreach ($languages as $code => $label): ?>
                                <button type="button"
                                    style="padding:0.3rem 0.6rem;background:none;border:0.5px solid <?php echo $code === $current_lang ? 'var(--gold)' : 'var(--border)'; ?>;color:<?php echo $code === $current_lang ? 'var(--gold)' : 'var(--muted-light)'; ?>;font-family:'Outfit',sans-serif;font-size:0.75rem;cursor:pointer;"
                                    @click="document.getElementById('mobileSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('mobileLangForm').submit();">
                                    <?php echo e($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error']) || isset($_SESSION['success'])): ?>
        <!-- Flash Messages -->
        <!-- <div style="padding-top:80px;max-width:1200px;margin:0 auto;padding-left:1.5rem;padding-right:1.5rem;"> -->
        <?php if (isset($_SESSION['error'])): ?>
            <div x-data="{ show: true }" x-show="show" x-transition class="alert alert-red" role="alert">
                <span><?php echo e($_SESSION['error']); ?></span>
                <button @click="show = false" class="alert-close" type="button">✕</button>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div x-data="{ show: true }" x-show="show" x-transition class="alert alert-green" role="alert">
                <span><?php echo e($_SESSION['success']); ?></span>
                <button @click="show = false" class="alert-close" type="button">✕</button>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <!-- </div> -->
    <?php endif; ?>