<?php

/**
 * User Login Page
 * Allows users to authenticate with email and password
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard');
    exit;
}

require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

// Check rate-limit state for current client
$is_locked_out = false;
$lockout_minutes_remaining = 0;
$client_ip = get_client_ip();
if (is_login_rate_limited($client_ip)) {
    $is_locked_out = true;
    $remaining_seconds = get_login_lockout_remaining($client_ip);
    $lockout_minutes_remaining = (int)ceil(max(1, $remaining_seconds) / 60);
}

// Get available languages for the switcher
$languages = get_available_languages();
$current_lang = get_current_language();
$current_lang_label = $languages[$current_lang] ?? 'English';
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

$site_name = get_setting('site_name', 'Investment Platform');
$site_logo = get_setting('site_logo', '');
$site_description = get_setting('site_description', '');
$site_keywords = get_setting('site_keywords', '');
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

$registered = isset($_GET['registered']) ? '1' : null;
$timeout = isset($_GET['timeout']) ? '1' : null;
$logout = isset($_GET['logout']) ? '1' : null;
$reset = isset($_GET['reset']) ? '1' : null;
$verified = isset($_GET['verified']) ? '1' : null;
$verify_required = isset($_GET['verify_required']) ? '1' : null;

// Check if error is about email verification
$show_resend_form = false;
if ($error && (strpos($error, 'verify your email') !== false || strpos($error, 'Verification link') !== false)) {
    $show_resend_form = true;
}

$meta_description = isset($page_description) && $page_description ? $page_description : $site_description;
$meta_keywords = isset($page_keywords) && $page_keywords ? $page_keywords : $site_keywords;

// Page title helper
$full_title = 'Login - ' . e($site_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Login'); ?> - <?php echo e($site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        dark: {
                            900: '#0B0F19',
                            800: '#111827',
                            700: '#1F2937'
                        },
                        neon: {
                            purple: '#8b5cf6',
                            cyan: '#06b6d4'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />

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

    <?php if (!empty($meta_description)): ?>
        <meta name="description" content="<?php echo e($meta_description); ?>" />
    <?php endif; ?>
    <?php if (!empty($meta_keywords)): ?>
        <meta name="keywords" content="<?php echo e($meta_keywords); ?>" />
    <?php endif; ?>

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

    <link rel="icon" type="image/png" href="<?php echo e(SITE_ICON); ?>">
    <style>
        body {
            background-color: #0B0F19;
            color: #fff;
        }

        .form-input {
            background: #111827;
            border: 1px solid #374151;
            color: white;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
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
    </style>
</head>

<body class="h-screen overflow-hidden flex">

    <div class="hidden lg:flex w-1/2 relative overflow-hidden bg-dark-900 items-center justify-center">
        <div class="absolute inset-0 bg-gradient-to-br from-neon-purple/20 to-dark-900 z-0"></div>
        <div class="absolute top-[-20%] left-[-20%] w-[800px] h-[800px] bg-neon-purple/10 blur-[120px] rounded-full animate-blob"></div>
        <div class="absolute bottom-[-20%] right-[-20%] w-[800px] h-[800px] bg-neon-cyan/10 blur-[120px] rounded-full animate-blob animation-delay-2000"></div>

        <div class="relative z-10 text-center px-12" data-aos="fade-up" data-aos-duration="1000">
            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                <div class="mb-6 inline-flex items-center justify-center w-64">
                    <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" class="object-contain p-1">
                </div>
            <?php endif ?>
            <h2 class="text-5xl font-extrabold mb-6 tracking-tight">
                <?php echo __('Welcome Back'); ?>
            </h2>
            <p class="text-gray-400 text-lg leading-relaxed">
                <?php echo __('Access your dashboard, manage your investments, and track your growth in real-time.'); ?>
            </p>
        </div>
    </div>

    <div class="w-full lg:w-1/2 bg-dark-900 flex flex-col h-full relative z-10">

        <div class="absolute top-6 left-6 z-20 flex items-center gap-4">
            <a href="/" class="w-10 h-10 rounded-full bg-dark-800 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-neon-purple transition-all" title="<?php echo __('Back to Home'); ?>">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>

        <!-- Language Switcher / Google Translate -->
        <div class="absolute top-6 right-6 z-20">
            <?php if (is_google_translate_enabled()): ?>
                <!-- Google Translate Widget -->
                <div class="flex items-center">
                    <?php render_google_translate_widget('auth'); ?>
                </div>
            <?php else: ?>
                <!-- Local Language Switcher -->
                <div x-data="{ langOpen: false }" @click.away="langOpen = false">
                    <form method="POST" action="/actions/switch-language-public.php" id="authLangForm">
                        <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                        <input type="hidden" name="lang" id="authSelectedLang" value="<?php echo e($current_lang); ?>">
                        <button type="button"
                            class="flex items-center gap-2 text-sm font-medium text-gray-300 hover:text-white transition-all py-2 px-3 rounded-xl bg-dark-800/80 border border-gray-700 hover:border-neon-cyan/50 backdrop-blur-sm"
                            @click="langOpen = !langOpen">
                            <i class="fas fa-globe text-neon-cyan"></i>
                            <span class="hidden sm:inline"><?php echo e($current_lang_label); ?></span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': langOpen }"></i>
                        </button>
                        <div x-show="langOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95 translate-y-2"
                            x-transition:enter-end="opacity-100 transform scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 transform scale-95 translate-y-2"
                            class="absolute right-0 mt-2 w-44 bg-dark-800/95 backdrop-blur-xl rounded-xl border border-gray-700 shadow-xl overflow-hidden z-50"
                            style="display: none;"
                            @click="langOpen = false">
                            <?php foreach ($languages as $code => $label): ?>
                                <button type="button"
                                    class="w-full text-left px-4 py-2.5 text-sm flex items-center gap-3 hover:bg-white/5 transition-colors <?php echo $code === $current_lang ? 'text-neon-cyan bg-neon-cyan/10' : 'text-gray-300'; ?>"
                                    @click="document.getElementById('authSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('authLangForm').submit();">
                                    <?php if ($code === $current_lang): ?>
                                        <i class="fas fa-check text-xs w-4"></i>
                                    <?php else: ?>
                                        <span class="w-4"></span>
                                    <?php endif; ?>
                                    <?php echo e($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="min-h-full flex items-center justify-center p-6 sm:p-12">
                <div class="w-full max-w-md space-y-8" data-aos="fade-left" data-aos-duration="800">

                    <div class="text-center">
                        <a href="/" class="inline-block lg:hidden mb-2 text-2xl font-bold text-white hover:text-neon-cyan transition-colors">
                            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                                <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" class="mx-auto h-12 object-contain">
                            <?php else: ?>
                                <?php echo e($site_name); ?>
                            <?php endif; ?>
                        </a>
                        <h2 class="text-3xl font-bold text-white"><?php echo __('Sign In'); ?></h2>
                        <p class="text-gray-500 mt-2"><?php echo __('Enter your credentials to continue'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-red-500/10 border border-red-500/50 text-red-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-exclamation-circle mt-0.5"></i>
                            <span class="flex-1"><?php echo e($error); ?></span>
                            <button @click="show = false" class="text-red-300 hover:text-red-100 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_locked_out): ?>
                        <div class="bg-red-500/10 border border-red-500/50 text-red-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-lock mt-0.5"></i>
                            <span class="flex-1"><?php echo sprintf(__('Too many failed login attempts. Please try again in %s minutes.'), $lockout_minutes_remaining); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_resend_form): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-blue-500/10 border border-blue-500/50 text-blue-200 px-4 py-3 rounded-xl text-sm">
                            <div class="flex items-start gap-2 mb-2">
                                <p class="flex-1"><?php echo __('Didn\'t receive the email? Enter your email below to resend the verification link.'); ?></p>
                                <button @click="show = false" class="text-blue-300 hover:text-blue-100 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <form action="/actions/resend-verification" method="POST" class="flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="email" name="email" placeholder="<?php echo __('Enter your email'); ?>" required class="flex-1 bg-dark-900 border border-gray-600 rounded-lg px-3 py-2 text-sm text-white focus:border-neon-cyan focus:outline-none">
                                <button type="submit" class="bg-neon-cyan hover:bg-cyan-500 text-dark-900 font-semibold px-4 py-2 rounded-lg text-sm transition-colors">
                                    <?php echo __('Resend'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($registered): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-green-500/10 border border-green-500/50 text-green-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <span class="flex-1"><?php echo $verify_required ? __('Registration successful! Please check your email to verify your account before logging in.') : __('Registration successful! Please sign in.'); ?></span>
                            <button @click="show = false" class="text-green-300 hover:text-green-100 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($verified): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-green-500/10 border border-green-500/50 text-green-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <span class="flex-1"><?php echo __('Your email has been verified! You can now log in.'); ?></span>
                            <button @click="show = false" class="text-green-300 hover:text-green-100 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($logout): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-blue-500/10 border border-blue-500/50 text-blue-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-info-circle mt-0.5"></i>
                            <span class="flex-1"><?php echo __('You have been logged out successfully.'); ?></span>
                            <button @click="show = false" class="text-blue-300 hover:text-blue-100 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form x-data="{ loading: false }" @submit="loading = true" action="/actions/login-submit" method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <?php if (!empty($_SESSION['redirect_after_login'])): ?>
                            <input type="hidden" name="redirect" value="<?php echo e($_SESSION['redirect_after_login']); ?>">
                        <?php endif; ?>

                        <div>
                            <label for="email" class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1"><?php echo __('Email Address'); ?></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" class="form-input w-full rounded-xl py-3 pl-11 pr-4" id="email" name="email" placeholder="<?php echo __('name@example.com'); ?>" required>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label for="password" class="block text-xs font-bold text-gray-400 uppercase ml-1"><?php echo __('Password'); ?></label>
                            </div>
                            <div class="relative" x-data="{ show: false }">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input :type="show ? 'text' : 'password'" class="form-input w-full rounded-xl py-3 pl-11 pr-12" id="password" name="password" placeholder="<?php echo __('••••••••'); ?>" required>
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-500 hover:text-gray-300 transition-colors">
                                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 rounded bg-dark-800 border-gray-600 text-neon-purple focus:ring-neon-purple focus:ring-offset-dark-900">
                                <label for="remember_me" class="ml-2 block text-sm text-gray-400"><?php echo __('Remember me'); ?></label>
                            </div>
                            <a href="/reset-password" class="text-sm font-medium text-neon-purple hover:text-neon-cyan transition-colors"><?php echo __('Forgot Password?'); ?></a>
                        </div>

                        <button type="submit" <?php echo $is_locked_out ? 'disabled' : ''; ?> :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-neon-purple to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-900/50 transition-all transform hover:-translate-y-0.5 <?php echo $is_locked_out ? 'cursor-not-allowed opacity-50' : ''; ?>">
                            <span x-show="!loading"><?php echo __('Sign In'); ?></span>
                            <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-2"></i> <?php echo __('Processing…'); ?></span>
                        </button>
                    </form>

                    <div class="text-center pt-2">
                        <p class="text-gray-500">
                            <?php echo __('Don\'t have an account?'); ?>
                            <a href="/register" class="text-white hover:text-neon-cyan font-bold transition-colors ml-1"><?php echo __('Create free account'); ?></a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                once: true,
                duration: 800,
                easing: 'ease-in-out',
            });
        });
    </script>

    <?php require_once ROOT . '/includes/auth-footer.php'; ?>

</body>

</html>