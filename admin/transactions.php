<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('All Transactions');

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search term
$search_term = !empty($search) ? "%{$search}%" : null;

// Initialize transactions array
$all_transactions = [];
$total_count = 0;

// For deposits, we need to include both transactions AND pending deposits from deposits table
if ($type_filter === 'all' || $type_filter === 'deposit') {
    // Get pending deposits that don't have transaction records yet
    $pending_deposit_where = ["d.status = 'pending'"];
    $pending_deposit_params = [];

    if ($search_term) {
        $pending_deposit_where[] = "(u.name LIKE ? OR u.email LIKE ? OR d.id LIKE ?)";
        $pending_deposit_params = [$search_term, $search_term, $search_term];
    }

    $pending_deposit_sql = "SELECT
            d.id,
            d.user_id,
            'deposit' as type,
            d.amount,
            d.status,
            CONCAT('Deposit via ', d.payment_method) as details,
            d.created_at,
            u.name as user_name,
            u.email as user_email,
            u.profile_picture,
            u.country,
            d.local_currency_amount,
            d.local_currency_code,
            d.exchange_rate_used
        FROM deposits d
        JOIN users u ON d.user_id = u.id
        WHERE " . implode(" AND ", $pending_deposit_where) . "
        AND NOT EXISTS (
            SELECT 1 FROM transactions t2 WHERE t2.source_id = d.id AND t2.type = 'deposit'
        )
        ORDER BY d.created_at DESC";

    $pending_deposits = db_query($pending_deposit_sql, $pending_deposit_params);

    // Get deposit count
    $deposit_count_sql = "SELECT COUNT(*) as count FROM deposits d 
                          JOIN users u ON d.user_id = u.id 
                          WHERE " . implode(" AND ", $pending_deposit_where) . "
                          AND NOT EXISTS (
                              SELECT 1 FROM transactions t2 WHERE t2.source_id = d.id AND t2.type = 'deposit'
                          )";
    $pending_deposit_count = db_query($deposit_count_sql, $pending_deposit_params)[0]['count'] ?? 0;
} else {
    $pending_deposits = [];
    $pending_deposit_count = 0;
}

// Get regular transactions (excluding deposits if we're showing deposits separately)
$tx_where = [];
$tx_params = [];

if ($type_filter !== 'all') {
    $tx_where[] = "t.type = ?";
    $tx_params[] = $type_filter;
}

if ($search_term) {
    $tx_where[] = "(u.name LIKE ? OR u.email LIKE ? OR t.id LIKE ?)";
    $tx_params[] = $search_term;
    $tx_params[] = $search_term;
    $tx_params[] = $search_term;
}

$tx_where_clause = !empty($tx_where) ? "WHERE " . implode(" AND ", $tx_where) : "";

// Get transaction count
$tx_count_sql = "SELECT COUNT(*) as count FROM transactions t 
                 JOIN users u ON t.user_id = u.id 
                 {$tx_where_clause}";
$tx_count_result = db_query($tx_count_sql, $tx_params);
$tx_count = $tx_count_result[0]['count'] ?? 0;

// Get transactions
$tx_sql = "SELECT
        t.id,
        t.user_id,
        t.type,
        t.amount,
        t.status,
        t.details,
        t.created_at,
        u.name as user_name,
        u.email as user_email,
        u.profile_picture,
        u.country
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    {$tx_where_clause}
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?";
$tx_query_params = array_merge($tx_params, [$per_page, $offset]);
$transactions = db_query($tx_sql, $tx_query_params);

// Compute local currency for non-investment transaction types on-the-fly
foreach ($transactions as &$tx) {
    if ($tx['type'] === 'investment') {
        continue;
    }
    $tx['local_currency_code'] = null;
    $tx['local_currency_amount'] = null;
    $tx['exchange_rate_used'] = null;
    if (!empty($tx['country'])) {
        $local_code = get_user_local_currency($tx['country']);
        if ($local_code) {
            $rate = get_rate_for_currency($local_code);
            if ($rate) {
                $tx['local_currency_code'] = $local_code;
                $tx['local_currency_amount'] = $tx['amount'] * $rate;
                $tx['exchange_rate_used'] = $rate;
            }
        }
    }
}
unset($tx);

// If showing deposits, merge pending deposits with transaction deposits
if ($type_filter === 'deposit' || $type_filter === 'all') {
    // Get approved deposits from transactions
    $all_items = array_merge($pending_deposits, $transactions);

    // Sort by created_at descending
    usort($all_items, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Apply pagination
    $total_count = $pending_deposit_count + $tx_count;
    $total_pages = ceil($total_count / $per_page);
    $display_items = $all_items;
} else {
    $total_count = $tx_count;
    $total_pages = ceil($total_count / $per_page);
    $display_items = $transactions;
}

// Get type counts for filter buttons
$type_counts = [
    'all' => (db_query("SELECT COUNT(*) as count FROM transactions", [])[0]['count'] ?? 0) +
        (db_query("SELECT COUNT(*) as count FROM deposits d WHERE status = 'pending' AND NOT EXISTS (SELECT 1 FROM transactions t2 WHERE t2.source_id = d.id AND t2.type = 'deposit')", [])[0]['count'] ?? 0),
    'deposit' => (db_query("SELECT COUNT(*) as count FROM transactions WHERE type = 'deposit'", [])[0]['count'] ?? 0) +
        (db_query("SELECT COUNT(*) as count FROM deposits d WHERE status = 'pending' AND NOT EXISTS (SELECT 1 FROM transactions t2 WHERE t2.source_id = d.id AND t2.type = 'deposit')", [])[0]['count'] ?? 0),
    'withdrawal' => db_query("SELECT COUNT(*) as count FROM transactions WHERE type = 'withdrawal'", [])[0]['count'] ?? 0,
    'investment' => db_query("SELECT COUNT(*) as count FROM transactions WHERE type = 'investment'", [])[0]['count'] ?? 0,
    'profit' => db_query("SELECT COUNT(*) as count FROM transactions WHERE type = 'profit'", [])[0]['count'] ?? 0,
    'referral' => db_query("SELECT COUNT(*) as count FROM transactions WHERE type = 'referral'", [])[0]['count'] ?? 0,
];

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4" x-data="{ 
    sheetOpen: false, 
    selectedTx: null,
    openSheet(tx) {
        this.selectedTx = tx;
        this.sheetOpen = true;
        try { if (window.adminHelpers && adminHelpers.lockBodyScroll) adminHelpers.lockBodyScroll(); } catch (e) {}
    },
    closeSheet() {
        this.sheetOpen = false;
        try { if (window.adminHelpers && adminHelpers.unlockBodyScroll) adminHelpers.unlockBodyScroll(); } catch (e) {}
        this.selectedTx = null;
    }
}" x-cloak>
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-semibold text-white mb-1"><?php echo __('All Transactions'); ?></h4>
            <p class="text-muted-custom small mb-0"><?php echo __('View all financial transactions across the platform.'); ?></p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?type=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'all' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'all' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('All'); ?> (<?php echo $type_counts['all']; ?>)
            </a>
            <a href="?type=deposit<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'deposit' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'deposit' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('Deposits'); ?> (<?php echo $type_counts['deposit']; ?>)
            </a>
            <a href="?type=withdrawal<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'withdrawal' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'withdrawal' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('Withdrawals'); ?> (<?php echo $type_counts['withdrawal']; ?>)
            </a>
            <a href="?type=investment<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'investment' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'investment' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('Investments'); ?> (<?php echo $type_counts['investment']; ?>)
            </a>
            <a href="?type=profit<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'profit' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'profit' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('Profits'); ?> (<?php echo $type_counts['profit']; ?>)
            </a>
            <a href="?type=referral<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-sm btn-dark border-subtle <?php echo $type_filter === 'referral' ? 'fw-medium' : ''; ?>"
                style="<?php echo $type_filter === 'referral' ? 'border-bottom: 2px solid var(--accent-color); color: var(--accent-color);' : ''; ?>">
                <?php echo __('Referrals'); ?> (<?php echo $type_counts['referral']; ?>)
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="d-flex gap-2 flex-wrap mb-4">
        <form method="GET" class="d-flex gap-2 flex-grow-1" style="min-width: 250px;">
            <input type="hidden" name="type" value="<?php echo e($type_filter); ?>">
            <div class="position-relative flex-grow-1">
                <i class="fa-solid fa-magnifying-glass position-absolute text-muted-custom" style="left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.85rem;"></i>
                <input type="text" name="search" class="form-control form-control-custom ps-5" placeholder="<?php echo __('Search by user, email, or ID...'); ?>" value="<?php echo e($search); ?>" />
            </div>
            <button type="submit" class="btn btn-accent btn-sm">
                <i class="fa-solid fa-magnifying-glass d-md-none"></i>
                <span class="d-none d-md-inline"><?php echo __('Search'); ?></span>
            </button>
            <?php if (!empty($search)): ?>
                <a href="?type=<?php echo e($type_filter); ?>" class="btn btn-dark btn-sm border-subtle">
                    <i class="fa-solid fa-times d-md-none"></i>
                    <span class="d-none d-md-inline"><?php echo __('Clear'); ?></span>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="card bg-card border-subtle">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0 align-middle" style="background: rgba(255, 255, 255, 0.02);">
                <thead style="background: rgba(255, 255, 255, 0.02);">
                    <tr>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('Transaction ID'); ?></th>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('User'); ?></th>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('Type'); ?></th>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('Amount'); ?></th>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('Status'); ?></th>
                        <th class="text-muted-custom fs-7 fw-semibold"><?php echo __('Date'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($display_items)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="fa-solid fa-receipt fa-3x mb-3 opacity-25"></i>
                                <p><?php echo __('No transactions found.'); ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($display_items as $tx):
                            $type_colors = [
                                'deposit' => 'success',
                                'withdrawal' => 'danger',
                                'investment' => 'info',
                                'profit' => 'warning',
                                'referral' => 'secondary',
                                'bonus' => 'primary'
                            ];
                            $status_color = 'secondary';
                            if ($tx['status'] === 'pending') {
                                $status_color = 'warning';
                            } elseif ($tx['status'] === 'completed' || $tx['status'] === 'approved') {
                                $status_color = 'success';
                            } elseif ($tx['status'] === 'rejected') {
                                $status_color = 'danger';
                            }
                            $type_color = $type_colors[$tx['type']] ?? 'secondary';
                            $initial = substr($tx['user_name'], 0, 1);

                            // Compute display-ready amount string with +/- prefix and currency formatting
                            $display_amount = (in_array($tx['type'], ['deposit', 'profit', 'referral', 'bonus']) ? '+' : '') . format_money($tx['amount']);
                            $tx['display_amount'] = $display_amount;
                        ?>
                            <tr @click="openSheet(<?php echo htmlspecialchars(json_encode($tx), ENT_QUOTES, 'UTF-8'); ?>)" style="cursor: pointer;">
                                <td><span class="text-mono text-white fw-medium small">#<?php echo e($tx['id']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($tx['profile_picture'])): ?>
                                            <img src="<?php echo e('/' . ltrim($tx['profile_picture'], '/')); ?>" alt="<?php echo e($tx['user_name']); ?>" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover; flex-shrink: 0;">
                                        <?php else: ?>
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="width: 28px; height: 28px; font-size: 0.75rem; flex-shrink: 0;">
                                                <?php echo e(strtoupper($initial)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-white small"><?php echo e($tx['user_name']); ?></div>
                                            <div class="small text-secondary"><?php echo e($tx['user_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $type_color; ?> bg-opacity-10 text-<?php echo $type_color; ?> border border-<?php echo $type_color; ?> border-opacity-25 fs-7"><?php echo ucfirst($tx['type']); ?></span>
                                </td>
                                <td class="text-mono fw-medium <?php echo in_array($tx['type'], ['deposit', 'profit', 'referral', 'bonus']) ? 'text-success' : 'text-danger'; ?> small">
                                    <?php echo in_array($tx['type'], ['deposit', 'profit', 'referral', 'bonus']) ? '+' : ''; ?><?php echo format_money($tx['amount']); ?>
                                    <?php if (!empty($tx['local_currency_amount']) && !empty($tx['local_currency_code']) && $tx['type'] !== 'investment'): ?>
                                        <div class="small text-secondary mt-1">
                                            <?php echo $tx['local_currency_code'] . ' ' . number_format($tx['local_currency_amount'], 2); ?>
                                            <?php if (!empty($tx['exchange_rate_used'])): ?>
                                                <span class="text-muted">@ <?php echo number_format($tx['exchange_rate_used'], 4); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="status-dot bg-<?php echo $status_color; ?>"></span>
                                        <span class="text-white small"><?php echo $tx['status'] === 'completed' || $tx['status'] === 'approved' ? __('Completed') : ucfirst($tx['status']); ?></span>
                                    </div>
                                </td>
                                <td class="text-mono text-secondary small"><?php echo date('M j, Y H:i', strtotime($tx['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="d-flex justify-content-center p-3 border-top">
                <ul class="pagination mb-0">
                    <?php
                    $query_string = '?type=' . $type_filter;
                    if (!empty($search)) $query_string .= '&search=' . urlencode($search);
                    ?>
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $query_string; ?>&page=1"><i class="fas fa-angle-double-left"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $query_string; ?>&page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="sheet-overlay" @click="closeSheet()" x-transition.opacity></div>
    </template>

    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }" :aria-hidden="!sheetOpen" role="dialog">

            <!-- Sheet Header -->
            <div class="p-4 border-bottom border-subtle d-flex justify-content-between align-items-start bg-card">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="text-mono text-white h5 mb-0" x-text="'#' + (selectedTx?.id || '---')"></span>
                    </div>
                    <span class="badge"
                        :class="{
                              'bg-success bg-opacity-10 text-success border border-success border-opacity-25': selectedTx?.status === 'completed' || selectedTx?.status === 'approved',
                              'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25': selectedTx?.status === 'pending',
                              'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25': selectedTx?.status === 'rejected'
                          }"
                        x-text="selectedTx?.status ? selectedTx.status.charAt(0).toUpperCase() + selectedTx.status.slice(1) : '---'">
                    </span>
                </div>
                <button @click="closeSheet()" class="btn btn-link text-muted-custom p-0">
                    <i class="fa-solid fa-times fa-lg"></i>
                </button>
            </div>

            <!-- Sheet Body -->
            <div class="p-4 flex-grow-1 sheet-body">
                <h6 class="text-muted-custom text-uppercase fs-7 fw-semibold mb-3"><?php echo __('Transaction Details'); ?></h6>
                <div class="row g-3 mb-4 border-bottom border-subtle pb-4">
                    <div class="col-12">
                        <label class="text-muted-custom small d-block mb-2"><?php echo __('User'); ?></label>
                        <div class="d-flex align-items-center gap-2">
                            <template x-if="selectedTx?.profile_picture">
                                <img :src="'/' + selectedTx.profile_picture" :alt="selectedTx?.user_name" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">
                            </template>
                            <template x-if="!selectedTx?.profile_picture">
                                <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="width: 48px; height: 48px;">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            </template>
                            <div>
                                <span class="text-white small fw-bold" x-text="selectedTx?.user_name || '---'"></span>
                                <div class="text-secondary small" x-text="selectedTx?.user_email || '---'"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="text-muted-custom small d-block mb-1"><?php echo __('Amount'); ?></label>
                        <span class="text-mono text-white small" x-text="selectedTx?.display_amount || '---'"></span>
                    </div>
                    <div class="col-6" x-show="selectedTx?.local_currency_amount && selectedTx?.local_currency_code && selectedTx?.type !== 'investment'">
                        <label class="text-muted-custom small d-block mb-1"><?php echo __('User Local Amount'); ?></label>
                        <span class="text-mono text-white small" x-text="(selectedTx?.local_currency_code || '') + ' ' + (selectedTx?.local_currency_amount ? parseFloat(selectedTx.local_currency_amount).toFixed(2) : '')"></span>
                        <span class="text-muted small" x-show="selectedTx?.exchange_rate_used" x-text="'@ ' + parseFloat(selectedTx.exchange_rate_used).toFixed(4)"></span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted-custom small d-block mb-1"><?php echo __('Type'); ?></label>
                        <span class="text-white small" x-text="selectedTx?.type ? selectedTx.type.charAt(0).toUpperCase() + selectedTx.type.slice(1) : '---'"></span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted-custom small d-block mb-1"><?php echo __('Date'); ?></label>
                        <span class="text-mono text-white small" x-text="selectedTx?.created_at || '---'"></span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted-custom small d-block mb-1"><?php echo __('Transaction ID'); ?></label>
                        <span class="text-mono text-white small" x-text="selectedTx?.id || '---'"></span>
                    </div>
                </div>

                <div class="mb-2">
                    <h6 class="text-muted-custom text-uppercase fs-7 fw-semibold mb-2"><?php echo __('Additional Details'); ?></h6>
                </div>
                <div class="code-block" x-text="selectedTx?.details || 'No additional details available'"></div>
            </div>

            <!-- Sheet Footer -->
            <div class="p-3 border-top border-subtle bg-card d-flex justify-content-center">
                <span class="text-muted-custom small" style="font-size: 0.75rem;"><?php echo __('Transaction processed via platform'); ?></span>
            </div>
        </div>
    </template>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>