<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/currency-conversion.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('My Portfolio');
$active_nav = 'portfolio';
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

ob_start();
?>
<style>
    .portfolio-tab {
        background: none;
        border: none;
        padding: 0 0 1rem 0;
        font-weight: 700;
        font-size: 0.875rem;
        color: #71717a;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
        position: relative;
    }

    .portfolio-tab:hover {
        color: #d4d4d8;
    }

    .portfolio-tab.active {
        color: #10b981;
        border-bottom-color: #10b981;
    }

    #allocationChart {
        min-height: 260px;
        width: 100%;
    }
</style>
<?php
$extra_css = ob_get_clean();

ob_start();
?>
<script>
    function portfolioData() {
        return {
            activeTab: 'active',
            showCancelModal: false,
            selectedInvestment: null,
            penaltyBreakdown: {},
            blockedMessage: <?php echo json_encode(__('Cancellation is no longer allowed once the first payout date has been reached.')); ?>,
            blockSettings: <?php echo json_encode(['block_after_waiting' => $cancellation_block_after_waiting]); ?>,
            cancelSettings: <?php echo json_encode([
                                'penalty_mode' => get_setting('cancellation_penalty_mode', 'percentage'),
                                'penalty_percentage' => floatval(get_setting('cancellation_penalty_percentage', 10)),
                                'penalty_flat' => floatval(get_setting('cancellation_penalty_flat', 5.00)),
                                'forfeit_profits' => get_setting('cancellation_forfeit_profits', 'no')
                            ]); ?>,
            isCancelBlockedByRule(investment) {
                if (this.blockSettings.block_after_waiting !== 'yes') return false;
                if (!investment || !investment.next_payout_date) return false;
                const nextPayoutTime = new Date(investment.next_payout_date).getTime();
                const nowTime = new Date().getTime();
                return nowTime >= nextPayoutTime;
            },
            calculatePenalty(investment) {
                if (!investment) return;
                let amount = parseFloat(investment.amount);
                const totalProfitEarned = parseFloat(investment.total_profit_earned || 0);
                let refundBase = amount;
                if (this.cancelSettings.forfeit_profits === 'no') {
                    refundBase += totalProfitEarned;
                }
                let penalty = 0;
                if (this.cancelSettings.penalty_mode === 'percentage') {
                    penalty = refundBase * (this.cancelSettings.penalty_percentage / 100);
                } else if (this.cancelSettings.penalty_mode === 'flat') {
                    penalty = this.cancelSettings.penalty_flat;
                }
                const refund = Math.max(0, refundBase - penalty);
                this.penaltyBreakdown = {
                    penalty: penalty,
                    refund: refund,
                    forfeitProfits: this.cancelSettings.forfeit_profits === 'yes'
                };
            },
            openModal(investment) {
                this.selectedInvestment = investment;
                this.showCancelModal = true;
            },
            closeModal() {
                this.showCancelModal = false;
                this.selectedInvestment = null;
                this.penaltyBreakdown = {};
            },
            init() {
                this.$watch('selectedInvestment', (value) => {
                    if (value) {
                        this.calculatePenalty(value);
                    } else {
                        this.penaltyBreakdown = {};
                    }
                });
            }
        };
    }
</script>
<?php if (!empty($active)): ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        const allocationLabels = <?php echo json_encode($allocation_labels); ?>;
        const allocationData = <?php echo json_encode($allocation_data); ?>;
        const allocationColors = <?php echo json_encode(array_slice($allocation_colors, 0, count($allocation_data))); ?>;
        const currencySymbol = <?php echo json_encode($currency_symbol); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const chartEl = document.querySelector('#allocationChart');
            if (!chartEl || allocationData.length === 0) return;

            const options = {
                series: allocationData,
                chart: {
                    type: 'donut',
                    height: 260,
                    fontFamily: 'Outfit, sans-serif',
                    background: 'transparent'
                },
                theme: {
                    mode: 'dark'
                },
                labels: allocationLabels,
                colors: allocationColors,
                dataLabels: {
                    enabled: false
                },
                legend: {
                    position: 'bottom',
                    fontSize: '13px',
                    fontWeight: 500,
                    labels: {
                        colors: '#94a3b8'
                    },
                    markers: {
                        radius: 12
                    }
                },
                stroke: {
                    show: true,
                    width: 3,
                    colors: ['#18181b']
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '75%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: <?php echo json_encode(__('Asset Mix')); ?>,
                                    fontSize: '14px',
                                    fontWeight: 600,
                                    color: '#94a3b8',
                                    formatter: function(w) {
                                        const total = w.globals.seriesTotals.reduce(function(a, b) {
                                            return a + b;
                                        }, 0);
                                        return currencySymbol + parseFloat(total).toLocaleString('en-US', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    theme: 'dark',
                    style: {
                        fontSize: '12px'
                    },
                    y: {
                        formatter: function(val) {
                            return currencySymbol + parseFloat(val).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            };

            const chart = new ApexCharts(chartEl, options);
            chart.render().then(function() {
                window.dispatchEvent(new Event('resize'));
            });
        });
    </script>
<?php endif; ?>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<?php if (get_maintenance_mode()): ?>
    <div class="mb-6 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-amber-400 shadow-sm" role="alert">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <span class="text-sm font-medium"><?php echo e(__('Platform maintenance is active. Some operations may be limited.')); ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl md:text-4xl font-bold text-zinc-50 mb-2 tracking-tight"><?php echo e(__('Portfolio Overview')); ?></h1>
        <p class="text-zinc-400 text-sm md:text-base"><?php echo e(__('Track and manage your active assets and history.')); ?></p>
    </div>
</header>

<!-- Portfolio Overview -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
    <!-- Net Worth & Core Stats -->
    <div class="col-span-1 lg:col-span-8 bg-brand-card rounded-3xl p-8 border border-zinc-800 relative overflow-hidden emerald-glow group">
        <div class="absolute top-0 right-0 w-64 h-64 bg-brand-accent opacity-[0.03] rounded-full blur-3xl group-hover:opacity-[0.05] transition-opacity duration-500"></div>

        <div class="flex justify-between items-start mb-8 relative z-10">
            <div>
                <p class="text-zinc-400 text-sm font-medium uppercase tracking-widest mb-2"><?php echo e(__('Total Net Worth')); ?></p>
                <div class="flex items-baseline gap-2">
                    <h2 class="text-4xl md:text-6xl font-bold text-zinc-50 tracking-tight" x-show="showBalance" x-text="formatCurrency(<?php echo ($active_capital + $total_profit); ?>)"></h2>
                    <h2 class="text-5xl md:text-6xl font-bold text-zinc-50 tracking-tight" x-show="!showBalance">••••••••</h2>
                </div>
                <p class="text-zinc-500 text-xs mt-2"><?php echo e(__('Total equity across all active plans')); ?></p>
            </div>
            <button class="w-10 h-10 rounded-xl bg-zinc-800/50 flex items-center justify-center text-zinc-400 hover:text-zinc-100 transition-colors"
                @click="toggleBalance()">
                <i class="fa-solid" :class="showBalance ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        </div>

        <!-- Sub-stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6 pt-6 border-t border-zinc-800/60 relative z-10">
            <div>
                <p class="text-zinc-500 text-[11px] font-bold uppercase tracking-wider mb-1"><?php echo e(__('Total Profit')); ?></p>
                <p class="text-xl font-bold text-zinc-100" x-show="showBalance" x-text="formatCurrency(<?php echo $total_profit; ?>)"></p>
                <p class="text-xl font-bold text-zinc-100" x-show="!showBalance">••••</p>
            </div>
            <div>
                <p class="text-zinc-500 text-[11px] font-bold uppercase tracking-wider mb-1"><?php echo e(__('Active Capital')); ?></p>
                <p class="text-xl font-bold text-zinc-100" x-show="showBalance" x-text="formatCurrency(<?php echo $active_capital; ?>)"></p>
                <p class="text-xl font-bold text-zinc-100" x-show="!showBalance">••••</p>
            </div>
            <div class="col-span-2 md:col-span-1">
                <p class="text-zinc-500 text-[11px] font-bold uppercase tracking-wider mb-1"><?php echo e(__('Active Plans')); ?></p>
                <p class="text-xl font-bold text-brand-accent"><?php echo e(count($active)); ?> <?php echo e(__('Running')); ?></p>
            </div>
        </div>
    </div>

    <!-- Asset Mix Donut -->
    <div class="col-span-1 lg:col-span-4 bg-brand-card rounded-3xl p-8 border border-zinc-800 flex flex-col items-center justify-center relative shadow-sm">
        <p class="text-zinc-400 text-xs font-medium uppercase tracking-widest absolute top-6 left-6"><?php echo e(__('Asset Mix')); ?></p>
        <?php if (!empty($active)): ?>
            <div class="w-full max-w-[280px] mt-6">
                <div id="allocationChart"></div>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center text-center py-10">
                <div class="w-16 h-16 rounded-2xl bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                    <i class="fa-solid fa-chart-pie text-2xl"></i>
                </div>
                <p class="text-zinc-500 text-sm"><?php echo e(__('No active investments')); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs -->
<div x-data="portfolioData()" x-init="init()" class="space-y-6">
    <div class="flex items-center gap-6 border-b border-zinc-800/80 mb-6 px-2">
        <button class="portfolio-tab" :class="{ 'active': activeTab === 'active' }" @click="activeTab = 'active'">
            <?php echo e(__('Active Investments')); ?>
            <?php if (!empty($active)): ?>
                <span class="absolute -top-1 -right-3 w-2 h-2 rounded-full bg-brand-accent animate-pulse"></span>
            <?php endif; ?>
        </button>
        <button class="portfolio-tab" :class="{ 'active': activeTab === 'history' }" @click="activeTab = 'history'">
            <?php echo e(__('History & Archive')); ?>
        </button>
    </div>

    <!-- Active Investments Tab -->
    <div x-show="activeTab === 'active'" x-transition.opacity>
        <?php if (!empty($active)): ?>
            <div class="space-y-4">
                <?php foreach ($active as $index => $inv):
                    if ($inv['roi_percentage'] >= 8) {
                        $icon_class = 'fa-crown';
                        $color = 'amber';
                    } elseif ($inv['roi_percentage'] >= 5) {
                        $icon_class = 'fa-rocket';
                        $color = 'sky';
                    } else {
                        $icon_class = 'fa-seedling';
                        $color = 'emerald';
                    }

                    $start = strtotime($inv['created_at']);
                    $end = strtotime($inv['end_date']);
                    $now = time();
                    $total_duration = $end - $start;
                    $elapsed = $now - $start;
                    $progress = $total_duration > 0 ? min(100, max(0, ($elapsed / $total_duration) * 100)) : 0;
                    $elapsed_days = floor($elapsed / 86400);
                    $total_days = ceil($total_duration / 86400);

                    $in_waiting = false;
                    $profits_begin_date = '';
                    if (isset($inv['waiting_period_value']) && intval($inv['waiting_period_value']) > 0 && !empty($inv['next_payout_date']) && floatval($inv['total_profit_earned']) <= 0) {
                        $next_payout_ts = strtotime($inv['next_payout_date']);
                        if ($next_payout_ts !== false && $next_payout_ts > time()) {
                            $in_waiting = true;
                            $profits_begin_date = format_date($inv['next_payout_date'], 'M d, Y');
                        }
                    }

                    $inv_json = htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="bg-brand-card border border-zinc-800 rounded-2xl p-5 md:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6 hover:border-zinc-700 transition-all group relative overflow-hidden">
                        <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-brand-accent/5 rounded-full blur-2xl group-hover:bg-brand-accent/10 transition-colors pointer-events-none"></div>

                        <!-- Plan Info -->
                        <div class="flex items-center gap-4 relative z-10 w-full lg:w-1/3">
                            <div class="w-12 h-12 flex-shrink-0 rounded-2xl bg-<?php echo e($color); ?>-500/10 border border-<?php echo e($color); ?>-500/20 flex items-center justify-center text-<?php echo e($color); ?>-500">
                                <i class="fa-solid <?php echo e($icon_class); ?> text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-zinc-100 font-bold text-lg leading-tight"><?php echo e($inv['plan_name']); ?></h3>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-zinc-500 text-xs font-mono">#INV-<?php echo e($inv['id']); ?></span>
                                    <span class="w-1 h-1 rounded-full bg-zinc-700"></span>
                                    <span class="text-brand-accent text-xs font-semibold"><?php echo e($inv['roi_percentage']); ?>% <?php echo e(__('Daily')); ?></span>
                                </div>
                                <?php if ($in_waiting): ?>
                                    <div class="inline-flex items-center gap-1.5 mt-2 px-2 py-1 rounded-md text-xs font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                        <i class="fa-solid fa-clock text-[10px]"></i>
                                        <?php echo sprintf(__('Waiting period — profits begin %s'), e($profits_begin_date)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="w-full lg:w-1/3 relative z-10">
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-zinc-400 text-xs font-medium"><?php echo sprintf(__('Progress (%s/%s Days)'), e($elapsed_days), e($total_days)); ?></span>
                                <span class="text-<?php echo e($color); ?>-500 font-bold text-sm"><?php echo e(round($progress)); ?>%</span>
                            </div>
                            <div class="h-1.5 w-full bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full bg-<?php echo e($color); ?>-500 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]" style="width: <?php echo e($progress); ?>%"></div>
                            </div>
                        </div>

                        <!-- Action & Amount -->
                        <div class="w-full lg:w-auto flex items-center justify-between lg:justify-end gap-8 relative z-10 border-t border-zinc-800/60 pt-4 lg:border-t-0 lg:pt-0">
                            <div class="lg:text-right">
                                <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-wider mb-0.5"><?php echo e(__('Invested')); ?></p>
                                <p class="text-lg font-bold text-zinc-100" x-text="formatCurrency(<?php echo $inv['amount']; ?>)"></p>
                            </div>
                            <button class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-100 text-sm font-semibold rounded-xl border border-zinc-700 transition-colors shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="openModal(<?php echo $inv_json; ?>)"
                                :disabled="isCancelBlockedByRule(<?php echo $inv_json; ?>)"
                                :title="isCancelBlockedByRule(<?php echo $inv_json; ?>) ? blockedMessage : ''">
                                <?php echo e(__('Manage')); ?> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-brand-card rounded-3xl p-8 border border-zinc-800 flex flex-col items-center justify-center text-center py-16 relative overflow-hidden">
                <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-zinc-800/10 rounded-full blur-xl"></div>
                <div class="w-14 h-14 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                    <i class="fa-solid fa-chart-pie text-xl"></i>
                </div>
                <h4 class="text-zinc-50 font-bold mb-1"><?php echo e(__('No Active Investments')); ?></h4>
                <p class="text-zinc-500 text-sm mb-5"><?php echo e(__('Start investing to see your portfolio here.')); ?></p>
                <a href="/user/invest" class="px-6 py-2.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors flex items-center gap-2 shadow-[0_0_20px_rgba(16,185,129,0.2)]">
                    <i class="fa-solid fa-plus text-sm"></i> <?php echo e(__('Invest Now')); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- History & Archive Tab -->
    <div x-show="activeTab === 'history'" x-transition.opacity x-cloak>
        <?php if (!empty($completed) || !empty($cancelled)): ?>
            <div class="bg-brand-card rounded-2xl border border-zinc-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-800/80 text-[10px] font-bold text-zinc-500 uppercase tracking-widest bg-zinc-900/30">
                                <th class="p-4 pl-6 whitespace-nowrap"><?php echo e(__('Investment Plan')); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo e(__('Date Started')); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo e(__('Date Ended')); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo e(__('Initial Capital')); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo e(__('Total Return')); ?></th>
                                <th class="p-4 pr-6 text-right whitespace-nowrap"><?php echo e(__('Status')); ?></th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach (array_merge($completed, $cancelled) as $hist):
                                $status_color = $hist['status'] === 'completed' ? 'emerald' : 'zinc';
                                $status_label = $hist['status'] === 'completed' ? __('Completed') : __('Cancelled');
                                $total_return = ($hist['total_profit_earned'] ?? 0) + ($hist['status'] === 'cancelled' ? 0 : $hist['amount']);
                            ?>
                                <tr class="border-b border-zinc-800/40 hover:bg-zinc-800/20 transition-colors">
                                    <td class="p-4 pl-6 font-medium text-zinc-200">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-zinc-800 flex items-center justify-center text-zinc-400">
                                                <i class="fa-solid fa-history text-xs"></i>
                                            </div>
                                            <?php echo e($hist['plan_name']); ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-zinc-400"><?php echo e(format_date($hist['created_at'], 'M d, Y')); ?></td>
                                    <td class="p-4 text-zinc-400"><?php echo e(format_date($hist['end_date'], 'M d, Y')); ?></td>
                                    <td class="p-4 text-zinc-100 font-medium" x-text="formatCurrency(<?php echo $hist['amount']; ?>)"></td>
                                    <td class="p-4 text-brand-accent font-semibold" x-text="'+' + formatCurrency(<?php echo $total_return; ?>)"></td>
                                    <td class="p-4 pr-6 text-right">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-<?php echo e($status_color); ?>-500/10 text-<?php echo e($status_color); ?>-500 border border-<?php echo e($status_color); ?>-500/20 uppercase tracking-wider">
                                            <?php echo e($status_label); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-brand-card rounded-3xl p-8 border border-zinc-800 flex flex-col items-center justify-center text-center py-16 relative overflow-hidden">
                <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-zinc-800/10 rounded-full blur-xl"></div>
                <div class="w-14 h-14 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                    <i class="fa-solid fa-history text-xl"></i>
                </div>
                <h4 class="text-zinc-50 font-bold mb-1"><?php echo e(__('No History Yet')); ?></h4>
                <p class="text-zinc-500 text-sm"><?php echo e(__('Your completed investments will appear here.')); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Manage / Cancel Modal -->
    <div x-show="showCancelModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="top: 0;" @keydown.escape.window="closeModal()">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="closeModal()"></div>

        <div class="relative w-full max-w-md bg-brand-card border border-zinc-800 rounded-3xl shadow-2xl transform transition-transform duration-300 p-6 flex flex-col max-h-[90vh] overflow-y-auto">
            <button type="button" @click="closeModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 hover:text-white transition-colors">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>

            <div class="flex flex-col items-center text-center mb-6 pt-2">
                <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mb-4 shadow-[0_0_15px_rgba(99,102,241,0.1)]">
                    <i class="fa-solid fa-chart-line text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-zinc-50" x-text="selectedInvestment?.plan_name ?? ''"></h2>
                <span class="mt-2 px-3 py-1 bg-brand-accent/10 text-brand-accent text-xs font-bold rounded-md font-mono border border-brand-accent/20">
                    #INV-<span x-text="selectedInvestment?.id ?? ''"></span>
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-zinc-900/50 border border-zinc-800/80 rounded-2xl p-4 text-center">
                    <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1"><?php echo e(__('Invested')); ?></p>
                    <p class="text-xl font-bold text-zinc-100" x-text="selectedInvestment ? formatCurrency(parseFloat(selectedInvestment.amount)) : ''"></p>
                </div>
                <div class="bg-zinc-900/50 border border-zinc-800/80 rounded-2xl p-4 text-center">
                    <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1"><?php echo e(__('Daily ROI')); ?></p>
                    <p class="text-xl font-bold text-brand-accent" x-text="selectedInvestment ? selectedInvestment.roi_percentage + '%' : ''"></p>
                </div>
            </div>

            <div class="space-y-3 mb-6 px-2">
                <div class="flex justify-between items-center text-sm border-b border-zinc-800/60 pb-3">
                    <span class="text-zinc-400"><?php echo e(__('Start Date')); ?></span>
                    <span class="text-zinc-100 font-medium" x-text="selectedInvestment ? new Date(selectedInvestment.created_at).toLocaleDateString() : ''"></span>
                </div>
                <div class="flex justify-between items-center text-sm border-b border-zinc-800/60 pb-3">
                    <span class="text-zinc-400"><?php echo e(__('End Date')); ?></span>
                    <span class="text-zinc-100 font-medium" x-text="selectedInvestment ? new Date(selectedInvestment.end_date).toLocaleDateString() : ''"></span>
                </div>
                <div class="flex justify-between items-center text-sm pb-1">
                    <span class="text-zinc-400"><?php echo e(__('Profit Earned')); ?></span>
                    <span class="text-brand-accent font-semibold" x-text="selectedInvestment ? formatCurrency(parseFloat(selectedInvestment.total_profit_earned || 0)) : ''"></span>
                </div>
            </div>

            <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-5 mb-6" x-show="selectedInvestment">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                    <h4 class="font-bold text-amber-500 text-sm"><?php echo e(__('Cancellation Penalty')); ?></h4>
                </div>
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-amber-500/70"><?php echo e(__('Original Amount')); ?>:</span>
                        <span class="text-amber-500 font-semibold" x-text="selectedInvestment ? formatCurrency(parseFloat(selectedInvestment.amount)) : ''"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-amber-500/70"><?php echo e(__('Penalty')); ?>:</span>
                        <span class="text-rose-400 font-bold" x-text="selectedInvestment ? '-' + formatCurrency(parseFloat(penaltyBreakdown.penalty || 0)) : ''"></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-amber-500/20">
                        <span class="text-amber-500 font-bold"><?php echo e(__('Refund Amount')); ?>:</span>
                        <span class="text-brand-accent font-bold" x-text="selectedInvestment ? formatCurrency(parseFloat(penaltyBreakdown.refund || 0)) : ''"></span>
                    </div>
                </div>
                <p class="text-amber-500/60 text-xs italic" x-show="penaltyBreakdown.forfeitProfits">
                    <?php echo e(__('Note: Earned profits will not be returned.')); ?>
                </p>
            </div>

            <div class="flex gap-3 mt-auto">
                <button type="button" @click="closeModal()" class="flex-1 py-3.5 bg-transparent border border-zinc-700 text-zinc-300 font-bold rounded-xl hover:bg-zinc-800 transition-colors">
                    <?php echo e(__('Close')); ?>
                </button>
                <form method="POST" action="/actions/cancel-investment.php" class="flex-[2]">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="investment_id" x-bind:value="selectedInvestment?.id ?? ''">
                    <button type="submit" class="w-full py-3.5 bg-rose-500 text-white font-bold rounded-xl hover:bg-rose-600 transition-colors shadow-[0_0_15px_rgba(244,63,94,0.3)] flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!selectedInvestment || isCancelBlockedByRule(selectedInvestment)">
                        <i class="fa-solid fa-xmark text-sm"></i> <?php echo e(__('Cancel Investment')); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>