<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Transactions');
$active_nav = 'transactions';
$user_id = $_SESSION['user_id'];

// Get filters from URL
$filter = $_GET['filter'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build date condition
$date_where = "";
if ($date_filter === 'today') {
    $date_where = " AND DATE(created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $date_where = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $date_where = " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
}

// Get completed transactions from transactions table
$params = [$user_id];
$where = "user_id = ?";

if ($filter === 'deposits') {
    $where .= " AND type = 'deposit'";
} elseif ($filter === 'withdrawals') {
    $where .= " AND type = 'withdrawal'";
} elseif ($filter === 'profits') {
    $where .= " AND type = 'profit'";
} elseif ($filter === 'referrals') {
    $where .= " AND type = 'referral'";
} elseif ($filter === 'investments') {
    $where .= " AND type = 'investment'";
}

// Add status filter for transactions
if ($status_filter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

// Add date filter
$where .= $date_where;

// Get count for pagination
$count_row = db_query("SELECT COUNT(*) as c FROM transactions WHERE $where", $params);
$completed_count = $count_row && count($count_row) ? (int)$count_row[0]['c'] : 0;

// Get transactions with pagination
$per_page = (int)$per_page;
$offset = (int)$offset;
$completed_transactions = db_query("SELECT * FROM transactions WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", $params);

// Get pending deposits for deposits filter
$pending_deposits = [];
$pending_count = 0;
if (($filter === 'deposits' || $filter === 'all') && ($status_filter === 'all' || $status_filter === 'pending')) {
    // Build pending deposits query with date filter
    $pending_where = "user_id = ? AND status = 'pending'";
    $pending_params = [$user_id];

    if ($date_filter === 'today') {
        $pending_where .= " AND DATE(created_at) = CURDATE()";
    } elseif ($date_filter === 'week') {
        $pending_where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($date_filter === 'month') {
        $pending_where .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
    }

    // Get pending count
    $pending_count_row = db_query("SELECT COUNT(*) as c FROM deposits WHERE $pending_where AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.source_id = deposits.id AND t.type = 'deposit')", $pending_params);
    $pending_count = $pending_count_row && count($pending_count_row) ? (int)$pending_count_row[0]['c'] : 0;

    // Calculate offset for pending deposits
    $pending_offset = max(0, $offset - $completed_count);
    $pending_limit = $per_page - count($completed_transactions);

    if ($pending_limit > 0) {
        $pending_raw = db_query(
            "SELECT id, user_id, amount, status, payment_method, created_at
             FROM deposits
             WHERE $pending_where AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.source_id = deposits.id AND t.type = 'deposit')
             ORDER BY created_at DESC LIMIT $pending_limit OFFSET $pending_offset",
            $pending_params
        );

        if ($pending_raw) {
            foreach ($pending_raw as $pd) {
                $pending_deposits[] = [
                    'id' => 'd_' . $pd['id'], // Prefix to avoid ID collisions
                    'user_id' => $pd['user_id'],
                    'amount' => $pd['amount'],
                    'type' => 'deposit',
                    'status' => $pd['status'],
                    'details' => $pd['payment_method'],
                    'created_at' => $pd['created_at']
                ];
            }
        }
    }
}

// Merge and sort
$transactions = array_merge($completed_transactions ?: [], $pending_deposits);
usort($transactions, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate total pages
$total = $completed_count + $pending_count;
$total_pages = max(1, ceil($total / $per_page));

// Helper function to build query string with filters
function build_query_string($filter, $page = 1, $status = null, $date = null)
{
    $params = ['filter' => $filter];
    if ($page > 1) $params['page'] = $page;
    if ($status && $status !== 'all') $params['status'] = $status;
    if ($date && $date !== 'all') $params['date'] = $date;
    return '?' . http_build_query($params);
}

// Helper: generate condensed pagination window with ellipses
function get_pagination_pages($current, $total, $max_buttons = 10)
{
    $pages = [];
    if ($total <= $max_buttons) {
        for ($i = 1; $i <= $total; $i++) $pages[] = $i;
        return $pages;
    }

    $pages[] = 1;
    $range = $max_buttons - 2; // reserve for first and last
    $start = max(2, $current - intval(floor($range / 2)));
    $end = min($total - 1, $start + $range - 1);
    // adjust if we're too close to the end
    if ($end - $start + 1 < $range) {
        $start = max(2, $end - $range + 1);
    }

    if ($start > 2) $pages[] = '...';

    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }

    if ($end < $total - 1) $pages[] = '...';
    $pages[] = $total;
    return $pages;
}

// Type styling map
$type_styles = [
    'deposit'    => ['icon' => 'fa-arrow-down', 'color' => 'emerald'],
    'withdrawal' => ['icon' => 'fa-arrow-up', 'color' => 'zinc'],
    'profit'     => ['icon' => 'fa-chart-line', 'color' => 'indigo'],
    'investment' => ['icon' => 'fa-rocket', 'color' => 'sky'],
    'referral'   => ['icon' => 'fa-users', 'color' => 'amber'],
    'refund'     => ['icon' => 'fa-rotate-left', 'color' => 'emerald'],
];

// Status styling map
$status_styles = [
    'completed' => ['color' => 'emerald', 'icon' => 'fa-check-circle'],
    'approved'  => ['color' => 'emerald', 'icon' => 'fa-check-circle'],
    'pending'   => ['color' => 'amber', 'icon' => 'fa-clock'],
    'failed'    => ['color' => 'rose', 'icon' => 'fa-xmark-circle'],
    'rejected'  => ['color' => 'rose', 'icon' => 'fa-xmark-circle'],
];

// Pagination result counters
$showing_start = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
$showing_end = $total > 0 ? min($showing_start + count($transactions) - 1, $total) : 0;

ob_start();
?>
<style>
    [x-cloak] {
        display: none !important;
    }

    /* Pagination container */
    ul.pagination {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        list-style: none;
        padding: 0;
        margin: 0;
        flex-wrap: wrap;
        justify-content: center;
    }

    /* Ellipsis jumper */
    .page-ellipsis {
        user-select: none;
    }
</style>
<?php
$extra_css = ob_get_clean();

ob_start();
?>
<script>
    document.addEventListener('click', function(e) {
        var ell = e.target.closest && e.target.closest('.page-ellipsis');
        if (!ell) return;
        e.preventDefault();
        var start = parseInt(ell.getAttribute('data-start'), 10);
        var end = parseInt(ell.getAttribute('data-end'), 10);
        if (isNaN(start) || isNaN(end) || end < start) return;

        // pick a random integer between start and end (inclusive)
        var rand = Math.floor(Math.random() * (end - start + 1)) + start;

        // try to use the template on the parent ul, fallback to manipulating current URL
        var ul = ell.closest('ul.pagination');
        var template = ul && ul.dataset && ul.dataset.hrefTemplate ? ul.dataset.hrefTemplate : (ul && ul.getAttribute('data-href-template'));
        if (template) {
            var href = template.replace('PAGE_PLACEHOLDER', rand);
            window.location.href = href;
            return;
        }

        // fallback: set `page` query param on current URL
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('page', rand);
            window.location.href = url.toString();
        } catch (ex) {
            // last resort: navigate to '?page='
            window.location.href = '?page=' + rand;
        }
    });
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<!-- Page header -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 border-b border-zinc-900 pb-6">
    <div>
        <h1 class="text-3xl md:text-4xl font-bold text-white tracking-tight"><?php echo e(__('Transactions')); ?></h1>
        <p class="text-zinc-400 mt-1 text-sm"><?php echo e(__('Track your financial history')); ?></p>
    </div>
</header>

<!-- Filters -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
    <!-- Filter pills -->
    <div class="overflow-x-auto scrollbar-hide py-2 w-full lg:w-auto">
        <div class="flex gap-3 min-w-max">
            <a href="<?php echo e(build_query_string('all', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors <?php echo $filter === 'all' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <?php echo e(__('All')); ?>
            </a>
            <a href="<?php echo e(build_query_string('deposits', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2 <?php echo $filter === 'deposits' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <i class="fa-solid fa-arrow-down text-xs"></i> <?php echo e(__('Deposits')); ?>
            </a>
            <a href="<?php echo e(build_query_string('withdrawals', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2 <?php echo $filter === 'withdrawals' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <i class="fa-solid fa-arrow-up text-xs"></i> <?php echo e(__('Withdrawals')); ?>
            </a>
            <a href="<?php echo e(build_query_string('investments', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2 <?php echo $filter === 'investments' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <i class="fa-solid fa-rocket text-xs"></i> <?php echo e(__('Investments')); ?>
            </a>
            <a href="<?php echo e(build_query_string('profits', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2 <?php echo $filter === 'profits' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <i class="fa-solid fa-chart-line text-xs"></i> <?php echo e(__('Profits')); ?>
            </a>
            <a href="<?php echo e(build_query_string('referrals', 1, $status_filter, $date_filter)); ?>"
                class="px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2 <?php echo $filter === 'referrals' ? 'bg-brand-accent text-brand-dark font-semibold shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-brand-card border border-zinc-800 text-zinc-400 hover:text-white'; ?>">
                <i class="fa-solid fa-users text-xs"></i> <?php echo e(__('Referrals')); ?>
            </a>
        </div>
    </div>

    <!-- Status + date filters -->
    <form method="GET" class="flex items-center gap-3 w-full lg:w-auto">
        <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
        <select name="status"
            class="bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs font-semibold rounded-lg px-3 py-2 focus:outline-none focus:border-brand-accent w-full lg:w-auto"
            onchange="this.form.submit()">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo e(__('All Status')); ?></option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo e(__('Completed')); ?></option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo e(__('Pending')); ?></option>
            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>><?php echo e(__('Failed')); ?></option>
        </select>
        <select name="date"
            class="bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs font-semibold rounded-lg px-3 py-2 focus:outline-none focus:border-brand-accent w-full lg:w-auto"
            onchange="this.form.submit()">
            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>><?php echo e(__('All Time')); ?></option>
            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>><?php echo e(__('Today')); ?></option>
            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>><?php echo e(__('This Week')); ?></option>
            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>><?php echo e(__('This Month')); ?></option>
        </select>
    </form>
</div>

<!-- Transactions list -->
<div class="bg-brand-dark/50 border border-zinc-800/50 rounded-3xl p-4 sm:p-6 backdrop-blur-sm">
    <?php if ($transactions): ?>
        <div class="space-y-3">
            <?php foreach ($transactions as $t):
                $type = $t['type'] ?? 'deposit';
                $style = $type_styles[$type] ?? $type_styles['deposit'];
                $icon = $style['icon'];
                $color = $style['color'];

                $status = strtolower($t['status'] ?? 'pending');
                $status_style = $status_styles[$status] ?? ['color' => 'zinc', 'icon' => 'fa-circle'];
                $status_color = $status_style['color'];
                $status_icon = $status_style['icon'];

                $is_positive = in_array($type, ['deposit', 'profit', 'referral', 'refund'], true);
                $sign = $is_positive ? '+' : '-';
                $amount_color = ($status === 'failed' || $status === 'rejected') ? 'zinc' : ($is_positive ? $color : 'zinc');
            ?>
                <div class="bg-brand-card border border-zinc-800/60 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-zinc-700 transition-colors group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-<?php echo e($color); ?>-500/10 text-<?php echo e($color); ?>-500 flex items-center justify-center border border-<?php echo e($color); ?>-500/20 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid <?php echo e($icon); ?>"></i>
                        </div>
                        <div>
                            <h4 class="text-white font-semibold"><?php echo e(__(ucfirst($t['type'] ?? __('Transaction')))); ?></h4>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                <?php echo e($t['details'] ?? __('Transaction')); ?>
                                <span class="mx-1">·</span>
                                <i class="fa-regular fa-calendar text-[10px] mr-1"></i><?php echo e(format_date($t['created_at'])); ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-end justify-center gap-1 w-full sm:w-auto">
                        <p class="text-<?php echo e($amount_color); ?>-500 font-bold <?php echo ($status === 'failed' || $status === 'rejected') ? 'line-through' : ''; ?>"
                            x-text="'<?php echo e($sign); ?>' + formatCurrency(<?php echo (float)$t['amount']; ?>)">
                        </p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-<?php echo e($status_color); ?>-500/10 text-<?php echo e($status_color); ?>-500 border border-<?php echo e($status_color); ?>-500/20 <?php echo $status === 'pending' ? 'animate-pulse' : ''; ?>">
                            <i class="fa-solid <?php echo e($status_icon); ?>"></i>
                            <?php echo e(__(ucfirst($t['status'] ?? __('Pending')))); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-8 mt-6 border-t border-zinc-800/60 px-2">
            <p class="text-xs text-zinc-500">
                <?php echo e(sprintf(__('Showing %s to %s of %s results'), $showing_start, $showing_end, $total)); ?>
            </p>

            <?php if ($total_pages > 1): ?>
                <ul class="pagination"
                    data-href-template="<?php echo e(build_query_string($filter, 'PAGE_PLACEHOLDER', $status_filter, $date_filter)); ?>">
                    <li>
                        <?php if ($page > 1): ?>
                            <a class="w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card flex items-center justify-center text-zinc-500 hover:text-white hover:border-zinc-700 transition-colors"
                                href="<?php echo e(build_query_string($filter, $page - 1, $status_filter, $date_filter)); ?>"
                                aria-label="<?php echo e(__('Previous page')); ?>">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card flex items-center justify-center text-zinc-500 opacity-50 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </span>
                        <?php endif; ?>
                    </li>

                    <?php
                    $pages_to_show = get_pagination_pages($page, $total_pages);
                    for ($pi = 0; $pi < count($pages_to_show); $pi++):
                        $p = $pages_to_show[$pi];
                        if ($p === '...'):
                            $prev = $pages_to_show[$pi - 1] ?? 1;
                            $next = $pages_to_show[$pi + 1] ?? $total_pages;
                            $start_between = (int)$prev + 1;
                            $end_between = (int)$next - 1;
                    ?>
                            <li>
                                <a href="#"
                                    class="page-ellipsis w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card text-zinc-400 hover:text-white hover:border-zinc-700 font-medium text-sm flex items-center justify-center transition-colors"
                                    data-start="<?php echo e($start_between); ?>"
                                    data-end="<?php echo e($end_between); ?>"
                                    aria-label="<?php echo e(__('More pages')); ?>">
                                    &hellip;
                                </a>
                            </li>
                        <?php elseif ($p == $page): ?>
                            <li>
                                <span class="w-9 h-9 rounded-xl bg-brand-accent/10 border border-brand-accent/30 text-brand-accent font-medium text-sm flex items-center justify-center shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                                    <?php echo e($p); ?>
                                </span>
                            </li>
                        <?php else: ?>
                            <li>
                                <a class="w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card text-zinc-400 hover:text-white hover:border-zinc-700 font-medium text-sm flex items-center justify-center transition-colors"
                                    href="<?php echo e(build_query_string($filter, $p, $status_filter, $date_filter)); ?>">
                                    <?php echo e($p); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <li>
                        <?php if ($page < $total_pages): ?>
                            <a class="w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card flex items-center justify-center text-zinc-500 hover:text-white hover:border-zinc-700 transition-colors"
                                href="<?php echo e(build_query_string($filter, $page + 1, $status_filter, $date_filter)); ?>"
                                aria-label="<?php echo e(__('Next page')); ?>">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-9 h-9 rounded-xl border border-zinc-800 bg-brand-card flex items-center justify-center text-zinc-500 opacity-50 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        <?php endif; ?>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Empty state -->
        <div class="flex flex-col items-center justify-center text-center py-16 px-4">
            <div class="w-16 h-16 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                <i class="fa-solid fa-search text-2xl"></i>
            </div>
            <h4 class="text-zinc-50 font-bold mb-1"><?php echo e(__('No Transactions Found')); ?></h4>
            <p class="text-zinc-500 text-sm"><?php echo e(__('Try adjusting your filters.')); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>