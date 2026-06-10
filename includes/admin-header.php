<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Load translation functions if not already loaded
if (!function_exists('__')) {
    require_once __DIR__ . '/translation-functions.php';
}

// Initialize translation system
if (isset($_SESSION['user_id'])) {
    $user_lang = get_user_language($_SESSION['user_id']);
    init_translation($user_lang);
} else {
    init_translation();
}

// Determine current admin user
$user_id = $_SESSION['user_id'] ?? ($GLOBALS['current_user']['id'] ?? null);
$admin_user = null;
if ($user_id) {
    $rows = db_query('SELECT * FROM users WHERE id = ?', [$user_id]);
    $admin_user = $rows[0] ?? null;
}

$site_name = get_setting('site_name', 'Investment Platform');
$full_title = (isset($page_title) ? e($page_title) . ' - ' : '') . e($site_name);

// Get site logo for favicon
$site_logo = SITE_ICON;

// Determine current page for breadcrumb
$current_uri = $_SERVER['REQUEST_URI'] ?? '';
// Parse URL to get just the path without query string
$path = parse_url($current_uri, PHP_URL_PATH) ?? '';
$page_segments = explode('/', trim($path, '/'));
$current_section = end($page_segments);
$current_section = str_replace('-', ' ', $current_section);
$current_section = ucwords($current_section);
if (empty($current_section) || in_array($current_section, ['Admin', 'Dashboard', 'Index'])) {
    $current_section = __('Dashboard');
}

$stylesheet_version = filemtime(ROOT . '/assets/css/admin-styles.css');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $full_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin-styles.css?hash=<?php echo $stylesheet_version; ?>">

    <link rel="icon" type="image/png" href="<?php echo e(SITE_ICON); ?>">

    <script>
        window.formatCurrency = function(amount) {
            return '<?php echo function_exists('get_currency_symbol') ? get_currency_symbol() : '$'; ?>' + parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
    </script>
</head>

<body>
    <div class="admin-layout" x-data="{ sidebarOpen: false, profileOpen: false }">
        <!-- Mobile Sidebar Backdrop -->
        <div class="sidebar-backdrop"
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            style="display: none;"></div>

        <?php include_once __DIR__ . '/admin-sidebar.php'; ?>

        <main class="main-content">
            <!-- Top Navbar with Glass-morphic Design -->
            <nav class="top-navbar glass-header" style="z-index: 1056;">
                <div class="d-flex align-items-center gap-3">
                    <!-- Mobile Hamburger Menu -->
                    <button @click="sidebarOpen = !sidebarOpen" class="mobile-menu-btn d-lg-none">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>

                    <!-- Breadcrumb Navigation -->
                    <div class="d-none d-lg-flex align-items-center gap-2 text-muted-custom small">
                        <span class="text-white"><?php echo __('Admin'); ?></span>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; opacity: 0.5;"></i>
                        <span class="text-secondary"><?php echo __($current_section); ?></span>
                    </div>

                    <!-- Site Name (Mobile only) -->
                    <span class="text-white font-mono d-lg-none" style="font-size: 1rem;">
                        <?php echo e(strtoupper($site_name)); ?>
                    </span>
                </div>

                <div class="d-flex align-items-center gap-4">
                    <div class="position-relative">
                        <div @click="profileOpen = !profileOpen" @click.outside="profileOpen = false" class="admin-profile-btn">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                style="width: 36px; height: 36px; font-size: 0.85rem; background: var(--bg-hover); border: 1px solid var(--border-color);">
                                <?php echo e(strtoupper(substr($admin_user['name'] ?? 'A', 0, 2))); ?>
                            </div>
                            <div class="d-none d-sm-block">
                                <div class="text-white text-truncate" style="font-size: 0.9rem; line-height: 1.1; max-width: 150px;"><?php echo e($admin_user['name'] ?? 'Admin'); ?></div>
                            </div>
                            <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; transition: transform 0.2s; color: var(--text-muted);" :style="profileOpen ? 'transform: rotate(180deg)' : ''"></i>
                        </div>

                        <div
                            x-show="profileOpen"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="position-absolute end-0 mt-2 v-card border-subtle profile-dropdown-menu"
                            style="width: 220px; display: none; z-index: 1061;">
                            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important; background: rgba(255,255,255,0.02);">
                                <p class="m-0 fw-semibold small text-white"><?php echo __('Admin Account'); ?></p>
                                <p class="m-0 small text-muted-custom text-truncate"><?php echo e($admin_user['email'] ?? ''); ?></p>
                            </div>
                            <div class="py-1">
                                <a href="/user/profile" class="d-block px-3 py-2 text-decoration-none text-white small hover-white">
                                    <i class="fa-solid fa-user-gear me-2 text-muted-custom"></i><?php echo __('Profile'); ?>
                                </a>
                                <div class="border-top my-1" style="border-color: var(--border-color) !important;"></div>
                                <a href="/logout" class="d-block px-3 py-2 text-decoration-none small text-red">
                                    <i class="fa-solid fa-power-off me-2 text-red"></i><?php echo __('Sign Out'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Profile Dropdown Full Page Overlay -->
            <div x-show="profileOpen"
                @click="profileOpen = false"
                x-transition.opacity.duration.200ms
                class="profile-overlay"
                style="display: none;"></div>

            <!-- Session messages -->
            <?php if (session_status() === PHP_SESSION_NONE) {
                session_start();
            } ?>
            <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error']) || !empty($_SESSION['info'])): ?>
                <div class="alert-fixed">
                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-check"></i>
                            <span><?php echo e($_SESSION['success']); ?></span>
                            <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert').style.display='none'"><i class="fas fa-times"></i></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-error alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-xmark"></i>
                            <span><?php echo e($_SESSION['error']); ?></span>
                            <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert').style.display='none'"><i class="fas fa-times"></i></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['info'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-info"></i>
                            <span><?php echo e($_SESSION['info']); ?></span>
                            <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert').style.display='none'"><i class="fas fa-times"></i></button>
                        </div>
                        <?php unset($_SESSION['info']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Content Area -->
            <div class="content-area fade-in">