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
if (!function_exists('get_setting')) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}

if (!function_exists('get_available_languages')) {
    require_once __DIR__ . '/translation-functions.php';
}

// Initialize translation system BEFORE any output
// This ensures proper locale setup for Gettext or fallback to PHP arrays
if (isset($_SESSION['user_id'])) {
    $user_lang = get_user_language($_SESSION['user_id']);
    init_translation($user_lang);
} else {
    init_translation();
}

// Get current user info if logged in
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
// Generate avatar URL (either uploaded image or fallback to initials)
$avatar_url = !empty($user_avatar) ? '/' . ltrim($user_avatar, '/') : null;

// Get available languages
$languages = get_available_languages();
$current_lang = get_current_language();

// Get current page for redirect after language switch
$current_page = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';

$cc = get_currency_code();

// Get user country and local currency
if (empty($user_id)) $user_id = null;
$user_d = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null;
$user_country = $user_d['country'] ?? null;
$local_currency_code = null;
$exchange_rate = null;

if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code)
        $exchange_rate = get_rate_for_currency_raw($local_currency_code);
}

$local_currency_symbol = $local_currency_code ? get_currency_symbol($local_currency_code) : null;


$local_icon = get_country_flag_url_from_currency($local_currency_code);
$icon = get_country_flag_url_from_currency($cc);

// Get theme mode setting
$theme_mode = get_setting('default_mode', 'light');

// Determine body class based on theme mode
$body_class = '';
if ($theme_mode === 'dark') {
    $body_class = 'dark-mode';
} elseif ($theme_mode === 'system') {
    $body_class = 'system-theme';
}

$stylesheet_version = filemtime(ROOT . '/assets/css/user-styles.css');

?>
<!DOCTYPE html>
<html lang="en" x-data="{
    sidebarOpen: false,
    mobileTab: '<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>',
    currency: '<?php echo $cc; ?>',
    currencyRef: '<?php echo $cc; ?>',
    showBalance: true,
    localRate: '<?php echo $exchange_rate; ?>',
    localFlag: '<?php echo $local_icon; ?>',
    currencyFlag: '<?php echo $icon; ?>',
    currencyFlagRef: '<?php echo $icon; ?>',
    currencySymbol: '<?php echo get_currency_symbol(); ?>',
    localCurrencySymbol: '<?php echo $local_currency_symbol; ?>',
    localCurrencyCode: '<?php echo $local_currency_code; ?>',
    precision: 2,
    headerStuck: false,
    darkMode: <?php echo ($theme_mode === 'dark') ? 'true' : 'false'; ?>,
    init() {
        // Restore persisted currency preference
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

        // Sticky header scroll detection
        const tickerHeight = 40; // Height of ticker
        window.addEventListener('scroll', () => {
            this.headerStuck = window.scrollY > tickerHeight;
        });
        // Check initial position
        this.headerStuck = window.scrollY > tickerHeight;

        // Handle system theme mode
        <?php if ($theme_mode === 'system'): ?>
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.darkMode = darkModeQuery.matches;

        // Update body class based on OS preference
        if (darkModeQuery.matches) {
            document.body.classList.add('dark-mode');
        }

        // Listen for OS theme changes
        darkModeQuery.addEventListener('change', (e) => {
            this.darkMode = e.matches;
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
}" x-init="init()">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f46e5">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : '';
            echo e(get_setting('site_name', 'Investment Platform')); ?></title>

    <link rel="icon" type="image/png" href="<?php echo e(SITE_ICON); ?>">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <script>
        window.formatCurrency = function(amount) {
            const data = document.documentElement._x_dataStack?.[0];
            if (data && data.formatCurrency) {
                return data.formatCurrency(amount);
            }
            return '<?php echo get_currency_symbol(); ?>' + parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
    </script>

    <!-- User Styles -->
    <link href="/assets/css/user-styles.css?hash=<?php echo $stylesheet_version; ?>" rel="stylesheet">
</head>

<body class="<?php echo $body_class; ?>">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Bottom Navigation (Mobile) -->
    <div class="bottom-nav d-lg-none">
        <a href="/user/dashboard" class="nav-item-mobile" :class="{ 'active': mobileTab === 'dashboard' }" @click="mobileTab = 'dashboard'">
            <i class="fas fa-home-alt"></i> <?php echo __('Home'); ?>
        </a>
        <a href="/user/invest" class="nav-item-mobile" :class="{ 'active': mobileTab === 'invest' }" @click="mobileTab = 'invest'">
            <i class="fas fa-chart-pie"></i> <?php echo __('Invest'); ?>
        </a>
        <a href="/user/deposit" style="transform: translateY(-20px)">
            <button class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; border: 3px solid #f1f5f9">
                <i class="fas fa-plus fa-lg"></i>
            </button>
        </a>
        <a href="/user/transactions" class="nav-item-mobile" :class="{ 'active': mobileTab === 'transactions' }" @click="mobileTab = 'transactions'">
            <i class="fas fa-wallet"></i> <?php echo __('Wallet'); ?>
        </a>
        <a href="#" class="nav-item-mobile" @click="$store.app.sidebarOpen = true">
            <i class="fas fa-bars"></i> <?php echo __('Menu'); ?>
        </a>
    </div>

    <main class="main-content">
        <!-- Ticker -->
        <div class="ticker-wrap">
            <div class="ticker">
                <span class="ticker-item"><i class="fas fa-caret-up text-success"></i> BTC/USD: $45,230.00 (+2.4%)</span>
                <span class="ticker-item"><i class="fas fa-caret-down text-danger"></i> EUR/USD: 1.0842 (-0.1%)</span>
                <span class="ticker-item"><i class="fas fa-caret-up text-success"></i> XAU/USD: $1,950.50 (+0.5%)</span>
                <span class="ticker-item"><i class="fas fa-caret-up text-success"></i> ETH/USD: $2,400.00 (+1.8%)</span>
                <!-- Single set of items for ticker (duplicates removed to avoid markup duplication) -->
            </div>
        </div>

        <!-- Sticky Header Container -->
        <div class="sticky-header-wrapper">
            <!-- Glass-morphic Top Navbar (desktop & tablet) -->
            <header class="glass-navbar sticky-header d-none d-md-flex align-items-center justify-content-between px-4 py-2">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="m-0 fw-bold page-title"><?php echo e(isset($page_title) ? $page_title : __('Dashboard')); ?></h4>
                </div>

                <div class="d-flex align-items-center gap-3 ms-auto">
                    <?php if (basename($_SERVER['PHP_SELF'], '.php') !== 'deposit' && basename($_SERVER['PHP_SELF'], '.php') !== 'profile'): ?>
                        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
                            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
                            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
                        </button>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-2 user-controls">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo e($avatar_url); ?>" alt="<?php echo e($user_name); ?>" class="user-avatar rounded-circle" style="width:40px; height:40px; object-fit:cover;">
                        <?php else: ?>
                            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width:40px; height:40px; font-weight:bold;">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-none d-lg-block text-end">
                            <div class="fw-bold" style="line-height:1"><?php echo e($user_name); ?></div>
                            <small class="text-muted"><?php echo e($user_email); ?></small>
                        </div>
                        <!-- <div class="ms-3 position-relative">
                            <i class="fas fa-bell fa-lg text-secondary"></i>
                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger rounded-circle border border-white"></span>
                        </div> -->
                    </div>
                </div>
            </header>

            <!-- Mobile Header -->
            <header class="sticky-header-mobile d-flex d-lg-none justify-content-between align-items-center p-3 bg-white border-bottom shadow-sm">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($avatar_url): ?>
                        <img src="<?php echo e($avatar_url); ?>" alt="<?php echo e($user_name); ?>" class="user-avatar rounded-circle" style="width:38px; height:38px; object-fit:cover;">
                    <?php else: ?>
                        <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width: 38px; height: 38px; font-weight: bold;">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <h5 class="m-0 fw-bold"><?php echo e(isset($page_title) ? $page_title : __('Dashboard')); ?></h5>
                </div>
                <?php if (basename($_SERVER['PHP_SELF'], '.php') !== 'deposit'): ?>
                    <button class="btn btn-white border shadow-sm fw-bold d-flex align-items-center gap-2 py-1 px-2 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
                        <img :src="currencyFlag" width="16" height="16" class="rounded-circle object-fit-cover" />
                        <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
                    </button>
                <?php endif; ?>
                <!-- <div class="position-relative">
                    <i class="fas fa-bell fa-lg text-secondary"></i>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger rounded-circle border border-white"></span>
                </div> -->
            </header>
        </div>

        <!-- Content Container -->
        <div class="p-3 p-md-4 fade-in">

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo e($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo __('Close'); ?>"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo e($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo __('Close'); ?>"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php
            // KYC Banner Logic
            $show_kyc_banner = false;
            $kyc_banner_status = '';

            if (isset($_SESSION['user_id']) && function_exists('get_setting')) {
                $kyc_required_h = get_setting('kyc_required', 'no');
                if ($kyc_required_h === 'yes') {
                    $kyc_timing_h        = get_setting('kyc_timing', 'before_withdrawal');
                    $kyc_always_show_h   = get_setting('kyc_always_show_message', 'no');
                    $kyc_dismissible_h   = get_setting('kyc_banner_dismissible', 'yes');
                    $current_user_h      = $GLOBALS['current_user'] ?? null;
                    $user_kyc_status_h   = $current_user_h['kyc_status'] ?? 'not_submitted';

                    $should_show = ($kyc_timing_h === 'immediately' || $kyc_always_show_h === 'yes');

                    if ($should_show && $user_kyc_status_h !== 'approved') {
                        $show_kyc_banner   = true;
                        $kyc_banner_status = $user_kyc_status_h; // 'not_submitted', 'pending', 'rejected'
                    }
                }
            }
            ?>

            <?php if ($show_kyc_banner): ?>
                <div x-data="{
                    show: !(<?php echo $kyc_dismissible_h === 'yes' ? 'true' : 'false'; ?> && sessionStorage.getItem('kyc_banner_dismissed') === '1'),
                    dismiss() {
                        this.show = false;
                        sessionStorage.setItem('kyc_banner_dismissed', '1');
                    }
                }" x-show="show" @click.outside="false" class="mb-4">
                    <?php if ($kyc_banner_status === 'not_submitted'): ?>
                        <div class="alert alert-warning alert-dismissible fade show border-2 border-warning" role="alert">
                            <div class="d-flex align-items-center gap-3 justify-content-between">
                                <div>
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo __('Complete Your Identity Verification'); ?>
                                    </h6>
                                    <p class="mb-0"><?php echo __('Complete your identity verification to unlock all platform features.'); ?></p>
                                </div>
                                <a href="/user/profile" class="btn btn-sm btn-warning ms-2 mt-1 flex-shrink-0">
                                    <?php echo __('Verify Now'); ?> <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <?php if ($kyc_dismissible_h === 'yes'): ?>
                                <button type="button" class="btn-close" aria-label="<?php echo __('Close'); ?>" @click="dismiss()"></button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($kyc_banner_status === 'pending'): ?>
                        <div class="alert alert-info alert-dismissible fade show border-2 border-info" role="alert">
                            <div class="d-flex align-items-start gap-3">
                                <div>
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-clock me-2"></i><?php echo __('KYC Under Review'); ?>
                                    </h6>
                                    <p class="mb-0"><?php echo __('Your KYC documents are under review. We\'ll notify you once approved.'); ?></p>
                                </div>
                            </div>
                            <?php if ($kyc_dismissible_h === 'yes'): ?>
                                <button type="button" class="btn-close" aria-label="<?php echo __('Close'); ?>" @click="dismiss()"></button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($kyc_banner_status === 'rejected'): ?>
                        <div class="alert alert-danger alert-dismissible fade show border-2 border-danger" role="alert">
                            <div class="d-flex align-items-start gap-3">
                                <div>
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-times-circle me-2"></i><?php echo __('KYC Verification Rejected'); ?>
                                    </h6>
                                    <p class="mb-0"><?php echo __('Your KYC verification was rejected. Please resubmit your documents.'); ?></p>
                                </div>
                                <a href="/user/profile" class="btn btn-sm btn-danger ms-2 mt-1 flex-shrink-0">
                                    <?php echo __('Resubmit'); ?> <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <?php if ($kyc_dismissible_h === 'yes'): ?>
                                <button type="button" class="btn-close" aria-label="<?php echo __('Close'); ?>" @click="dismiss()"></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>