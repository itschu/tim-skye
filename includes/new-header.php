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

// Ensure required variables are set
$page_title = $page_title ?? '';
$active_nav = $active_nav ?? '';

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
$avatar_url = !empty($user_avatar) ? '/' . ltrim($user_avatar, '/') : null;
$initial = strtoupper(substr($user_name, 0, 1));

$cc = get_currency_code();
$site_name = get_setting('site_name', 'Investment Platform');

// KYC banner logic (mirrors header.php)
$show_kyc_banner = false;
$kyc_banner_status = '';
$kyc_dismissible_h = 'yes';
if (isset($_SESSION['user_id']) && function_exists('get_setting')) {
    $kyc_required_h = get_setting('kyc_required', 'no');
    if ($kyc_required_h === 'yes') {
        $kyc_timing_h      = get_setting('kyc_timing', 'before_withdrawal');
        $kyc_always_show_h = get_setting('kyc_always_show_message', 'no');
        $kyc_dismissible_h = get_setting('kyc_banner_dismissible', 'yes');

        // auth.php normally sets $GLOBALS['current_user']; fall back to a direct query if not present
        $current_user_h = $GLOBALS['current_user'] ?? null;
        if (empty($current_user_h) && function_exists('db_query')) {
            $user_rows_h = db_query("SELECT kyc_status FROM users WHERE id = ?", [$_SESSION['user_id']]);
            $current_user_h = $user_rows_h[0] ?? null;
        }
        $user_kyc_status_h = $current_user_h['kyc_status'] ?? 'not_submitted';

        $should_show = ($kyc_timing_h === 'immediately' || $kyc_always_show_h === 'yes');

        if ($should_show && $user_kyc_status_h !== 'approved') {
            $show_kyc_banner   = true;
            $kyc_banner_status = $user_kyc_status_h;
        }
    }
}

$site_logo_h = get_setting('site_logo', '');
$has_logo_h = !empty($site_logo_h) && file_exists(ROOT . '/' . ltrim($site_logo_h, '/'));

// Navigation helper
$nav_item = function ($key, $url, $icon, $label) use ($active_nav) {
    $is_active = $active_nav === $key;
    $classes = $is_active
        ? 'text-zinc-100 font-medium flex items-center gap-2'
        : 'text-zinc-500 hover:text-zinc-100 transition-colors flex items-center gap-2';
    $icon_color = $is_active ? 'text-brand-accent' : '';
?>
    <a href="<?php echo e($url); ?>" class="<?php echo $classes; ?> text-sm">
        <i class="<?php echo e($icon); ?> <?php echo $icon_color; ?> text-sm"></i> <?php echo e($label); ?>
    </a>
<?php
};
?>

<!-- Sticky glass navbar -->
<nav class="glass-panel sticky top-0 z-40 px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3 min-w-0">
        <a href="/user/dashboard" class="flex items-center gap-3 min-w-0">
            <?php if ($has_logo_h): ?>
                <img src="/<?php echo e($site_logo_h); ?>" alt="<?php echo e($site_name); ?>" class="h-10 md:h-12 w-auto max-w-[200px] object-contain">
            <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-brand-accent to-emerald-300 flex items-center justify-center text-brand-dark shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                    <i class="fa-solid fa-bolt text-lg"></i>
                </div>
            <?php endif; ?>
            <span class="text-zinc-100 font-bold text-xl tracking-wide hidden sm:block truncate"><?php echo e($site_name); ?><span class="text-brand-accent">.</span></span>
        </a>
    </div>

    <!-- Desktop nav -->
    <div class="hidden lg:flex items-center gap-5 xl:gap-6 bg-zinc-900/80 px-4 xl:px-6 py-2.5 rounded-full border border-zinc-800 flex-wrap">
        <?php $nav_item('dashboard', '/user/dashboard', 'fa-solid fa-shapes', __('Dashboard')); ?>
        <?php $nav_item('invest', '/user/invest', 'fa-solid fa-chart-pie', __('Invest Now')); ?>
        <?php $nav_item('deposit', '/user/deposit', 'fa-solid fa-coins', __('Deposit')); ?>
        <?php $nav_item('withdraw', '/user/withdraw', 'fa-solid fa-money-bill-wave', __('Withdraw')); ?>
        <?php $nav_item('portfolio', '/user/my-investments', 'fa-solid fa-briefcase', __('Portfolio')); ?>
        <?php $nav_item('transactions', '/user/transactions', 'fa-solid fa-clock-rotate-left', __('Transactions')); ?>
        <?php $nav_item('referrals', '/user/referrals', 'fa-solid fa-users', __('Referrals')); ?>
    </div>

    <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
        <!-- Currency toggle -->
        <div class="flex items-center gap-2 px-2 sm:px-3 py-1.5 bg-zinc-900 rounded-lg border border-zinc-800 text-xs font-semibold text-zinc-300 cursor-pointer hover:border-zinc-700 transition-colors"
            @click="toggleCurrency()">
            <img :src="currencyFlag" class="w-5 h-5 rounded-full object-cover flex-shrink-0" alt="<?php echo e(__('Currency flag')); ?>">
            <span x-text="currency" class="truncate max-w-[4rem] hidden sm:inline"><?php echo e($cc); ?></span>

            <i class="fa-solid fa-caret-down text-zinc-500 ml-1 flex-shrink-0"></i>
        </div>

        <!-- User avatar + dropdown -->
        <div class="flex items-center gap-3 pl-2 sm:border-l border-zinc-800 relative group cursor-pointer" tabindex="0">
            <?php if ($avatar_url): ?>
                <img src="<?php echo e($avatar_url); ?>" alt="<?php echo e($user_name); ?>" class="w-9 h-9 rounded-full object-cover border border-brand-accent/30 shadow-[0_0_10px_rgba(16,185,129,0.1)]">
            <?php else: ?>
                <div class="w-9 h-9 rounded-full bg-emerald-500/20 border border-brand-accent/30 text-brand-accent flex items-center justify-center font-bold text-sm shadow-[0_0_10px_rgba(16,185,129,0.1)] transition-colors group-hover:bg-brand-accent group-hover:text-brand-dark">
                    <?php echo e($initial); ?>
                </div>
            <?php endif; ?>

            <div class="hidden lg:block text-right">
                <p class="text-zinc-100 text-sm font-semibold leading-tight flex items-center gap-1">
                    <?php echo e($user_name); ?>
                    <i class="fa-solid fa-chevron-down text-[10px] text-zinc-500 transition-transform group-hover:rotate-180"></i>
                </p>
                <p class="text-zinc-500 text-xs"><?php echo e($user_email); ?></p>
            </div>

            <div class="absolute right-0 top-full mt-4 w-48 bg-zinc-900 border border-zinc-800 rounded-2xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden transform origin-top-right group-hover:scale-100 scale-95">
                <div class="p-2">
                    <a href="/user/profile" class="flex items-center gap-3 px-3 py-2.5 text-sm text-zinc-300 hover:bg-zinc-800 hover:text-white rounded-xl transition-colors">
                        <div class="w-6 h-6 rounded-md bg-zinc-800 flex items-center justify-center">
                            <i class="fa-solid fa-user text-xs"></i>
                        </div>
                        <?php echo e(__('Profile')); ?>
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="/admin" class="flex items-center gap-3 px-3 py-2.5 text-sm text-zinc-300 hover:bg-zinc-800 hover:text-white rounded-xl transition-colors">
                            <div class="w-6 h-6 rounded-md bg-zinc-800 flex items-center justify-center">
                                <i class="fa-solid fa-cogs text-xs"></i>
                            </div>
                            <?php echo e(__('Admin Portal')); ?>
                        </a>
                    <?php endif; ?>
                    <div class="h-px bg-zinc-800/80 w-full my-1"></div>
                    <?php if (!isset($_SESSION['admin_original_id'])): ?>
                        <a href="/logout" class="flex items-center gap-3 px-3 py-2.5 text-sm text-rose-400 hover:bg-rose-500/10 rounded-xl transition-colors">
                            <div class="w-6 h-6 rounded-md bg-rose-500/10 flex items-center justify-center">
                                <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                            </div>
                            <?php echo e(__('Logout')); ?>
                        </a>
                    <?php else: ?>
                        <form method="POST" action="/admin/actions/exit-login-as" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-rose-400 hover:bg-rose-500/10 rounded-xl transition-colors text-left">
                                <div class="w-6 h-6 rounded-md bg-rose-500/10 flex items-center justify-center">
                                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                                </div>
                                <?php echo e(__('Back To Admin')); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main content wrapper opened by header; closed by footer -->
<main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-28 md:pb-10 space-y-8">

    <!-- Session flash messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div x-data="{ show: true, dismiss() { this.show = false; } }" x-show="show" x-transition.duration.300ms
            class="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-rose-400 flex items-start justify-between shadow-sm" role="alert">
            <span class="text-sm font-medium"><?php echo e($_SESSION['error']); ?></span>
            <button type="button" @click="dismiss()" class="ml-3 text-rose-400 hover:text-rose-300">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div x-data="{ show: true, dismiss() { this.show = false; } }" x-show="show" x-transition.duration.300ms
            class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-brand-accent flex items-start justify-between shadow-sm" role="alert">
            <span class="text-sm font-medium"><?php echo e($_SESSION['success']); ?></span>
            <button type="button" @click="dismiss()" class="ml-3 text-brand-accent hover:text-emerald-400">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- KYC banner -->
    <?php if ($show_kyc_banner): ?>
        <div x-data="{
            show: !(<?php echo $kyc_dismissible_h === 'yes' ? 'true' : 'false'; ?> && sessionStorage.getItem('kyc_banner_dismissed') === '1'),
            dismiss() {
                this.show = false;
                sessionStorage.setItem('kyc_banner_dismissed', '1');
            }
        }" x-show="show" x-transition.duration.300ms class="mb-4" @click.outside="false">

            <?php if ($kyc_banner_status === 'not_submitted'): ?>
                <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-4 text-amber-400 shadow-sm" role="alert">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-exclamation-triangle mt-0.5"></i>
                            <div>
                                <h6 class="font-bold text-sm text-amber-300 mb-1"><?php echo e(__('Complete Your Identity Verification')); ?></h6>
                                <p class="text-sm text-amber-400/90"><?php echo e(__('Complete your identity verification to unlock all platform features.')); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <a href="/user/profile" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-brand-dark text-sm font-semibold rounded-lg transition-colors">
                                <?php echo e(__('Verify Now')); ?> <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                            <?php if ($kyc_dismissible_h === 'yes'): ?>
                                <button type="button" @click="dismiss()" class="text-amber-400 hover:text-amber-300">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($kyc_banner_status === 'pending'): ?>
                <div class="relative rounded-xl border border-sky-500/20 bg-sky-500/10 px-4 py-4 text-sky-400 shadow-sm" role="alert">
                    <div class="flex items-start gap-3">
                        <i class="fa-solid fa-clock mt-0.5"></i>
                        <div>
                            <h6 class="font-bold text-sm text-sky-300 mb-1"><?php echo e(__('KYC Under Review')); ?></h6>
                            <p class="text-sm text-sky-400/90"><?php echo e(__('Your KYC documents are under review. We\'ll notify you once approved.')); ?></p>
                        </div>
                    </div>
                    <?php if ($kyc_dismissible_h === 'yes'): ?>
                        <button type="button" @click="dismiss()" class="absolute top-4 right-4 text-sky-400 hover:text-sky-300">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php endif; ?>
                </div>

            <?php elseif ($kyc_banner_status === 'rejected'): ?>
                <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-4 text-rose-400 shadow-sm" role="alert">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-times-circle mt-0.5"></i>
                            <div>
                                <h6 class="font-bold text-sm text-rose-300 mb-1"><?php echo e(__('KYC Verification Rejected')); ?></h6>
                                <p class="text-sm text-rose-400/90"><?php echo e(__('Your KYC verification was rejected. Please resubmit your documents.')); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <a href="/user/profile" class="px-4 py-2 bg-rose-500 hover:bg-rose-400 text-white text-sm font-semibold rounded-lg transition-colors">
                                <?php echo e(__('Resubmit')); ?> <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                            <?php if ($kyc_dismissible_h === 'yes'): ?>
                                <button type="button" @click="dismiss()" class="text-rose-400 hover:text-rose-300">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>