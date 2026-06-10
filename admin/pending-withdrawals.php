<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/admin-header.php';

$page_title = __('Pending Withdrawals');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search conditions
$where = "w.status = 'pending'";
$params = [];

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR w.id LIKE ?)";
    $params = [$search_term, $search_term, $search_term];
}

// Get total count with search
$count_sql = "SELECT COUNT(*) as count FROM withdrawals w 
              JOIN users u ON w.user_id = u.id 
              WHERE " . $where;
$total_row = db_query($count_sql, $params);
$total_count = $total_row[0]['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// Get pending withdrawals with search and pagination
$sql = "SELECT w.*, u.name, u.email, u.balance, u.profile_picture, u.country FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        WHERE " . $where . "
        ORDER BY w.created_at DESC
        LIMIT ? OFFSET ?";
$list_params = array_merge($params, [$per_page, $offset]);
$withdrawals = db_query($sql, $list_params);

// Prepare JS-friendly withdrawal payload
$js_withdrawals = [];
foreach ($withdrawals as $w) {
    // Parse account details JSON
    $account_details = json_decode($w['account_details'] ?? '{}', true);
    $type = $account_details['type'] ?? '';

    // Format destination based on type
    $destination_display = '';
    $destination_full = [];

    switch ($type) {
        case 'crypto':
            $address = $account_details['address'] ?? '';
            $network = $account_details['network'] ?? '';
            $destination_display = $network ? "$network: " . substr($address, 0, 10) . '...' . substr($address, -6) : substr($address, 0, 10) . '...' . substr($address, -6);
            $destination_full = ['Network' => $network, 'Address' => $address];
            break;
        case 'fiat':
        case 'bank':
            $bank_name = $account_details['bank_name'] ?? '';
            $account_number = $account_details['account_number'] ?? '';
            $destination_display = $bank_name . ' ****' . substr($account_number, -4);
            $destination_full = [
                'Bank' => $bank_name,
                'Account Name' => $account_details['account_name'] ?? '',
                'Account Number' => $account_number
            ];
            break;
        case 'momo':
        case 'mobile_money':
            $provider = $account_details['provider'] ?? '';
            $phone = $account_details['phone_number'] ?? '';
            $full_name = $account_details['full_name'] ?? '';
            $reference = $account_details['reference'] ?? '';
            $destination_display = ($full_name ? $full_name . ' - ' : '') . $provider . ' ' . $phone . ($reference ? ' (' . $reference . ')' : '');
            $destination_full = ['Full Name' => $full_name, 'Provider' => $provider, 'Phone Number' => $phone];
            if (!empty($reference)) {
                $destination_full['Reference'] = $reference;
            }
            break;
        case 'ewallet':
            $provider = $account_details['provider'] ?? '';
            $wallet_id = $account_details['wallet_id'] ?? '';
            $destination_display = $provider . ': ' . $wallet_id;
            $destination_full = ['Provider' => $provider, 'Wallet ID' => $wallet_id];
            break;
        default:
            // Fallback to raw display
            $destination_display = substr($w['account_details'] ?? '', 0, 20) . '...';
            $destination_full = ['Details' => $w['account_details'] ?? ''];
    }

    // Compute local currency amount on-the-fly
    $local_currency_code = null;
    $local_currency_amount = null;
    $local_currency_fee = null;
    $local_currency_net = null;
    $exchange_rate_used = null;
    if (!empty($w['country'])) {
        $local_currency_code = get_user_local_currency($w['country']);
        if ($local_currency_code) {
            $rate = get_rate_for_currency($local_currency_code);
            if ($rate) {
                $local_currency_amount = $w['amount'] * $rate;
                $local_currency_fee = $w['fee_amount'] * $rate;
                $local_currency_net = $w['net_amount'] * $rate;
                $exchange_rate_used = $rate;
            }
        }
    }

    // Prefer stored local values if available
    $stored_local_amount = isset($w['local_currency_amount']) ? (float)$w['local_currency_amount'] : null;
    $stored_rate = isset($w['exchange_rate_used']) ? (float)$w['exchange_rate_used'] : null;
    $stored_local_code = $w['local_currency_code'] ?? null;

    $js_withdrawals[] = [
        'id' => $w['id'],
        'username' => $w['name'],
        'email' => $w['email'],
        'method' => $w['payment_method'] ?? '',
        'method_type' => $type,
        'destination_display' => $destination_display,
        'destination_full' => $destination_full,
        'amount' => format_money($w['amount']),
        'fee' => format_money($w['fee_amount']),
        'net_amount' => format_money($w['net_amount']),
        'date' => date('M j, Y H:i', strtotime($w['created_at'])),
        'user_balance' => format_money($w['balance']),
        'local_currency_code' => $stored_local_code ?: $local_currency_code,
        'local_currency_amount' => $stored_local_amount !== null ? number_format($stored_local_amount, 2) : ($local_currency_amount !== null ? number_format($local_currency_amount, 2) : null),
        'local_currency_fee' => $local_currency_fee !== null ? number_format($local_currency_fee, 2) : null,
        'local_currency_net' => $local_currency_net !== null ? number_format($local_currency_net, 2) : null,
        'exchange_rate_used' => $stored_rate ?: $exchange_rate_used,
    ];
}
$withdrawals_js = json_encode($js_withdrawals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Get all withdrawals count for button
$all_count = db_query("SELECT COUNT(*) as count FROM withdrawals", [])[0]['count'] ?? 0;
?>

<script>
    window.__withdrawals = <?php echo $withdrawals_js ?: '[]'; ?>;
</script>

<!-- Alpine.js Scope Wrapper -->
<div x-data="{
    searchQuery: '',
    sheetOpen: false,
    selected: { id: '', username: '', email: '', method: '', method_type: '', amount: '', fee: '', net_amount: '', user_balance: '', profile_picture: '', destination_display: '', destination_full: {}, local_currency_code: '', local_currency_amount: '', local_currency_fee: '', local_currency_net: '', exchange_rate_used: '' },
    rejectMode: false,
    rejectionReason: '',
    submitting: false,
    withdrawals: window.__withdrawals || [],
    get filteredWithdrawals() {
        if (!this.searchQuery) return this.withdrawals;
        const q = this.searchQuery.toLowerCase();
        return this.withdrawals.filter(w => (w.username || '').toLowerCase().includes(q) || String(w.id).toLowerCase().includes(q));
    },
    openSheet(item) { 
        this.selected = item; 
        this.rejectMode = false;
        this.rejectionReason = ''; 
        this.sheetOpen = true; 
        try { if (window.adminHelpers && adminHelpers.lockBodyScroll) adminHelpers.lockBodyScroll(); } catch (e) {}
    },
    closeSheet() { 
        this.sheetOpen = false; 
        try { if (window.adminHelpers && adminHelpers.unlockBodyScroll) adminHelpers.unlockBodyScroll(); } catch (e) {}
        this.submitting = false;
        setTimeout(() => { 
            if (!this.sheetOpen) { 
                this.selected = {}; 
                this.rejectMode = false;
                this.rejectionReason = ''; 
            } 
        }, 300); 
    },
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text || '');
        }
    },
    submitReject(form) {
        if (!this.rejectionReason.trim()) {
            alert('<?php echo htmlspecialchars(json_encode(__('Please provide a rejection reason'))); ?>');
            return;
        }
        this.submitting = true;
        form.submit();
    }
}" x-init="$nextTick(() => { sheetOpen = false; })">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><?php echo __('Pending Withdrawals'); ?></h4>
            <p class="text-secondary small mb-0"><?php echo __('Review payout requests and process securely.'); ?></p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="v-card p-3 mb-4">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <div class="position-relative flex-grow-1" style="min-width: 250px;">
                <i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="left: 1rem; top: 50%; transform: translateY(-50%);"></i>
                <input type="text" name="search" class="form-control form-control-custom ps-5" placeholder="<?php echo __('Search by user, email, or ID...'); ?>" value="<?php echo e($search); ?>" />
            </div>
            <button type="submit" class="btn btn-primary-glow">
                <i class="fa-solid fa-magnifying-glass d-md-none"></i>
                <span class="d-none d-md-inline"><?php echo __('Search'); ?></span>
            </button>
            <?php if (!empty($search)): ?>
                <a href="?page=1" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-times d-md-none"></i>
                    <span class="d-none d-md-inline"><?php echo __('Clear'); ?></span>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table Card -->
    <div class="card-bento">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('User'); ?></th>
                        <th><?php echo __('Method'); ?></th>
                        <th><?php echo __('Destination'); ?></th>
                        <th><?php echo __('Amount'); ?></th>
                        <th><?php echo __('Date'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th class="text-end"><?php echo __('Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in filteredWithdrawals" :key="item.id">
                        <tr @click="openSheet(item)" style="cursor:pointer">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <template x-if="item.profile_picture">
                                        <img :src="'/' + item.profile_picture" :alt="item.username" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                    </template>
                                    <template x-if="!item.profile_picture">
                                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="width: 32px; height: 32px;">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                    </template>
                                    <div>
                                        <div class="fw-bold text-white" x-text="item.username"></div>
                                        <div class="small text-secondary" x-text="item.email"></div>
                                        <div class="small text-secondary"><span class="font-mono text-xs" x-text="item.date"></span></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-brands fa-bitcoin text-warning" x-show="item.method_type === 'crypto'"></i>
                                    <i class="fa-solid fa-building-columns text-secondary" x-show="item.method_type === 'fiat' || item.method_type === 'bank'"></i>
                                    <i class="fa-solid fa-mobile-screen text-success" x-show="item.method_type === 'momo' || item.method_type === 'mobile_money'"></i>
                                    <i class="fa-solid fa-wallet text-info" x-show="item.method_type === 'ewallet'"></i>
                                    <span x-text="item.method"></span>
                                </div>
                            </td>
                            <td>
                                <div class="font-mono text-secondary small" x-text="item.destination_display"></div>
                            </td>
                            <td>
                                <div class="fw-bold text-white"><span class="font-mono" x-text="item.amount"></span></div>
                                <div class="small text-danger" x-show="item.fee && item.fee !== '$0.00'"><?php echo __('Fee'); ?>: <span x-text="item.fee"></span></div>
                                <div class="small text-success" x-show="item.net_amount && item.net_amount !== '$0.00'"><?php echo __('Net'); ?>: <span x-text="item.net_amount"></span></div>
                                <div class="small text-secondary mt-1" x-show="item.local_currency_amount && item.local_currency_code">
                                    <span x-text="item.local_currency_code + ' ' + item.local_currency_amount"></span>
                                    <span class="text-muted" x-show="item.exchange_rate_used" x-text="'@ ' + parseFloat(item.exchange_rate_used).toFixed(4)"></span>
                                </div>
                                <div class="small text-danger mt-1" x-show="item.local_currency_fee && item.local_currency_code">
                                    <?php echo __('Local Fee'); ?>: <span x-text="item.local_currency_code + ' ' + item.local_currency_fee"></span>
                                </div>
                                <div class="small text-success mt-1" x-show="item.local_currency_net && item.local_currency_code">
                                    <?php echo __('Local Net'); ?>: <span x-text="item.local_currency_code + ' ' + item.local_currency_net"></span>
                                </div>
                            </td>
                            <td class="text-secondary small"><span class="font-mono text-xs" x-text="item.date"></span></td>
                            <td>
                                <span class="status-pill status-processing"><i class="fa-solid fa-hourglass-half"></i> <?php echo __('Processing'); ?></span>
                            </td>
                            <td class="text-end">
                                <button @click.stop="openSheet(item)" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredWithdrawals.length === 0">
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                            <p class="m-0"><?php echo __('No pending withdrawals found.'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="d-flex justify-content-center p-3" style="border-top: 1px solid var(--glass-border);">
            <ul class="pagination mb-0">
                <?php
                $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
                ?>
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo $search_param; ?>"><i class="fas fa-angle-double-left"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_param; ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_param; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_param; ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $search_param; ?>"><i class="fas fa-angle-double-right"></i></a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Sheet Overlay -->
    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="sheet-overlay" @click="closeSheet()" x-transition.opacity></div>
    </template>

    <!-- Sheet Panel -->
    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }" role="dialog">
            <!-- Sheet Header -->
            <div class="p-4 border-bottom border-subtle bg-black d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="m-0 fw-bold text-white"><?php echo __('Process Withdrawal'); ?></h5>
                    <div class="small text-secondary mt-1"><span class="text-mono"><?php echo __('ID: #'); ?></span><span class="text-mono" x-text="selected.id"></span></div>
                    <span class="status-pill status-processing mt-2" x-show="selected.method">
                        <i class="fa-solid fa-hourglass-half"></i> <?php echo __('Processing'); ?>
                    </span>
                </div>
                <button @click="closeSheet()" class="btn btn-link text-muted-custom p-0"><i class="fas fa-times"></i></button>
            </div>

            <!-- Sheet Body -->
            <div class="sheet-body">
                <!-- Balance Warning Alert -->
                <div class="p-4 border-bottom border-subtle">
                    <div class="alert d-flex gap-3 align-items-start mb-0 border-danger bg-danger bg-opacity-10">
                        <i class="fa-solid fa-triangle-exclamation mt-1 text-danger"></i>
                        <div class="small text-secondary"><?php echo __('Ensure the user has sufficient balance and no active suspicious flags before processing.'); ?></div>
                    </div>
                </div>

                <!-- Current Balance -->
                <div class="p-4 border-bottom border-subtle">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background: var(--bg-surface-hover); border: 1px solid var(--glass-border);">
                        <span class="small fw-bold text-uppercase text-secondary"><?php echo __('User Balance'); ?></span>
                        <span class="fw-bold text-white fs-5 font-mono" x-text="selected.user_balance"></span>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="p-4 border-bottom border-subtle">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="small text-secondary text-uppercase fw-bold"><?php echo __('User'); ?></label>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <template x-if="selected.profile_picture">
                                    <img :src="'/' + selected.profile_picture" :alt="selected.username" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">
                                </template>
                                <template x-if="!selected.profile_picture">
                                    <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="padding: 12px;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                </template>
                                <div>
                                    <div class="fw-bold text-white" x-text="selected.username"></div>
                                    <div class="small text-secondary" x-text="selected.email"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <label class="small text-secondary text-uppercase fw-bold"><?php echo __('Payout Amount'); ?></label>
                            <div class="fs-5 fw-bold text-white" style="color: var(--primary);" x-text="selected.amount"></div>
                            <div class="small text-secondary" x-show="selected.fee && selected.fee !== '$0.00'"><?php echo __('Net'); ?>: <span x-text="selected.net_amount"></span></div>
                            <div class="small text-success mt-1" x-show="selected.local_currency_net && selected.local_currency_code">
                                <?php echo __('Local Net'); ?>: <span x-text="selected.local_currency_code + ' ' + selected.local_currency_net"></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><?php echo __('Transaction Details'); ?></h6>
                    <table class="table table-custom small border-subtle" style="background: var(--bg-black);">
                        <tbody>
                            <tr>
                                <td class="text-secondary"><?php echo __('Amount'); ?></td>
                                <td class="text-white"><span class="font-mono" x-text="selected.amount"></span></td>
                            </tr>
                            <tr x-show="selected.fee && selected.fee !== '$0.00'">
                                <td class="text-secondary"><?php echo __('Withdrawal Fee'); ?></td>
                                <td class="text-danger"><span class="font-mono" x-text="selected.fee"></span></td>
                            </tr>
                            <tr x-show="selected.fee && selected.fee !== '$0.00'">
                                <td class="text-secondary fw-bold"><?php echo __('Net Payout'); ?></td>
                                <td class="text-success fw-bold"><span class="font-mono" x-text="selected.net_amount"></span></td>
                            </tr>
                            <tr x-show="selected.local_currency_amount && selected.local_currency_code">
                                <td class="text-secondary"><?php echo __('User Local Amount'); ?></td>
                                <td class="text-white">
                                    <span class="font-mono" x-text="selected.local_currency_code + ' ' + selected.local_currency_amount"></span>
                                    <span class="text-muted small" x-show="selected.exchange_rate_used" x-text="'@ ' + parseFloat(selected.exchange_rate_used).toFixed(4)"></span>
                                </td>
                            </tr>
                            <tr x-show="selected.local_currency_fee && selected.local_currency_code">
                                <td class="text-secondary"><?php echo __('Local Fee'); ?></td>
                                <td class="text-danger"><span class="font-mono" x-text="selected.local_currency_code + ' ' + selected.local_currency_fee"></span></td>
                            </tr>
                            <tr x-show="selected.local_currency_net && selected.local_currency_code">
                                <td class="text-secondary fw-bold"><?php echo __('Local Net Payout'); ?></td>
                                <td class="text-success fw-bold"><span class="font-mono" x-text="selected.local_currency_code + ' ' + selected.local_currency_net"></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Destination Details -->
                <div class="p-4 border-bottom border-subtle">
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><?php echo __('Destination Info'); ?></h6>
                    <div class="p-3 rounded" style="background: var(--bg-surface-hover); border: 1px solid var(--glass-border);">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-secondary"><?php echo __('Method'); ?>:</span>
                            <span class="fw-bold text-white" x-text="selected.method"></span>
                        </div>
                        <template x-for="(value, key) in selected.destination_full" :key="key">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-secondary" x-text="key + ':'"></span>
                                <div class="d-flex align-items-center gap-2">
                                    <code class="text-white bg-dark border px-2 py-1 rounded font-mono small" x-text="value" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></code>
                                    <button class="btn btn-link p-0 text-secondary" @click.prevent="copyToClipboard(value)" title="<?php echo __('Copy'); ?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Rejection Reason (Two-Step) -->
                <div class="p-4 border-bottom border-subtle" x-show="rejectMode" x-transition>
                    <label class="small text-secondary text-uppercase fw-bold mb-2"><?php echo __('Rejection Reason'); ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-custom border-danger" rows="3" placeholder="<?php echo __('Explain why this withdrawal is rejected...'); ?>" x-model="rejectionReason" style="border-color: var(--color-danger);"></textarea>
                    <div class="form-text text-secondary small mt-2"><?php echo __('User will see this reason.'); ?></div>
                </div>
            </div>

            <!-- Sheet Footer -->
            <div class="p-3 border-top border-subtle bg-black d-flex flex-wrap justify-content-end gap-2">
                <!-- Normal State -->
                <template x-if="!rejectMode">
                    <div class="w-100 d-flex gap-2">
                        <button @click="rejectMode = true" class="btn btn-outline-danger flex-grow-1">
                            <i class="fas fa-xmark me-1"></i> <?php echo __('Reject'); ?>
                        </button>
                        <form method="POST" action="/admin/actions/withdrawal-approve" class="flex-grow-1" @submit.prevent="submitting = true; $el.submit()">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="withdrawal_id" :value="selected.id">
                            <button type="submit" :disabled="submitting" class="btn btn-success w-100">
                                <span x-show="!submitting"><i class="fas fa-check me-1"></i> <?php echo __('Approve Payout'); ?></span>
                                <span x-show="submitting" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </form>
                    </div>
                </template>

                <!-- Reject State -->
                <template x-if="rejectMode">
                    <div class="w-100 d-flex gap-2">
                        <button @click="rejectMode = false; rejectionReason = ''" class="btn btn-outline-secondary flex-grow-1">
                            <i class="fas fa-chevron-left me-1"></i> <?php echo __('Cancel'); ?>
                        </button>
                        <form method="POST" action="/admin/actions/withdrawal-reject" class="flex-grow-1" @submit.prevent="submitReject($el)" data-no-spinner>
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="withdrawal_id" :value="selected.id">
                            <textarea name="rejection_reason" x-model="rejectionReason" class="d-none"></textarea>
                            <button type="submit" :disabled="submitting" class="btn btn-danger w-100">
                                <span x-show="!submitting"><i class="fas fa-check me-1"></i> <?php echo __('Confirm Rejection'); ?></span>
                                <span x-show="submitting" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </template>

</div><!-- End Alpine.js Scope Wrapper -->

<?php require_once ROOT . '/includes/admin-footer.php'; ?>