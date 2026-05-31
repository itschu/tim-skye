<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('KYC Review');

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// Get total count
$count_query = "SELECT COUNT(*) as count FROM kyc_documents WHERE status = 'pending'";
$count_result = db_query($count_query, []);
$total_count = $count_result[0]['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// Ensure page is within bounds
$page = min($page, max(1, $total_pages));

// Build query with pagination
$offset = ($page - 1) * $per_page;
$query = "SELECT k.*, u.name, u.email, u.kyc_status, u.profile_picture FROM kyc_documents k JOIN users u ON k.user_id = u.id WHERE k.status = 'pending' ORDER BY k.created_at ASC LIMIT ? OFFSET ?";
$documents = db_query($query, [$per_page, $offset]);

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4" x-data="{ 
    sheetOpen: false,
    selected: {},
    rejectMode: false,
    rejectionReason: '',
    openSheet(doc) {
        this.selected = doc || {};
        this.sheetOpen = true;
        this.rejectMode = false;
        this.rejectionReason = '';
        document.body.style.overflow = 'hidden';
    },
    closeSheet() {
        this.sheetOpen = false;
        document.body.style.overflow = '';
        setTimeout(() => { this.selected = {}; this.rejectionReason = ''; this.rejectMode = false; }, 300);
    },
    openReject() {
        this.rejectMode = true;
    },
    cancelReject() {
        this.rejectMode = false;
        this.rejectionReason = '';
    }
}">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><?php echo __('KYC Review'); ?></h4>
            <p class="text-secondary small mb-0"><?php echo __('Review and verify user identity documents.'); ?></p>
        </div>
        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo e($total_count); ?> <?php echo __('pending'); ?></span>
    </div>

    <!-- Documents Table -->
    <div class="card-bento">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('User'); ?></th>
                        <th><?php echo __('Documents'); ?></th>
                        <th><?php echo __('Email'); ?></th>
                        <th><?php echo __('Submitted'); ?></th>
                        <th class="text-end"><?php echo __('Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle icon"></i>
                                    <p><?php echo __('All KYC documents have been reviewed!'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc):
                            $initial = substr($doc['name'], 0, 1);
                        ?>
                            <tr class="table-row-hover">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($doc['profile_picture'])): ?>
                                            <img src="<?php echo e('/' . ltrim($doc['profile_picture'], '/')); ?>" alt="<?php echo e($doc['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; border: 1px solid var(--glass-border);">
                                        <?php else: ?>
                                            <div class="bg-zinc-800 rounded-circle text-zinc-400 fw-bold d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; font-size: 12px; border-color: var(--glass-border);">
                                                <?php echo e(strtoupper($initial)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="d-block fw-bold small text-white"><?php echo e($doc['name']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($doc['id_passport_path'])): ?><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1"><?php echo __('ID'); ?></span><?php endif; ?>
                                    <?php if (!empty($doc['proof_address_path'])): ?><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1"><?php echo __('Address'); ?></span><?php endif; ?>
                                    <?php if (!empty($doc['selfie_path'])): ?><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo __('Selfie'); ?></span><?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?php echo e($doc['email']); ?></td>
                                <td class="small text-secondary text-mono"><?php echo e(time_ago($doc['created_at'])); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary-glow py-1 px-2" @click="openSheet(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                        <i class="fas fa-eye"></i> <?php echo __('Review'); ?>
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
            <nav aria-label="Page navigation" class="admin-pagination d-flex justify-content-center p-3">
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="kyc-review?page=1"><i class="fas fa-angle-double-left"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="kyc-review?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($p = $start_page; $p <= $end_page; $p++):
                    ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="kyc-review?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="kyc-review?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="kyc-review?page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Sheet Overlay + Panel -->
    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="sheet-overlay" @click="closeSheet()" x-transition.opacity></div>
    </template>

    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }" role="dialog" aria-hidden="!sheetOpen" style="justify-content: space-between;">
            <div class="p-4 border-bottom border-subtle sheet-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-outline-secondary me-2" @click="closeSheet()"><i class="fas fa-times"></i></button>
                    <template x-if="selected.profile_picture">
                        <img :src="'/' + selected.profile_picture" :alt="selected.name" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;">
                    </template>
                    <div>
                        <h5 class="m-0 fw-bold text-white" x-text="selected.name"></h5>
                        <div class="small text-secondary" x-text="selected.email"></div>
                    </div>
                </div>
            </div>

            <div class="p-4 overflow-auto sheet-body">
                <div class="row mb-4 border-bottom border-subtle">
                    <div class="col-md-12">
                        <h6 class="fw-bold text-uppercase text-secondary small mb-3"><?php echo __('User Information'); ?></h6>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <template x-if="selected.profile_picture">
                                <img :src="'/' + selected.profile_picture" :alt="selected.name" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                            </template>
                            <div>
                                <div class="fw-bold text-white" x-text="selected.name"></div>
                                <div class="small text-secondary" x-text="selected.email"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold text-uppercase text-secondary small mb-2"><?php echo __('Documents'); ?></h6>
                    <table class="table table-custom mb-0 small border-subtle bg-black p-2" style="min-width: auto;">
                        <tbody>
                            <tr x-show="selected.id_passport_path">
                                <td class="text-secondary" style="width:40%"><?php echo __('ID / Passport'); ?></td>
                                <td>
                                    <a :href="'/user/view-kyc?file=' + selected.id_passport_path" target="_blank">
                                        <img :src="'/user/view-kyc?file=' + selected.id_passport_path"
                                            style="width:80px; height:80px; object-fit:cover; border-radius:6px;" />
                                    </a>
                                </td>
                            </tr>
                            <tr x-show="selected.proof_address_path">
                                <td class="text-secondary"><?php echo __('Proof of Address'); ?></td>
                                <td>
                                    <a :href="'/user/view-kyc?file=' + selected.proof_address_path" target="_blank">
                                        <img :src="'/user/view-kyc?file=' + selected.proof_address_path"
                                            style="width:80px; height:80px; object-fit:cover; border-radius:6px;" />
                                    </a>
                                </td>
                            </tr>
                            <tr x-show="selected.selfie_path">
                                <td class="text-secondary"><?php echo __('Selfie'); ?></td>
                                <td>
                                    <a :href="'/user/view-kyc?file=' + selected.selfie_path" target="_blank">
                                        <img :src="'/user/view-kyc?file=' + selected.selfie_path"
                                            style="width:80px; height:80px; object-fit:cover; border-radius:6px;" />
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                        <form method="POST" action="/admin/actions/kyc-approve" class="flex-grow-1" @submit.prevent="$el.querySelector('input[name=document_id]').value = selected?.id; $el.submit();">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="document_id" value="">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check me-1"></i> <?php echo __('Approve'); ?>
                            </button>
                        </form>
                    </div>
                </template>

                <!-- Reject State -->
                <template x-if="rejectMode">
                    <div class="w-100">
                        <div class="mb-3">
                            <label class="small"><?php echo __('Rejection Reason'); ?> <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" x-model="rejectionReason" class="form-control-custom" rows="3" required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" @click="cancelReject()" class="btn btn-outline-secondary flex-grow-1">
                                <i class="fas fa-chevron-left me-1"></i> <?php echo __('Cancel'); ?>
                            </button>
                            <form method="POST" action="/admin/actions/kyc-reject" class="flex-grow-1">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="document_id" :value="selected?.id">
                                <textarea name="rejection_reason" x-model="rejectionReason" class="d-none"></textarea>
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-check me-1"></i> <?php echo __('Confirm Rejection'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>