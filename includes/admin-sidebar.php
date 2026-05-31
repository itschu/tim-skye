<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

if (!function_exists('get_available_languages')) {
    require_once __DIR__ . '/translation-functions.php';
}

// Reusable admin sidebar component
$site_name = get_setting('site_name', 'Platform');
$site_logo = get_setting('site_logo', '');

// Check if KYC is enabled
$kyc_required = get_setting('kyc_required', 'no');
$kyc_enabled = ($kyc_required === 'yes');

// Get pending counts for badges
$pd = db_query("SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'");
$pending_deposits = $pd[0]['count'] ?? 0;

$pw = db_query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals = $pw[0]['count'] ?? 0;

$pending_kyc = 0;
if ($kyc_enabled) {
    $pk = db_query("SELECT COUNT(*) as count FROM kyc_documents WHERE status = 'pending'");
    $pending_kyc = $pk[0]['count'] ?? 0;
}

$current_uri = $_SERVER['REQUEST_URI'] ?? '';

// Get active investments count for badge
$ai_count = db_query("SELECT COUNT(*) as count FROM investments WHERE status = 'active'")[0]['count'] ?? 0;
?>
<aside class="sidebar" :class="sidebarOpen ? 'show' : ''">
    <div class="sidebar-brand">
        <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
            <a href="/">
                <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" style="max-height: 50px; max-width: 200px; object-fit: contain;">
            </a>
        <?php else: ?>
            <span class="fw-bold" style="letter-spacing: 0.5px;"><?php echo e(strtoupper($site_name)); ?></span>
        <?php endif; ?>
        <!-- <span class="text-white ms-1" style="opacity: 0.85;"><?php echo __('ADMIN'); ?></span> -->
    </div>

    <div class="nav-scrollable">
        <div class="nav-section">
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/dashboard') !== false) ? 'active' : ''; ?>" href="/admin/dashboard">
                <i class="fa-solid fa-gauge-high" style="opacity: 0.6;"></i>
                <?php echo __('Dashboard'); ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title"><?php echo __('Approvals'); ?></div>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/pending-deposits') !== false) ? 'active' : ''; ?>" href="/admin/pending-deposits">
                <i class="fa-solid fa-file-invoice-dollar" style="opacity: 0.6;"></i>
                <?php echo __('Pending Deposits'); ?>
                <?php if ($pending_deposits > 0): ?>
                    <span class="badge bg-amber bg-opacity-10 text-amber border border-amber border-opacity-25 ms-auto" style="font-size: 0.7rem; box-shadow: 0 0 10px rgba(251, 191, 36, 0.3);"><?php echo $pending_deposits; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/pending-withdrawals') !== false) ? 'active' : ''; ?>" href="/admin/pending-withdrawals">
                <i class="fa-solid fa-money-bill-transfer" style="opacity: 0.6;"></i>
                <?php echo __('Pending Withdrawals'); ?>
                <?php if ($pending_withdrawals > 0): ?>
                    <span class="badge bg-amber bg-opacity-10 text-amber border border-amber border-opacity-25 ms-auto" style="font-size: 0.7rem; box-shadow: 0 0 10px rgba(251, 191, 36, 0.3);"><?php echo $pending_withdrawals; ?></span>
                <?php endif; ?>
            </a>
            <?php if ($kyc_enabled): ?>
                <a class="nav-link <?php echo (strpos($current_uri, '/admin/kyc-review') !== false) ? 'active' : ''; ?>" href="/admin/kyc-review">
                    <i class="fa-solid fa-id-card" style="opacity: 0.6;"></i>
                    <?php echo __('KYC Review'); ?>
                    <?php if ($pending_kyc > 0): ?>
                        <span class="badge bg-amber bg-opacity-10 text-amber border border-amber border-opacity-25 ms-auto" style="font-size: 0.7rem; box-shadow: 0 0 10px rgba(251, 191, 36, 0.3);"><?php echo $pending_kyc; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="nav-section">
            <div class="nav-section-title"><?php echo __('Management'); ?></div>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/users') !== false) ? 'active' : ''; ?>" href="/admin/users">
                <i class="fa-solid fa-users" style="opacity: 0.6;"></i>
                <?php echo __('All Users'); ?>
            </a>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/in-mail') !== false) ? 'active' : ''; ?>" href="/admin/in-mail">
                <i class="fa-solid fa-envelope" style="opacity: 0.6;"></i>
                <?php echo __('In-Mail'); ?>
            </a>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/plans') !== false) ? 'active' : ''; ?>" href="/admin/plans">
                <i class="fa-solid fa-gem" style="opacity: 0.6;"></i>
                <?php echo __('Investment Plans'); ?>
            </a>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/investments') !== false) ? 'active' : ''; ?>" href="/admin/investments">
                <i class="fa-solid fa-chart-line" style="opacity: 0.6;"></i>
                <?php echo __('Investments'); ?>
                <?php if ($ai_count > 0): ?>
                    <span class="badge bg-emerald bg-opacity-10 text-emerald border border-emerald border-opacity-25 ms-auto" style="font-size: 0.7rem; box-shadow: 0 0 10px rgba(16,185,129,0.25);"><?php echo $ai_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/transactions') !== false) ? 'active' : ''; ?>" href="/admin/transactions">
                <i class="fa-solid fa-money-bill-transfer" style="opacity: 0.6;"></i>
                <?php echo __('All Transactions'); ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title"><?php echo __('Settings'); ?></div>
            <a class="nav-link <?php echo (strpos($current_uri, '/admin/settings') !== false) ? 'active' : ''; ?>" href="/admin/settings">
                <i class="fa-solid fa-gears" style="opacity: 0.6;"></i>
                <?php echo __('Site Settings'); ?>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="mb-3">
            <?php if (is_google_translate_enabled()): ?>
                <!-- Google Translate Widget (admin style) -->
                <?php render_google_translate_widget('admin'); ?>
            <?php else: ?>
                <!-- Local Language Switcher -->
                <form action="/actions/switch-language" method="POST" id="langForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="redirect" value="<?php echo e($_SERVER['REQUEST_URI'] ?? '/admin/dashboard'); ?>">
                    <select name="lang" class="form-select form-select-sm form-select-dark" onchange="document.getElementById('langForm').submit()">
                        <option value="en_US" <?php echo ($admin_user['language'] ?? 'en_US') === 'en_US' ? 'selected' : ''; ?>>🇺🇸 English</option>
                        <option value="fr_FR" <?php echo ($admin_user['language'] ?? '') === 'fr_FR' ? 'selected' : ''; ?>>🇫🇷 Français</option>
                    </select>
                </form>
            <?php endif; ?>
        </div>
        <a href="/logout" class="btn btn-sm btn-danger-outline w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="fa-solid fa-right-from-bracket"></i>
            <?php echo __('Logout'); ?>
        </a>
    </div>
</aside>