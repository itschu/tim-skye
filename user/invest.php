<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/currency-conversion.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Investment Market');
$active_nav = 'invest';
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();

$plan_id = isset($_GET['plan']) ? intval($_GET['plan']) : null;

// Get user's country for filtering plans
$user_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0]['country'] ?? null;

// Local currency info for frontend conversion
$local_currency_code = null;
$exchange_rate = null;
if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code) {
        $exchange_rate = get_rate_for_currency_raw($local_currency_code);
    }
}
$local_currency_symbol = $local_currency_code ? get_currency_symbol($local_currency_code) : null;

$plan = $plan_id ? db_query("SELECT * FROM investment_plans WHERE id = ? AND status = 'active' AND (country IS NULL OR country = '' OR country = ?)", [$plan_id, $user_country])[0] ?? null : null;
$plans = db_query("SELECT * FROM investment_plans WHERE status = 'active' AND (country IS NULL OR country = '' OR country = ?) ORDER BY min_amount ASC", [$user_country]);
$available = get_available_balance($user_id);

// Get user's balance
$user_balance = get_user_balance($user_id);

// Determine which plan is featured (first one or the one with highest ROI)
$featured_plan_id = null;
if (!empty($plans)) {
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

// Default calculator values - use featured plan (or preselected plan)
$default_plan = null;
$initial_plan_id = $featured_plan_id;
if ($plan_id && $plan) {
    $initial_plan_id = $plan['id'];
}
foreach ($plans as $p) {
    if ($p['id'] == $initial_plan_id) {
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

// Build clean plan metadata for the frontend
$plans_js = [];
foreach ($plans as $p) {
    $waiting_period_value = isset($p['waiting_period_value']) ? intval($p['waiting_period_value']) : 0;
    $waiting_period_unit = $p['waiting_period_unit'] ?? 'days';
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
    $waiting_display_days = $waiting_period_value > 0 ? ceil($waiting_seconds / 86400) : 0;
    $total_display_days = (int)$p['duration_days'] + $waiting_display_days;

    if ($p['roi_percentage'] >= 8) {
        $icon_class = 'fa-crown';
        $icon_color = 'amber';
    } elseif ($p['roi_percentage'] >= 5) {
        $icon_class = 'fa-rocket';
        $icon_color = 'indigo';
    } else {
        $icon_class = 'fa-seedling';
        $icon_color = 'emerald';
    }

    $interval_type = $p['payout_interval_type'] ?? 'days';
    $interval_value = isset($p['payout_interval_value']) ? intval($p['payout_interval_value']) : null;

    $payout_label = __('Daily');
    if (($p['payout_interval'] ?? '') === 'hourly') {
        $payout_label = __('Hourly');
    } elseif (($p['payout_interval'] ?? '') === 'end_of_term') {
        $payout_label = __('End of Term');
    } elseif (($p['payout_interval'] ?? '') === 'custom' && $interval_value) {
        $payout_label = format_payout_interval($interval_type, $interval_value);
    }

    if (($p['payout_interval'] ?? '') === 'custom' && $interval_value) {
        $roi_badge_text = format_payout_interval($interval_type, $interval_value, round($p['roi_percentage'], 2), true);
    } else {
        $roi_badge_text = e(round($p['roi_percentage'], 2)) . '% ' . $payout_label;
    }

    $plans_js[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'description' => $p['description'] ?? __('Investment plan with guaranteed returns'),
        'min_amount' => (float)$p['min_amount'],
        'roi_percentage' => (float)$p['roi_percentage'],
        'duration_days' => (int)$p['duration_days'],
        'total_display_days' => $total_display_days,
        'payout_interval' => $p['payout_interval'] ?? 'daily',
        'payout_interval_type' => $interval_type,
        'payout_interval_value' => $interval_value ?: 1,
        'waiting_period_value' => $waiting_period_value,
        'waiting_period_unit' => $waiting_period_unit,
        'waiting_display_days' => $waiting_display_days,
        'return_capital' => (bool)($p['return_capital'] ?? false),
        'is_featured' => ((int)$p['id'] === (int)$featured_plan_id),
        'icon_class' => $icon_class,
        'icon_color' => $icon_color,
        'roi_badge_text' => $roi_badge_text,
    ];
}

$initial_plan = null;
foreach ($plans_js as $pj) {
    if ($pj['id'] == $initial_plan_id) {
        $initial_plan = $pj;
        break;
    }
}
if (!$initial_plan && !empty($plans_js)) {
    $initial_plan = $plans_js[0];
}

ob_start();
?>
<style>
    input[type='number']::-webkit-inner-spin-button,
    input[type='number']::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .plans-scroll-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
    }

    @media (max-width: 1024px) {
        .plans-scroll-container {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-top: 1.5rem;
            padding-bottom: 2rem;
        }

        .plans-scroll-container .plan-card {
            scroll-snap-align: center;
            min-width: 85vw;
            flex-shrink: 0;
        }
    }

    @media (max-width: 640px) {
        .plans-scroll-container .plan-card {
            min-width: 82vw;
        }
    }

    .roi-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>
<script>
    window.investData = {
        plans: <?php echo json_encode($plans_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        initialPlanId: <?php echo json_encode($initial_plan ? $initial_plan['id'] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        availableBalance: <?php echo json_encode((float)$available, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        currencySymbol: <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        localCurrencySymbol: <?php echo json_encode($local_currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        localRate: <?php echo json_encode((float)($exchange_rate ?: 1), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        localCurrencyCode: <?php echo json_encode($local_currency_code, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    };
</script>
<?php
$extra_css = ob_get_clean();

ob_start();
?>
<script>
    function investmentCalc(data) {
        const initialPlan = data.plans.find(function(p) {
            return p.id === data.initialPlanId;
        }) || data.plans[0] || {};
        return {
            plans: data.plans,
            calculatorAmount: parseFloat(initialPlan.min_amount || 0),
            usdAmount: parseFloat(initialPlan.min_amount || 0),
            selectedRoi: (parseFloat(initialPlan.roi_percentage || 0) / 100),
            selectedDuration: parseInt(initialPlan.total_display_days || initialPlan.duration_days || 30),
            selectedProfitDuration: parseInt(initialPlan.duration_days || 30),
            planName: initialPlan.name || <?php echo json_encode(__('Select a plan')); ?>,
            selectedPlanId: initialPlan.id || null,
            selectedPlanMin: parseFloat(initialPlan.min_amount || 0),
            selectedPlanMax: parseFloat(initialPlan.max_amount || 0),
            selectedPayoutInterval: initialPlan.payout_interval || 'daily',
            selectedIntervalType: initialPlan.payout_interval_type || 'days',
            selectedIntervalValue: parseInt(initialPlan.payout_interval_value || 1),
            selectedWaitingPeriodValue: parseInt(initialPlan.waiting_period_value || 0),
            selectedWaitingPeriodUnit: initialPlan.waiting_period_unit || 'days',
            availableBalance: data.availableBalance,
            currencySymbol: data.currencySymbol,
            localCurrencySymbol: data.localCurrencySymbol,
            localRate: data.localRate,
            localCurrencyCode: data.localCurrencyCode,
            isLocalCurrency: false,
            showModal: false,
            _suppressWatcher: false,
            _lastCurrency: '',

            init() {
                this._lastCurrency = this.getRootCurrency();
                this.syncFromRootCurrency();
                setInterval(() => {
                    const current = this.getRootCurrency();
                    if (current !== this._lastCurrency) {
                        this._lastCurrency = current;
                        this.convertOnCurrencyToggle();
                    }
                }, 500);

                this.$watch('calculatorAmount', (value) => {
                    if (this._suppressWatcher) return;
                    const num = parseFloat(value);
                    if (isNaN(num)) {
                        this.usdAmount = 0;
                        return;
                    }
                    if (this.isLocalCurrency && this.localRate) {
                        this.usdAmount = parseFloat((num / this.localRate).toFixed(15));
                    } else {
                        this.usdAmount = num;
                    }
                });
            },

            getRootCurrency() {
                const rootEl = document.getElementById('app-root');
                const root = rootEl && rootEl._x_dataStack ? rootEl._x_dataStack[0] : null;
                return root ? root.currency : <?php echo json_encode(e(get_currency_code()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            },

            syncFromRootCurrency() {
                const rootEl = document.getElementById('app-root');
                const root = rootEl && rootEl._x_dataStack ? rootEl._x_dataStack[0] : null;
                if (root && this.localCurrencyCode && root.currency === this.localCurrencyCode) {
                    this.isLocalCurrency = true;
                    this._suppressWatcher = true;
                    this.calculatorAmount = parseFloat((this.usdAmount * this.localRate).toFixed(2));
                    this.$nextTick(() => {
                        this._suppressWatcher = false;
                    });
                }
            },

            convertOnCurrencyToggle() {
                const rootEl = document.getElementById('app-root');
                const root = rootEl && rootEl._x_dataStack ? rootEl._x_dataStack[0] : null;
                const nowLocal = root && root.currency === this.localCurrencyCode;
                const wasLocal = this.isLocalCurrency;
                this.isLocalCurrency = nowLocal;
                this._suppressWatcher = true;
                if (nowLocal && !wasLocal) {
                    this.calculatorAmount = parseFloat((this.usdAmount * this.localRate).toFixed(2));
                } else if (!nowLocal && wasLocal) {
                    this.calculatorAmount = this.usdAmount;
                }
                this.$nextTick(() => {
                    this._suppressWatcher = false;
                });
            },

            get maxAmount() {
                if (this.isLocalCurrency && this.localRate) {
                    return parseFloat((this.availableBalance * this.localRate).toFixed(2));
                }
                return this.availableBalance;
            },

            get activeSymbol() {
                return this.isLocalCurrency ? (this.localCurrencySymbol || this.currencySymbol) : this.currencySymbol;
            },

            setMax() {
                this.calculatorAmount = this.maxAmount;
            },

            setPlan(plan) {
                this.planName = plan.name;
                this.selectedRoi = parseFloat(plan.roi_percentage) / 100;
                this.selectedDuration = parseInt(plan.total_display_days || plan.duration_days);
                this.selectedProfitDuration = parseInt(plan.duration_days);
                this.selectedPlanId = plan.id;
                this.selectedPlanMin = parseFloat(plan.min_amount || 0);
                this.selectedPlanMax = parseFloat(plan.max_amount || 0);
                this.selectedPayoutInterval = plan.payout_interval;
                this.selectedIntervalType = plan.payout_interval_type;
                this.selectedIntervalValue = parseInt(plan.payout_interval_value || 1);
                this.selectedWaitingPeriodValue = parseInt(plan.waiting_period_value || 0);
                this.selectedWaitingPeriodUnit = plan.waiting_period_unit || 'days';
                this.setPlanAmount(parseFloat(plan.min_amount));
            },

            setPlanAmount(rawUsd) {
                const usd = parseFloat(rawUsd) || 0;
                this.usdAmount = usd;
                this._suppressWatcher = true;
                if (this.isLocalCurrency && this.localRate) {
                    this.calculatorAmount = parseFloat((usd * this.localRate).toFixed(2));
                } else {
                    this.calculatorAmount = usd;
                }
                this.$nextTick(() => {
                    this._suppressWatcher = false;
                });
            },

            get selectedPlan() {
                return this.plans.find(function(p) { return p.id === this.selectedPlanId; }.bind(this)) || {};
            },

            get amountValidation() {
                const input = parseFloat(this.calculatorAmount) || 0;
                const usdAmount = parseFloat(this.usdAmount) || 0;
                const min = parseFloat(this.selectedPlanMin) || 0;
                const max = parseFloat(this.selectedPlanMax) || 0;
                const balance = parseFloat(this.availableBalance) || 0;

                if (input <= 0) {
                    return { valid: false, message: <?php echo json_encode(__('Enter an amount to continue'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> };
                }
                if (min > 0 && usdAmount < min) {
                    return { valid: false, message: <?php echo json_encode(sprintf(__('Minimum investment for this plan is %s'), '%s'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('%s', this.formatCurrency(min)) };
                }
                if (max > 0 && usdAmount > max) {
                    return { valid: false, message: <?php echo json_encode(sprintf(__('Maximum investment for this plan is %s'), '%s'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('%s', this.formatCurrency(max)) };
                }
                if (usdAmount > balance) {
                    return { valid: false, message: <?php echo json_encode(__('Amount exceeds your available balance'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> };
                }
                return { valid: true, message: '' };
            },

            calculateTotalIntervals() {
                const durationSeconds = this.selectedProfitDuration * 86400;
                let intervalSeconds = 86400;

                if (this.selectedPayoutInterval === 'end_of_term') return 1;

                if (this.selectedPayoutInterval === 'hourly') {
                    intervalSeconds = 3600;
                } else if (this.selectedPayoutInterval === 'custom') {
                    const iv = Number(this.selectedIntervalValue);
                    if (!isFinite(iv) || iv <= 0) return 0;
                    switch (this.selectedIntervalType) {
                        case 'minutes':
                            intervalSeconds = iv * 60;
                            break;
                        case 'hours':
                            intervalSeconds = iv * 3600;
                            break;
                        case 'days':
                            intervalSeconds = iv * 86400;
                            break;
                        case 'weeks':
                            intervalSeconds = iv * 604800;
                            break;
                        case 'months':
                            intervalSeconds = iv * 2592000;
                            break;
                        default:
                            intervalSeconds = iv * 86400;
                            break;
                    }
                }

                if (!isFinite(intervalSeconds) || intervalSeconds <= 0) return 0;
                return Math.floor(durationSeconds / intervalSeconds);
            },

            calculateProfit() {
                const totalIntervals = this.calculateTotalIntervals();
                const profitPerInterval = this.usdAmount * this.selectedRoi;
                return profitPerInterval * totalIntervals;
            },

            payoutScheduleLabel() {
                const pi = this.selectedPayoutInterval;
                const iv = this.selectedIntervalValue;
                const it = this.selectedIntervalType;
                if (pi === 'hourly') return <?php echo json_encode(__('Hourly')); ?>;
                if (pi === 'daily') return <?php echo json_encode(__('Daily')); ?>;
                if (pi === 'end_of_term') return <?php echo json_encode(__('End of Term')); ?>;
                if (pi === 'custom') {
                    const unitMap = {
                        minutes: iv === 1 ? <?php echo json_encode(__('minute')); ?> : <?php echo json_encode(__('minutes')); ?>,
                        hours: iv === 1 ? <?php echo json_encode(__('hour')); ?> : <?php echo json_encode(__('hours')); ?>,
                        days: iv === 1 ? <?php echo json_encode(__('day')); ?> : <?php echo json_encode(__('days')); ?>,
                        weeks: iv === 1 ? <?php echo json_encode(__('week')); ?> : <?php echo json_encode(__('weeks')); ?>,
                        months: iv === 1 ? <?php echo json_encode(__('month')); ?> : <?php echo json_encode(__('months')); ?>,
                    };
                    return <?php echo json_encode(__('Every')); ?> + ' ' + iv + ' ' + (unitMap[it] || <?php echo json_encode(__('days')); ?>);
                }
                return <?php echo json_encode(__('Daily')); ?>;
            },

            waitingPeriodLabel() {
                const wp = this.selectedWaitingPeriodValue;
                const unit = this.selectedWaitingPeriodUnit;
                const unitMap = {
                    seconds: wp === 1 ? <?php echo json_encode(__('second')); ?> : <?php echo json_encode(__('seconds')); ?>,
                    minutes: wp === 1 ? <?php echo json_encode(__('minute')); ?> : <?php echo json_encode(__('minutes')); ?>,
                    hours: wp === 1 ? <?php echo json_encode(__('hour')); ?> : <?php echo json_encode(__('hours')); ?>,
                    days: wp === 1 ? <?php echo json_encode(__('day')); ?> : <?php echo json_encode(__('days')); ?>,
                    weeks: wp === 1 ? <?php echo json_encode(__('week')); ?> : <?php echo json_encode(__('weeks')); ?>,
                };
                return wp + ' ' + (unitMap[unit] || unit);
            }
        };
    }
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';

$maintenance = get_maintenance_mode();
?>

<?php if ($maintenance): ?>
    <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-4 text-amber-400 shadow-sm mb-6" role="alert">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <div>
                <h6 class="font-bold text-sm text-amber-300 mb-1"><?php echo e(__('Platform Maintenance')); ?></h6>
                <p class="text-sm text-amber-400/90"><?php echo e(__('Platform is temporarily under maintenance. Deposits, withdrawals, and investments are disabled.')); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<header class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-zinc-900 pb-6 mb-8">
    <div>
        <h1 class="text-2xl md:text-4xl font-bold text-zinc-50 mb-2 tracking-tight"><?php echo e(__('Investment Market')); ?></h1>
        <p class="text-zinc-400 text-sm md:text-base"><?php echo e(__('Deploy your capital. Choose a strategy and start compounding.')); ?></p>
    </div>
    <div class="flex items-center gap-3">
        <div class="bg-brand-card border border-zinc-800 rounded-xl px-5 py-3 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-zinc-800/50 flex items-center justify-center text-zinc-400">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <p class="text-zinc-500 text-xs font-medium uppercase tracking-wider mb-0.5"><?php echo e(__('Available Balance')); ?></p>
                <h3 class="text-xl font-bold text-zinc-50" x-text="formatCurrency(<?php echo $available; ?>)"></h3>
            </div>
        </div>
    </div>
</header>

<?php if (empty($plans)): ?>
    <!-- Empty State -->
    <div class="bg-brand-card rounded-3xl p-8 md:p-12 border border-zinc-800 flex flex-col items-center justify-center text-center mb-8">
        <div class="w-16 h-16 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
            <i class="fa-solid fa-chart-pie text-2xl"></i>
        </div>
        <h4 class="text-zinc-50 font-bold text-lg mb-2"><?php echo e(__('No Investment Plans Available')); ?></h4>
        <p class="text-zinc-500 text-sm max-w-xs mb-0"><?php echo e(__('Please check back later for new investment opportunities.')); ?></p>
    </div>
<?php else: ?>
    <div x-data="investmentCalc(window.investData)" x-init="init()" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Available Plans -->
        <div class="col-span-1 lg:col-span-8 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-zinc-100"><?php echo e(__('Available Plans')); ?></h2>
                <div class="lg:hidden text-zinc-500 text-xs">
                    <i class="fa-solid fa-arrow-right mr-1"></i><?php echo e(__('Swipe')); ?>
                </div>
            </div>

            <div class="plans-scroll-container">
                <?php foreach ($plans_js as $plan): ?>
                    <?php $color = $plan['icon_color']; ?>
                    <div class="plan-card relative bg-brand-card rounded-3xl p-6 border border-zinc-800 flex flex-col hover:border-zinc-700 transition-all group <?php echo $plan['is_featured'] ? 'border-brand-accent/40 emerald-glow' : ''; ?>"
                        :class="{ 'border-brand-accent ring-1 ring-brand-accent/40': selectedPlanId === <?php echo (int)$plan['id']; ?> }">

                        <?php if ($plan['is_featured']): ?>
                            <div class="absolute top-0 right-6 -translate-y-1/2 z-10">
                                <span class="bg-brand-accent text-brand-dark px-4 py-1 rounded-full text-xs font-bold shadow-[0_5px_15px_rgba(16,185,129,0.3)]"><?php echo e(__('MOST POPULAR')); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-start mb-6 <?php echo $plan['is_featured'] ? 'mt-2' : ''; ?>">
                            <div class="w-12 h-12 rounded-2xl bg-<?php echo e($color); ?>-500/10 border border-<?php echo e($color); ?>-500/20 flex items-center justify-center text-<?php echo e($color); ?>-500 shadow-sm">
                                <i class="fa-solid <?php echo e($plan['icon_class']); ?> text-xl"></i>
                            </div>
                            <span class="roi-badge <?php echo $plan['is_featured'] ? 'bg-brand-accent/10 border border-brand-accent/20 text-brand-accent' : 'bg-zinc-800 text-zinc-300'; ?>">
                                <?php echo e($plan['roi_badge_text']); ?>
                            </span>
                        </div>

                        <h3 class="text-2xl font-bold text-zinc-50 mb-1"><?php echo e($plan['name']); ?></h3>
                        <p class="text-zinc-500 text-sm mb-6"><?php echo e($plan['description']); ?></p>

                        <div class="space-y-3 mb-8 flex-1">
                            <div class="flex justify-between items-center py-2 border-b border-zinc-800/60 text-sm">
                                <span class="text-zinc-400"><?php echo e(__('Min Deposit')); ?></span>
                                <span class="text-zinc-100 font-semibold" x-text="formatCurrency(<?php echo $plan['min_amount']; ?>)"></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-zinc-800/60 text-sm">
                                <span class="text-zinc-400"><?php echo e(__('Duration')); ?></span>
                                <span class="text-zinc-100 font-semibold"><?php echo sprintf(__('%d Days'), (int)$plan['total_display_days']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 text-sm">
                                <span class="text-zinc-400"><?php echo $plan['return_capital'] ? e(__('Capital Return')) : e(__('Compounding')); ?></span>
                                <span class="text-brand-accent font-medium flex items-center gap-1">
                                    <i class="fa-solid fa-check"></i>
                                    <?php echo $plan['return_capital'] ? e(__('Yes')) : e(__('Active')); ?>
                                </span>
                            </div>
                            <?php if ($plan['waiting_period_value'] > 0): ?>
                                <div class="text-amber-500 text-xs font-semibold pt-1">
                                    <i class="fa-regular fa-clock mr-1"></i>
                                    <?php echo sprintf(__('Includes %d %s waiting period before profits begin'), (int)$plan['waiting_period_value'], e(__($plan['waiting_period_unit']))); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="button"
                            class="w-full py-3.5 rounded-xl font-bold transition-colors <?php echo $plan['is_featured'] ? 'bg-brand-accent text-brand-dark hover:bg-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.2)]' : 'bg-transparent text-zinc-100 border border-zinc-700 hover:bg-zinc-800'; ?>"
                            @click="setPlan(<?php echo e(json_encode($plan, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)); ?>); showModal = true">
                            <?php echo $plan['is_featured'] ? e(__('Invest Now')) : e(__('Choose Plan')); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Profit Estimator -->
        <div class="col-span-1 lg:col-span-4 lg:sticky lg:top-28">
            <div class="bg-brand-card rounded-3xl p-6 border border-zinc-800/80 shadow-2xl">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <h2 class="text-lg font-bold text-zinc-100"><?php echo e(__('Profit Estimator')); ?></h2>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-zinc-400 text-xs font-medium mb-2 uppercase tracking-wide"><?php echo e(__('Selected Plan')); ?></label>
                        <div class="relative">
                            <select x-model="selectedPlanId"
                                @change="const pid = Number($event.target.value); const p = plans.find(function(plan){ return plan.id === pid; }); if (p) setPlan(p);"
                                class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3.5 pr-10 outline-none focus:border-brand-accent/50 focus:ring-1 focus:ring-brand-accent/50 transition-all appearance-none">
                                <option value="" disabled><?php echo e(__('Choose a plan')); ?></option>
                                <template x-for="p in plans" :key="p.id">
                                    <option :value="p.id" x-text="p.name"></option>
                                </template>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-zinc-400 text-xs font-medium mb-2 uppercase tracking-wide">
                            <?php echo e(__('Investment Amount')); ?> (<span x-text="activeSymbol"></span>)
                        </label>
                        <div class="relative flex items-center bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden focus-within:border-brand-accent/50 focus-within:ring-1 focus-within:ring-brand-accent/50 transition-all"
                             :class="amountValidation.valid ? '' : 'border-rose-500/60 focus-within:border-rose-500 focus-within:ring-rose-500/30'">
                            <div class="pl-4 pr-2 text-zinc-500 font-semibold" x-text="activeSymbol"></div>
                            <input type="number" x-model="calculatorAmount" min="0" :max="maxAmount"
                                class="w-full bg-transparent text-zinc-100 text-lg font-medium py-3 outline-none" placeholder="0.00" />
                        </div>
                        <div class="mt-2 space-y-1">
                            <p class="text-xs text-zinc-500 flex items-center justify-between">
                                <span><?php echo e(__('Available Balance')); ?>: <strong x-text="formatCurrency(availableBalance)"></strong></span>
                                <span class="text-zinc-500">
                                    <?php echo e(__('Min')); ?>: <span x-text="formatCurrency(selectedPlanMin)"></span>
                                    <span x-show="selectedPlanMax > 0"> | <?php echo e(__('Max')); ?>: <span x-text="formatCurrency(selectedPlanMax)"></span></span>
                                </span>
                            </p>
                            <p x-show="!amountValidation.valid && calculatorAmount !== ''" class="text-xs text-rose-400 flex items-center gap-1.5" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <span x-text="amountValidation.message"></span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 bg-zinc-900/50 rounded-2xl p-5 border border-zinc-800 relative overflow-hidden">
                    <div class="absolute right-0 bottom-0 w-32 h-32 bg-brand-accent/10 rounded-tl-full blur-xl pointer-events-none"></div>

                    <p class="text-zinc-500 text-xs font-medium uppercase tracking-wider mb-2"><?php echo e(__('Projected Net Profit')); ?></p>
                    <div class="flex items-baseline gap-1 mb-2">
                        <h3 class="text-4xl font-bold text-brand-accent tracking-tight drop-shadow-[0_0_15px_rgba(16,185,129,0.2)]" x-text="formatCurrency(parseFloat(calculateProfit()))"></h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            <?php echo e(__('Based on')); ?> <span x-text="(selectedRoi * 100).toFixed(0) + '%'"></span> <?php echo e(__('ROI')); ?>
                            <span x-show="selectedWaitingPeriodValue > 0">, <span x-text="selectedProfitDuration + ' ' + <?php echo json_encode(__('day profit period')); ?>"></span></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Investment Confirmation Modal -->
        <div x-cloak
            x-show="showModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @click="showModal = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-md" @click.stop>
                <div class="bg-brand-card border border-zinc-800 rounded-3xl shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-800">
                        <h5 class="text-lg font-bold text-zinc-100" id="investModalLabel"><?php echo e(__('Confirm Investment')); ?></h5>
                        <button type="button" class="text-zinc-500 hover:text-zinc-300 transition-colors" @click="showModal = false">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form x-data="{ loading: false }" @submit="loading = true" action="/actions/invest-submit.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="plan_id" :value="selectedPlanId">
                        <input type="hidden" name="amount" :value="usdAmount">

                        <div class="p-6 space-y-5">
                            <div class="text-center">
                                <h2 class="text-3xl font-bold text-brand-accent mb-1" x-text="formatCurrency(parseFloat(usdAmount || 0))"></h2>
                                <p class="text-zinc-500 text-xs"><?php echo e(__('Investment Amount')); ?></p>
                            </div>

                            <div>
                                <label class="block text-zinc-400 text-xs font-medium mb-1.5 uppercase tracking-wide"><?php echo e(__('Plan')); ?></label>
                                <input type="text" x-model="planName" readonly
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3 outline-none" />
                            </div>

                            <div>
                                <label class="block text-zinc-400 text-xs font-medium mb-1.5 uppercase tracking-wide"><?php echo e(__('Payout Schedule')); ?></label>
                                <input type="text" :value="payoutScheduleLabel()" readonly
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3 outline-none" />
                            </div>

                            <div x-show="selectedWaitingPeriodValue > 0">
                                <label class="block text-zinc-400 text-xs font-medium mb-1.5 uppercase tracking-wide"><?php echo e(__('Waiting Period')); ?></label>
                                <input type="text" :value="waitingPeriodLabel()" readonly
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3 outline-none" />
                                <p class="text-zinc-500 text-xs mt-1">
                                    <i class="fa-regular fa-clock mr-1"></i><?php echo e(__('Profits will not be credited until this period elapses after investment start.')); ?>
                                </p>
                            </div>

                            <div x-show="selectedWaitingPeriodValue > 0">
                                <label class="block text-zinc-400 text-xs font-medium mb-1.5 uppercase tracking-wide"><?php echo e(__('Profit Period')); ?></label>
                                <input type="text" :value="selectedProfitDuration + ' ' + (selectedProfitDuration === 1 ? <?php echo json_encode(__('day')); ?> : <?php echo json_encode(__('days')); ?>)" readonly
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 text-sm rounded-xl px-4 py-3 outline-none" />
                                <p class="text-zinc-500 text-xs mt-1"><?php echo e(__('Profits are calculated based on this period only.')); ?></p>
                            </div>

                            <div>
                                <label class="block text-zinc-400 text-xs font-medium mb-1.5 uppercase tracking-wide">
                                    <?php echo e(__('Amount to Invest')); ?> (<span x-text="activeSymbol"></span>)
                                </label>
                                <div class="relative flex items-center bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden focus-within:border-brand-accent/50 focus-within:ring-1 focus-within:ring-brand-accent/50 transition-all"
                                     :class="amountValidation.valid ? '' : 'border-rose-500/60 focus-within:border-rose-500 focus-within:ring-rose-500/30'">
                                    <div class="pl-4 pr-2 text-zinc-500 font-semibold" x-text="activeSymbol"></div>
                                    <input type="number" x-model="calculatorAmount" min="0" :max="maxAmount" required
                                        class="w-full bg-transparent text-zinc-100 text-lg font-bold py-3 outline-none" placeholder="0.00" />
                                </div>
                                <div class="mt-1.5 space-y-1">
                                    <p class="text-zinc-500 text-xs flex items-center justify-between">
                                        <span><?php echo e(__('Available Balance')); ?>: <span x-text="formatCurrency(availableBalance)"></span></span>
                                        <span class="text-zinc-500">
                                            <?php echo e(__('Min')); ?>: <span x-text="formatCurrency(selectedPlanMin)"></span>
                                            <span x-show="selectedPlanMax > 0"> | <?php echo e(__('Max')); ?>: <span x-text="formatCurrency(selectedPlanMax)"></span></span>
                                        </span>
                                    </p>
                                    <p x-show="!amountValidation.valid && calculatorAmount !== ''" class="text-xs text-rose-400 flex items-center gap-1.5" style="display: none;">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        <span x-text="amountValidation.message"></span>
                                    </p>
                                </div>
                            </div>

                            <div class="bg-zinc-900/50 rounded-2xl p-4 border border-zinc-800 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400"><?php echo e(__('Total Payouts')); ?></span>
                                    <span class="font-bold text-zinc-100" x-text="calculateTotalIntervals()"></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400"><?php echo e(__('Projected Profit')); ?></span>
                                    <span class="font-bold text-brand-accent" x-text="formatCurrency(parseFloat(calculateProfit()) || 0)"></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-400"><?php echo e(__('Total Return')); ?></span>
                                    <span class="font-bold text-indigo-400" x-text="formatCurrency((parseFloat(usdAmount || 0)) + (parseFloat(calculateProfit()) || 0))"></span>
                                </div>
                            </div>

                            <button type="submit" :disabled="loading || !amountValidation.valid"
                                class="w-full py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed">
                                <span x-show="!loading"><?php echo e(__('Confirm Investment')); ?></span>
                                <span x-show="loading" class="flex items-center gap-2" style="display: none;">
                                    <i class="fa-solid fa-circle-notch fa-spin"></i><?php echo e(__('Processing…')); ?>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<?php require ROOT . '/includes/new-footer.php'; ?>