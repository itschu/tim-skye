<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('Investment Plans');

// Query all plans
$query = "SELECT * FROM investment_plans ORDER BY status ASC, created_at DESC";
$plans = db_query($query, []);

// Separate active and archived plans
$active_plans = [];
$archived_plans = [];

foreach ($plans as $plan) {
    if ($plan['status'] === 'active') {
        $active_plans[] = $plan;
    } else {
        $archived_plans[] = $plan;
    }
}

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid p-3 p-md-4" x-data="{ 
    sheetOpen: false,
    isEdit: false,
    currentPlan: null,
    openSheet(plan = null) {
        this.isEdit = plan !== null;
        if (plan === null) {
            this.currentPlan = {
                name: '',
                roi_percentage: 0,
                duration_days: 30,
                min_amount: 0,
                max_amount: 0,
                payout_interval: 'daily',
                payout_interval_type: null,
                payout_interval_value: null,
                is_compounding: 0,
                capital_return: 1,
                status: 'active',
                is_featured: 0,
                waiting_period_value: 0,
                waiting_period_unit: 'days'
            };
        } else {
            this.currentPlan = JSON.parse(JSON.stringify(plan));
            if (typeof this.currentPlan.payout_interval_value === 'undefined' && typeof this.currentPlan.payout_interval_days !== 'undefined') {
                this.currentPlan.payout_interval_value = this.currentPlan.payout_interval_days || null;
            }
            if (typeof this.currentPlan.payout_interval_type === 'undefined') {
                this.currentPlan.payout_interval_type = this.currentPlan.payout_interval_type || 'days';
            }
            if (typeof this.currentPlan.is_compounding === 'undefined') {
                this.currentPlan.is_compounding = 0;
            }
            if (typeof this.currentPlan.waiting_period_value === 'undefined') this.currentPlan.waiting_period_value = 0;
            if (typeof this.currentPlan.waiting_period_unit === 'undefined') this.currentPlan.waiting_period_unit = 'days';
        }
        this.sheetOpen = true;
        document.body.style.overflow = 'hidden';
    },
    closeSheet() {
        this.sheetOpen = false;
        document.body.style.overflow = '';
        setTimeout(() => { this.currentPlan = null; }, 200);
    },
    waitingExceedsDuration() {
        if (!this.currentPlan || this.currentPlan.waiting_period_value <= 0) return false;

        // Convert waiting period to days
        let waiting_days = 0;
        switch (this.currentPlan.waiting_period_unit) {
            case 'seconds':
                waiting_days = this.currentPlan.waiting_period_value / 86400;
                break;
            case 'minutes':
                waiting_days = this.currentPlan.waiting_period_value / 1440;
                break;
            case 'hours':
                waiting_days = this.currentPlan.waiting_period_value / 24;
                break;
            case 'days':
                waiting_days = this.currentPlan.waiting_period_value;
                break;
            case 'weeks':
                waiting_days = this.currentPlan.waiting_period_value * 7;
                break;
            default:
                waiting_days = this.currentPlan.waiting_period_value;
        }

        return waiting_days > (this.currentPlan.duration_days || 0);
    }
}">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h5 fw-semibold text-white mb-1"><?php echo __('Investment Plans'); ?></h2>
            <p class="text-muted small mb-0"><?php echo __('Manage active and archived investment plans.'); ?></p>
        </div>
        <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="openSheet()">
            <i class="fa-solid fa-plus" style="font-size:16px;"></i> <?php echo __('Create New Plan'); ?>
        </button>
    </div>

    <!-- Active Plans Section -->
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3 px-1">
            <i class="fa-solid fa-circle-check text-success" style="font-size:16px;"></i>
            <h6 class="text-white text-uppercase fw-bold mb-0" style="font-size:0.75rem"><?php echo __('Active Plans'); ?></h6>
        </div>
        <div class="card bg-card border-subtle">
            <?php if (empty($active_plans)): ?>
                <div class="py-5 text-center text-muted">
                    <i class="fa-solid fa-inbox text-muted opacity-25 mb-3 d-block" style="font-size:48px;"></i>
                    <p><?php echo __('No active investment plans.'); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th><?php echo __('Plan Name'); ?></th>
                                <th><?php echo __('ROI'); ?></th>
                                <th><?php echo __('Duration'); ?></th>
                                <th><?php echo __('Limits'); ?></th>
                                <th><?php echo __('Interval'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_plans as $plan):
                                $interval_labels = [
                                    'hourly' => __('Hourly'),
                                    'daily' => __('Daily'),
                                    'end_of_term' => __('End of Term'),
                                    'custom' => __('Custom')
                                ];
                                $interval_value = $plan['payout_interval_value'] ?? $plan['payout_interval_days'] ?? null;
                                $interval_type = $plan['payout_interval_type'] ?? 'days';
                            ?>
                                <tr @click="openSheet(<?php echo htmlspecialchars(json_encode($plan)); ?>)" style="cursor:pointer">
                                    <td>
                                        <div>
                                            <span class="text-white fw-medium"><?php echo e($plan['name']); ?></span>
                                            <?php if ($plan['is_featured']): ?>
                                                <div><span class="badge badge-featured mt-1"><?php echo __('Featured'); ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-success fw-medium text-mono"><?php echo format_percentage($plan['roi_percentage']); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-white text-mono"><?php echo e($plan['duration_days']); ?> <?php echo __('days'); ?></span>
                                    </td>
                                    <td>
                                        <div class="text-mono small">
                                            <div><?php echo format_money($plan['min_amount']); ?></div>
                                            <div class="text-muted"><?php echo format_money($plan['max_amount']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted-custom small text-capitalize">
                                            <?php if (($plan['payout_interval'] ?? '') === 'custom' && $interval_value): ?>
                                                <?php
                                                $iv = intval($interval_value);
                                                switch ($interval_type) {
                                                    case 'minutes':
                                                        echo sprintf(__('Every %d min'), $iv);
                                                        break;
                                                    case 'hours':
                                                        echo sprintf(__('Every %d hr'), $iv);
                                                        break;
                                                    case 'days':
                                                        echo sprintf(__('Every %d day(s)'), $iv);
                                                        break;
                                                    case 'weeks':
                                                        echo sprintf(__('Every %d wk'), $iv);
                                                        break;
                                                    case 'months':
                                                        echo sprintf(__('Every %d mo'), $iv);
                                                        break;
                                                    default:
                                                        echo sprintf(__('Every %d day(s)'), $iv);
                                                }
                                                ?>
                                            <?php else: ?>
                                                <?php echo $interval_labels[$plan['payout_interval']] ?? ucfirst($plan['payout_interval']); ?>
                                            <?php endif; ?>
                                            <?php if (($plan['payout_interval'] ?? '') === 'end_of_term' && !empty($plan['is_compounding'])): ?>
                                                <span class="ms-1 badge badge-active" title="<?php echo __('Compounding'); ?>" style="font-size:0.65rem"><i class="fa-solid fa-trending-up" style="font-size:12px;"></i></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-gear text-muted-custom" style="font-size:16px;"></i>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Archived Plans Section -->
    <div>
        <div class="d-flex align-items-center gap-2 mb-3 px-1">
            <i class="fa-solid fa-archive text-muted" style="font-size:16px;"></i>
            <h6 class="text-white text-uppercase fw-bold mb-0" style="font-size:0.75rem"><?php echo __('Archived Plans'); ?></h6>
        </div>
        <div class="card bg-card border-subtle opacity-75">
            <?php if (empty($archived_plans)): ?>
                <div class="py-5 text-center text-muted">
                    <i class="fa-solid fa-inbox text-muted opacity-25 mb-3 d-block" style="font-size:48px;"></i>
                    <p><?php echo __('No archived investment plans.'); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th><?php echo __('Plan Name'); ?></th>
                                <th><?php echo __('ROI'); ?></th>
                                <th><?php echo __('Duration'); ?></th>
                                <th><?php echo __('Limits'); ?></th>
                                <th><?php echo __('Interval'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archived_plans as $plan):
                                $interval_labels = [
                                    'hourly' => __('Hourly'),
                                    'daily' => __('Daily'),
                                    'end_of_term' => __('End of Term'),
                                    'custom' => __('Custom')
                                ];
                                $interval_value = $plan['payout_interval_value'] ?? $plan['payout_interval_days'] ?? null;
                                $interval_type = $plan['payout_interval_type'] ?? 'days';
                            ?>
                                <tr @click="openSheet(<?php echo htmlspecialchars(json_encode($plan)); ?>)" style="cursor:pointer">
                                    <td>
                                        <div>
                                            <span class="text-muted"><?php echo e($plan['name']); ?></span>
                                            <?php if ($plan['is_featured']): ?>
                                                <div><span class="badge badge-archived mt-1"><?php echo __('Featured'); ?></span></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted text-mono"><?php echo format_percentage($plan['roi_percentage']); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted text-mono"><?php echo e($plan['duration_days']); ?> <?php echo __('days'); ?></span>
                                    </td>
                                    <td>
                                        <div class="text-mono small text-muted">
                                            <div><?php echo format_money($plan['min_amount']); ?></div>
                                            <div><?php echo format_money($plan['max_amount']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted-custom small text-capitalize">
                                            <?php if (($plan['payout_interval'] ?? '') === 'custom' && $interval_value): ?>
                                                <?php
                                                $iv = intval($interval_value);
                                                switch ($interval_type) {
                                                    case 'minutes':
                                                        echo sprintf(__('Every %d min'), $iv);
                                                        break;
                                                    case 'hours':
                                                        echo sprintf(__('Every %d hr'), $iv);
                                                        break;
                                                    case 'days':
                                                        echo sprintf(__('Every %d day(s)'), $iv);
                                                        break;
                                                    case 'weeks':
                                                        echo sprintf(__('Every %d wk'), $iv);
                                                        break;
                                                    case 'months':
                                                        echo sprintf(__('Every %d mo'), $iv);
                                                        break;
                                                    default:
                                                        echo sprintf(__('Every %d day(s)'), $iv);
                                                }
                                                ?>
                                            <?php else: ?>
                                                <?php echo $interval_labels[$plan['payout_interval']] ?? ucfirst($plan['payout_interval']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-chevron-right text-muted-custom" style="font-size:16px;"></i>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create/Edit Plan Sheet -->
    <template x-teleport="body">
        <div x-show="sheetOpen"
            x-cloak
            class="sheet-overlay"
            x-transition.opacity
            @click="closeSheet()"></div>
    </template>

    <template x-teleport="body">
        <div class="sheet" :class="{ 'open': sheetOpen }">
            <div class="p-4 border-bottom border-subtle bg-black d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <h5 class="h6 mb-0 text-white" x-text="isEdit ? <?php echo htmlspecialchars(json_encode(__('Edit Plan')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('Create New Plan')), ENT_QUOTES, 'UTF-8'); ?>"></h5>
                        <p class="text-muted-custom small mb-0"><?php echo __('Manage plan settings and payouts'); ?></p>
                    </div>
                </div>
                <button class="btn btn-link text-muted-custom p-0" @click="closeSheet()"><i class="fas fa-times"></i></button>
            </div>

            <form id="planForm" method="POST" action="/admin/actions/plan-create"
                class="d-flex flex-column flex-grow-1 sheet-body" data-no-spinner
                @submit.prevent="(function(e){
                    var form = e.target;
                    form.action = isEdit ? '/admin/actions/plan-update' : '/admin/actions/plan-create';
                    if (isEdit) { form.querySelector('input[name=plan_id]').value = currentPlan?.id; }

                    var payout = currentPlan.payout_interval;
                    var duration = parseInt(currentPlan.duration_days) || 0;

                    if (payout === 'custom') {
                        if (!currentPlan.payout_interval_type) {
                            alert(<?php echo htmlspecialchars(json_encode(__('Interval type is required for custom intervals')), ENT_QUOTES, 'UTF-8'); ?>); return;
                        }
                        var v = parseInt(currentPlan.payout_interval_value);
                        if (!v || v <= 0) {
                            alert(<?php echo htmlspecialchars(json_encode(__('Interval value must be a positive integer')), ENT_QUOTES, 'UTF-8'); ?>); return;
                        }

                        var interval_days = 0;
                        switch (currentPlan.payout_interval_type) {
                            case 'minutes': interval_days = v / 1440; break;
                            case 'hours': interval_days = v / 24; break;
                            case 'days': interval_days = v; break;
                            case 'weeks': interval_days = v * 7; break;
                            case 'months': interval_days = v * 30; break;
                            default: interval_days = v;
                        }
                        if (duration > 0 && interval_days > duration) {
                            alert(<?php echo htmlspecialchars(json_encode(__('Interval cannot exceed plan duration')), ENT_QUOTES, 'UTF-8'); ?>); return;
                        }
                    }

                    if (currentPlan.is_compounding == 1 && currentPlan.payout_interval !== 'end_of_term') {
                        alert(<?php echo htmlspecialchars(json_encode(__('Compounding is only available for end of term plans')), ENT_QUOTES, 'UTF-8'); ?>);
                        currentPlan.is_compounding = 0; return;
                    }

                    if (currentPlan.payout_interval !== 'end_of_term') {
                        currentPlan.is_compounding = 0;
                    }

                    form.submit();
                }).call(this, event)">

                <template x-if="currentPlan">
                    <div class="flex-grow-1 overflow-y-auto p-4 sheet-body" style="padding-bottom: 100px !important;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="plan_id" value="">

                        <!-- Basic Details Section -->
                        <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Basic Details'); ?></h6>
                        <div class="mb-4">
                            <label class="form-label small text-muted-custom fw-bold"><?php echo __('Plan Name'); ?></label>
                            <input type="text" name="name" class="form-control-custom" x-model="currentPlan.name" placeholder="<?php echo __('e.g., Premium Plan'); ?>">
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small text-muted-custom fw-bold"><?php echo __('ROI %'); ?></label>
                                <input type="number" name="roi_percentage" class="form-control-custom" step="0.01" min="0" max="100" x-model="currentPlan.roi_percentage">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted-custom fw-bold"><?php echo __('Duration (days)'); ?></label>
                                <input type="number" name="duration_days" class="form-control-custom" min="1" x-model="currentPlan.duration_days">
                            </div>
                        </div>

                        <!-- Financial Limits Section -->
                        <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Financial Limits'); ?></h6>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small text-muted-custom fw-bold"><?php echo __('Min Amount'); ?></label>
                                <div class="input-group nowrap">
                                    <span class="input-group-text bg-black border-subtle text-muted-custom"><?php echo get_currency_symbol(get_currency_code()); ?></span>
                                    <input type="number" name="min_amount" class="form-control-custom" step="0.01" min="0" x-model="currentPlan.min_amount">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted-custom fw-bold"><?php echo __('Max Amount'); ?></label>
                                <div class="input-group nowrap">
                                    <span class="input-group-text bg-black border-subtle text-muted-custom"><?php echo get_currency_symbol(get_currency_code()); ?></span>
                                    <input type="number" name="max_amount" class="form-control-custom" step="0.01" min="0" x-model="currentPlan.max_amount">
                                </div>
                            </div>
                        </div>

                        <!-- Payout Schedule Section -->
                        <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Payout Schedule'); ?></h6>
                        <div class="mb-4">
                            <label class="form-label small text-muted-custom fw-bold"><?php echo __('Interval'); ?></label>
                            <select name="payout_interval" class="form-select-custom" x-model="currentPlan.payout_interval">
                                <option value="hourly"><?php echo __('Hourly'); ?></option>
                                <option value="daily"><?php echo __('Daily'); ?></option>
                                <option value="end_of_term"><?php echo __('End of Term'); ?></option>
                                <option value="custom"><?php echo __('Custom'); ?></option>
                            </select>
                        </div>

                        <div x-show="currentPlan.payout_interval === 'custom'" class="mb-4">
                            <label class="form-label small text-muted-custom fw-bold mb-2"><?php echo __('Interval Type'); ?></label>
                            <select name="payout_interval_type" class="form-select-custom mb-3" x-model="currentPlan.payout_interval_type">
                                <option value="" selected><?php echo __('Select type'); ?></option>
                                <option value="minutes"><?php echo __('Minutes'); ?></option>
                                <option value="hours"><?php echo __('Hours'); ?></option>
                                <option value="days"><?php echo __('Days'); ?></option>
                                <option value="weeks"><?php echo __('Weeks'); ?></option>
                                <option value="months"><?php echo __('Months'); ?></option>
                            </select>

                            <label class="form-label small text-muted-custom fw-bold"><?php echo __('Interval Value'); ?></label>
                            <input type="number" name="payout_interval_value" class="form-control-custom" min="1" x-model.number="currentPlan.payout_interval_value" placeholder="<?php echo __('e.g., 2'); ?>">
                            <div class="form-text small text-muted-custom mt-2"><?php echo __('Cannot exceed plan duration'); ?></div>
                        </div>

                        <div x-show="currentPlan.payout_interval === 'end_of_term'" class="mb-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border border-subtle rounded bg-black">
                                <div>
                                    <label class="form-label small text-white fw-bold mb-0"><?php echo __('Enable Compounding'); ?></label>
                                    <p class="text-muted-custom small mb-0"><?php echo __('Compound interest calculation'); ?></p>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="is_compounding" value="1" x-model.number="currentPlan.is_compounding">
                                </div>
                            </div>
                        </div>

                        <!-- Waiting Period Section -->
                        <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Waiting Period'); ?></h6>
                        <div class="mb-4">
                            <div class="bg-black border-subtle rounded p-3">
                                <div class="d-flex gap-2 mb-2">
                                    <input type="number" name="waiting_period_value" class="form-control-custom" min="0" placeholder="0" x-model.number="currentPlan.waiting_period_value">
                                    <select name="waiting_period_unit" class="form-select-custom" x-model="currentPlan.waiting_period_unit">
                                        <option value="seconds"><?php echo __('seconds'); ?></option>
                                        <option value="minutes"><?php echo __('minutes'); ?></option>
                                        <option value="hours"><?php echo __('hours'); ?></option>
                                        <option value="days" selected><?php echo __('days'); ?></option>
                                        <option value="weeks"><?php echo __('weeks'); ?></option>
                                    </select>
                                </div>
                                <p class="text-muted-custom small mt-2 mb-0"><?php echo __('⏳ Profits will not be credited until this period elapses after investment start.'); ?></p>
                                <div x-show="currentPlan.waiting_period_value > 0 && waitingExceedsDuration()" class="alert alert-warning small mt-2 mb-0">
                                    <?php echo __('Warning: The waiting period exceeds the plan duration. Investors will never receive a payout.'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Section -->
                        <h6 class="text-muted-custom text-uppercase fw-bold mb-3"><?php echo __('Settings'); ?></h6>

                        <div class="d-flex justify-content-between align-items-center p-3 border border-subtle rounded bg-black mb-3">
                            <div>
                                <label class="form-label small text-white fw-bold mb-0"><?php echo __('Return Capital'); ?></label>
                                <p class="text-muted-custom small mb-0"><?php echo __('At end of investment'); ?></p>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="capital_return" value="1" :checked="currentPlan.capital_return == 1" @change="currentPlan.capital_return = $event.target.checked ? 1 : 0">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-3 border border-subtle rounded bg-black mb-3">
                            <div>
                                <label class="form-label small text-white fw-bold mb-0"><?php echo __('Featured Plan'); ?></label>
                                <p class="text-muted-custom small mb-0"><?php echo __('Highlight on homepage'); ?></p>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="is_featured" value="1" :checked="currentPlan.is_featured == 1" @change="currentPlan.is_featured = $event.target.checked ? 1 : 0">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small text-muted-custom fw-bold"><?php echo __('Status'); ?></label>
                            <select name="status" class="form-select-custom" x-model="currentPlan.status">
                                <option value="active"><?php echo __('Active'); ?></option>
                                <option value="archived"><?php echo __('Archived'); ?></option>
                            </select>
                        </div>
                    </div>
                </template>

                <div class="p-4 border-top border-subtle bg-black d-flex gap-3" style="position: absolute; bottom: 0; width: 100%;">
                    <button type="button" class="btn btn-outline-secondary border-subtle text-white flex-fill" @click="closeSheet()"><?php echo __('Cancel'); ?></button>
                    <button type="submit" class="btn btn-primary flex-fill"><?php echo __('Save Plan'); ?></button>
                    <button type="button" class="btn btn-outline-danger border-danger text-danger flex-fill" @click="if (confirm(<?php echo htmlspecialchars(json_encode(__('Are you sure you want to delete this plan?')), ENT_QUOTES, 'UTF-8'); ?>)) { document.getElementById('deleteForm').submit(); }" x-show="isEdit"><?php echo __('Delete'); ?></button>
                </div>
            </form>

            <!-- Delete Form (submit from sheet footer delete button) -->
            <form id="deleteForm" action="/admin/actions/plan-delete" method="POST" style="display:none">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="plan_id" value="">
            </form>
        </div>
    </template>
</div>

<?php require_once ROOT . '/includes/admin-footer.php'; ?>