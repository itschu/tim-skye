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
    $theme_color = '#0B0F19';
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

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            900: '#0B0F19', // Deep Space Background
                            800: '#111827', // Card Background
                            700: '#1F2937', // Border
                        },
                        neon: {
                            purple: '#8b5cf6',
                            cyan: '#06b6d4',
                            pink: '#ec4899'
                        }
                    },
                    backgroundImage: {
                        'hero-glow': 'conic-gradient(from 180deg at 50% 50%, #0B0F19 0deg, #1e1b4b 180deg, #0B0F19 360deg)',
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%': {
                                transform: 'translate(0px, 0px) scale(1)'
                            },
                            '33%': {
                                transform: 'translate(30px, -50px) scale(1.1)'
                            },
                            '66%': {
                                transform: 'translate(-20px, 20px) scale(0.9)'
                            },
                            '100%': {
                                transform: 'translate(0px, 0px) scale(1)'
                            },
                        }
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.13.3/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.13.3/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        body {
            /* Prevent horizontal scroll during slide-in animations */
            -webkit-overflow-scrolling: touch;
            background-color: #0B0F19;
            color: #ffffff;
        }

        html,
        body {
            overflow-x: hidden !important;
        }

        /* Improve animation rendering and avoid overflow from transform animations (AOS, custom slide-ins) */
        [data-aos],
        .aos-init,
        .aos-animate {
            will-change: transform, opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            /* Ensure transformed elements don't create layout overflow */
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0B0F19;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #8b5cf6;
        }

        /* Glassmorphism */
        .glass-panel {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Vibrant Text Gradient */
        .text-glow-gradient {
            background: linear-gradient(to right, #22d3ee, #a78bfa, #e879f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: gradientMove 5s ease infinite;
        }

        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Blob Animation */
        @keyframes blob {
            0% {
                transform: translate(0px, 0px) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }

            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }


        /* Ambient Background Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: 0;
        }

        /* 3D Perspective Grid */
        .perspective-grid {
            background-size: 50px 50px;
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            mask-image: linear-gradient(to bottom, black 40%, transparent 100%);
            transform: perspective(1000px) rotateX(60deg) translateY(-100px) scale(2);
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: 0.3;
            pointer-events: none;
        }

        /* Hover Glow Card */
        .hover-glow-card:hover {
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        /* Float Animation */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delayed {
            animation: float 6s ease-in-out 3s infinite;
        }

        /* Pulse Slow Animation */
        @keyframes pulse-slow {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse-slow {
            animation: pulse-slow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* x-cloak for Alpine.js */
        [x-cloak] {
            display: none !important;
        }
    </style>

    <noscript>
        <style>
            [data-aos] {
                opacity: 1 !important;
                transform: translate(0) scale(1) !important;
            }
        </style>
    </noscript>
</head>

<body x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20) ? true : false" :class="{ 'overflow-hidden': mobileMenuOpen }" class="antialiased">

    <nav :class="{ 'glass-panel shadow-lg shadow-neon-purple/5': scrolled, 'bg-transparent': !scrolled }" class="fixed w-full z-50 top-0 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-1 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <a href="/" class="flex items-center gap-2 group">
                    <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                        <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" class="h-16 w-auto">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-gradient-to-br from-neon-cyan to-neon-purple rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-neon-purple/30">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <span class="text-xl font-bold tracking-tight text-white group-hover:text-neon-cyan transition-colors"><?php echo e($site_name); ?></span>
                    <?php endif; ?>
                </a>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('Home'); ?></a>
                    <a href="/about" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('About'); ?></a>
                    <a href="/./#plans-section" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('Plans'); ?></a>
                    <a href="/./#testimonials" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('Testimonials'); ?></a>
                    <a href="/./#faq" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('FAQs'); ?></a>
                    <a href="/contact" class="text-sm font-medium text-gray-300 hover:text-white transition-all"><?php echo __('Contact'); ?></a>
                </div>

                <div class="hidden md:flex items-center gap-4">
                    <!-- Language Switcher / Google Translate -->
                    <?php if (is_google_translate_enabled()): ?>
                        <!-- Google Translate Widget -->
                        <div class="flex items-center">
                            <?php render_google_translate_widget('navbar'); ?>
                        </div>
                    <?php else: ?>
                        <!-- Local Language Switcher -->
                        <div class="relative" x-data="{ langOpen: false }" @click.away="langOpen = false">
                            <form method="POST" action="/actions/switch-language-public.php" id="publicLangForm">
                                <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                                <input type="hidden" name="lang" id="publicSelectedLang" value="<?php echo e($current_lang); ?>">
                                <button type="button"
                                    class="flex items-center gap-2 text-sm font-medium text-gray-300 hover:text-white transition-all py-2 px-3 rounded-lg hover:bg-white/5"
                                    @click="langOpen = !langOpen">
                                    <i class="fas fa-globe text-neon-cyan"></i>
                                    <span><?php echo e($current_lang_label); ?></span>
                                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': langOpen }"></i>
                                </button>
                                <div x-show="langOpen"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 transform scale-100"
                                    x-transition:leave-end="opacity-0 transform scale-95"
                                    class="absolute right-0 mt-2 w-40 glass-panel rounded-xl border border-gray-700 shadow-xl overflow-hidden z-50"
                                    style="display: none;">
                                    <?php foreach ($languages as $code => $label): ?>
                                        <button type="button"
                                            class="w-full text-left px-4 py-2.5 text-sm flex items-center gap-2 hover:bg-white/10 transition-colors <?php echo $code === $current_lang ? 'text-neon-cyan bg-neon-cyan/10' : 'text-gray-300'; ?>"
                                            @click="document.getElementById('publicSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('publicLangForm').submit();">
                                            <?php if ($code === $current_lang): ?>
                                                <i class="fas fa-check text-xs"></i>
                                            <?php else: ?>
                                                <span class="w-3"></span>
                                            <?php endif; ?>
                                            <?php echo e($label); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (!$is_logged_in): ?>
                        <a href="/login" class="text-sm font-medium text-white hover:text-neon-cyan transition-colors"><?php echo __('Login'); ?></a>
                        <a href="/register" class="relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium rounded-full group bg-gradient-to-br from-neon-purple to-blue-500 group-hover:from-neon-purple group-hover:to-blue-500 hover:text-white focus:ring-4 focus:outline-none focus:ring-blue-800">
                            <span class="relative px-6 py-2.5 transition-all ease-in duration-75 bg-dark-900 rounded-full group-hover:bg-opacity-0">
                                <?php echo __('Register'); ?>
                            </span>
                        </a>
                    <?php else: ?>
                        <a href="/user/dashboard" class="relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium rounded-full group bg-gradient-to-br from-neon-cyan to-blue-500 hover:text-white">
                            <span class="relative px-6 py-2.5 transition-all ease-in duration-75 bg-dark-900 rounded-full group-hover:bg-opacity-0">
                                <?php echo __('Dashboard'); ?>
                            </span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white p-2 relative w-10 h-10 flex items-center justify-center">
                        <i class="fas fa-bars text-xl transition-all duration-300" :class="{ 'opacity-0 rotate-180': mobileMenuOpen }"></i>
                        <i class="fas fa-times text-xl transition-all duration-300 absolute opacity-0 rotate-180" :class="{ 'opacity-100 rotate-0': mobileMenuOpen }"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu Overlay - Outside nav to cover entire viewport -->
    <div x-show="mobileMenuOpen"
        x-transition:enter="transition-opacity duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileMenuOpen = false"
        class="fixed inset-0 bg-dark-900/70 backdrop-blur-md z-40 md:hidden"
        style="top: 80px;"
        x-cloak>
    </div>

    <!-- Mobile Menu Dropdown -->
    <div x-show="mobileMenuOpen"
        x-collapse
        class="fixed left-0 right-0 top-20 md:hidden glass-panel border-t border-gray-800 z-50 max-h-[calc(100vh-80px)] overflow-y-auto"
        x-cloak>
        <div class="px-6 py-4 space-y-4">
            <a href="/" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('Home'); ?></a>
            <a href="/about" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('About'); ?></a>
            <a href="#plans-section" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('Plans'); ?></a>
            <a href="#testimonials" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('Testimonials'); ?></a>
            <a href="#faq" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('FAQs'); ?></a>
            <a href="/contact" @click="mobileMenuOpen = false" class="block text-gray-300 hover:text-white py-2"><?php echo __('Contact'); ?></a>

            <!-- Mobile Language Switcher -->
            <?php if (is_google_translate_enabled()): ?>
                <div class="pt-2 border-t border-gray-700">
                    <p class="text-xs text-gray-500 uppercase mb-2"><?php echo __('Language'); ?></p>
                    <?php render_google_translate_widget('mobile'); ?>
                </div>
            <?php else: ?>
                <div class="pt-2 border-t border-gray-700">
                    <p class="text-xs text-gray-500 uppercase mb-2"><?php echo __('Language'); ?></p>
                    <form method="POST" action="/actions/switch-language-public.php" id="mobileLangForm">
                        <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                        <input type="hidden" name="lang" id="mobileSelectedLang" value="<?php echo e($current_lang); ?>">
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach ($languages as $code => $label): ?>
                                <button type="button"
                                    class="text-left px-3 py-2 text-sm rounded-lg flex items-center gap-2 transition-colors <?php echo $code === $current_lang ? 'bg-neon-cyan/20 text-neon-cyan border border-neon-cyan/30' : 'text-gray-300 hover:bg-white/5'; ?>"
                                    @click="document.getElementById('mobileSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('mobileLangForm').submit();">
                                    <?php if ($code === $current_lang): ?>
                                        <i class="fas fa-check text-xs"></i>
                                    <?php endif; ?>
                                    <?php echo e($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <?php if (!$is_logged_in): ?>
                    <a href="/login" class="text-center py-2 border border-gray-700 rounded-lg text-white"><?php echo __('Login'); ?></a>
                    <a href="/register" class="text-center py-2 bg-neon-purple rounded-lg text-white font-bold"><?php echo __('Register'); ?></a>
                <?php else: ?>
                    <a href="/user/dashboard" class="col-span-2 text-center py-2 bg-neon-cyan rounded-lg text-white font-bold"><?php echo __('Dashboard'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 mt-20">
        <?php if (isset($_SESSION['error'])): ?>
            <div x-data="{ show: true }" x-show="show" x-transition class="bg-red-500/10 border border-red-500/50 text-red-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm mb-4" role="alert">
                <i class="fas fa-exclamation-circle mt-0.5"></i>
                <span class="flex-1"><?php echo e($_SESSION['error']); ?></span>
                <button @click="show = false" class="text-red-300 hover:text-red-100 transition-colors" type="button">
                    <i class="fas fa-times"></i>
                </button>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div x-data="{ show: true }" x-show="show" x-transition class="bg-green-500/10 border border-green-500/50 text-green-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm mb-4" role="alert">
                <i class="fas fa-check-circle mt-0.5"></i>
                <span class="flex-1"><?php echo e($_SESSION['success']); ?></span>
                <button @click="show = false" class="text-green-300 hover:text-green-100 transition-colors" type="button">
                    <i class="fas fa-times"></i>
                </button>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
    </div>