<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('Investments');

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as count FROM investments i 
                JOIN users u ON i.user_id = u.id 
                JOIN investment_plans p ON i.plan_id = p.id 
                {$where_clause}";
$count_result = db_query($count_query, $params);
$total_count = $count_result[0]['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// Ensure page is within bounds
$page = min($page, max(1, $total_pages));

// Build query with pagination
$offset = ($page - 1) * $per_page;
$query = "SELECT i.*, i.total_profit_earned AS profit_earned, u.name as user_name, u.email as user_email, u.profile_picture, p.name as plan_name, p.roi_percentage, p.duration_days, p.payout_interval, p.capital_return 
          FROM investments i 
          JOIN users u ON i.user_id = u.id 
          JOIN investment_plans p ON i.plan_id = p.id 
          {$where_clause} 
          ORDER BY i.created_at DESC 
          LIMIT ? OFFSET ?";
$query_params = array_merge($params, [$per_page, $offset]);
$investments = db_query($query, $query_params);

// Get counts for each status
$status_counts = [
    'all' => db_query("SELECT COUNT(*) as count FROM investments", [])[0]['count'] ?? 0,
    'active' => db_query("SELECT COUNT(*) as count FROM investments WHERE status = 'active'", [])[0]['count'] ?? 0,
    'completed' => db_query("SELECT COUNT(*) as count FROM investments WHERE status = 'completed'", [])[0]['count'] ?? 0,
    'cancelled' => db_query("SELECT COUNT(*) as count FROM investments WHERE status = 'cancelled'", [])[0]['count'] ?? 0,
];

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4" x-data="{ sheetOpen: false, selected: {} }">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h5 fw-semibold text-white mb-1"><?php echo __('Investments'); ?></h2>
            <p class="text-muted small mb-0"><?php echo __('Manage and monitor all user investments.'); ?></p>
        </div>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
            <div class="position-relative" style="min-width: 200px;">
                <i class="fa-solid fa-magnifying-glass position-absolute text-muted-custom" style="left: 1rem; top: 50%; transform: translateY(-50%); font-size:16px;"></i>
                <input type="text" name="search" class="form-control form-control-custom ps-5" placeholder="<?php echo __('Search investor or plan...'); ?>" value="<?php echo e($search); ?>">
            </div>
            <button type="submit" class="btn btn-dark border border-subtle">
                <i class="fa-solid fa-magnifying-glass d-md-none" style="font-size:16px;"></i>
                <span class="d-none d-md-inline"><?php echo __('Search'); ?></span>
            </button>
            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="/admin/investments" class="btn btn-dark border border-subtle">
                    <i class="fa-solid fa-xmark d-md-none" style="font-size:16px;"></i>
                    <span class="d-none d-md-inline"><?php echo __('Clear'); ?></span>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-4 overflow-x-auto pb-2 border-bottom border-subtle flex-wrap">
        <a href="?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
            <?php echo __('All Investments'); ?> (<?php echo $status_counts['all']; ?>)
        </a>
        <a href="?status=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
            <?php echo __('Active Running'); ?> (<?php echo $status_counts['active']; ?>)
        </a>
        <a href="?status=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
            <?php echo __('Completed'); ?> (<?php echo $status_counts['completed']; ?>)
        </a>
        <a href="?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
            <?php echo __('Cancelled'); ?> (<?php echo $status_counts['cancelled']; ?>)
        </a>
    </div>

    <!-- Investments Table -->
    <div class="card bg-card border-subtle">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th><?php echo __('Investor'); ?></th>
                        <th><?php echo __('Plan Details'); ?></th>
                        <th><?php echo __('Amount'); ?></th>
                        <th><?php echo __('Profit Earned'); ?></th>
                        <th><?php echo __('Progress'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($investments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-magnifying-glass text-muted opacity-25 mb-3 d-block" style="font-size:48px;"></i>
                                <p><?php echo __('No investments found.'); ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($investments as $inv):
                            $initial = substr($inv['user_name'], 0, 1);

                            // Calculate progress
                            $start_time = strtotime($inv['start_date']);
                            $end_time = strtotime($inv['end_date']);
                            $current_time = time();

                            if ($inv['status'] === 'completed') {
                                $progress = 100;
                                $days_left = 0;
                            } elseif ($inv['status'] === 'cancelled') {
                                $progress = 0;
                                $days_left = 0;
                            } else {
                                $total_duration = $end_time - $start_time;
                                $elapsed = $current_time - $start_time;
                                $progress = $total_duration > 0 ? min(100, max(0, ($elapsed / $total_duration) * 100)) : 0;
                                $days_left = max(0, ceil(($end_time - $current_time) / 86400));
                            }
                        ?>
                            <tr @click="selected = <?php echo htmlspecialchars(json_encode([
                                                        'id' => $inv['id'],
                                                        'user_name' => $inv['user_name'],
                                                        'user_email' => $inv['user_email'],
                                                        'plan_name' => $inv['plan_name'],
                                                        'roi_percentage' => $inv['roi_percentage'],
                                                        'amount' => $inv['amount'],
                                                        'profit_earned' => $inv['profit_earned'],
                                                        'status' => $inv['status'],
                                                        'start_date' => format_datetime_iso($inv['start_date']),
                                                        'end_date' => format_datetime_iso($inv['end_date']),
                                                        'next_payout_date' => format_datetime_iso($inv['next_payout_date']),
                                                        'duration_days' => $inv['duration_days'],
                                                        'payout_interval' => $inv['payout_interval'],
                                                        'capital_return' => $inv['capital_return'],
                                                        'progress' => round($progress),
                                                        'days_left' => $days_left
                                                    ])); ?>; sheetOpen = true" style="cursor:pointer">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($inv['profile_picture'])): ?>
                                            <img src="<?php echo e('/' . ltrim($inv['profile_picture'], '/')); ?>" alt="<?php echo e($inv['user_name']); ?>" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover; border: 1px solid var(--border-color);">
                                        <?php else: ?>
                                            <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-white border border-subtle" style="width: 36px; height: 36px; font-size: 0.9rem">
                                                <?php echo e(strtoupper($initial)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-medium text-white"><?php echo e($inv['user_name']); ?></div>
                                            <div class="small text-muted-custom text-mono"><?php echo e(substr($inv['id'], 0, 8)); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="text-white fw-medium"><?php echo e($inv['plan_name']); ?></span>
                                        <div class="text-success text-mono small"><?php echo format_percentage($inv['roi_percentage']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-white text-mono"><?php echo format_money($inv['amount']); ?></span>
                                </td>
                                <td>
                                    <span class="text-success text-mono fw-medium"><?php echo format_money($inv['profit_earned']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex justify-content-between" style="font-size:0.65rem">
                                            <span class="text-muted-custom"><?php echo $days_left; ?> <?php echo __('days left'); ?></span>
                                            <span class="text-white"><?php echo round($progress); ?>%</span>
                                        </div>
                                        <div class="progress-slim">
                                            <div class="progress-bar-slim" style="width:<?php echo round($progress); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill status-<?php echo e($inv['status']); ?>">
                                        <span class="d-inline-block rounded-circle" style="width:6px;height:6px;background-color:currentColor"></span>
                                        <?php echo ucfirst($inv['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fa-solid fa-chevron-right text-muted-custom" style="font-size:16px;"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top border-subtle d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <span class="small text-muted">
                    <?php echo __('Showing'); ?> <?php echo (($page - 1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_count); ?> <?php echo __('of'); ?> <?php echo $total_count; ?> <?php echo __('entries'); ?>
                </span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $query_string = '?status=' . $status_filter;
                        if (!empty($search)) $query_string .= '&search=' . urlencode($search);
                        ?>
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>"><i class="fa-solid fa-chevron-left" style="font-size:16px;"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fa-solid fa-chevron-left" style="font-size:16px;"></i></span></li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>"><i class="fa-solid fa-chevron-right" style="font-size:16px;"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fa-solid fa-chevron-right" style="font-size:16px;"></i></span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detail Sheet -->
    <template x-teleport="body">
        <div x-show="sheetOpen"
            x-cloak
            class="sheet-overlay"
            x-transition.opacity
            @click="sheetOpen = false"></div>
    </template>


    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }">
            <!-- Sheet Header -->
            <div class="p-4 border-bottom border-subtle bg-black d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <template x-if="selected.profile_picture">
                        <img :src="'/' + selected.profile_picture" :alt="selected.user_name" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border-color);">
                    </template>
                    <template x-if="!selected.profile_picture">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-white border border-subtle" style="width: 40px; height: 40px; font-size: 0.95rem; flex-shrink: 0;">
                            <span x-text="selected.user_name ? selected.user_name.charAt(0).toUpperCase() : ''"></span>
                        </div>
                    </template>
                    <div>
                        <a :href="'/admin/users?search=' + selected.user_email" class="" style="text-decoration: none;">
                            <h6 class="h6 mb-0 text-white" x-text="selected.user_name"></h6>
                        </a>
                        <div class="small text-muted-custom d-flex align-items-center gap-2">
                            <span x-text="selected.plan_name"></span>
                            <span @click="navigator.clipboard.writeText(selected.id); this.getElementById('copyIcon').style.display='none'; this.getElementById('checkIcon').style.display='inline'; setTimeout(() => { this.getElementById('copyIcon').style.display='inline'; this.getElementById('checkIcon').style.display='none'; }, 2000)" style="cursor:pointer;">
                                <i id="copyIcon" class="fas fa-copy"></i>
                                <i id="checkIcon" class="fas fa-check text-success" style="display:none;"></i>
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="status-pill" :class="'status-' + selected.status" x-text="selected.status"></span>
                    </div>
                </div>

                <button class="btn btn-link text-muted-custom p-0" @click="sheetOpen = false"><i class="fas fa-times"></i></button>
            </div>

            <!-- Sheet Body -->
            <div class="flex-grow-1 overflow-y-auto p-4 sheet-body">
                <!-- Amounts Section -->
                <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Investment Summary'); ?></h6>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 border border-subtle rounded bg-black">
                            <p class="small text-muted-custom fw-bold mb-2"><?php echo __('Invested Amount'); ?></p>
                            <p class="text-white h6 mb-0 text-mono" x-text="'$' + parseFloat(selected.amount || 0).toFixed(2)"></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border border-subtle rounded bg-black">
                            <p class="small text-muted-custom fw-bold mb-2"><?php echo __('Current Value'); ?></p>
                            <p class="text-success h6 mb-0 text-mono" x-text="'$' + (parseFloat(selected.amount || 0) + parseFloat(selected.profit_earned || 0)).toFixed(2)"></p>
                        </div>
                    </div>
                </div>

                <!-- Maturity Progress -->
                <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Progress'); ?></h6>
                <div class="p-3 border border-subtle rounded bg-black mb-4">
                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.75rem;">
                        <span class="text-muted-custom" x-text="selected.start_date"></span>
                        <span class="text-white fw-bold" x-text="selected.progress + '%'"></span>
                        <span class="text-muted-custom" x-text="selected.end_date"></span>
                    </div>
                    <div class="progress-slim">
                        <div class="progress-bar-slim" :style="{ width: selected.progress + '%' }"></div>
                    </div>
                    <p class="text-muted-custom small mt-2 mb-0"><span x-text="selected.days_left"></span> <?php echo __('days remaining'); ?></p>
                </div>

                <!-- Performance Details -->
                <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Performance Details'); ?></h6>
                <div class="p-3 border border-subtle rounded bg-black mb-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted-custom small"><?php echo __('Plan ROI'); ?></span>
                            <span class="text-success fw-bold" x-text="selected.roi_percentage + '%'"></span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top border-subtle pt-3">
                        <span class="text-muted-custom small"><?php echo __('Net Profit Earned'); ?></span>
                        <span class="text-success text-mono fw-bold" x-text="'$' + parseFloat(selected.profit_earned || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Additional Details -->
                <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Additional Details'); ?></h6>
                <div class="p-3 border border-subtle rounded bg-black mb-4">
                    <div class="row g-2">
                        <div class="col-6">
                            <p class="text-muted-custom small mb-1"><?php echo __('Payout Interval'); ?></p>
                            <p class="text-white small fw-medium mb-0" x-text="selected.payout_interval"></p>
                        </div>
                        <div class="col-6">
                            <p class="text-muted-custom small mb-1"><?php echo __('Duration'); ?></p>
                            <p class="text-white small fw-medium mb-0" x-text="selected.duration_days + ' ' + '<?php echo __('days'); ?>'"></p>
                        </div>
                        <div class="col-6">
                            <p class="text-muted-custom small mb-1"><?php echo __('Next Payout'); ?></p>
                            <p class="text-white text-mono small mb-0" x-text="selected.next_payout_date"></p>
                        </div>
                        <div class="col-6">
                            <p class="text-muted-custom small mb-1"><?php echo __('Capital Return'); ?></p>
                            <span class="badge small" :class="selected.capital_return ? 'badge-active' : 'badge-archived'" x-text="selected.capital_return ? '<?php echo __('Yes'); ?>' : '<?php echo __('No'); ?>'"></span>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions -->
                <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Actions'); ?></h6>
                <div class="d-flex gap-2">
                    <a :href="'/admin/investment-details?id=' + selected.id" class="btn btn-sm btn-primary flex-grow-1">
                        <?php echo __('View Full Details'); ?>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-danger border-subtle text-danger" x-show="selected.status === 'active'">
                        <?php echo __('Cancel'); ?>
                    </a>
                </div>
            </div>
        </div>
    </template>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>