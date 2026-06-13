<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Load required functions if not already loaded
if (!function_exists('get_setting') || !function_exists('e')) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}

if (!function_exists('get_available_languages')) {
    require_once __DIR__ . '/translation-functions.php';
}

// Initialize translation system BEFORE any output
if (isset($_SESSION['user_id'])) {
    $user_lang = get_user_language($_SESSION['user_id']);
    init_translation($user_lang);
} else {
    init_translation();
}

// Currency / user data needed by the root Alpine object
$user_name = '';
$user_email = '';
$user_avatar = '';
if (isset($_SESSION['user_id'])) {
    $db = db_connect();
    $user_rows = db_query('SELECT name, email, profile_picture FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!empty($user_rows)) {
        $user_name = $user_rows[0]['name'] ?? 'User';
        $user_email = $user_rows[0]['email'] ?? '';
        $user_avatar = $user_rows[0]['profile_picture'] ?? '';
    }
}
$avatar_url = !empty($user_avatar) ? '/' . ltrim($user_avatar, '/') : null;

$cc = get_currency_code();

$user_id = $_SESSION['user_id'] ?? null;
$user_d = $user_id ? (db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null) : null;
$user_country = $user_d['country'] ?? null;
$local_currency_code = null;
$exchange_rate = null;
if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code) {
        $exchange_rate = get_rate_for_currency_raw($local_currency_code);
    }
}
$local_currency_symbol = $local_currency_code ? get_currency_symbol($local_currency_code) : null;
$local_icon = get_country_flag_url_from_currency($local_currency_code);
$icon = get_country_flag_url_from_currency($cc);

// Theme handling — new template is dark-only, so always base on dark-mode
$theme_mode = get_setting('default_mode', 'light');
if (!isset($body_class)) {
    $body_class = 'dark-mode min-h-screen flex flex-col font-sans antialiased selection:bg-brand-accent selection:text-white relative overflow-x-hidden';
    if ($theme_mode === 'system') {
        $body_class .= ' system-theme';
    }
}

$script_hash = filemtime(ROOT . '/assets/js/user-scripts.js');
?><!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', get_current_language())); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09090b">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : '';
            echo e(get_setting('site_name', 'Investment Platform')); ?></title>

    <link rel="icon" type="image/png" href="<?php echo e(SITE_ICON); ?>">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        brand: {
                            dark: '#09090b',
                            card: '#18181b',
                            accent: '#10b981',
                        },
                    },
                },
            },
        };
    </script>

    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>

    <!-- User Scripts -->
    <script src="/assets/js/user-scripts.js?hash=<?php echo $script_hash; ?>"></script>

    <!-- Extra CSS / Head Scripts -->
    <?php if (!empty($extra_css)) echo $extra_css; ?>
    <?php if (!empty($extra_head_scripts)) echo $extra_head_scripts; ?>

    <style>
        body {
            background-color: #09090b;
            color: #a1a1aa;
        }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 4px; }
        .glass-panel {
            background: rgba(24, 24, 27, 0.75);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(63, 63, 70, 0.3);
        }
        .glass-nav-bottom {
            background: rgba(24, 24, 27, 0.9);
            backdrop-filter: blur(16px);
            border-top: 1px solid rgba(63, 63, 70, 0.4);
        }
        .emerald-glow { box-shadow: 0 0 50px -12px rgba(16, 185, 129, 0.12); }
        .emerald-glow-strong { box-shadow: 0 0 30px -5px rgba(16, 185, 129, 0.25); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="<?php echo e($body_class); ?>">
    <!-- Ambient background glows -->
    <div class="absolute top-24 md:left-1/4 w-96 md:h-96 bg-brand-accent/5 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-10 md:right-10 w-80 h-80 bg-emerald-500/5 rounded-full blur-[100px] pointer-events-none"></div>

    <!-- Root Alpine wrapper -->
    <div id="app-root"
         x-data="{
             currency: '<?php echo e($cc); ?>',
             currencyRef: '<?php echo e($cc); ?>',
             showBalance: true,
             localRate: '<?php echo e($exchange_rate); ?>',
             localFlag: '<?php echo e($local_icon); ?>',
             currencyFlag: '<?php echo e($icon); ?>',
             currencyFlagRef: '<?php echo e($icon); ?>',
             currencySymbol: '<?php echo e(get_currency_symbol()); ?>',
             localCurrencySymbol: '<?php echo e($local_currency_symbol); ?>',
             localCurrencyCode: '<?php echo e($local_currency_code); ?>',
             precision: <?php echo (get_currency_code() === 'BTC' ? 8 : 2); ?>,
             init() {
                 const savedCurrency = localStorage.getItem('tradeonix_currency');
                 const savedFlag = localStorage.getItem('tradeonix_currencyFlag');
                 if (savedCurrency) {
                     if (savedCurrency === this.localCurrencyCode) {
                         this.currency = savedCurrency;
                         this.currencyFlag = savedFlag || this.localFlag;
                     } else if (savedCurrency === this.currencyRef) {
                         this.currency = savedCurrency;
                         this.currencyFlag = savedFlag || this.currencyFlagRef;
                     }
                 }

                 <?php if ($theme_mode === 'system'): ?>
                 const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                 if (darkModeQuery.matches) {
                     document.body.classList.add('dark-mode');
                 }
                 darkModeQuery.addEventListener('change', (e) => {
                     if (e.matches) {
                         document.body.classList.add('dark-mode');
                     } else {
                         document.body.classList.remove('dark-mode');
                     }
                 });
                 <?php endif; ?>
             },
             formatCurrency(amount) {
                 if (this.currency === this.localCurrencyCode) {
                     return this.localCurrencySymbol + (amount * parseFloat(this.localRate)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                 }
                 return this.currencySymbol + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: this.precision, maximumFractionDigits: this.precision });
             },
             toggleBalance() {
                 this.showBalance = !this.showBalance;
             },
             toggleCurrency() {
                 this.currencyFlag = this.currency === this.localCurrencyCode ? this.currencyFlagRef : this.localFlag;
                 this.currency = this.currency !== this.localCurrencyCode ? this.localCurrencyCode : this.currencyRef;
                 localStorage.setItem('tradeonix_currency', this.currency);
                 localStorage.setItem('tradeonix_currencyFlag', this.currencyFlag);
             }
         }"
         x-init="init()"
         class="flex flex-col min-h-screen w-full relative">
