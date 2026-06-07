<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Investment Market');
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();

$plan_id = isset($_GET['plan']) ? intval($_GET['plan']) : null;

// Get user's country for filtering plans
$user_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0]['country'] ?? null;

$plan = $plan_id ? db_query("SELECT * FROM investment_plans WHERE id = ? AND status = 'active' AND (country IS NULL OR country = '' OR country = ?)", [$plan_id, $user_country])[0] ?? null : null;
$plans = db_query("SELECT * FROM investment_plans WHERE status = 'active' AND (country IS NULL OR country = '' OR country = ?) ORDER BY min_amount ASC", [$user_country]);
$available = get_available_balance($user_id);

// Get user's balance
$user_balance = get_user_balance($user_id);

// Determine which plan is featured (first one or the one with highest ROI)
$featured_plan_id = null;
if (!empty($plans)) {
    // Find plan with highest ROI that's marked as featured, or just the middle one
    foreach ($plans as $p) {
        if ($p['is_featured'] ?? false) {
            $featured_plan_id = $p['id'];
            break;
        }
    }
    // If no featured plan, pick the middle one
    if (!$featured_plan_id) {
        $middle_index = floor(count($plans) / 2);
        $featured_plan_id = $plans[$middle_index]['id'] ?? $plans[0]['id'];
    }
}

// Default calculator values - use featured plan
$default_plan = null;
foreach ($plans as $p) {
    if ($p['id'] == $featured_plan_id) {
        $default_plan = $p;
        break;
    }
}
if (!$default_plan && !empty($plans)) {
    $default_plan = $plans[0];
}

$default_amount = $default_plan ? (float)$default_plan['min_amount'] : 1000;
$default_roi = $default_plan ? (float)$default_plan['roi_percentage'] / 100 : 0.05;
$default_duration = $default_plan ? (int)$default_plan['duration_days'] : 30;
$default_plan_name = $default_plan ? $default_plan['name'] : __('Select a plan');

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Investment Market'); ?></h3>
        <p class="text-secondary mb-0 small"><?php echo __('Choose your strategy and start earning'); ?></p>
    </div>
    <div>
        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
        </button>
    </div>
</div>

<?php if (empty($plans)): ?>
    <!-- No Plans Available State -->
    <div class="card border-0 shadow-sm mb-5 overflow-hidden" style="border-radius: 1.25rem">
        <div class="p-5 text-center">
            <div class="bg-light rounded-circle p-4 mb-3 d-inline-flex">
                <i class="fas fa-chart-pie fa-3x text-secondary opacity-25"></i>
            </div>
            <h5 class="fw-bold text-dark mb-2"><?php echo __('No Investment Plans Available'); ?></h5>
            <p class="text-muted mb-0"><?php echo __('Please check back later for new investment opportunities.'); ?></p>
        </div>
    </div>
<?php else: ?>
    <div x-data="{
        calculatorAmount: <?php echo $default_amount; ?>,
        selectedRoi: <?php echo $default_roi; ?>,
        selectedDuration: <?php echo $default_duration; ?>,
        selectedProfitDuration: <?php echo $default_duration; ?>,
        planName: <?php echo htmlspecialchars(json_encode($default_plan_name), ENT_QUOTES, 'UTF-8'); ?>,
        selectedPlanId: <?php echo $default_plan ? $default_plan['id'] : 'null'; ?>,
        selectedPayoutInterval: '<?php echo $default_plan ? $default_plan['payout_interval'] : 'daily'; ?>',
        selectedIntervalType: '<?php echo $default_plan ? ($default_plan['payout_interval_type'] ?? 'days') : 'days'; ?>',
        selectedIntervalValue: <?php echo $default_plan && isset($default_plan['payout_interval_value']) ? intval($default_plan['payout_interval_value']) : 1; ?>,
        selectedWaitingPeriodValue: <?php echo $default_plan && isset($default_plan['waiting_period_value']) ? intval($default_plan['waiting_period_value']) : 0; ?>,
        selectedWaitingPeriodUnit: '<?php echo $default_plan ? ($default_plan['waiting_period_unit'] ?? 'days') : 'days'; ?>',
        availableBalance: <?php echo $available; ?>,
        currencySymbol: '<?php echo $currency_symbol; ?>',

        // Calculate number of payout intervals within the duration
        calculateTotalIntervals() {
            const durationSeconds = this.selectedProfitDuration * 86400;
            let intervalSeconds = 86400; // default to daily

            if (this.selectedPayoutInterval === 'end_of_term') {
                // Single payout irrespective of interval length
                return 1;
            }

            if (this.selectedPayoutInterval === 'hourly') {
                intervalSeconds = 3600;
            } else if (this.selectedPayoutInterval === 'custom') {
                // Validate interval value to avoid NaN/Infinity and zero-division
                const iv = Number(this.selectedIntervalValue);
                if (!isFinite(iv) || iv <= 0) {
                    return 0;
                }

                switch (this.selectedIntervalType) {
                    case 'minutes': intervalSeconds = iv * 60; break;
                    case 'hours': intervalSeconds = iv * 3600; break;
                    case 'days': intervalSeconds = iv * 86400; break;
                    case 'weeks': intervalSeconds = iv * 604800; break;
                    case 'months': intervalSeconds = iv * 2592000; break;
                    default: intervalSeconds = iv * 86400; break;
                }
            }

            // Guard against zero or invalid intervalSeconds
            if (!isFinite(intervalSeconds) || intervalSeconds <= 0) {
                return 0;
            }

            // Use floor so intervals shorter than the plan duration yield 0 payouts
            const intervals = Math.floor(durationSeconds / intervalSeconds);
            return intervals;
        },

        calculateProfit() {
            const totalIntervals = this.calculateTotalIntervals();
            const profitPerInterval = this.calculatorAmount * this.selectedRoi;
            return (profitPerInterval * totalIntervals).toFixed(2);
        }
    }">
        <!-- Profit Estimator Card -->
        <div class="card border-0 shadow-sm mb-5 overflow-hidden" style="border-radius: 1.25rem">
            <div class="row g-0">
                <div class="col-lg-8 p-4 bg-white">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h5 class="fw-bold m-0"><?php echo __('Profit Estimator'); ?></h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold mb-1"><?php echo __('Selected Plan'); ?></label>
                            <input type="text" class="form-control bg-light border-0 fw-bold text-primary" x-model="planName" readonly />
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold mb-1"><?php echo __('Investment Amount'); ?> (<?php echo e($currency_symbol); ?>)</label>
                            <input type="number" class="form-control bg-light border-0 fw-bold" x-model="calculatorAmount" min="0" :max="availableBalance" />
                        </div>
                    </div>
                    <div class="mt-3 small text-muted">
                        <?php echo __('Available Balance'); ?>: <strong x-text="formatCurrency(<?php echo $available; ?>)"><?php echo format_money($available); ?></strong>
                    </div>
                </div>
                <!-- GRADIENT Projected Profit Section -->
                <div class="col-lg-4 text-white p-4 d-flex flex-column justify-content-center position-relative overflow-hidden" style="background: var(--gradient-card);">
                    <div class="position-absolute top-0 end-0 bg-white opacity-10 rounded-circle" style="width: 150px; height: 150px; transform: translate(30%, -30%)"></div>

                    <p class="text-white-50 small text-uppercase fw-bold mb-1"><?php echo __('Projected Net Profit'); ?></p>
                    <h2 class="fw-bold mb-0 display-5"><span x-text="formatCurrency(parseFloat(calculateProfit()))"></span></h2>
                    <small class="opacity-75 mt-1"><i class="fas fa-info-circle me-1"></i> <?php echo __('Based on'); ?> <span x-text="(selectedRoi * 100).toFixed(0) + '%'"></span> <?php echo __('ROI'); ?><span x-show="selectedWaitingPeriodValue > 0">, <span x-text="selectedProfitDuration + ' ' + '<?php echo __('day profit period'); ?>'"></span></span></small>
                </div>
            </div>
        </div>

        <!-- Available Plans Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><?php echo __('Available Plans'); ?></h5>
            <div class="d-lg-none text-secondary small"><i class="fas fa-arrow-right me-1"></i> <?php echo __('Swipe'); ?></div>
        </div>

        <div class="plans-scroll-container pb-3">
            <?php foreach ($plans as $p):
                $is_featured = ($p['id'] == $featured_plan_id);
                $icon_class = 'fa-seedling';
                $icon_bg = 'bg-secondary';

                // Calculate total display days including waiting period
                $waiting_period_value = isset($p['waiting_period_value']) ? intval($p['waiting_period_value']) : 0;
                $waiting_period_unit = $p['waiting_period_unit'] ?? 'days';
                $waiting_display_days = 0;
                if ($waiting_period_value > 0) {
                    // Convert waiting period to seconds, then ceiling to days
                    $waiting_seconds = 0;
                    switch ($waiting_period_unit) {
                        case 'seconds':
                            $waiting_seconds = $waiting_period_value;
                            break;
                        case 'minutes':
                            $waiting_seconds = $waiting_period_value * 60;
                            break;
                        case 'hours':
                            $waiting_seconds = $waiting_period_value * 3600;
                            break;
                        case 'days':
                            $waiting_seconds = $waiting_period_value * 86400;
                            break;
                        case 'weeks':
                            $waiting_seconds = $waiting_period_value * 604800;
                            break;
                    }
                    $waiting_display_days = ceil($waiting_seconds / 86400);
                }
                $total_display_days = $p['duration_days'] + $waiting_display_days;

                if ($p['roi_percentage'] >= 8) {
                    $icon_class = 'fa-crown';
                    $icon_bg = 'bg-warning';
                } elseif ($p['roi_percentage'] >= 5) {
                    $icon_class = 'fa-rocket';
                    $icon_bg = 'bg-primary';
                } else {
                    $icon_class = 'fa-seedling';
                    $icon_bg = 'bg-secondary';
                }

                // Build the click handler exactly like the design example (including interval info)
                $click_handler = sprintf(
                    "planName=%s; selectedRoi=%f; selectedDuration=%d; selectedProfitDuration=%d; calculatorAmount=%.2f; selectedPlanId=%d; selectedPayoutInterval=%s; selectedIntervalType=%s; selectedIntervalValue=%d; selectedWaitingPeriodValue=%d; selectedWaitingPeriodUnit=%s",
                    json_encode($p['name']),
                    $p['roi_percentage'] / 100,
                    $p['duration_days'],
                    $p['duration_days'],
                    $p['min_amount'],
                    $p['id'],
                    json_encode($p['payout_interval'] ?? 'daily'),
                    json_encode($p['payout_interval_type'] ?? 'days'),
                    isset($p['payout_interval_value']) ? intval($p['payout_interval_value']) : 1,
                    $waiting_period_value,
                    $waiting_period_unit
                );
            ?>
                <div class="card-plan <?php echo $is_featured ? 'popular' : ''; ?>"
                    @click="<?php echo htmlspecialchars($click_handler, ENT_QUOTES, 'UTF-8'); ?>"
                    :class="{ 'border-primary border-2': selectedPlanId === <?php echo $p['id']; ?> }">

                    <?php if ($is_featured): ?>
                        <div class="position-absolute top-0 start-50 translate-middle badge bg-primary rounded-pill px-3 py-2 shadow-sm"><?php echo __('MOST POPULAR'); ?></div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-start mb-3 <?php echo $is_featured ? 'mt-2' : ''; ?>">
                        <div class="<?php echo $icon_bg; ?> bg-opacity-10 text-<?php echo $icon_bg === 'bg-warning' ? 'warning' : ($icon_bg === 'bg-primary' ? 'primary' : 'secondary'); ?> rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px">
                            <i class="fas <?php echo $icon_class; ?> fa-lg"></i>
                        </div>
                        <?php
                        $payout_label = __('Daily');
                        $interval_type = $p['payout_interval_type'] ?? 'days';
                        $interval_value = isset($p['payout_interval_value']) ? intval($p['payout_interval_value']) : null;

                        if (($p['payout_interval'] ?? '') === 'hourly') {
                            $payout_label = __('Hourly');
                        } elseif (($p['payout_interval'] ?? '') === 'end_of_term') {
                            $payout_label = __('End of Term');
                        } elseif (($p['payout_interval'] ?? '') === 'custom' && $interval_value) {
                            // Use helper function for custom intervals
                            $payout_label = format_payout_interval($interval_type, $interval_value);
                        }
                        ?>
                        <span class="roi-badge <?php echo $is_featured ? 'bg-primary text-white' : 'bg-light text-dark'; ?>">
                            <?php
                            if (($p['payout_interval'] ?? '') === 'custom' && $interval_value) {
                                echo format_payout_interval($interval_type, $interval_value, round($p['roi_percentage'], 2), true);
                            } else {
                                echo e(round($p['roi_percentage'], 2)) . '% ' . $payout_label;
                            }
                            ?>
                        </span>
                    </div>

                    <h4 class="fw-bold mb-1"><?php echo e($p['name']); ?></h4>
                    <p class="text-secondary small mb-4"><?php echo e($p['description'] ?? __('Investment plan with guaranteed returns')); ?></p>

                    <div class="border-top border-bottom py-3 mb-4">
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted"><?php echo __('Min Deposit'); ?></span>
                            <span class="fw-bold <?php echo $is_featured ? 'text-primary' : ''; ?>" x-text="formatCurrency(<?php echo $p['min_amount']; ?>)"><?php echo format_money($p['min_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted"><?php echo __('Duration'); ?></span>
                            <div>
                                <span class="fw-bold"><?php echo e($total_display_days); ?> <?php echo __('Days'); ?></span>
                            </div>
                        </div>
                        <?php if ($p['return_capital'] ?? false): ?>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted"><?php echo __('Capital Return'); ?></span>
                                <span class="fw-bold text-success"><?php echo __('Yes'); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted"><?php echo __('Compounding'); ?></span>
                                <span class="fw-bold text-success"><i class="fas fa-check"></i> <?php echo __('Active'); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($waiting_period_value > 0): ?>
                            <div class="fw-bold" style="color:#92400e;font-size:0.75rem;margin:6px 0px" class="mb-2">
                                ⏳ <?php echo __('Includes'); ?> <?php echo e($waiting_period_value); ?> <?php echo __($waiting_period_unit); ?> <?php echo __('waiting period before profits begin'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button class="btn <?php echo $is_featured ? 'btn-primary shadow-sm' : 'btn-outline-dark'; ?> w-100 rounded-pill fw-bold"
                        @click="
                            selectedRoi = <?php echo $p['roi_percentage']; ?> / 100;
                            selectedDuration = <?php echo $total_display_days; ?>;
                            selectedProfitDuration = <?php echo $p['duration_days']; ?>;
                            planName = <?php echo htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8'); ?>;
                            selectedPlanId = <?php echo $p['id']; ?>;
                            selectedPayoutInterval = '<?php echo $p['payout_interval']; ?>';
                            selectedIntervalType = '<?php echo $p['payout_interval_type'] ?? 'days'; ?>';
                            selectedIntervalValue = <?php echo isset($p['payout_interval_value']) ? intval($p['payout_interval_value']) : 1; ?>;
                            selectedWaitingPeriodValue = <?php echo $waiting_period_value; ?>;
                            selectedWaitingPeriodUnit = '<?php echo $waiting_period_unit; ?>';
                        "
                        data-bs-toggle="modal"
                        data-bs-target="#investModal">
                        <?php echo $is_featured ? __('Invest Now') : __('Choose Plan'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Investment Confirmation Modal -->
        <div class="modal fade" id="investModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-light border-0">
                        <h5 class="modal-title fw-bold"><?php echo __('Confirm Investment'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form x-data="{ loading: false }" @submit="loading = true" action="/actions/invest-submit.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="plan_id" x-model="selectedPlanId">

                        <div class="modal-body p-4">
                            <div class="text-center mb-4">
                                <h2 class="fw-bold text-primary mb-0" x-text="formatCurrency(parseFloat(calculatorAmount || 0))"></h2>
                                <p class="text-muted small"><?php echo __('Investment Amount'); ?></p>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1"><?php echo __('Plan'); ?></label>
                                <input type="text" class="form-control bg-light border-0" x-model="planName" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1"><?php echo __('Payout Schedule'); ?></label>
                                <input type="text" class="form-control bg-light border-0"
                                    x-bind:value="(() => {
                                        if (selectedPayoutInterval === 'hourly') return <?php echo htmlspecialchars(json_encode(__('Hourly')), ENT_QUOTES, 'UTF-8'); ?>;
                                        if (selectedPayoutInterval === 'daily') return <?php echo htmlspecialchars(json_encode(__('Daily')), ENT_QUOTES, 'UTF-8'); ?>;
                                        if (selectedPayoutInterval === 'end_of_term') return <?php echo htmlspecialchars(json_encode(__('End of Term')), ENT_QUOTES, 'UTF-8'); ?>;
                                        if (selectedPayoutInterval === 'custom') {
                                            const units = {
                                                'minutes': selectedIntervalValue === 1 ? <?php echo htmlspecialchars(json_encode(__('minute')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('minutes')), ENT_QUOTES, 'UTF-8'); ?>,
                                                'hours': selectedIntervalValue === 1 ? <?php echo htmlspecialchars(json_encode(__('hour')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('hours')), ENT_QUOTES, 'UTF-8'); ?>,
                                                'days': selectedIntervalValue === 1 ? <?php echo htmlspecialchars(json_encode(__('day')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('days')), ENT_QUOTES, 'UTF-8'); ?>,
                                                'weeks': selectedIntervalValue === 1 ? <?php echo htmlspecialchars(json_encode(__('week')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('weeks')), ENT_QUOTES, 'UTF-8'); ?>,
                                                'months': selectedIntervalValue === 1 ? <?php echo htmlspecialchars(json_encode(__('month')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('months')), ENT_QUOTES, 'UTF-8'); ?>
                                            };
                                            return <?php echo htmlspecialchars(json_encode(__('Every')), ENT_QUOTES, 'UTF-8'); ?> + ' ' + selectedIntervalValue + ' ' + units[selectedIntervalType];
                                        }
                                        return <?php echo htmlspecialchars(json_encode(__('Daily')), ENT_QUOTES, 'UTF-8'); ?>;
                                    })()"
                                    readonly>
                            </div>

                            <div class="mb-3" x-show="selectedWaitingPeriodValue > 0">
                                <label class="small fw-bold text-muted mb-1"><?php echo __('Waiting Period'); ?></label>
                                <input type="text" class="form-control bg-light border-0"
                                    x-bind:value="(() => {
                                        const units = {
                                            'seconds': selectedWaitingPeriodValue === 1 ? <?php echo htmlspecialchars(json_encode(__('second')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('seconds')), ENT_QUOTES, 'UTF-8'); ?>,
                                            'minutes': selectedWaitingPeriodValue === 1 ? <?php echo htmlspecialchars(json_encode(__('minute')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('minutes')), ENT_QUOTES, 'UTF-8'); ?>,
                                            'hours': selectedWaitingPeriodValue === 1 ? <?php echo htmlspecialchars(json_encode(__('hour')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('hours')), ENT_QUOTES, 'UTF-8'); ?>,
                                            'days': selectedWaitingPeriodValue === 1 ? <?php echo htmlspecialchars(json_encode(__('day')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('days')), ENT_QUOTES, 'UTF-8'); ?>,
                                            'weeks': selectedWaitingPeriodValue === 1 ? <?php echo htmlspecialchars(json_encode(__('week')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('weeks')), ENT_QUOTES, 'UTF-8'); ?>
                                        };
                                        return selectedWaitingPeriodValue + ' ' + units[selectedWaitingPeriodUnit];
                                    })()"
                                    readonly>
                                <small class="text-muted"><?php echo __('⏳ Profits will not be credited until this period elapses after investment start.'); ?></small>
                            </div>

                            <div class="mb-3" x-show="selectedWaitingPeriodValue > 0">
                                <label class="small fw-bold text-muted mb-1"><?php echo __('Profit Period'); ?></label>
                                <input type="text" class="form-control bg-light border-0"
                                    x-bind:value="selectedProfitDuration + ' ' + (selectedProfitDuration === 1 ? <?php echo htmlspecialchars(json_encode(__('day')), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars(json_encode(__('days')), ENT_QUOTES, 'UTF-8'); ?>)"
                                    readonly>
                                <small class="text-muted"><?php echo __('💰 Profits are calculated based on this period only.'); ?></small>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1"><?php echo __('Amount to Invest'); ?></label>
                                <input type="number" name="amount" class="form-control form-control-lg bg-light border-0 fw-bold"
                                    x-model="calculatorAmount"
                                    min="0"
                                    max="<?php echo $available; ?>"
                                    required>
                                <small class="text-muted"><?php echo __('Available Balance'); ?>: <span x-text="formatCurrency(<?php echo $available; ?>)"><?php echo format_money($available); ?></span></small>
                            </div>

                            <div class="mb-3 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-muted"><?php echo __('Total Payouts'); ?></span>
                                    <span class="fw-bold" x-text="calculateTotalIntervals()"></span>
                                </div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted"><?php echo __('Projected Profit'); ?></span>
                                    <span class="fw-bold text-success" x-text="formatCurrency(parseFloat(calculateProfit()) || 0)"></span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted"><?php echo __('Total Return'); ?></span>
                                    <span class="fw-bold text-primary" x-text="formatCurrency((parseFloat(calculatorAmount || 0)) + (parseFloat(calculateProfit()) || 0))"></span>
                                </div>
                            </div>

                            <button type="submit" :disabled="loading" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold shadow">
                                <span x-show="!loading"><?php echo __('Confirm Investment'); ?></span>
                                <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Plan cards specific styles */
    .plans-scroll-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }

    @media (max-width: 991px) {
        .plans-scroll-container {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-top: 1.5rem;
            padding-bottom: 2rem;
            margin-right: -1rem;
            padding-right: 1rem;
        }

        .plans-scroll-container .card-plan {
            scroll-snap-align: center;
            min-width: 85vw;
            flex-shrink: 0;
        }

        .plans-scroll-container .card-plan.popular {
            transform: none;
        }
    }

    .roi-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
</style>

<?php require ROOT . '/includes/footer.php'; ?>