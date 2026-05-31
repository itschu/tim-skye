<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Feature flags from settings (render-time gating)
$kyc_enabled = get_setting('kyc_required', 'no') === 'yes';
$email_verification_enabled = get_setting('require_email_verification', 'no') === 'yes';

$page_title = __('User Management');

// Get search and filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$kyc_filter = isset($_GET['kyc']) ? $_GET['kyc'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search_term)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($role_filter !== 'all') {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if ($kyc_filter !== 'all') {
    $where_conditions[] = "u.kyc_status = ?";
    $params[] = $kyc_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as count FROM users u {$where_clause}";
$count_result = db_query($count_query, $params);
$total_count = $count_result[0]['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// Ensure page is within bounds
$page = min($page, max(1, $total_pages));

// Build query with pagination
$offset = ($page - 1) * $per_page;
$query = "SELECT u.*, r.name as referrer_name FROM users u LEFT JOIN users r ON u.referred_by = r.id {$where_clause} ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$query_params = array_merge($params, [$per_page, $offset]);
$users = db_query($query, $query_params);

// Prefetch aggregates to avoid N+1 queries
// Prefetch deposits and KYC data in bulk using native single-row schema
$deposits_map = [];
$kyc_counts_map = [];
$kyc_docs_map = [];
$user_ids = array_column($users, 'id');
if (!empty($user_ids)) {
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $deposit_rows = db_query("SELECT user_id, COALESCE(SUM(amount),0) as total_deposits FROM transactions WHERE user_id IN ($placeholders) AND type = 'deposit' AND status = 'completed' GROUP BY user_id", $user_ids);
    foreach ($deposit_rows as $r) {
        $deposits_map[$r['user_id']] = $r['total_deposits'];
    }

    $kyc_count_rows = db_query("SELECT user_id, COUNT(*) as cnt FROM kyc_documents WHERE user_id IN ($placeholders) GROUP BY user_id", $user_ids);
    foreach ($kyc_count_rows as $r) {
        $kyc_counts_map[$r['user_id']] = $r['cnt'];
    }

    // Native single-row schema: store direct paths per user
    $kyc_docs_rows = db_query("SELECT id, user_id, id_passport_path, proof_address_path, selfie_path, created_at, status FROM kyc_documents WHERE user_id IN ($placeholders)", $user_ids);
    foreach ($kyc_docs_rows as $r) {
        $kyc_docs_map[$r['user_id']] = [
            'id' => $r['id'],
            'id_passport_path' => $r['id_passport_path'],
            'proof_address_path' => $r['proof_address_path'],
            'selfie_path' => $r['selfie_path'],
            'status' => $r['status'] ?? 'pending',
            'created_at' => $r['created_at']
        ];
    }
}

// Build query string for pagination links
$query_string_parts = [];
if (!empty($search_term)) $query_string_parts['search'] = $search_term;
if ($role_filter !== 'all') $query_string_parts['role'] = $role_filter;
if ($status_filter !== 'all') $query_string_parts['status'] = $status_filter;
if ($kyc_filter !== 'all') $query_string_parts['kyc'] = $kyc_filter;
$query_string = !empty($query_string_parts) ? '&' . http_build_query($query_string_parts) : '';

$admin_id = $_SESSION['user_id'] ?? null;

// Get countries list for edit form
$countries_list = get_countries();
$accepted_countries_list = get_accepted_countries();

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4" x-data="{
    sheetOpen: false,
    sheetTab: 'overview',
    selectedUser: null,
    editMode: false,
    loginAsLoading: false,
    resendLoading: false,
    adminId: <?php echo htmlspecialchars(json_encode($admin_id), ENT_QUOTES, 'UTF-8'); ?>,
    acceptedCountriesList: <?php echo htmlspecialchars(json_encode($accepted_countries_list), ENT_QUOTES, 'UTF-8'); ?>,
    editData: {},
    moreOpen: false,
    adjustStatus: '',
    adjustMessage: '',
    resetStatus: '',
    resetMessage: '',
    adjustAmount: '',
    copiedEmail: false,
    resetPasswordInput: '',
    currencySymbol: <?php echo htmlspecialchars(json_encode(get_currency_symbol()), ENT_QUOTES, 'UTF-8'); ?>,
    referralData: null,
    referralLoading: false,
    referralError: '',
    prefetchCache: {},
    openSheet(user) {
        this.selectedUser = user;
        this.sheetTab = 'overview';
        this.sheetOpen = true;
        this.editMode = false;
        this.editData = {};
        this.fetchReferralData(user.id);
        try { if (window.adminHelpers && adminHelpers.lockBodyScroll) adminHelpers.lockBodyScroll(); } catch (e) {}
    },
    closeSheet() {
        this.sheetOpen = false;
        this.resetReferralState();
        try { if (window.adminHelpers && adminHelpers.unlockBodyScroll) adminHelpers.unlockBodyScroll(); } catch (e) {}
        this.moreOpen = false;
        this.adjustStatus = '';
        this.adjustMessage = '';
        this.resetStatus = '';
        this.resetMessage = '';
        this.copiedEmail = false;
        this.resetPasswordInput = '';
        this.loginAsLoading = false;
        this.resendLoading = false;
        setTimeout(() => { this.selectedUser = null; }, 300);
    },
    startEdit() {
        if (!this.selectedUser) return;
        this.editData = Object.assign({}, this.selectedUser);
        // ensure country is present in editData
        this.editData.country = this.selectedUser.country || '';
        this.editMode = true;
        this.moreOpen = false;
    },
    cancelEdit() {
        this.editMode = false;
        this.editData = {};
    },
    async saveEdit() {
        this.adjustStatus = '';
        this.adjustMessage = '';
        if (!this.selectedUser) return;
        try {
            const fd = new FormData();
            fd.append('csrf_token', '<?php echo csrf_token(); ?>');
            fd.append('user_id', this.selectedUser.id);
            fd.append('name', this.editData.name || '');
            fd.append('email', this.editData.email || '');
            fd.append('phone', this.editData.phone || '');
            fd.append('role', this.editData.role || 'user');
            fd.append('status', this.editData.status || 'active');
            fd.append('balance', this.selectedUser.balance || 0);
            fd.append('country', this.editData.country || '');
            fd.append('ajax', '1');

            const res = await fetch('/admin/actions/user-update', { method: 'POST', body: fd });
            const ct = res.headers.get('Content-Type') || '';
            if (ct.indexOf('application/json') === -1) {
                // Non-JSON response - treat as error and show server text if available
                const txt = await res.text();
                this.adjustStatus = 'error';
                this.adjustMessage = txt || <?php echo htmlspecialchars(json_encode(__('Invalid server response')), ENT_QUOTES, 'UTF-8'); ?>;
            } else {
                const data = await res.json();
                if (data && data.success) {
                    Object.assign(this.selectedUser, this.editData);
                    this.editMode = false;
                } else {
                    this.adjustStatus = 'error';
                    this.adjustMessage = (data && data.message) ? data.message : <?php echo htmlspecialchars(json_encode(__('An error occurred while updating the user')), ENT_QUOTES, 'UTF-8'); ?>;
                }
            }
        } catch (e) {
            this.adjustStatus = 'error';
            this.adjustMessage = <?php echo htmlspecialchars(json_encode(__('An error occurred while updating the user')), ENT_QUOTES, 'UTF-8'); ?>;
        }
    },
    async adjustBalance(type) {
        this.adjustStatus = '';
        this.adjustMessage = '';
        if (!this.selectedUser) return;
        const amt = parseFloat(this.adjustAmount);
        if (!amt || amt <= 0) {
            this.adjustStatus = 'error';
            this.adjustMessage = <?php echo htmlspecialchars(json_encode(__('Enter a valid amount')), ENT_QUOTES, 'UTF-8'); ?>;
            return;
        }
        try {
            const fd = new FormData();
            fd.append('csrf_token', '<?php echo csrf_token(); ?>');
            fd.append('user_id', this.selectedUser.id);
            fd.append('adjust_amount', amt);
            fd.append('adjust_type', type);
            const res = await fetch('/admin/actions/user-balance-adjust', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                this.selectedUser.balance = data.new_balance;
                this.adjustStatus = 'success';
                this.adjustMessage = data.message || <?php echo htmlspecialchars(json_encode(__('Balance updated')), ENT_QUOTES, 'UTF-8'); ?>;
                this.adjustAmount = '';
            } else {
                this.adjustStatus = 'error';
                this.adjustMessage = data.message || <?php echo htmlspecialchars(json_encode(__('Adjustment failed')), ENT_QUOTES, 'UTF-8'); ?>;
            }
        } catch (e) {
            this.adjustStatus = 'error';
            this.adjustMessage = <?php echo htmlspecialchars(json_encode(__('Adjustment failed')), ENT_QUOTES, 'UTF-8'); ?>;
        }
    },
    copyEmail() {
        if (!this.selectedUser || !this.selectedUser.email) return;
        try {
            navigator.clipboard.writeText(this.selectedUser.email);
            this.copiedEmail = true;
            setTimeout(() => { this.copiedEmail = false; }, 2000);
        } catch (e) {
            // ignore clipboard errors
        }
    },
    async resetPassword() {
        this.resetStatus = '';
        this.resetMessage = '';
        if (!this.selectedUser) return;
        const pw = (this.resetPasswordInput || '').trim();
        if (!pw || pw.length < 6) {
            this.resetStatus = 'error';
            this.resetMessage = <?php echo htmlspecialchars(json_encode(__('Password must be at least 6 characters')), ENT_QUOTES, 'UTF-8'); ?>;
            return;
        }
        try {
            const fd = new FormData();
            fd.append('csrf_token', '<?php echo csrf_token(); ?>');
            fd.append('user_id', this.selectedUser.id);
            fd.append('new_password', pw);
            const res = await fetch('/admin/actions/user-reset-password', { method: 'POST', body: fd });
            const data = await res.json();
            if (data && data.success) {
                this.resetStatus = 'success';
                this.resetMessage = data.message || <?php echo htmlspecialchars(json_encode(__('Password updated successfully')), ENT_QUOTES, 'UTF-8'); ?>;
                this.resetPasswordInput = '';
            } else {
                this.resetStatus = 'error';
                this.resetMessage = (data && data.message) ? data.message : <?php echo htmlspecialchars(json_encode(__('Password reset failed')), ENT_QUOTES, 'UTF-8'); ?>;
            }
        } catch (e) {
            this.resetStatus = 'error';
            this.resetMessage = <?php echo htmlspecialchars(json_encode(__('Password reset failed')), ENT_QUOTES, 'UTF-8'); ?>;
        }
    },
    deleteUser() {
        if (!this.selectedUser) return;
        if (!confirm(<?php echo htmlspecialchars(json_encode(__('Delete this user? This action cannot be undone.')), ENT_QUOTES, 'UTF-8'); ?>)) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/actions/user-delete';
        const inpId = document.createElement('input'); inpId.type = 'hidden'; inpId.name = 'user_id'; inpId.value = this.selectedUser.id; form.appendChild(inpId);
        const inpCs = document.createElement('input'); inpCs.type = 'hidden'; inpCs.name = 'csrf_token'; inpCs.value = '<?php echo csrf_token(); ?>'; form.appendChild(inpCs);
        document.body.appendChild(form);
        form.submit();
    },
    async fetchReferralData(userId) {
        this.referralLoading = true;
        this.referralError = '';
        try {
            const fd = new FormData();
            fd.append('csrf_token', '<?php echo csrf_token(); ?>');
            fd.append('user_id', userId);
            const res = await fetch('/admin/actions/get-user-referral', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                this.referralData = data;
                this.prefetchCache[userId] = data;
            } else {
                this.referralError = data.message || <?php echo htmlspecialchars(json_encode(__('Could not load referral data')), ENT_QUOTES, 'UTF-8'); ?>;
            }
        } catch (e) {
            this.referralError = <?php echo htmlspecialchars(json_encode(__('Could not load referral data')), ENT_QUOTES, 'UTF-8'); ?>;
        } finally {
            this.referralLoading = false;
        }
    },
    navigateToUpline(uplineUser) {
        this.selectedUser = uplineUser;
        this.sheetTab = 'referrals';
        this.referralData = null;
        this.referralLoading = true;
        this.referralError = '';
        if (this.prefetchCache[uplineUser.id]) {
            this.referralData = this.prefetchCache[uplineUser.id];
            this.referralLoading = false;
        }
        this.fetchReferralData(uplineUser.id);
    },
    resetReferralState() {
        this.referralData = null;
        this.referralLoading = false;
        this.referralError = '';
        this.prefetchCache = {};
    }
}">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><?php echo __('User Management'); ?></h4>
            <p class="text-secondary small mb-0"><?php echo __('View, edit, and manage all platform users.'); ?></p>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?php echo e($total_count); ?> <?php echo __('users'); ?></span>
    </div>

    <!-- Search & Filter Card -->
    <div class="card-bento p-4 mb-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-filter me-2 text-zinc-400"></i> <?php echo __('Search & Filter'); ?></h6>
        <form method="GET">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control-custom" placeholder="<?php echo __('Search by name or email'); ?>" value="<?php echo e($search_term); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select-custom">
                        <option value="all"><?php echo __('All Roles'); ?></option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>><?php echo __('User'); ?></option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>><?php echo __('Admin'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select-custom">
                        <option value="all"><?php echo __('All Status'); ?></option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('Active'); ?></option>
                        <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>><?php echo __('Banned'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="kyc" class="form-select-custom">
                        <option value="all"><?php echo __('All KYC'); ?></option>
                        <option value="not_submitted" <?php echo $kyc_filter === 'not_submitted' ? 'selected' : ''; ?>><?php echo __('Not Submitted'); ?></option>
                        <option value="pending" <?php echo $kyc_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('Pending'); ?></option>
                        <option value="approved" <?php echo $kyc_filter === 'approved' ? 'selected' : ''; ?>><?php echo __('Approved'); ?></option>
                        <option value="rejected" <?php echo $kyc_filter === 'rejected' ? 'selected' : ''; ?>><?php echo __('Rejected'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-glow w-100">
                        <i class="fas fa-search d-md-none"></i>
                        <span class="d-none d-md-inline"><i class="fas fa-search me-1"></i> <?php echo __('Search'); ?></span>
                    </button>
                </div>
            </div>
        </form>
        <?php if (!empty($query_string_parts)): ?>
            <div class="mt-3">
                <a href="/admin/users" class="btn btn-sm btn-outline-secondary"><?php echo __('Clear Filters'); ?></a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Users Table -->
    <div class="card-bento">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('User'); ?></th>
                        <th><?php echo __('Email & Phone'); ?></th>
                        <th><?php echo __('Referred By'); ?></th>
                        <th><?php echo __('Balance'); ?></th>
                        <th><?php echo __('Role'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th><?php echo __('KYC'); ?></th>
                        <th class="text-end"><?php echo __('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-users icon"></i>
                                    <p><?php echo __('No users found.'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user):
                            $initial = substr($user['name'], 0, 1);
                            $user_enriched = $user;
                            $user_enriched['total_deposits'] = $deposits_map[$user['id']] ?? 0.00;
                            $user_enriched['kyc_count'] = $kyc_counts_map[$user['id']] ?? 0;
                            $user_enriched['kyc_docs'] = $kyc_docs_map[$user['id']] ?? [];
                            $user_enriched['registration_ip'] = $user['registration_ip'] ?? ($user['created_ip'] ?? '');
                            $user_enriched['country'] = $user['country'] ?? '';
                            $kyc_colors = [
                                'not_submitted' => 'secondary',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger'
                            ];
                            $kyc_color = $kyc_colors[$user['kyc_status']] ?? 'secondary';
                            $kyc_text = ucwords(str_replace('_', ' ', $user['kyc_status']));
                            $user_json = json_encode($user_enriched, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                            <tr class="table-row-hover" style="cursor: pointer;" data-user='<?php echo htmlspecialchars($user_json, ENT_NOQUOTES, 'UTF-8'); ?>' @click="openSheet($event.currentTarget.dataset.user ? JSON.parse($event.currentTarget.dataset.user) : null)">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($user['profile_picture'])): ?>
                                            <img src="<?php echo e('/' . ltrim($user['profile_picture'], '/')); ?>" alt="<?php echo e($user['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; border-color: var(--glass-border);" class="border">
                                        <?php else: ?>
                                            <div class="bg-zinc-800 rounded-circle text-zinc-400 fw-bold d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; font-size: 12px; border-color: var(--glass-border);">
                                                <?php echo e(strtoupper($initial)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="d-block fw-bold small text-white"><?php echo e($user['name']); ?></span>
                                            <small class="text-zinc-400 text-mono">#<?php echo e($user['id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="d-block text-secondary"><?php echo e($user['email']); ?></small>
                                    <small class="text-secondary"><?php echo e($user['phone'] ?? __('N/A')); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($user['referrer_name'])): ?>
                                        <span class="small text-secondary"><?php echo e($user['referrer_name']); ?></span>
                                    <?php else: ?>
                                        <span class="small text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-success text-mono"><?php echo format_money($user['balance']); ?></td>
                                <td>
                                    <span class="badge bg-indigo bg-opacity-10 text-indigo border border-indigo border-opacity-25"><?php echo ucfirst($user['role']); ?></span>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?php echo __('Active'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?php echo __('Banned'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $kyc_color; ?> bg-opacity-10 text-<?php echo $kyc_color; ?> border border-<?php echo $kyc_color; ?> border-opacity-25"><?php echo __($kyc_text); ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" @click.stop="openSheet($event.currentTarget.closest('tr').dataset.user ? JSON.parse($event.currentTarget.closest('tr').dataset.user) : null)"><i class="fas fa-chevron-right"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="admin-pagination d-flex justify-content-center p-3">
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="users?page=1<?php echo $query_string; ?>"><i class="fas fa-angle-double-left"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="users?page=<?php echo $page - 1; ?><?php echo $query_string; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($p = $start_page; $p <= $end_page; $p++):
                    ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="users?page=<?php echo $p; ?><?php echo $query_string; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="users?page=<?php echo $page + 1; ?><?php echo $query_string; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="users?page=<?php echo $total_pages; ?><?php echo $query_string; ?>"><i class="fas fa-angle-double-right"></i></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Side Sheet (Slide-over panel) -->
    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="sheet-overlay" @click="closeSheet" x-transition.opacity></div>
    </template>

    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }" :aria-hidden="!sheetOpen" role="dialog">
            <!-- Sheet Header -->
            <div class="p-4 border-bottom border-subtle bg-black d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-outline-secondary me-2" @click="closeSheet()"><i class="fas fa-times"></i></button>
                    <template x-if="selectedUser?.profile_picture">
                        <img :src="'/' + selectedUser.profile_picture" :alt="selectedUser?.name" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; border: 1px solid var(--glass-border);">
                    </template>
                    <template x-if="!selectedUser?.profile_picture">
                        <div class="user-avatar" x-text="selectedUser && selectedUser.name ? selectedUser.name.substr(0,2).toUpperCase() : ''"></div>
                    </template>
                    <div class="ms-2">
                        <h3 class="h6 mb-0 text-white" x-text="selectedUser?.name"></h3>
                        <div class="small text-secondary d-flex align-items-center gap-2">
                            <span x-text="selectedUser?.email"></span>
                            <span @click="copyEmail()" style="cursor:pointer">
                                <i class="fas fa-copy" x-show="!copiedEmail"></i>
                                <i class="fas fa-check text-success" x-show="copiedEmail"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-outline-secondary" @click="moreOpen = !moreOpen" @click.outside="moreOpen = false"><i class="fas fa-ellipsis-v"></i></button>
                    <div x-show="moreOpen" x-cloak class="v-card position-absolute end-0 mt-2 p-2" style="min-width:160px;">
                        <button class="dropdown-item-custom" @click.prevent="startEdit()"><?php echo __('Edit'); ?></button>
                        <button x-show="selectedUser && selectedUser.id != adminId" class="dropdown-item-custom text-danger" @click.prevent="deleteUser()"><?php echo __('Delete'); ?></button>
                    </div>
                </div>
            </div>

            <div class="p-3 border-bottom border-subtle bg-hover gap-2 overflow-x-auto" x-show="selectedUser && selectedUser.id != adminId" :class="{'d-flex': selectedUser && selectedUser.id != adminId}">
                <form action="/admin/actions/user-login-as" method="POST" class="d-inline" @submit.prevent="$el.querySelector('input[name=user_id]').value = selectedUser?.id; loginAsLoading = true; $el.submit();">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="user_id" value="">
                    <button class="btn btn-sm btn-outline-secondary border-subtle text-white text-nowrap" :disabled="loginAsLoading">
                        <span x-show="!loginAsLoading"><i class="fas fa-sign-in-alt me-1"></i> <?php echo __('Login as User'); ?></span>
                        <span x-show="loginAsLoading" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                    </button>
                </form>
                <?php if ($email_verification_enabled): ?>
                    <form x-show="selectedUser && selectedUser.email_verified == 0" action="/actions/resend-verification" method="POST" class="" @submit.prevent="$el.querySelector('input[name=email]').value = selectedUser?.email || ''; resendLoading = true; $el.submit();">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="email" value="">
                        <button class="btn btn-sm btn-outline-secondary border-subtle text-white text-nowrap" :disabled="resendLoading">
                            <span x-show="!resendLoading"><i class="fas fa-user-check me-1"></i> <?php echo __('Resend Verification'); ?></span>
                            <span x-show="resendLoading" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                        </button>
                    </form>
                <?php endif; ?>
                <a :href="'/admin/in-mail?user_id=' + (selectedUser?.id || '')" class="btn btn-sm btn-outline-secondary border-subtle text-white text-nowrap"><i class="fas fa-envelope me-1"></i> <?php echo __('Send Email'); ?></a>
            </div>
            <div class="p-2 px-3">
                <div x-show="resetStatus" x-cloak class="small mt-2" :class="{'text-success': resetStatus==='success','text-danger': resetStatus==='error'}" x-text="resetMessage"></div>
            </div>

            <div class="sheet-tabs bg-black">
                <div class="sheet-tab" :class="{'active': sheetTab === 'overview'}" @click.prevent="sheetTab = 'overview'"><?php echo __('Overview'); ?></div>
                <div class="sheet-tab" :class="{'active': sheetTab === 'referrals'}" @click.prevent="sheetTab = 'referrals'"><?php echo __('Referrals'); ?></div>
                <div class="sheet-tab" :class="{'active': sheetTab === 'reset'}" @click.prevent="sheetTab = 'reset'"><?php echo __('Reset Password'); ?></div>
            </div>

            <div class="p-3 sheet-body" style="flex:1;">
                <!-- Overview -->
                <div x-show="sheetTab === 'overview'">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 border border-subtle rounded bg-black">
                                <div class="small text-secondary mb-1"><?php echo __('Total Balance'); ?></div>
                                <div class="fw-bold text-white h5" x-text="selectedUser ? selectedUser.balance : ''"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border border-subtle rounded bg-black">
                                <div class="small text-secondary mb-1"><?php echo __('Total Deposits'); ?></div>
                                <div class="fw-bold text-white h5" x-text="selectedUser ? (selectedUser.total_deposits || 0) : 0"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="small text-uppercase text-secondary mb-2"><?php echo __('User Details'); ?></h6>

                    <table x-show="!editMode" class="table table-custom mb-0 small border-subtle">
                        <tbody>
                            <tr>
                                <td><?php echo __('User ID'); ?></td>
                                <td><span class="text-mono" x-text="selectedUser?.id"></span></td>
                            </tr>
                            <tr>
                                <td><?php echo __('Registered IP'); ?></td>
                                <td x-text="selectedUser?.registration_ip || '-'" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <td><?php echo __('Country'); ?></td>
                                <td x-text="selectedUser?.country || '-'" class="text-secondary"></td>
                            </tr>
                            <tr>
                                <td><?php echo __('Phone'); ?></td>
                                <td x-text="selectedUser?.phone || '-'" class="text-secondary"></td>
                            </tr>
                            <?php if ($kyc_enabled): ?>
                                <tr>
                                    <td><?php echo __('KYC Status'); ?></td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" x-text="selectedUser ? (selectedUser.kyc_status || '-') : '-' "></span>
                                        <a :href="'/admin/kyc-review?user_id=' + (selectedUser?.id || '')" class="ms-2 small text-primary"><?php echo __('Review KYC'); ?></a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div x-show="editMode" class="pt-3">
                        <div class="mb-2">
                            <label class="small text-secondary d-block mb-1"><?php echo __('Name'); ?></label>
                            <input class="form-control-custom" x-model="editData.name" placeholder="<?php echo addslashes(__('Name')); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="small text-secondary d-block mb-1"><?php echo __('Email'); ?></label>
                            <input class="form-control-custom" x-model="editData.email" placeholder="<?php echo addslashes(__('Email')); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="small text-secondary d-block mb-1"><?php echo __('Phone'); ?></label>
                            <input class="form-control-custom" x-model="editData.phone" placeholder="<?php echo addslashes(__('Phone')); ?>">
                        </div>
                        <div class="mb-4">
                            <label class="small text-secondary d-block mb-1"><?php echo __('Country'); ?></label>
                            <select class="form-select-custom" x-model="editData.country">
                                <option value=""><?php echo __('Select Country'); ?></option>
                                <?php foreach ($countries_list as $code => $name): ?>
                                    <?php if (in_array($code, $accepted_countries_list)): ?>
                                        <option value="<?php echo e($code); ?>"><?php echo e($name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <select x-model="editData.role" class="form-select-custom">
                                <option value="user"><?php echo __('User'); ?></option>
                                <option value="admin"><?php echo __('Admin'); ?></option>
                            </select>
                            <select x-model="editData.status" class="form-select-custom">
                                <option value="active"><?php echo __('Active'); ?></option>
                                <option value="banned"><?php echo __('Banned'); ?></option>
                            </select>
                            <button class="btn btn-success btn-sm" @click.prevent="saveEdit()"><?php echo __('Save'); ?></button>
                            <button class="btn btn-outline-secondary btn-sm" @click.prevent="cancelEdit()"><?php echo __('Cancel'); ?></button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="small text-uppercase text-secondary mb-2"><?php echo __('Balance Adjustment'); ?></h6>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="number" step="0.01" class="form-control-custom text-mono form-control-sm" x-model="adjustAmount" placeholder="0.00">
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click.prevent="adjustBalance('credit')"><?php echo __('Credit'); ?></button>
                            <button type="button" class="btn btn-sm btn-danger" @click.prevent="adjustBalance('debit')"><?php echo __('Debit'); ?></button>
                        </div>
                        <div x-show="adjustStatus" x-cloak class="small mt-2" :class="{'text-success': adjustStatus==='success','text-danger': adjustStatus==='error'}" x-text="adjustMessage"></div>
                    </div>
                </div>

                <!-- Reset Password Tab -->
                <div x-show="sheetTab === 'reset'">
                    <div class="mb-3 mt-2">
                        <h6 class="mb-3 small text-uppercase text-secondary"><?php echo __('Set New Password'); ?></h6>
                        <div class="mb-4">
                            <input type="password" class="form-control-custom" x-model="resetPasswordInput" placeholder="<?php echo addslashes(__('Enter new password (min 6 characters)')); ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" @click.prevent="resetPassword()">
                                <span x-show="!resetStatus || resetStatus==='idle'"><?php echo __('Reset Password'); ?></span>
                            </button>
                        </div>
                        <div x-show="resetStatus" x-cloak class="small mt-2" :class="{'text-success': resetStatus==='success','text-danger': resetStatus==='error'}" x-text="resetMessage"></div>
                    </div>
                </div>

                <!-- Referrals Tab -->
                <div x-show="sheetTab === 'referrals'">
                    <!-- Loading state -->
                    <div x-show="referralLoading && !referralData">
                        <div class="d-flex flex-column align-items-center justify-content-center py-5">
                            <div class="spinner-border text-indigo mb-3" role="status" style="width: 1.5rem; height: 1.5rem;"></div>
                            <span class="text-muted small"><?php echo __('Loading referral data…'); ?></span>
                        </div>
                    </div>

                    <!-- Error state -->
                    <div x-show="referralError && !referralLoading" class="alert alert-danger" x-text="referralError"></div>

                    <!-- Loaded state -->
                    <div x-show="referralData && !referralLoading">
                        <!-- Stat cards row -->
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <div class="card-bento p-3 text-center">
                                    <div class="stat-label"><?php echo __('Users Referred'); ?></div>
                                    <div class="stat-value" x-text="referralData.users_referred_count ?? 0"></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card-bento p-3 text-center">
                                    <div class="stat-label"><?php echo __('Bonus Events'); ?></div>
                                    <div class="stat-value" x-text="referralData.credited_bonus_events_count ?? 0"></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card-bento p-3 text-center">
                                    <div class="stat-label"><?php echo __('Total Earned'); ?></div>
                                    <div class="stat-value" x-text="currencySymbol + (referralData.total_earnings ?? '0.00')"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Referred By section -->
                        <div x-show="referralData.upline" class="mb-3">
                            <div class="stat-label mb-2"><?php echo __('Referred By'); ?></div>
                            <div class="card-bento p-3 d-flex align-items-center gap-3 cursor-pointer" @click="navigateToUpline(referralData.upline)" style="cursor: pointer;">
                                <div class="d-flex align-items-center justify-content-center rounded-circle fw-bold text-white" style="width: 32px; height: 32px; background: linear-gradient(135deg, #1e3a5f, #0f172a); font-size: 12px;"
                                    x-text="(referralData.upline.name || '').split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase()">
                                </div>
                                <div class="flex-fill">
                                    <div class="fw-bold text-indigo" x-text="referralData.upline.name"></div>
                                    <div class="small text-muted" x-text="referralData.upline_of_upline ? '<?php echo __('Has upline · Click to view'); ?>' : '<?php echo __('Top of chain'); ?>'"></div>
                                </div>
                                <div class="text-muted" style="font-size: 18px;">›</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>
</template>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>