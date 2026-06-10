<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('My Portfolio');
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();
$cancellation_block_after_waiting = get_setting('cancellation_block_after_waiting', 'no');

$active = db_query("SELECT i.*, p.name as plan_name, p.roi_percentage, p.duration_days FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.user_id = ? AND i.status = 'active'", [$user_id]);
$completed = db_query("SELECT i.*, p.name as plan_name FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.user_id = ? AND i.status = 'completed'", [$user_id]);
$cancelled = db_query("SELECT i.*, p.name as plan_name FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.user_id = ? AND i.status = 'cancelled'", [$user_id]);

$total_invested_row = db_query("SELECT COALESCE(SUM(amount),0) as s FROM investments WHERE user_id = ? AND status != 'cancelled'", [$user_id]);
$total_invested = $total_invested_row[0]['s'] ?? 0;

$active_capital_row = db_query("SELECT COALESCE(SUM(amount),0) as s FROM investments WHERE user_id = ? AND status = 'active'", [$user_id]);
$active_capital = $active_capital_row[0]['s'] ?? 0;

$total_profit_row = db_query("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE user_id = ? AND type = 'profit'", [$user_id]);
$total_profit = $total_profit_row[0]['s'] ?? 0;

$allocation_labels = [];
$allocation_data = [];
$allocation_colors = ['#4f46e5', '#f59e0b', '#06b6d4', '#10b981', '#ec4899'];

foreach ($active as $index => $inv) {
    $allocation_labels[] = $inv['plan_name'];
    $allocation_data[] = floatval($inv['amount']);
}

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Portfolio Overview'); ?></h3>
        <p class="text-secondary mb-0 small"><?php echo __('Track and manage your investments'); ?></p>
    </div>
    <div>
        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
        </button>
    </div>
</div>

<!-- Portfolio Overview Card -->
<div class="card border-0 shadow-sm mb-5 overflow-hidden" style="border-radius: 1.25rem;"
    x-data="{ showBalance: true }">
    <div class="row g-0">
        <!-- Left Side: Gradient Background -->
        <div class="col-lg-8 p-4 p-lg-5 text-white position-relative" style="background: var(--gradient-card);">
            <!-- Decorative circles -->
            <div class="position-absolute" style="top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
            <div class="position-absolute" style="bottom: -30%; left: -5%; width: 200px; height: 200px; background: rgba(255,255,255,0.08); border-radius: 50%; pointer-events: none;"></div>

            <div class="position-relative">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <small class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px"><?php echo __('Total Net Worth'); ?></small>
                        <div class="d-flex align-items-baseline gap-3 mt-1">
                            <h1 class="fw-bold mb-0 display-4" style="letter-spacing: -1px" x-show="showBalance" x-text="formatCurrency(<?php echo ($active_capital + $total_profit); ?>)"></h1>
                            <h1 class="fw-bold mb-0 display-4" x-show="!showBalance">••••••••</h1>
                        </div>
                        <p class="text-white-50 small mt-2 mb-0"><?php echo __('Total equity across all active plans'); ?></p>
                    </div>
                    <button class="btn btn-sm text-white-50 p-0" @click="showBalance = !showBalance" style="background: rgba(255,255,255,0.1); border-radius: 50%; width: 40px; height: 40px;">
                        <i class="fas" :class="showBalance ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>

                <div class="row g-4">
                    <div class="col-6 col-sm-4">
                        <span class="d-block text-white-50 small text-uppercase fw-bold mb-1"><?php echo __('Total Profit'); ?></span>
                        <h5 class="fw-bold text-white mb-0" x-show="showBalance" x-text="formatCurrency(<?php echo $total_profit; ?>)"></h5>
                        <h5 class="fw-bold text-white mb-0" x-show="!showBalance">••••</h5>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="d-block text-white-50 small text-uppercase fw-bold mb-1"><?php echo __('Active Capital'); ?></span>
                        <h5 class="fw-bold text-white mb-0" x-show="showBalance" x-text="formatCurrency(<?php echo $active_capital; ?>)"></h5>
                        <h5 class="fw-bold text-white mb-0" x-show="!showBalance">••••</h5>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="d-block text-white-50 small text-uppercase fw-bold mb-1"><?php echo __('Active Plans'); ?></span>
                        <h5 class="fw-bold text-white mb-0"><?php echo count($active); ?> <?php echo __('Running'); ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Asset Mix Chart -->
        <div class="col-12 col-lg-4 p-3 p-lg-4 d-flex flex-column justify-content-center align-items-center portfolio-chart-side" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
            <?php if (!empty($active)): ?>
                <div style="width: 100%; max-width: 280px">
                    <div id="allocationChart" style="min-height: 280px; width: 100%;"></div>
                </div>
            <?php else: ?>
                <div class="text-center text-secondary">
                    <i class="fas fa-chart-pie fa-3x mb-3 opacity-25"></i>
                    <p class="small mb-0"><?php echo __('No active investments'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs-line mb-4"
    x-data="{ activeTab: 'active' }"
    x-init="$watch('activeTab', value => { $dispatch('tab-changed', value); })">
    <button class="tab-btn"
        :class="{ 'active': activeTab === 'active' }"
        @click="activeTab = 'active'">
        <?php echo __('Active Investments'); ?>
    </button>
    <button class="tab-btn"
        :class="{ 'active': activeTab === 'history' }"
        @click="activeTab = 'history'">
        <?php echo __('History & Archive'); ?>
    </button>
</div>

<!-- Active Investments Tab -->
<div x-data="{
    activeTab: 'active',
    showCancelModal: false,
    selectedInvestment: null,
    penaltyBreakdown: {},
    blockSettings: <?php echo htmlspecialchars(json_encode([
                        'block_after_waiting' => $cancellation_block_after_waiting
                    ]), ENT_QUOTES, 'UTF-8'); ?>,
    isCancelBlockedByRule(investment) {
        if (this.blockSettings.block_after_waiting !== 'yes') return false;
        if (!investment.next_payout_date) return false;
        const nextPayoutTime = new Date(investment.next_payout_date).getTime();
        const nowTime = new Date().getTime();
        return nowTime >= nextPayoutTime;
    },
    calculatePenalty(investment) {
        const amount = parseFloat(investment.amount);
        /* FIX: Added htmlspecialchars to prevent quote conflict */
        const settings = <?php echo htmlspecialchars(json_encode([
                                'penalty_mode' => get_setting('cancellation_penalty_mode', 'percentage'),
                                'penalty_percentage' => floatval(get_setting('cancellation_penalty_percentage', 10)),
                                'penalty_flat' => floatval(get_setting('cancellation_penalty_flat', 5.00)),
                                'forfeit_profits' => get_setting('cancellation_forfeit_profits', 'no')
                            ]), ENT_QUOTES, 'UTF-8'); ?>;
        
        let penalty = 0;
        if (settings.penalty_mode === 'percentage') {
            penalty = amount * (settings.penalty_percentage / 100);
        } else {
            penalty = settings.penalty_flat;
        }
        const refund = Math.max(0, amount - penalty);
        this.penaltyBreakdown = {
            penalty: penalty.toFixed(2),
            refund: refund.toFixed(2),
            forfeitProfits: settings.forfeit_profits === 'yes'
        };
    }
}" @tab-changed.window="activeTab = $event.detail">
    <div x-show="activeTab === 'active'" x-transition.opacity>
        <?php if ($active): ?>
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 1rem">
                <?php foreach ($active as $index => $inv):
                    $icon_class = 'fa-seedling';
                    $icon_bg = 'bg-secondary';
                    $icon_color = 'text-secondary';

                    if ($inv['roi_percentage'] >= 8) {
                        $icon_class = 'fa-crown';
                        $icon_bg = 'bg-warning';
                        $icon_color = 'text-warning';
                    } elseif ($inv['roi_percentage'] >= 5) {
                        $icon_class = 'fa-rocket';
                        $icon_bg = 'bg-primary';
                        $icon_color = 'text-primary';
                    } else {
                        $icon_class = 'fa-seedling';
                        $icon_bg = 'bg-secondary';
                        $icon_color = 'text-secondary';
                    }

                    $start = strtotime($inv['created_at']);
                    $end = strtotime($inv['end_date']);
                    $now = time();
                    $total_duration = $end - $start;
                    $elapsed = $now - $start;
                    $progress = $total_duration > 0 ? min(100, max(0, ($elapsed / $total_duration) * 100)) : 0;
                    $elapsed_days = floor($elapsed / 86400);
                    $total_days = ceil($total_duration / 86400);
                ?>
                    <div class="d-flex align-items-center justify-content-between p-3 p-lg-4 investment-row <?php echo $index > 0 ? 'border-top' : ''; ?>" style="border-color: var(--border-color) !important;">
                        <div class="d-flex align-items-center gap-3 gap-lg-4 flex-grow-1">
                            <div class="<?php echo $icon_bg; ?> bg-opacity-10 <?php echo $icon_color; ?> rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                <i class="fas <?php echo $icon_class; ?> fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><?php echo e($inv['plan_name']); ?></h6>
                                <div class="text-muted small">
                                    <span class="fw-medium">#INV-<?php echo $inv['id']; ?></span> •
                                    <span class="text-success fw-bold"><?php echo e($inv['roi_percentage']); ?>% <?php echo __('Daily'); ?></span>
                                </div>
                                <?php
                                $in_waiting = false;
                                $profits_begin_date = '';
                                if (isset($inv['waiting_period_value']) && intval($inv['waiting_period_value']) > 0 && !empty($inv['next_payout_date']) && floatval($inv['total_profit_earned']) <= 0) {
                                    $next_payout_ts = strtotime($inv['next_payout_date']);
                                    if ($next_payout_ts !== false && $next_payout_ts > time()) {
                                        $in_waiting = true;
                                        $profits_begin_date = format_date($inv['next_payout_date'], 'M d, Y');
                                    }
                                }
                                ?>
                                <?php if ($in_waiting): ?>
                                    <div class="small mt-1" style="color:#92400e;background:#fef3c7;border-radius:6px;padding:2px 8px;display:inline-block;">
                                        ⏳ <?php echo sprintf(__('Waiting period — profits begin %s'), e($profits_begin_date)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-none d-md-block px-4 flex-grow-1" style="max-width: 300px;">
                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                <span class="text-muted"><?php echo __('Progress'); ?> (<?php echo $elapsed_days; ?>/<?php echo $total_days; ?> <?php echo __('Days'); ?>)</span>
                                <span class="text-primary"><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="progress" style="height: 6px; background: #f1f5f9;">
                                <div class="progress-bar bg-<?php echo $icon_bg === 'bg-warning' ? 'warning' : ($icon_bg === 'bg-primary' ? 'primary' : 'secondary'); ?>" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>

                        <div class="text-end ps-3" style="min-width: 120px;">
                            <small class="text-muted d-block text-uppercase fw-bold small mb-1"><?php echo __('Invested'); ?></small>
                            <h6 class="fw-bold text-dark mb-0" x-text="formatCurrency(<?php echo $inv['amount']; ?>)"><?php echo format_money($inv['amount']); ?></h6>
                        </div>

                        <div class="ps-3">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold"
                                @click="selectedInvestment = <?php echo htmlspecialchars(json_encode($inv)); ?>; calculatePenalty(selectedInvestment); showCancelModal = true"
                                :disabled="isCancelBlockedByRule(<?php echo htmlspecialchars(json_encode($inv)); ?>)"
                                :title="isCancelBlockedByRule(<?php echo htmlspecialchars(json_encode($inv)); ?>) ? '<?php echo e(__('Cancellation is no longer allowed once the first payout date has been reached.')); ?>' : ''">
                                <?php echo __('Manage'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-3 mb-5">
                <i class="fas fa-chart-pie fa-3x mb-3 opacity-25 text-secondary"></i>
                <h6 class="fw-bold text-dark"><?php echo __('No Active Investments'); ?></h6>
                <p class="small text-muted mb-3"><?php echo __('Start investing to see your portfolio here.'); ?></p>
                <a href="/user/invest" class="btn btn-primary rounded-pill px-4 fw-bold"><?php echo __('Invest Now'); ?></a>
            </div>
        <?php endif; ?>
    </div>

    <!-- History Tab -->
    <div x-show="activeTab === 'history'" x-transition.opacity x-cloak>
        <?php if ($completed || $cancelled): ?>
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 1rem">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Investment Plan'); ?></th>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Date Started'); ?></th>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Date Ended'); ?></th>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Initial Capital'); ?></th>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Total Return'); ?></th>
                                <th class="py-3 px-4 text-uppercase small fw-bold text-muted text-center" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo __('Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_merge($completed, $cancelled) as $hist):
                                $status_class = $hist['status'] === 'completed' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary';
                                $total_return = ($hist['total_profit_earned'] ?? 0) + ($hist['status'] === 'cancelled' ? 0 : $hist['amount']);
                            ?>
                                <tr>
                                    <td class="py-3 px-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-light p-2 text-muted">
                                                <i class="fas fa-history"></i>
                                            </div>
                                            <span class="fw-bold text-dark"><?php echo e($hist['plan_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-muted"><?php echo format_date($hist['created_at'], 'M d, Y'); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo format_date($hist['end_date'], 'M d, Y'); ?></td>
                                    <td class="py-3 px-4 text-end fw-medium text-dark" x-text="formatCurrency(<?php echo $hist['amount']; ?>)"><?php echo format_money($hist['amount']); ?></td>
                                    <td class="py-3 px-4 text-end fw-bold text-success" x-text="'+' + formatCurrency(<?php echo $total_return; ?>)">+<?php echo format_money($total_return); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="badge <?php echo $status_class; ?> px-3 py-2"><?php echo __(ucfirst($hist['status'])); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-3">
                <i class="fas fa-history fa-3x mb-3 opacity-25 text-secondary"></i>
                <h6 class="fw-bold text-dark"><?php echo __('No History Yet'); ?></h6>
                <p class="small text-muted mb-0"><?php echo __('Your completed investments will appear here.'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Manage/Cancel Modal -->
    <div class="modal fade" tabindex="-1" x-show="showCancelModal" style="display: none; z-index: 1055;"
        :class="{ 'show d-block': showCancelModal }"
        @keydown.escape.window="showCancelModal = false">
        <div class="modal-dialog modal-dialog-centered" style="z-index: 1056;">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><?php echo __('Manage Investment'); ?></h5>
                    <button type="button" class="btn-close" @click="showCancelModal = false"></button>
                </div>
                <div class="modal-body p-4">
                    <template x-if="selectedInvestment">
                        <div>
                            <div class="text-center mb-4">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                                <h4 class="fw-bold mb-1" x-text="selectedInvestment.plan_name"></h4>
                                <span class="badge bg-success bg-opacity-10 text-success px-3">#INV-<span x-text="selectedInvestment.id"></span></span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3">
                                        <span class="d-block text-muted small text-uppercase fw-bold"><?php echo __('Invested'); ?></span>
                                        <h6 class="fw-bold text-dark mb-0 mt-1" x-text="formatCurrency(parseFloat(selectedInvestment.amount))"></h6>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3">
                                        <span class="d-block text-muted small text-uppercase fw-bold"><?php echo __('Daily ROI'); ?></span>
                                        <h6 class="fw-bold text-success mb-0 mt-1" x-text="selectedInvestment.roi_percentage + '%'"></h6>
                                    </div>
                                </div>
                            </div>

                            <ul class="list-group list-group-flush small mb-4">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted"><?php echo __('Start Date'); ?></span>
                                    <span class="fw-bold text-dark" x-text="new Date(selectedInvestment.created_at).toLocaleDateString()"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted"><?php echo __('End Date'); ?></span>
                                    <span class="fw-bold text-dark" x-text="new Date(selectedInvestment.end_date).toLocaleDateString()"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted"><?php echo __('Profit Earned'); ?></span>
                                    <span class="fw-bold text-success" x-text="formatCurrency(parseFloat(selectedInvestment.total_profit_earned || 0))"></span>
                                </li>
                            </ul>

                            <div class="alert alert-warning">
                                <strong><i class="fas fa-exclamation-triangle me-2"></i><?php echo __('Cancellation Penalty'); ?></strong>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span><?php echo __('Original Amount'); ?>:</span>
                                        <strong x-text="formatCurrency(parseFloat(selectedInvestment.amount))"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span><?php echo __('Penalty'); ?>:</span>
                                        <strong class="text-danger" x-text="'-' + formatCurrency(parseFloat(penaltyBreakdown.penalty))"></strong>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold"><?php echo __('Refund Amount'); ?>:</span>
                                        <strong class="text-success" x-text="formatCurrency(parseFloat(penaltyBreakdown.refund))"></strong>
                                    </div>
                                    <div class="mt-2 small" x-show="penaltyBreakdown.forfeitProfits">
                                        <span class="text-danger"><?php echo __('Note: Earned profits will not be returned.'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-outline-secondary fw-bold rounded-pill" @click="showCancelModal = false">
                        <?php echo __('Close'); ?>
                    </button>
                    <form action="/actions/cancel-investment.php" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="investment_id" x-bind:value="selectedInvestment?.id ?? ''">
                        <button type="submit" class="btn btn-danger fw-bold rounded-pill">
                            <i class="fas fa-times me-1"></i> <?php echo __('Cancel Investment'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Backdrop - Separate from modal to ensure proper z-index stacking -->
    <div class="modal-backdrop fade show" x-show="showCancelModal" @click="showCancelModal = false" style="z-index: 1050;"></div>
</div>

<?php if (!empty($active)): ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        const currencySymbol = <?php echo json_encode($currency_symbol); ?>;

        // Helper function to check dark mode status
        function getIsDarkMode() {
            return document.body.classList.contains('dark-mode');
        }

        // Helper function to get donut chart options based on dark mode
        function getDonutChartOptions(isDark) {
            return {
                theme: {
                    mode: isDark ? 'dark' : 'light'
                },
                legend: {
                    labels: {
                        colors: isDark ? '#94a3b8' : '#64748b'
                    }
                },
                stroke: {
                    colors: [isDark ? '#1e293b' : '#ffffff']
                },
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                total: {
                                    color: isDark ? '#94a3b8' : '#64748b'
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light'
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Wait for any dark mode class to be applied
            setTimeout(function() {
                const isDark = getIsDarkMode();
                const darkModeOptions = getDonutChartOptions(isDark);

                var options = {
                    series: <?php echo json_encode($allocation_data); ?>,
                    chart: {
                        type: 'donut',
                        height: 260,
                        fontFamily: 'Plus Jakarta Sans, sans-serif',
                        background: 'transparent'
                    },
                    theme: darkModeOptions.theme,
                    labels: <?php echo json_encode($allocation_labels); ?>,
                    colors: <?php echo json_encode(array_slice($allocation_colors, 0, count($allocation_data))); ?>,
                    dataLabels: {
                        enabled: false
                    },
                    legend: {
                        position: 'bottom',
                        fontSize: '13px',
                        fontWeight: 500,
                        labels: darkModeOptions.legend.labels,
                        markers: {
                            radius: 12
                        }
                    },
                    stroke: {
                        show: true,
                        width: 3,
                        colors: darkModeOptions.stroke.colors
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '75%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: <?php echo json_encode(__("Asset Mix")); ?>,
                                        fontSize: '14px',
                                        fontWeight: 600,
                                        color: darkModeOptions.plotOptions.pie.donut.labels.total.color,
                                        formatter: function(w) {
                                            var total = w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0);
                                            return currencySymbol + parseFloat(total).toLocaleString('en-US', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            });
                                        }
                                    }
                                },
                            },
                        },
                    },
                    tooltip: {
                        theme: darkModeOptions.tooltip.theme,
                        style: {
                            fontSize: '12px'
                        },
                        y: {
                            formatter: function(val) {
                                return currencySymbol + parseFloat(val).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            },
                        },
                    },
                };
                var chart = new ApexCharts(document.querySelector('#allocationChart'), options);
                chart.render().then(function() {
                    window.dispatchEvent(new Event('resize'));
                });

                // Listen for system theme changes
                const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                darkModeQuery.addEventListener('change', function(e) {
                    // For system mode, trust the OS preference (e.matches)
                    const newIsDark = e.matches;
                    const newOptions = getDonutChartOptions(newIsDark);

                    chart.updateOptions({
                        theme: newOptions.theme,
                        legend: {
                            labels: newOptions.legend.labels
                        },
                        stroke: {
                            colors: newOptions.stroke.colors
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        total: {
                                            color: newOptions.plotOptions.pie.donut.labels.total.color
                                        }
                                    }
                                }
                            }
                        },
                        tooltip: newOptions.tooltip
                    });
                });
            }, 100); // Small delay to ensure dark mode class is applied

        });
    </script>
<?php endif; ?>

<style>
    /* Tab styling to match design */
    .tabs-line {
        display: flex;
        gap: 2rem;
        border-bottom: 1px solid var(--border-color);
    }

    .tab-btn {
        background: none;
        border: none;
        padding: 0 0 1rem 0;
        font-weight: 600;
        color: var(--text-muted);
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
    }

    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .tab-btn:hover:not(.active) {
        color: var(--text-main);
    }

    [x-cloak] {
        display: none !important;
    }

    @media (max-width: 991.98px) {
        .portfolio-chart-side {
            min-height: 300px;
        }
    }

    @media (min-width: 992px) {
        .portfolio-chart-side {
            border-left: 1px solid #e2e8f0 !important;
            border-top: none !important;
        }
    }
</style>

<?php require ROOT . '/includes/footer.php'; ?>