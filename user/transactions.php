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

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Transactions'); ?></h3>
            <p class="text-secondary mb-0 small"><?php echo __('Track your financial history'); ?></p>
        </div>
    </div>

    <div class="d-flex gap-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
                <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
                <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
            </button>
        </div>

        <!-- Filters -->
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <select name="status" class="form-select form-select-sm border-0 bg-white shadow-sm" style="width: auto; min-width: 120px;" onchange="this.form.submit()">
                <option value="all"><?php echo __('All Status'); ?></option>
                <option value="completed" <?php echo ($_GET['status'] ?? 'all') === 'completed' ? 'selected' : ''; ?>><?php echo __('Completed'); ?></option>
                <option value="pending" <?php echo ($_GET['status'] ?? 'all') === 'pending' ? 'selected' : ''; ?>><?php echo __('Pending'); ?></option>
                <option value="failed" <?php echo ($_GET['status'] ?? 'all') === 'failed' ? 'selected' : ''; ?>><?php echo __('Failed'); ?></option>
            </select>
            <select name="date" class="form-select form-select-sm border-0 bg-white shadow-sm" style="width: auto; min-width: 120px;" onchange="this.form.submit()">
                <option value="all"><?php echo __('All Time'); ?></option>
                <option value="today" <?php echo ($_GET['date'] ?? 'all') === 'today' ? 'selected' : ''; ?>><?php echo __('Today'); ?></option>
                <option value="week" <?php echo ($_GET['date'] ?? 'all') === 'week' ? 'selected' : ''; ?>><?php echo __('This Week'); ?></option>
                <option value="month" <?php echo ($_GET['date'] ?? 'all') === 'month' ? 'selected' : ''; ?>><?php echo __('This Month'); ?></option>
            </select>
        </form>
    </div>

</div>

<!-- Filter Pills -->
<div class="d-flex gap-2 flex-wrap pb-2 mb-3">
    <a href="<?php echo build_query_string('all', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
        <?php echo __('All'); ?>
    </a>
    <a href="<?php echo build_query_string('deposits', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'deposits' ? 'active' : ''; ?>">
        <i class="fas fa-arrow-down me-1"></i> <?php echo __('Deposits'); ?>
    </a>
    <a href="<?php echo build_query_string('withdrawals', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'withdrawals' ? 'active' : ''; ?>">
        <i class="fas fa-arrow-up me-1"></i> <?php echo __('Withdrawals'); ?>
    </a>
    <a href="<?php echo build_query_string('investments', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'investments' ? 'active' : ''; ?>">
        <i class="fas fa-rocket me-1"></i> <?php echo __('Investments'); ?>
    </a>
    <a href="<?php echo build_query_string('profits', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'profits' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line me-1"></i> <?php echo __('Profits'); ?>
    </a>
    <a href="<?php echo build_query_string('referrals', 1, $status_filter, $date_filter); ?>" class="filter-btn <?php echo $filter === 'referrals' ? 'active' : ''; ?>">
        <i class="fas fa-users me-1"></i> <?php echo __('Referrals'); ?>
    </a>
</div>

<!-- Transactions List -->
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 1.25rem">
    <!-- Desktop Header -->
    <div class="d-none d-md-flex bg-light border-bottom py-3 px-4 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
        <div class="col-5"><?php echo __('Details'); ?></div>
        <div class="col-3"><?php echo __('Date'); ?></div>
        <div class="col-2"><?php echo __('Status'); ?></div>
        <div class="col-2 text-end"><?php echo __('Amount'); ?></div>
    </div>

    <!-- Transaction Rows -->
    <div class="list-wrapper">
        <?php if ($transactions): foreach ($transactions as $t):
                // Determine styling based on type
                $icon_class = 'fa-magic';
                $bg_class = 'bg-primary';
                $text_class = 'text-primary';
                $is_positive = false;

                switch ($t['type']) {
                    case 'deposit':
                        $icon_class = 'fa-arrow-down';
                        $bg_class = 'bg-success';
                        $text_class = 'text-success';
                        $is_positive = true;
                        break;
                    case 'withdrawal':
                        $icon_class = 'fa-arrow-up';
                        $bg_class = 'bg-danger';
                        $text_class = 'text-danger';
                        break;
                    case 'profit':
                        $icon_class = 'fa-chart-line';
                        $bg_class = 'bg-info';
                        $text_class = 'text-info';
                        $is_positive = true;
                        break;
                    case 'investment':
                        $icon_class = 'fa-rocket';
                        $bg_class = 'bg-primary';
                        $text_class = 'text-primary';
                        break;
                    case 'referral':
                        $icon_class = 'fa-users';
                        $bg_class = 'bg-warning';
                        $text_class = 'text-warning';
                        $is_positive = true;
                    case 'refund':
                        $is_positive = true;
                        break;
                }

                // Status styling
                $status_bg = 'bg-secondary';
                $status_text = 'text-secondary';
                $status_icon = 'fa-circle';
                if ($t['status'] === 'completed' || $t['status'] === 'approved') {
                    $status_bg = 'bg-success';
                    $status_text = 'text-success';
                    $status_icon = 'fa-check-circle';
                } elseif ($t['status'] === 'pending') {
                    $status_bg = 'bg-warning';
                    $status_text = 'text-warning';
                    $status_icon = 'fa-clock';
                } elseif ($t['status'] === 'failed' || $t['status'] === 'rejected') {
                    $status_bg = 'bg-danger';
                    $status_text = 'text-danger';
                    $status_icon = 'fa-times-circle';
                }
        ?>
                <div class="transaction-row p-3 p-md-4">
                    <div class="row align-items-center gy-2">
                        <!-- Details Column -->
                        <div class="col-12 col-md-5 d-flex align-items-center gap-3">
                            <div class="icon-circle <?php echo $bg_class; ?> bg-opacity-10 <?php echo $text_class; ?>">
                                <i class="fas <?php echo $icon_class; ?>"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><?php echo __(ucfirst(e($t['type']))); ?></h6>
                                <p class="text-secondary small mb-0"><?php echo e($t['details'] ?? __('Transaction')); ?></p>
                            </div>
                        </div>

                        <!-- Date Column -->
                        <div class="col-6 col-md-3 text-secondary small mt-2 mt-md-0">
                            <i class="far fa-calendar me-1"></i> <?php echo format_date($t['created_at']); ?>
                        </div>

                        <!-- Status Column -->
                        <div class="col-6 col-md-2 mt-2 mt-md-0 d-flex justify-content-end justify-content-md-start">
                            <span class="badge badge-soft <?php echo $status_bg; ?> bg-opacity-10 <?php echo $status_text; ?>">
                                <i class="fas me-1 <?php echo $status_icon; ?>"></i>
                                <?php echo __(ucfirst(e($t['status']))); ?>
                            </span>
                        </div>

                        <!-- Amount Column -->
                        <div class="col-12 col-md-2 text-md-end mt-2 mt-md-0 d-flex justify-content-between d-md-block align-items-center">
                            <span class="d-md-none text-secondary small fw-bold"><?php echo __('Amount'); ?></span>
                            <h6 class="fw-bold mb-0 <?php echo $is_positive ? 'text-success' : 'text-danger'; ?>" x-text="'<?php echo ($is_positive ? '+' : '-'); ?>' + formatCurrency(<?php echo $t['amount']; ?>)">
                                <?php echo ($is_positive ? '+' : '-') . format_money($t['amount']); ?>
                            </h6>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top py-4">
        <nav>
            <ul class="pagination justify-content-center mb-0 gap-2" data-href-template="<?php echo build_query_string($filter, 'PAGE_PLACEHOLDER', $status_filter, $date_filter); ?>">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link rounded-circle border-0 bg-light text-dark d-flex align-items-center justify-content-center" href="<?php echo build_query_string($filter, $page - 1, $status_filter, $date_filter); ?>" style="width: 40px; height: 40px">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link rounded-circle border-0 bg-light text-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    </li>
                <?php endif; ?>

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
                        <li class="page-item">
                            <a href="#" class="page-link page-ellipsis rounded-circle border-0 bg-light text-secondary d-flex align-items-center justify-content-center" data-start="<?php echo $start_between; ?>" data-end="<?php echo $end_between; ?>" style="width: 40px; height: 40px" aria-label="More pages">&hellip;</a>
                        </li>
                    <?php elseif ($p == $page): ?>
                        <li class="page-item active">
                            <span class="page-link rounded-circle border-0 bg-primary text-white shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                                <?php echo $p; ?>
                            </span>
                        </li>
                    <?php else: ?>
                        <li class="page-item">
                            <a class="page-link rounded-circle border-0 bg-light text-dark d-flex align-items-center justify-content-center" href="<?php echo build_query_string($filter, $p, $status_filter, $date_filter); ?>" style="width: 40px; height: 40px">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link rounded-circle border-0 bg-light text-dark d-flex align-items-center justify-content-center" href="<?php echo build_query_string($filter, $page + 1, $status_filter, $date_filter); ?>" style="width: 40px; height: 40px">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link rounded-circle border-0 bg-light text-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
<?php else: ?>
</div>

<!-- Empty State -->
<div class="p-5 text-center">
    <div class="bg-light rounded-circle p-4 mb-3 d-inline-flex">
        <i class="fas fa-search fa-2x text-secondary opacity-25"></i>
    </div>
    <h5 class="fw-bold text-dark"><?php echo __('No Transactions Found'); ?></h5>
    <p class="text-muted small"><?php echo __('Try adjusting your filters.'); ?></p>
</div>
<?php endif; ?>
</div>
</div>

<style>
    /* Hide elements with x-cloak until Alpine loads */
    [x-cloak] {
        display: none !important;
    }

    /* Filter Pills */
    .filter-btn {
        border: 1px solid #e2e8f0;
        background: white;
        color: var(--text-muted);
        padding: 0.5rem 1.25rem;
        border-radius: 50rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        white-space: nowrap;
        text-decoration: none;
    }

    .filter-btn:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
    }

    /* Transaction Row */
    .transaction-row {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f1f5f9;
    }

    .transaction-row:hover {
        background: #f8fafc;
    }

    .transaction-row:last-child {
        border-bottom: none;
    }

    /* Icon Circle */
    .icon-circle {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* Badge Soft */
    .badge-soft {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    /* Smooth Transitions */
    .transition {
        transition-property: all;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 300ms;
    }

    /* Hide scrollbar for filter pills */
    .filter-btn::-webkit-scrollbar {
        display: none;
    }

    /* Responsive pagination: allow wrapping and horizontal scroll on small screens */
    .pagination {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        overflow-x: auto;
        padding: 0.5rem 0;
        -webkit-overflow-scrolling: touch;
    }

    .pagination .page-link {
        min-width: 40px;
        width: auto;
        height: 40px;
        padding: 0;
    }
</style>

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

<?php require ROOT . '/includes/footer.php'; ?>
</body>

</html>