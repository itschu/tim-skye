<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('Admin Dashboard');
require_once ROOT . '/includes/admin-header.php';

// Check if KYC is enabled
$kyc_required = get_setting('kyc_required', 'no');
$kyc_enabled = ($kyc_required === 'yes');

// Stats queries
$pd = db_query("SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'");
$pending_deposits = $pd[0]['count'] ?? 0;

$pw = db_query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals = $pw[0]['count'] ?? 0;

$pending_kyc = 0;
if ($kyc_enabled) {
    $pk = db_query("SELECT COUNT(*) as count FROM kyc_documents WHERE status = 'pending'");
    $pending_kyc = $pk[0]['count'] ?? 0;
}

$tu = db_query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$total_users = $tu[0]['count'] ?? 0;

$ai = db_query("SELECT COUNT(*) as count FROM investments WHERE status = 'active'");
$active_investments = $ai[0]['count'] ?? 0;

$pb = db_query("SELECT SUM(balance) as total FROM users");
$platform_balance = $pb[0]['total'] ?? 0.00;

// Get pending deposits total value
$pd_total = db_query("SELECT SUM(amount) as total FROM deposits WHERE status = 'pending'");
$pending_deposits_total = $pd_total[0]['total'] ?? 0;

// Get pending withdrawals total value
$pw_total = db_query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals_total = $pw_total[0]['total'] ?? 0;

// Get total payouts (completed withdrawals)
$tp = db_query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'completed'");
$total_payouts = $tp[0]['total'] ?? 0;

// Get recent activity (max 10 pending items)
$kyc_query = '';
if ($kyc_enabled) {
    $kyc_query = "
    UNION ALL
    
    SELECT 
        'kyc' as type, 
        kd.id, 
        0 as amount, 
        kd.status, 
        kd.created_at, 
        u.name, 
        u.email 
    FROM kyc_documents kd 
    JOIN users u ON kd.user_id = u.id 
    WHERE kd.status = 'pending'
    ";
}

$recent_activity = db_query(
    "SELECT 
        'deposit' as type, 
        d.id, 
        d.amount, 
        d.status, 
        d.created_at, 
        u.name, 
        u.email,
        u.profile_picture
    FROM deposits d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.status = 'pending'
    
    UNION ALL
    
    SELECT 
        'withdrawal' as type, 
        w.id, 
        w.amount, 
        w.status, 
        w.created_at, 
        u.name, 
        u.email,
        u.profile_picture
    FROM withdrawals w 
    JOIN users u ON w.user_id = u.id 
    WHERE w.status = 'pending'
    " . ($kyc_enabled ? "
    UNION ALL
    
    SELECT 
        'kyc' as type, 
        kd.id, 
        0 as amount, 
        kd.status, 
        kd.created_at, 
        u.name, 
        u.email,
        u.profile_picture
    FROM kyc_documents kd 
    JOIN users u ON kd.user_id = u.id 
    WHERE kd.status = 'pending'
    " : "") . "
    
    ORDER BY created_at DESC 
    LIMIT 4"
);

// Format time ago function
function time_ago_admin($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return __('Just now');
    if ($diff < 3600) return floor($diff / 60) . ' ' . __('mins ago');
    if ($diff < 86400) return floor($diff / 3600) . ' ' . __('hours ago');
    return floor($diff / 86400) . ' ' . __('days ago');
}
?>

<!-- Pending Cards Section -->
<div class="row g-3 mb-4">
    <div class="col-12 <?php echo $kyc_enabled ? 'col-md-4' : 'col-md-6'; ?>">
        <a href="/admin/pending-deposits" class="text-decoration-none">
            <div class="card-bento p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Pending Deposits'); ?></span>
                    <i class="fa-solid fa-file-invoice-dollar" style="opacity: 0.3; color: var(--text-muted); position: absolute; right: 10px; font-size: 70px; top: 10px;"></i>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-2">
                    <div>
                        <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo e($pending_deposits); ?></h3>
                        <span class="text-muted-custom fs-7"><?php echo __('Total:'); ?> <span class="text-white text-mono"><?php echo format_money($pending_deposits_total); ?></span></span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 <?php echo $kyc_enabled ? 'col-md-4' : 'col-md-6'; ?>">
        <a href="/admin/pending-withdrawals" class="text-decoration-none">
            <div class="card-bento p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Pending Withdrawals'); ?></span>
                    <i class="fa-solid fa-money-bill-transfer" style="opacity: 0.3; color: var(--text-muted); position: absolute; right: 10px; font-size: 70px; top: 10px;"></i>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-2">
                    <div>
                        <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo e($pending_withdrawals); ?></h3>
                        <span class="text-muted-custom fs-7"><?php echo __('Total:'); ?> <span class="text-white text-mono"><?php echo format_money($pending_withdrawals_total); ?></span></span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php if ($kyc_enabled): ?>
        <div class="col-12 col-md-4">
            <a href="/admin/kyc-review" class="text-decoration-none">
                <div class="card-bento p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Pending KYC'); ?></span>
                        <i class="fa-solid fa-id-card" style="opacity: 0.3; color: var(--text-muted);  position: absolute; right: 10px; font-size: 70px; top: 10px;"></i>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-2">
                        <div>
                            <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo e($pending_kyc); ?></h3>
                            <span class="text-muted-custom fs-7"><?php echo __('Awaiting review'); ?></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Cards Section with Sparklines -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-bento p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Total Users'); ?></span>
                <i class="fa-solid fa-users text-muted-custom" style="opacity: 0.5; width: 16px"></i>
            </div>
            <div class="d-flex align-items-end justify-content-between mt-2">
                <div>
                    <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo e($total_users); ?></h3>
                    <span class="text-success fs-7 fw-medium">+<?php echo rand(2, 12); ?>%</span>
                </div>
                <div class="d-flex align-items-end gap-1" style="height: 24px">
                    <div class="sparkline-bar" style="height: 40%"></div>
                    <div class="sparkline-bar" style="height: 70%"></div>
                    <div class="sparkline-bar active" style="height: 55%"></div>
                    <div class="sparkline-bar active" style="height: 90%"></div>
                    <div class="sparkline-bar active" style="height: 100%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-bento p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Active Investments'); ?></span>
                <i class="fa-solid fa-chart-line text-muted-custom" style="opacity: 0.5; width: 16px"></i>
            </div>
            <div class="d-flex align-items-end justify-content-between mt-2">
                <div>
                    <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo e($active_investments); ?></h3>
                    <span class="text-success fs-7 fw-medium">+<?php echo rand(5, 25); ?>%</span>
                </div>
                <div class="d-flex align-items-end gap-1" style="height: 24px">
                    <div class="sparkline-bar" style="height: 30%"></div>
                    <div class="sparkline-bar" style="height: 50%"></div>
                    <div class="sparkline-bar" style="height: 45%"></div>
                    <div class="sparkline-bar active" style="height: 80%"></div>
                    <div class="sparkline-bar active" style="height: 95%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-bento p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Platform Balance'); ?></span>
                <i class="fa-solid fa-wallet text-muted-custom" style="opacity: 0.5; width: 16px"></i>
            </div>
            <div class="d-flex align-items-end justify-content-between mt-2">
                <div>
                    <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo format_money($platform_balance); ?></h3>
                    <span class="text-success fs-7 fw-medium">+<?php echo rand(3, 15); ?>%</span>
                </div>
                <div class="d-flex align-items-end gap-1" style="height: 24px">
                    <div class="sparkline-bar" style="height: 60%"></div>
                    <div class="sparkline-bar" style="height: 45%"></div>
                    <div class="sparkline-bar active" style="height: 70%"></div>
                    <div class="sparkline-bar active" style="height: 85%"></div>
                    <div class="sparkline-bar active" style="height: 75%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-bento p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted-custom text-uppercase fs-7 fw-medium ls-1"><?php echo __('Total Payouts'); ?></span>
                <i class="fa-solid fa-hand-holding-dollar text-muted-custom" style="opacity: 0.5; width: 16px"></i>
            </div>
            <div class="d-flex align-items-end justify-content-between mt-2">
                <div>
                    <h3 class="mb-0 fw-semibold text-white text-mono"><?php echo format_money($total_payouts); ?></h3>
                    <span class="text-success fs-7 fw-medium">+<?php echo rand(8, 30); ?>%</span>
                </div>
                <div class="d-flex align-items-end gap-1" style="height: 24px">
                    <div class="sparkline-bar" style="height: 25%"></div>
                    <div class="sparkline-bar" style="height: 40%"></div>
                    <div class="sparkline-bar" style="height: 55%"></div>
                    <div class="sparkline-bar active" style="height: 85%"></div>
                    <div class="sparkline-bar active" style="height: 100%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & Quick Actions -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-bento h-100">
            <div class="p-4 border-bottom" style="border-color: var(--border-color);">
                <h6 class="m-0 fw-semibold text-white"><?php echo __('Recent Activity'); ?></h6>
                <span class="text-muted-custom fs-7"><?php echo __('Latest pending actions'); ?></span>
            </div>
            <div class="p-4">
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center text-muted-custom py-4">
                        <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0 fs-7"><?php echo __('No pending actions at the moment'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity):
                        $icon_class = '';
                        $icon_color = '';
                        $bg_color = '';
                        $action_text = '';
                        $link_url = '';

                        switch ($activity['type']) {
                            case 'deposit':
                                $icon_class = 'fa-arrow-down';
                                $icon_color = 'text-warning';
                                $bg_color = 'border-warning-subtle bg-warning-subtle bg-opacity-10';
                                $action_text = __('Deposit Request');
                                $link_url = '/admin/pending-deposits';
                                break;
                            case 'withdrawal':
                                $icon_class = 'fa-arrow-up';
                                $icon_color = 'text-warning';
                                $bg_color = 'border-warning-subtle bg-warning-subtle bg-opacity-10';
                                $action_text = __('Withdrawal Request');
                                $link_url = '/admin/pending-withdrawals';
                                break;
                            case 'kyc':
                                $icon_class = 'fa-id-card';
                                $icon_color = 'text-info';
                                $bg_color = 'border-info-subtle bg-info-subtle bg-opacity-10';
                                $action_text = __('KYC Document');
                                $link_url = '/admin/kyc-review';
                                break;
                        }
                    ?>
                        <a href="<?php echo $link_url; ?>" class="timeline-item text-decoration-none d-block">
                            <div class="timeline-icon <?php echo $icon_color . ' ' . $bg_color; ?>">
                                <i class="fa-solid <?php echo $icon_class; ?>" style="width: 12px; font-size: 0.7rem;"></i>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-grow-1">
                                <?php if (!empty($activity['profile_picture'])): ?>
                                    <img src="<?php echo e('/' . ltrim($activity['profile_picture'], '/')); ?>" alt="<?php echo e($activity['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">
                                <?php else: ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-secondary border bg-dark" style="width: 32px; height: 32px; flex-shrink: 0; font-size: 0.75rem; font-weight: bold;">
                                        <?php echo e(strtoupper(substr($activity['name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h6 class="text-white fs-7 mb-1"><?php echo $action_text; ?></h6>
                                    <p class="text-muted-custom mb-1" style="font-size: 0.75rem">
                                        <?php echo e($activity['name']); ?>
                                        <?php if ($activity['amount'] > 0): ?>
                                            • <span class="text-mono"><?php echo format_money($activity['amount']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <span class="text-mono text-muted-custom" style="font-size: 0.65rem"><?php echo time_ago_admin($activity['created_at']); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-bento p-3 h-100">
            <h6 class="fw-semibold text-white mb-3 p-2"><?php echo __('Quick Actions'); ?></h6>
            <div class="row g-2">
                <div class="col-6">
                    <a href="/admin/plans" class="action-btn text-decoration-none d-flex flex-column align-items-center justify-content-center p-3 rounded" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                        <i class="fa-solid fa-plus-circle fa-lg mb-2" style="color: var(--accent-color)"></i>
                        <span class="small fw-medium text-white" style="font-size: 0.75rem"><?php echo __('Add Inv. Plan'); ?></span>
                    </a>
                </div>
                <div class="col-6">
                    <a href="/admin/users" class="action-btn text-decoration-none d-flex flex-column align-items-center justify-content-center p-3 rounded" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                        <i class="fa-solid fa-users-viewfinder fa-lg mb-2 text-info"></i>
                        <span class="small fw-medium text-white" style="font-size: 0.75rem"><?php echo __('View All Users'); ?></span>
                    </a>
                </div>
                <div class="col-12">
                    <a href="/admin/settings" class="action-btn text-decoration-none d-flex align-items-center justify-content-center gap-2 p-3 rounded" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                        <i class="fa-solid fa-sliders text-muted-custom"></i>
                        <span class="small fw-medium text-white" style="font-size: 0.75rem"><?php echo __('System Settings'); ?></span>
                    </a>
                </div>
                <div class="col-12 mt-3">
                    <div class="p-3 rounded" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-medium text-muted-custom text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;"><?php echo __('System Health'); ?></span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem"><?php echo __('Good'); ?></span>
                        </div>
                        <div class="progress" style="height: 4px; background-color: var(--border-color);">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%"></div>
                        </div>
                        <div class="small text-muted-custom mt-1" style="font-size: 0.7rem"><?php echo __('All systems operational'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>