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

$active_nav = $active_nav ?? '';
$kyc_required = get_setting('kyc_required', 'no');
$kyc_enabled = ($kyc_required === 'yes');

// Get current user info if logged in
$user_name = '';
if (isset($_SESSION['user_id'])) {
    $db = db_connect();
    $user_rows = db_query('SELECT name FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!empty($user_rows)) {
        $user_name = $user_rows[0]['name'] ?? 'User';
    }
}

// Languages
$languages = get_available_languages();
$current_lang = $GLOBALS['current_language'] ?? get_current_language() ?? 'en_US';
$current_lang_label = $languages[$current_lang] ?? 'English';
$current_page = $_SERVER['REQUEST_URI'] ?? '/user/dashboard';

$site_name = get_setting('site_name', 'Investment Platform');

// Drawer link helper
$drawer_link = function ($key, $url, $icon, $label) use ($active_nav) {
    $is_active = $active_nav === $key;
    $classes = $is_active
        ? 'flex items-center gap-3 px-3 py-3 rounded-xl bg-brand-accent/10 border border-brand-accent/20 text-brand-accent font-semibold transition-all'
        : 'flex items-center gap-3 px-3 py-3 rounded-xl text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-100 transition-colors font-medium';
?>
    <a href="<?php echo e($url); ?>" class="<?php echo $classes; ?>">
        <div class="w-6 text-center">
            <i class="<?php echo e($icon); ?>"></i>
        </div>
        <?php echo e($label); ?>
    </a>
<?php
};
?>

<!-- Mobile bottom navigation -->
<div class="md:hidden glass-nav-bottom fixed bottom-0 left-0 right-0 z-40 px-6 py-2 pb-safe">
    <div class="flex justify-between items-end relative h-14">
        <a href="/user/dashboard" class="flex flex-col items-center gap-1 w-12 <?php echo $active_nav === 'dashboard' ? 'text-brand-accent' : 'text-zinc-500 hover:text-zinc-300'; ?> transition-colors">
            <i class="fa-solid fa-house text-lg"></i>
            <span class="text-[10px] font-medium"><?php echo e(__('Home')); ?></span>
        </a>

        <a href="/user/invest" class="flex flex-col items-center gap-1 w-12 mr-7 <?php echo $active_nav === 'invest' ? 'text-brand-accent' : 'text-zinc-500 hover:text-zinc-300'; ?> transition-colors">
            <i class="fa-solid fa-chart-pie text-lg"></i>
            <span class="text-[10px] font-medium"><?php echo e(__('Invest')); ?></span>
        </a>

        <div class="absolute left-1/2 -translate-x-1/2 -top-6">
            <a href="/user/deposit">
                <button class="w-14 h-14 rounded-full bg-brand-accent text-brand-dark flex items-center justify-center text-2xl shadow-[0_4px_20px_rgba(16,185,129,0.4)] hover:scale-105 transition-transform border-[4px] border-[#09090b]">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </a>
        </div>

        <a href="/user/transactions" class="flex flex-col items-center gap-1 w-12 ml-7 <?php echo $active_nav === 'transactions' ? 'text-brand-accent' : 'text-zinc-500 hover:text-zinc-300'; ?> transition-colors">
            <i class="fa-solid fa-wallet text-lg"></i>
            <span class="text-[10px] font-medium"><?php echo e(__('Wallet')); ?></span>
        </a>

        <a href="#" id="mobile-menu-btn" class="flex flex-col items-center gap-1 w-12 text-zinc-500 hover:text-zinc-300 transition-colors cursor-pointer">
            <i class="fa-solid fa-bars text-lg"></i>
            <span class="text-[10px] font-medium"><?php echo e(__('Menu')); ?></span>
        </a>
    </div>
</div>

<!-- Mobile drawer overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] opacity-0 invisible transition-all duration-300 md:hidden cursor-pointer"></div>

<!-- Mobile drawer -->
<div id="mobile-menu-drawer" class="fixed top-0 right-0 h-full w-[280px] bg-[#09090b] border-l border-zinc-800 z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col md:hidden shadow-2xl">
    <div class="p-5 border-b border-zinc-800/80 space-y-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-brand-accent to-emerald-300 flex items-center justify-center text-brand-dark shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                    <i class="fa-solid fa-layer-group text-sm"></i>
                </div>
                <span class="text-zinc-100 font-bold tracking-wide"><?php echo e($site_name); ?></span>
            </div>
            <button id="close-menu-btn" class="w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-400 flex items-center justify-center hover:text-white hover:bg-zinc-800 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Currency converter -->
        <?php if (isset($_SESSION['user_id'])):
            $cc_mobile = get_currency_code();
        ?>
            <button type="button"
                @click="toggleCurrency()"
                class="w-full flex items-center justify-between px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-xl text-zinc-300 hover:border-zinc-700 transition-colors">
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Currency')); ?></span>
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <img :src="currencyFlag" class="w-5 h-5 rounded-full object-cover" alt="">
                    <span x-text="currency"><?php echo e($cc_mobile); ?></span>
                    <i class="fa-solid fa-caret-down text-zinc-500"></i>
                </span>
            </button>
        <?php endif; ?>
    </div>

    <div class="flex-1 overflow-y-auto py-5 px-4 space-y-1 scrollbar-hide">
        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest px-2 mb-3"><?php echo e(__('Menu')); ?></p>
        <?php $drawer_link('dashboard', '/user/dashboard', 'fa-solid fa-shapes', __('Dashboard')); ?>
        <?php $drawer_link('invest', '/user/invest', 'fa-solid fa-chart-line', __('Invest Now')); ?>
        <?php $drawer_link('portfolio', '/user/my-investments', 'fa-solid fa-briefcase', __('My Portfolio')); ?>
        <?php $drawer_link('transactions', '/user/transactions', 'fa-solid fa-clock-rotate-left', __('Transactions')); ?>

        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest px-2 mb-3 mt-6"><?php echo e(__('Funds')); ?></p>
        <?php $drawer_link('deposit', '/user/deposit', 'fa-solid fa-arrow-down', __('Deposit')); ?>
        <?php $drawer_link('withdraw', '/user/withdraw', 'fa-solid fa-arrow-up', __('Withdraw')); ?>

        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest px-2 mb-3 mt-6"><?php echo e(__('Account')); ?></p>
        <?php $drawer_link('profile', '/user/profile', 'fa-solid fa-user', $kyc_enabled ? __('Profile & KYC') : __('Profile')); ?>
        <?php $drawer_link('referrals', '/user/referrals', 'fa-solid fa-users', __('Referrals')); ?>
        <?php if (is_admin()): ?>
            <?php $drawer_link('admin', '/admin', 'fa-solid fa-cogs', __('Admin Portal')); ?>
        <?php endif; ?>
    </div>

    <div class="p-5 border-t border-zinc-800/80 flex flex-col gap-3 bg-zinc-950">
        <!-- Language switcher -->
        <?php if (is_google_translate_enabled()): ?>
            <div class="relative" x-data="{ langOpen: false }" @click.outside="langOpen = false">
                <?php render_google_translate_widget('mobile'); ?>
            </div>
        <?php else: ?>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <form method="POST" action="/actions/switch-language.php" id="mobileLanguageForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                    <input type="hidden" name="lang" id="mobileSelectedLang" value="<?php echo e($current_lang); ?>">

                    <button type="button"
                        @click="open = !open"
                        class="flex items-center justify-between w-full px-4 py-3 bg-zinc-900 rounded-xl border border-zinc-800 text-sm text-zinc-300 cursor-pointer hover:border-zinc-700 transition-colors">
                        <div class="flex items-center gap-3 font-medium">
                            <i class="fa-solid fa-globe text-brand-accent"></i>
                            <span><?php echo e($current_lang_label); ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-zinc-500 text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>

                    <div x-show="open"
                        x-transition.origin.top.duration.200ms
                        class="mt-2 bg-zinc-900 border border-zinc-800 rounded-xl shadow-xl overflow-hidden"
                        x-cloak
                        style="display: none;">
                        <?php foreach ($languages as $code => $label): ?>
                            <button type="button"
                                class="w-full text-left px-4 py-2.5 text-sm flex items-center gap-3 transition-colors <?php echo $code === $current_lang ? 'text-brand-accent bg-brand-accent/10' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white'; ?>"
                                @click="document.getElementById('mobileSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('mobileLanguageForm').submit();">
                                <span class="w-4">
                                    <?php if ($code === $current_lang): ?>
                                        <i class="fa-solid fa-check text-xs"></i>
                                    <?php endif; ?>
                                </span>
                                <span><?php echo e($label); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Logout / Back to admin -->
        <?php if (!isset($_SESSION['admin_original_id'])): ?>
            <a href="/logout"
                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-rose-500 border border-rose-500/20 hover:bg-rose-500/10 transition-colors font-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> <?php echo e(__('Logout')); ?>
            </a>
        <?php else: ?>
            <form method="POST" action="/admin/actions/exit-login-as">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-rose-500 border border-rose-500/20 hover:bg-rose-500/10 transition-colors font-semibold">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <?php echo e(__('Back To Admin')); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        const menuBtn = document.getElementById('mobile-menu-btn');
        const closeBtn = document.getElementById('close-menu-btn');
        const overlay = document.getElementById('mobile-menu-overlay');
        const drawer = document.getElementById('mobile-menu-drawer');

        function toggleMenu(e) {
            if (e) e.preventDefault();
            const isOpen = drawer.classList.contains('translate-x-0');
            if (isOpen) {
                drawer.classList.remove('translate-x-0');
                drawer.classList.add('translate-x-full');
                overlay.classList.remove('opacity-100', 'visible');
                overlay.classList.add('opacity-0', 'invisible');
                document.body.style.overflow = 'auto';
            } else {
                drawer.classList.remove('translate-x-full');
                drawer.classList.add('translate-x-0');
                overlay.classList.remove('opacity-0', 'invisible');
                overlay.classList.add('opacity-100', 'visible');
                document.body.style.overflow = 'hidden';
            }
        }

        if (menuBtn) menuBtn.addEventListener('click', toggleMenu);
        if (closeBtn) closeBtn.addEventListener('click', toggleMenu);
        if (overlay) overlay.addEventListener('click', toggleMenu);
    })();
</script>