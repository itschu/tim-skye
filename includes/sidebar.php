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

// Get current user info if logged in
$user_name = '';
$user_email = '';
if (isset($_SESSION['user_id'])) {
    $db = db_connect();
    $user_rows = db_query('SELECT name, email FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!empty($user_rows)) {
        $user_name = $user_rows[0]['name'] ?? 'User';
        $user_email = $user_rows[0]['email'] ?? '';
    }
}

// Get available languages
$languages = get_available_languages();

// Get current language from global (ensures it's set after init_translation)
$current_lang = $GLOBALS['current_language'] ?? get_current_language() ?? 'en_US';

// Get current language label
$current_lang_label = $languages[$current_lang] ?? 'English';

$kyc_required = get_setting('kyc_required', 'no');
$kyc_enabled = ($kyc_required === 'yes');

// Get current page for redirect after language switch
$current_page = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
?>

<!-- Mobile Overlay - closes sidebar when clicked -->
<div class="sidebar-overlay d-lg-none"
    :class="{ 'active': $store.app.sidebarOpen }"
    @click="$store.app.closeSidebar()"
    x-show="$store.app.sidebarOpen"
    x-transition.opacity.duration.300ms
    style="display: none;"></div>

<?php
$site_logo = get_setting('site_logo', '');
$site_name = get_setting('site_name', 'Investment Platform');
?>
<nav class="sidebar d-flex flex-column" :class="{ 'active': $store.app.sidebarOpen }">
    <div class="px-4 mb-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2 text-white">
            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                <a href="/">
                    <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" style="max-height: 44px; max-width: 180px;">
                </a>
                <!-- <h5 class="mb-0 fw-bold" style="letter-spacing: -0.5px"><?php echo e($site_name); ?></h5> -->

            <?php else: ?>
                <div class="rounded-3 bg-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h5 class="mb-0 fw-bold" style="letter-spacing: -0.5px"><?php echo e($site_name); ?></h5>
            <?php endif; ?>
        </div>
        <button class="sidebar-close-btn d-lg-none" @click="$store.app.closeSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="flex-grow-1 overflow-auto">
        <small class="text-secondary px-4 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px"><?php echo __('Menu'); ?></small>
        <a href="/user/dashboard" class="nav-link mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i> <?php echo __('Dashboard'); ?>
        </a>
        <a href="/user/invest" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'invest.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> <?php echo __('Invest Now'); ?>
        </a>
        <a href="/user/my-investments" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'my-investments.php' ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> <?php echo __('My Portfolio'); ?>
        </a>
        <a href="/user/transactions" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'transactions.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> <?php echo __('Transactions'); ?>
        </a>

        <small class="text-secondary px-4 text-uppercase fw-bold mt-4 d-block" style="font-size: 0.7rem; letter-spacing: 1px"><?php echo __('Funds'); ?></small>
        <a href="/user/deposit" class="nav-link mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'deposit.php' ? 'active' : ''; ?>">
            <i class="fas fa-arrow-down"></i> <?php echo __('Deposit'); ?>
        </a>
        <a href="/user/withdraw" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'withdraw.php' ? 'active' : ''; ?>">
            <i class="fas fa-arrow-up"></i> <?php echo __('Withdraw'); ?>
        </a>

        <small class="text-secondary px-4 text-uppercase fw-bold mt-4 d-block" style="font-size: 0.7rem; letter-spacing: 1px"><?php echo __('Account'); ?></small>
        <a href="/user/profile" class="nav-link mt-2 <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> <?php echo $kyc_enabled ? __('Profile & KYC') : __('Profile'); ?>
        </a>
        <a href="/user/referrals" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'referrals.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <?php echo __('Referrals'); ?>
        </a>
        <?php if (is_admin()): ?>
            <a href="/admin" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === '/admin' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> <?php echo __('Admin Portal'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="p-4 mt-auto">
        <?php if (is_google_translate_enabled()): ?>
            <!-- Google Translate Widget -->
            <div class="mb-3">
                <?php render_google_translate_widget('sidebar'); ?>
            </div>
        <?php else: ?>
            <!-- Professional Language Switcher -->
            <div class="language-switcher mb-3" x-data="{ open: false }" @click.away="open = false">
                <form method="POST" action="/actions/switch-language.php" id="languageForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                    <input type="hidden" name="lang" id="selectedLang" value="<?php echo e($current_lang); ?>">

                    <button type="button"
                        class="language-switcher-btn"
                        @click="open = !open">
                        <span class="language-icon">
                            <i class="fas fa-globe"></i>
                        </span>
                        <span class="language-label"><?php echo e($current_lang_label); ?></span>
                        <span class="language-chevron" :class="{ 'rotate': open }">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </button>

                    <div class="language-dropdown" x-show="open" x-transition.origin.top.duration.200ms style="display: none;">
                        <?php foreach ($languages as $code => $label): ?>
                            <button type="button"
                                class="language-option <?php echo $code === $current_lang ? 'active' : ''; ?>"
                                @click="document.getElementById('selectedLang').value = '<?php echo e($code); ?>'; document.getElementById('languageForm').submit();">
                                <span class="language-check">
                                    <?php if ($code === $current_lang): ?>
                                        <i class="fas fa-check"></i>
                                    <?php endif; ?>
                                </span>
                                <span><?php echo e($label); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Logout Button -->
        <?php if (!isset($_SESSION['admin_original_id'])): ?>
            <a href="/logout" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i> <?php echo __('Logout'); ?>
            </a>
        <?php else: ?>
            <form method="POST" action="/admin/actions/exit-login-as">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <button type="submit" class="nav-link text-danger w-full">
                    <i class="fas fa-sign-out-alt"></i> <?php echo __('Back To Admin'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</nav>