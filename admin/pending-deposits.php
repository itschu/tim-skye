<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/admin-header.php';

$currency_symbol = get_currency_symbol();

$page_title = __('Pending Deposits');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get search query from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search conditions
$where = "d.status = 'pending'";
$params = [];

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR d.id LIKE ?)";
    $params = [$search_term, $search_term, $search_term];
}

// Get total count with search filter
$count_query = "SELECT COUNT(*) as count FROM deposits d 
                JOIN users u ON d.user_id = u.id 
                WHERE " . $where;
$total_row = db_query($count_query, $params);
$total_count = $total_row[0]['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// Get pending deposits with search filter and pagination
$list_query = "SELECT d.*, u.name, u.email, u.profile_picture FROM deposits d 
               JOIN users u ON d.user_id = u.id 
               WHERE " . $where . "
               ORDER BY d.created_at DESC 
               LIMIT ? OFFSET ?";
$list_params = array_merge($params, [$per_page, $offset]);
$deposits = db_query($list_query, $list_params);
?>

<!-- Alpine.js Scope Wrapper -->
<div x-data="{ 
    sheetOpen: false, 
    selected: {}, 
    rejectMode: false,
    rejectionReason: '',
    submitting: false,
    currencySymbol: <?php echo htmlspecialchars(json_encode($currency_symbol), ENT_QUOTES, 'UTF-8'); ?>,
    openSheet(deposit) {
        this.selected = deposit;
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
            this.selected = {};
            this.rejectMode = false;
            this.rejectionReason = '';
        }, 300);
    },
    submitReject(form) {
        if (!this.rejectionReason.trim()) {
            alert('<?php echo htmlspecialchars(json_encode(__('Rejection reason is required'))); ?>');
            return;
        }
        this.submitting = true;
        form.submit();
    }
}" x-init="$nextTick(() => { sheetOpen = false; })">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><?php echo __('Pending Deposits'); ?></h4>
            <p class="text-secondary small mb-0"><?php echo __('Review and approve manual deposit requests.'); ?></p>
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

    <!-- Deposits Table -->
    <div class="card-bento">
        <div class="table-responsive">
            <table class="table table-custom mb-0" id="depositsTable">
                <thead>
                    <tr>
                        <th><?php echo __('User'); ?></th>
                        <th><?php echo __('Payment Method'); ?></th>
                        <th><?php echo __('Amount'); ?></th>
                        <th><?php echo __('Date Submitted'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th class="text-end"><?php echo __('Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deposits)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                                <p><?php echo __('No pending deposits found.'); ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $dep):
                            $initial = substr($dep['name'], 0, 1);
                        ?>
                            <tr @click="openSheet(<?php echo htmlspecialchars(json_encode($dep)); ?>)" style="cursor:pointer">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($dep['profile_picture'])): ?>
                                            <img src="<?php echo e('/' . ltrim($dep['profile_picture'], '/')); ?>" alt="<?php echo e($dep['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="width: 32px; height: 32px">
                                                <?php echo e(strtoupper($initial)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-white"><?php echo e($dep['name']); ?></div>
                                            <div class="small text-secondary"><?php echo e($dep['email']); ?></div>
                                            <div class="small text-secondary"><span class="font-mono text-xs"><?php echo time_ago($dep['created_at']); ?></span></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo e($dep['payment_method']); ?></td>
                                <td class="fw-bold text-white">
                                    <span class="font-mono"><?php echo format_money($dep['amount']); ?></span>
                                    <?php if (!empty($dep['fee_amount']) && floatval($dep['fee_amount']) > 0): ?>
                                        <div class="small text-secondary mt-1">
                                            <?php echo __('Fee:'); ?> <?php echo format_money($dep['fee_amount']); ?> &middot; <?php echo __('Net:'); ?> <?php echo format_money($dep['net_amount']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-secondary small"><span class="font-mono text-xs"><?php echo time_ago($dep['created_at']); ?></span></td>
                                <td><span class="status-pill status-pending"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo __('Pending'); ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" @click.stop="openSheet(<?php echo htmlspecialchars(json_encode($dep)); ?>)">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2" style="border-color: var(--glass-border);">
                <span class="small text-secondary">
                    <?php echo __('Showing'); ?> <?php echo (($page - 1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_count); ?> <?php echo __('of'); ?> <?php echo $total_count; ?> <?php echo __('entries'); ?>
                </span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $search_param = !empty($search) ? '&search=' . urlencode($search) : '';
                        ?>
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_param; ?>"><i class="fas fa-chevron-left"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_param; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_param; ?>"><i class="fas fa-chevron-right"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

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
                    <h5 class="m-0 fw-bold text-white"><?php echo __('Review Deposit'); ?></h5>
                    <div class="small text-secondary mt-1"><span class="text-mono"><?php echo __('ID: #'); ?></span><span class="text-mono" x-text="selected?.id"></span></div>
                    <span class="status-pill status-pending mt-2" x-show="selected?.status === 'pending'">
                        <i class="fa-solid fa-clock-rotate-left"></i> <?php echo __('Pending'); ?>
                    </span>
                </div>
                <button @click="closeSheet()" class="btn btn-link text-muted-custom p-0"><i class="fas fa-times"></i></button>
            </div>

            <!-- Sheet Body -->
            <div class="sheet-body">
                <!-- User Info Block -->
                <div class="p-4 border-bottom border-subtle">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <template x-if="selected?.profile_picture">
                            <img :src="'/' + selected.profile_picture" :alt="selected?.name" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover; border: 1px solid var(--glass-border);">
                        </template>
                        <template x-if="!selected?.profile_picture">
                            <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-secondary border" style="width: 48px; height: 48px" x-text="selected?.name?.charAt(0).toUpperCase()"></div>
                        </template>
                        <div>
                            <a :href="'/admin/users?search=' + selected?.email" style="text-decoration: none;">
                                <div class="fw-bold text-white" x-text="selected?.name"></div>
                            </a>
                            <div class="small text-secondary" x-text="selected?.email"></div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="p-4 border-bottom border-subtle">
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><?php echo __('Transaction Details'); ?></h6>
                    <div class="p-3 border border-subtle rounded bg-black mb-4">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted-custom small"><?php echo __('Gross Amount'); ?></span>
                                <span class="font-mono" x-text="selected?.amount ? currencySymbol + parseFloat(selected.amount).toFixed(2) : ''"></span>
                            </div>
                        </div>
                        <div class="mb-3" x-show="selected?.fee_amount && parseFloat(selected.fee_amount) > 0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted-custom small"><?php echo __('Deposit Fee'); ?></span>
                                <span class="text-end text-white"><span class="font-mono" x-text="selected?.fee_amount ? currencySymbol + parseFloat(selected.fee_amount).toFixed(2) : ''"></span></span>
                            </div>
                        </div>
                        <div class="mb-3" x-show="selected?.fee_amount && parseFloat(selected.fee_amount) > 0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted-custom small"><?php echo __('Net Credited'); ?></span>
                                <span class="text-end fw-bold text-white"><span class="font-mono" x-text="selected?.net_amount ? currencySymbol + parseFloat(selected.net_amount).toFixed(2) : ''"></span></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted-custom small"><?php echo __('Payment Method'); ?></span>
                                <span class="text-end text-white" x-text="selected?.payment_method"></span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted-custom small"><?php echo __('Submitted'); ?></span>
                                <span class="text-end text-white"><span class="font-mono text-xs" x-text="selected?.created_at"></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Proof of Payment -->
                <div class="p-4 border-bottom border-subtle" x-show="selected?.proof_path">
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3"><?php echo __('Proof of Payment'); ?></h6>
                    <div class="proof-thumbnail">
                        <a :href="'/user/view-proof?file=' + selected?.proof_path" target="_blank">
                            <img :src="'/user/view-proof?file=' + selected?.proof_path" style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 6px;">
                            <div class="proof-overlay">
                                <i class="fas fa-search-plus"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Rejection Reason (Two-Step) -->
                <div class="p-4 border-bottom border-subtle" x-show="rejectMode" x-transition>
                    <label class="small text-secondary text-uppercase fw-bold mb-2"><?php echo __('Rejection Reason'); ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-custom border-danger" rows="3" placeholder="<?php echo __('Explain why this deposit is rejected...'); ?>" x-model="rejectionReason" style="border-color: var(--color-danger);"></textarea>
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
                        <form method="POST" action="/admin/actions/deposit-approve" class="flex-grow-1" @submit.prevent="$el.querySelector('input[name=deposit_id]').value = selected?.id; submitting = true; $el.submit();">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="deposit_id" value="">
                            <button type="submit" :disabled="submitting" class="btn btn-success w-100">
                                <span x-show="!submitting"><i class="fas fa-check me-1"></i> <?php echo __('Approve'); ?></span>
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
                        <form method="POST" action="/admin/actions/deposit-reject" class="flex-grow-1" @submit.prevent="submitReject($el)" data-no-spinner>
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="deposit_id" :value="selected?.id">
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

</div><!-- /Alpine.js Scope Wrapper -->

<?php require_once ROOT . '/includes/admin-footer.php'; ?>