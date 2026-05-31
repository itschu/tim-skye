<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('Investment Details');

$investment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($investment_id <= 0) {
    $_SESSION['error'] = __('Invalid investment ID');
    header('Location: /admin/investments');
    exit;
}

// Get investment details
$investment = db_query(
    // Alias total_profit_earned to profit_earned for template compatibility
    "SELECT i.*, i.total_profit_earned AS profit_earned, u.name as user_name, u.email as user_email, u.profile_picture, p.name as plan_name, p.roi_percentage, p.duration_days, p.payout_interval, p.capital_return 
     FROM investments i 
     JOIN users u ON i.user_id = u.id 
     JOIN investment_plans p ON i.plan_id = p.id 
     WHERE i.id = ?",
    [$investment_id]
);

if (empty($investment)) {
    $_SESSION['error'] = __('Investment not found');
    header('Location: /admin/investments');
    exit;
}

$inv = $investment[0];

// Get profit history with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$profit_like = '%Investment #' . $investment_id . '%';
$count_row = db_query("SELECT COUNT(*) as c FROM transactions WHERE user_id = ? AND type = 'profit' AND details LIKE ?", [$inv['user_id'], $profit_like]);
$profits_count = $count_row && count($count_row) ? (int)$count_row[0]['c'] : 0;
$total_pages = max(1, ceil($profits_count / $per_page));

$profits = db_query(
    "SELECT * FROM transactions 
     WHERE user_id = ? AND type = 'profit' AND details LIKE ? 
     ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$inv['user_id'], $profit_like, $per_page, $offset]
);

// Simple pagination helper
function get_pagination_pages_local($current, $total, $max_buttons = 7)
{
    $pages = [];
    if ($total <= $max_buttons) {
        for ($i = 1; $i <= $total; $i++) $pages[] = $i;
        return $pages;
    }

    $pages[] = 1;
    $range = $max_buttons - 2;
    $start = max(2, $current - intval(floor($range / 2)));
    $end = min($total - 1, $start + $range - 1);
    if ($end - $start + 1 < $range) {
        $start = max(2, $end - $range + 1);
    }
    if ($start > 2) $pages[] = '...';
    for ($i = $start; $i <= $end; $i++) $pages[] = $i;
    if ($end < $total - 1) $pages[] = '...';
    $pages[] = $total;
    return $pages;
}

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h5 fw-semibold text-white mb-1"><?php echo __('Investment Details'); ?></h2>
            <p class="text-muted small mb-0"><?php echo __('View investment information and profit history.'); ?></p>
        </div>
        <a href="/admin/investments" class="btn btn-outline-secondary border-subtle text-muted-custom">
            <i data-lucide="arrow-left" size="16" class="me-1"></i><?php echo __('Back to Investments'); ?>
        </a>
    </div>

    <div class="row g-4">
        <!-- Investment Info Card -->
        <div class="col-lg-8">
            <div class="card bg-card border-subtle mb-4">
                <div class="p-4 border-bottom border-subtle d-flex justify-content-between align-items-center">
                    <h5 class="text-white mb-0">
                        <i data-lucide="chart-line" size="18" class="me-2 text-primary"></i><?php echo __('Investment Information'); ?>
                    </h5>
                    <?php
                    $status_colors = [
                        'active' => 'active',
                        'completed' => 'active',
                        'cancelled' => 'archived'
                    ];
                    $status_class = $status_colors[$inv['status']] ?? 'active';
                    ?>
                    <span class="status-pill status-<?php echo e($inv['status']); ?>"><?php echo ucfirst($inv['status']); ?></span>
                </div>
                <div class="p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('User'); ?></label>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <?php if (!empty($inv['profile_picture'])): ?>
                                    <img src="<?php echo e('/' . ltrim($inv['profile_picture'], '/')); ?>" alt="<?php echo e($inv['user_name']); ?>" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover; border: 1px solid var(--border-color);">
                                <?php else: ?>
                                    <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-white border border-subtle" style="width: 48px; height: 48px; font-size: 0.9rem">
                                        <?php echo e(strtoupper(substr($inv['user_name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-white"><?php echo e($inv['user_name']); ?></div>
                                    <div class="small text-muted-custom"><?php echo e($inv['user_email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Investment Plan'); ?></label>
                            <div class="mt-2">
                                <div class="fw-bold fs-5 text-white"><?php echo e($inv['plan_name']); ?></div>
                                <div class="text-success text-mono small"><?php echo format_percentage($inv['roi_percentage']); ?> ROI</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Invested Amount'); ?></label>
                            <div class="fw-bold fs-4 text-white text-mono mt-2"><?php echo format_money($inv['amount']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Profit Earned'); ?></label>
                            <div class="fw-bold fs-4 text-success text-mono mt-2"><?php echo format_money($inv['profit_earned']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Total Return'); ?></label>
                            <div class="fw-bold fs-4 text-primary text-mono mt-2"><?php echo format_money($inv['amount'] + $inv['profit_earned']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Start Date'); ?></label>
                            <div class="text-white text-mono mt-2"><?php echo date('M j, Y H:i', strtotime($inv['start_date'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('End Date'); ?></label>
                            <div class="text-white text-mono mt-2"><?php echo date('M j, Y H:i', strtotime($inv['end_date'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Duration'); ?></label>
                            <div class="text-white mt-2"><?php echo $inv['duration_days']; ?> <?php echo __('days'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Payout Interval'); ?></label>
                            <div class="text-white mt-2"><?php echo ucfirst($inv['payout_interval']); ?></div>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted-custom text-uppercase fw-bold"><?php echo __('Capital Return'); ?></label>
                            <div class="mt-2">
                                <?php if ($inv['capital_return']): ?>
                                    <span class="badge badge-active"><i data-lucide="check" size="12" class="me-1"></i><?php echo __('Yes - Capital will be returned at end of term'); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-archived"><i data-lucide="x" size="12" class="me-1"></i><?php echo __('No - Capital not returned'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profit History -->
            <div class="card bg-card border-subtle">
                <div class="p-4 border-bottom border-subtle">
                    <h5 class="text-white mb-0">
                        <i data-lucide="history" size="18" class="me-2 text-primary"></i><?php echo __('Profit History'); ?>
                    </h5>
                </div>
                <div class="p-0">
                    <?php if (empty($profits)): ?>
                        <div class="p-4 text-center text-muted">
                            <i data-lucide="inbox" size="48" class="text-muted opacity-25 d-block mb-2"></i>
                            <p class="m-0"><?php echo __('No profit payouts yet.'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo __('Date'); ?></th>
                                        <th><?php echo __('Amount'); ?></th>
                                        <th><?php echo __('Status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($profits as $profit): ?>
                                        <tr>
                                            <td class="text-mono small"><?php echo date('M j, Y H:i', strtotime($profit['created_at'])); ?></td>
                                            <td class="fw-bold text-success text-mono"><?php echo format_money($profit['amount']); ?></td>
                                            <td><span class="badge badge-active"><?php echo __('Completed'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for profit history -->
                        <?php if ($total_pages > 1): ?>
                            <div class="p-3 border-top border-subtle">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0 gap-2">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $investment_id; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                    <i data-lucide="chevron-left" size="16"></i>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link"><i data-lucide="chevron-left" size="16"></i></span></li>
                                        <?php endif; ?>

                                        <?php
                                        $pages_to_show = get_pagination_pages_local($page, $total_pages);
                                        foreach ($pages_to_show as $p):
                                            if ($p === '...'):
                                        ?>
                                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                            <?php elseif ($p == $page): ?>
                                                <li class="page-item active"><span class="page-link"><?php echo $p; ?></span></li>
                                            <?php else: ?>
                                                <li class="page-item"><a class="page-link" href="?id=<?php echo $investment_id; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                                        <?php endif;
                                        endforeach; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $investment_id; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                                    <i data-lucide="chevron-right" size="16"></i>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link"><i data-lucide="chevron-right" size="16"></i></span></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <!-- Progress Card -->
            <?php if ($inv['status'] === 'active'): ?>
                <?php
                $start = strtotime($inv['start_date']);
                $end = strtotime($inv['end_date']);
                $now = time();
                $progress = min(100, max(0, (($now - $start) / ($end - $start)) * 100));
                ?>
                <div class="card bg-card border-subtle mb-4">
                    <div class="p-4 border-bottom border-subtle">
                        <h5 class="text-white mb-0"><?php echo __('Progress'); ?></h5>
                    </div>
                    <div class="p-4">
                        <div class="progress-slim mb-3">
                            <div class="progress-bar-slim" style="width:<?php echo round($progress); ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted-custom small">
                            <span><?php echo __('Started'); ?></span>
                            <span><?php echo round($progress); ?>% <?php echo __('complete'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card bg-card border-subtle">
                <div class="p-4 border-bottom border-subtle">
                    <h5 class="text-white mb-0"><?php echo __('Quick Actions'); ?></h5>
                </div>
                <div class="p-4 d-flex flex-column gap-2">
                    <a href="/admin/users?search=<?php echo urlencode($inv['user_email']); ?>" class="btn btn-primary btn-sm w-100">
                        <i data-lucide="user" size="16" class="me-1"></i><?php echo __('View User Profile'); ?>
                    </a>
                    <a href="/admin/plans" class="btn btn-outline-secondary border-subtle text-muted-custom btn-sm w-100">
                        <i data-lucide="gem" size="16" class="me-1"></i><?php echo __('View All Plans'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>