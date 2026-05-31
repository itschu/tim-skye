<?php
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

// Get available languages for the switcher
$languages = get_available_languages();
$current_lang = get_current_language();
$current_lang_label = $languages[$current_lang] ?? 'English';
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

$site_name = get_setting('site_name', 'Investment Platform');
$site_logo = get_setting('site_logo', '');
$site_description = get_setting('site_description', '');
$site_keywords = get_setting('site_keywords', '');
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);

$reset_token = isset($_GET['token']) ? sanitize_input($_GET['token']) : null;
$sent = isset($_GET['sent']) ? '1' : null;
$mode = $reset_token ? 'reset' : 'request';

$meta_description = isset($page_description) && $page_description ? $page_description : $site_description;
$meta_keywords = isset($page_keywords) && $page_keywords ? $page_keywords : $site_keywords;

// Page title helper
$full_title = 'Reset Password - ' . e($site_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $full_title; ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

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
        <div class="absolute inset-0 bg-gradient-to-bl from-dark-900 to-blue-900/20 z-0"></div>
        <div class="absolute top-[30%] left-[20%] w-[500px] h-[500px] bg-neon-cyan/10 blur-[100px] rounded-full animate-blob"></div>
        <div class="absolute bottom-[10%] right-[10%] w-[400px] h-[400px] bg-blue-500/10 blur-[100px] rounded-full animate-blob animation-delay-2000"></div>

        <div class="relative z-10 text-center px-12" data-aos="fade-up" data-aos-duration="1000">
            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                <div class="mb-6 inline-flex items-center justify-center w-64">
                    <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" class="object-contain p-1">
                </div>
            <?php else: ?>
                <div class="mb-6 inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-neon-cyan shadow-lg shadow-blue-500/30 text-white text-3xl">

                    <i class="fas fa-shield-alt"></i>
                </div>
            <?php endif; ?>
            <h2 class="text-4xl font-extrabold mb-4 tracking-tight">
                <?php echo __('Secure Your Account'); ?>
            </h2>
            <p class="text-gray-400 text-lg leading-relaxed">
                <?php echo __('We take security seriously. Restore access to your portfolio safely and securely.'); ?>
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
            <div class="min-h-full flex items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
                <div class="w-full max-w-md mx-auto space-y-8" data-aos="fade-left" data-aos-duration="800">

                    <div class="text-center">
                        <a href="/" class="inline-block lg:hidden mb-2 text-2xl font-bold text-white hover:text-neon-cyan transition-colors">
                            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                                <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" class="mx-auto h-12 object-contain">
                            <?php else: ?>
                                <?php echo e($site_name); ?>
                            <?php endif; ?>
                        </a>
                        <h2 class="text-3xl font-bold text-white"><?php echo __('Account Recovery'); ?></h2>
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

                    <?php if ($message): ?>
                        <div x-data="{ show: true }" x-show="show" x-transition class="bg-green-500/10 border border-green-500/50 text-green-200 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <span class="flex-1"><?php echo e($message); ?></span>
                            <button @click="show = false" class="text-green-300 hover:text-green-100 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($sent && !$reset_token): ?>
                        <div class="text-center py-4">
                            <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-6 text-green-400 text-3xl ring-1 ring-green-500/30">
                                <i class="fas fa-envelope-open-text"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('Check your email'); ?></h3>
                            <p class="text-gray-400 text-sm mb-8 leading-relaxed">
                                <?php echo __('We have sent a password reset link to your email address. Please check your inbox and spam folder.'); ?>
                            </p>
                            <a href="/login" class="block w-full py-3.5 rounded-xl font-bold text-white bg-dark-800 border border-gray-700 hover:border-neon-cyan hover:text-neon-cyan transition-all">
                                <?php echo __('Back to Login'); ?>
                            </a>
                        </div>

                    <?php elseif ($mode === 'reset'): ?>
                        <p class="text-center text-gray-500"><?php echo __('Create a new strong password for your account.'); ?></p>

                        <form x-data="{ loading: false }" @submit="loading = true" action="" method="POST" class="space-y-6">
                            <input type="hidden" name="token" value="<?php echo e($reset_token); ?>">

                            <div>
                                <label for="new_password" class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1"><?php echo __('New Password'); ?></label>
                                <input type="password" class="form-input w-full rounded-xl py-3 px-4" id="new_password" name="new_password" required minlength="6">
                                <p class="text-xs text-gray-500 mt-1 ml-1"><?php echo __('Minimum 6 characters'); ?></p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1"><?php echo __('Confirm Password'); ?></label>
                                <input type="password" class="form-input w-full rounded-xl py-3 px-4" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>

                            <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-neon-purple to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-900/50 transition-all transform hover:-translate-y-0.5">
                                <span x-show="!loading"><?php echo __('Update Password'); ?></span>
                                <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-2"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </form>

                    <?php else: ?>
                        <p class="text-center text-gray-500"><?php echo __('Enter your email address to receive a reset link.'); ?></p>

                        <form x-data="{ loading: false }" @submit="loading = true" action="/actions/reset-password-submit" method="POST" class="space-y-6">
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

                            <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-neon-purple to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-900/50 transition-all transform hover:-translate-y-0.5">
                                <span x-show="!loading"><?php echo __('Send Reset Link'); ?></span>
                                <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-2"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </form>

                        <div class="text-center pt-6">
                            <a href="/login" class="text-sm font-medium text-gray-400 hover:text-white transition-colors">
                                <?php echo __('Remembered your password?'); ?> <span class="text-neon-cyan"><?php echo __('Login'); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>

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