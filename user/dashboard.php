<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Dashboard');
$active_nav = 'dashboard';
$user_id = $_SESSION['user_id'];

// Data
$balance = get_available_balance($user_id);
$locked_balance = get_locked_balance($user_id);
$available_balance = $balance - $locked_balance;
$active_investments = db_query("SELECT i.*, p.name as plan_name, p.roi_percentage FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.user_id = ? AND i.status = 'active'", [$user_id]);

// Get recent transactions and pending deposits for activity feed
$recent_transactions = db_query("SELECT * FROM transactions WHERE user_id = ? AND NOT (type = 'deposit' AND status = 'pending') ORDER BY created_at DESC LIMIT 5", [$user_id]);
$pending_deposits = db_query("SELECT id, amount, status, payment_method as details, created_at, 'deposit' as type FROM deposits WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 5", [$user_id]);

// Combine and sort by date
$all_activity = array_merge($recent_transactions, $pending_deposits);
usort($all_activity, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recent_transactions = array_slice($all_activity, 0, 5);
$total_profit_row = db_query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id = ? AND type = 'profit'", [$user_id]);
$total_profit = $total_profit_row && count($total_profit_row) ? $total_profit_row[0]['total'] : 0;

// Referral earnings
$referral_stats = db_query("SELECT COALESCE(SUM(bonus_amount),0) as total, COUNT(*) as count FROM referrals WHERE referrer_id = ?", [$user_id]);
$referral_earnings = $referral_stats && count($referral_stats) ? $referral_stats[0]['total'] : 0;
$referral_count = $referral_stats && count($referral_stats) ? $referral_stats[0]['count'] : 0;

// Chart data for last 7 days
$profit_rows_7d = db_query("SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM transactions WHERE user_id = ? AND type = 'profit' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d ASC", [$user_id]);
$labels_7d = [];
$series_7d = [];
if ($profit_rows_7d && count($profit_rows_7d)) {
    foreach ($profit_rows_7d as $r) {
        $labels_7d[] = date('D', strtotime($r['d']));
        $series_7d[] = (float)$r['s'];
    }
}

// Chart data for last 30 days
$profit_rows_30d = db_query("SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM transactions WHERE user_id = ? AND type = 'profit' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d ASC", [$user_id]);
$labels_30d = [];
$series_30d = [];
if ($profit_rows_30d && count($profit_rows_30d)) {
    foreach ($profit_rows_30d as $r) {
        $labels_30d[] = date('M d', strtotime($r['d']));
        $series_30d[] = (float)$r['s'];
    }
}

// Determine if we have data for chart
$has_profit_chart_data = count($series_7d) > 0 || count($series_30d) > 0;

// Default to 7 day data
$labels = count($labels_7d) > 0 ? $labels_7d : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$series = count($series_7d) > 0 ? $series_7d : [0, 0, 0, 0, 0, 0, 0];

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    // Chart data storage
    let profitChart = null;
    const chartData = {
        '7': {
            labels: <?php echo json_encode(count($labels_7d) > 0 ? $labels_7d : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']); ?>,
            series: <?php echo json_encode(count($series_7d) > 0 ? $series_7d : [0, 0, 0, 0, 0, 0, 0]); ?>
        },
        '30': {
            labels: <?php echo json_encode(count($labels_30d) > 0 ? $labels_30d : []); ?>,
            series: <?php echo json_encode(count($series_30d) > 0 ? $series_30d : []); ?>
        }
    };

    function updateChartPeriod(period) {
        if (!profitChart || !chartData[period]) return;
        profitChart.updateOptions({
            xaxis: {
                categories: chartData[period].labels
            }
        });
        profitChart.updateSeries([{
            data: chartData[period].series
        }]);
    }

    function getChartOptions() {
        return {
            theme: {
                mode: 'dark'
            },
            xaxis: {
                labels: {
                    style: {
                        colors: '#94a3b8',
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#94a3b8'
                    }
                }
            },
            grid: {
                borderColor: '#334155'
            },
            tooltip: {
                theme: 'dark'
            }
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Chart Configuration
        const chartEl = document.querySelector('#profitChart');
        if (chartEl) {
            const darkOptions = getChartOptions();
            const options = {
                series: [{
                    name: <?php echo json_encode(__('Profit')); ?>,
                    data: chartData['7'].series,
                }],
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'Outfit, sans-serif',
                    parentHeightOffset: 0,
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.5,
                        opacityTo: 0.05,
                        stops: [0, 90, 100],
                    },
                },
                dataLabels: {
                    enabled: false
                },
                colors: ['#4f46e5'],
                theme: darkOptions.theme,
                xaxis: {
                    categories: chartData['7'].labels,
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: darkOptions.xaxis.labels,
                },
                yaxis: {
                    show: false,
                    labels: darkOptions.yaxis.labels,
                },
                grid: {
                    borderColor: darkOptions.grid.borderColor,
                    strokeDashArray: 4,
                    padding: {
                        left: 10,
                        right: 0
                    },
                },
                tooltip: {
                    theme: darkOptions.tooltip.theme,
                    y: {
                        formatter: function(val) {
                            const alpineData = document.querySelector('[x-data]')?._x_dataStack?.[0];
                            const symbol = alpineData?.currencySymbol || <?php echo json_encode(get_currency_symbol()); ?>;
                            const precision = (alpineData && typeof alpineData.precision !== 'undefined') ? alpineData.precision : <?php echo (get_currency_code() === 'BTC' ? 8 : 2); ?>;
                            return symbol + val.toFixed(precision);
                        }
                    }
                },
            };

            profitChart = new ApexCharts(chartEl, options);
            profitChart.render();
        }

        // Countdown timers
        const timers = document.querySelectorAll('.countdown-timer');
        timers.forEach(el => {
            const targetDate = el.getAttribute('data-target');
            if (targetDate && typeof createCountdownTimer === 'function') {
                createCountdownTimer(targetDate, (time, expired) => {
                    if (expired) {
                        el.textContent = <?php echo json_encode(__("Due")); ?>;
                        el.classList.add('text-brand-accent');
                    } else {
                        el.textContent = time;
                        el.classList.remove('text-brand-accent');
                    }
                });
            }
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<!-- Page header -->
<header class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 border-b border-zinc-900 pb-4">
    <div class="flex-shrink-0">
        <h1 class="text-3xl font-bold text-zinc-50 mb-1 tracking-tight"><?php echo __('Welcome back!'); ?> <span class="text-2xl">👋</span></h1>
        <p class="text-zinc-400 text-sm"><?php echo __("Here's your portfolio performance."); ?></p>
    </div>

    <div class="flex-1 max-w-md flex items-center justify-between gap-3 px-5 py-3 bg-zinc-900/50 border border-zinc-800 rounded-2xl shadow-sm hover:border-zinc-700 transition-colors">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-brand-accent/10 border border-brand-accent/20 flex items-center justify-center text-brand-accent">
                <i class="fa-solid fa-users text-sm"></i>
            </div>
            <div>
                <p class="text-zinc-400 text-[10px] font-bold uppercase tracking-wider mb-0.5"><?php echo __('Referral Program'); ?></p>
                <p class="text-brand-accent text-sm font-bold">
                    <?php echo __('Earned'); ?> <span x-text="formatCurrency(<?php echo $referral_earnings; ?>)"></span>
                    <span class="text-zinc-500 text-xs font-normal"><?php echo __('from'); ?> <?php echo e($referral_count); ?> <?php echo __('invites'); ?></span>
                </p>
            </div>
        </div>
        <a href="/user/referrals" class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-medium rounded-lg transition-colors border border-zinc-700"><?php echo __('Copy Link'); ?></a>
    </div>

    <!-- Market ticker -->
    <div class="flex-shrink-0 flex gap-4 text-xs font-mono bg-zinc-900 border border-zinc-800/80 rounded-xl px-4 py-3 shadow-[0_0_15px_rgba(0,0,0,0.2)]">
        <span class="flex items-center gap-2">
            <i class="fa-brands fa-bitcoin text-brand-accent"></i>
            <span>$45,230 <span class="text-brand-accent text-[10px]">▲ 2.4%</span></span>
        </span>
        <span class="text-zinc-800">|</span>
        <span class="flex items-center gap-2">
            <i class="fa-brands fa-ethereum text-zinc-400"></i>
            <span>$2,400 <span class="text-brand-accent text-[10px]">▲ 1.8%</span></span>
        </span>
    </div>
</header>

<!-- Dashboard grid -->
<div class="grid grid-cols-1 md:grid-cols-12 gap-6">

    <!-- Total capital card -->
    <div class="col-span-1 md:col-span-8 bg-brand-card rounded-3xl p-8 border border-zinc-800 relative overflow-hidden emerald-glow group">
        <div class="absolute top-0 right-0 w-64 h-64 bg-brand-accent opacity-[0.03] rounded-full blur-3xl group-hover:opacity-[0.05] transition-opacity duration-500"></div>

        <div class="flex flex-col md:flex-row justify-between gap-8 relative z-10 h-full">
            <div class="flex flex-col justify-between">
                <div>
                    <p class="text-zinc-400 text-sm font-medium uppercase tracking-widest mb-2"><?php echo __('Total Balance'); ?></p>
                    <div class="flex items-baseline gap-4">
                        <h2 class="text-4xl lg:text-6xl font-bold text-zinc-50 tracking-tight" x-show="showBalance" x-text="formatCurrency(<?php echo $balance; ?>)"></h2>
                        <h2 class="text-5xl lg:text-6xl font-bold text-zinc-50 tracking-tight" x-show="!showBalance">••••••••</h2>
                        <button class="text-zinc-600 hover:text-zinc-300 transition-colors" @click="toggleBalance()">
                            <i class="fa-solid" :class="showBalance ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="flex gap-3 mt-8">
                    <a href="/user/deposit" class="px-8 py-3.5 bg-brand-accent text-brand-dark font-bold rounded-xl hover:bg-emerald-400 transition-colors flex items-center gap-2 shadow-[0_0_20px_rgba(16,185,129,0.2)]">
                        <i class="fa-solid fa-plus text-sm"></i> <?php echo __('Deposit'); ?>
                    </a>
                    <a href="/user/withdraw" class="flex-1 px-3 md:px-8 py-3.5 bg-zinc-800 text-zinc-100 font-bold rounded-xl hover:bg-zinc-700 border border-zinc-700 transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i> <?php echo __('Withdraw'); ?>
                    </a>
                </div>
            </div>

            <div class="hidden lg:flex flex-1 border-l border-zinc-800/60 pl-8 flex-col justify-center">
                <div class="flex justify-between text-xs mb-4 text-zinc-500">
                    <span><?php echo __('24h Trend'); ?></span>
                    <span class="text-brand-accent font-semibold"><?php echo __('+2.10%'); ?></span>
                </div>
                <div class="h-24 w-full flex items-end gap-1 opacity-60 group-hover:opacity-90 transition-opacity duration-300">
                    <div class="w-full bg-zinc-800 rounded-t-sm h-[20%]"></div>
                    <div class="w-full bg-zinc-800 rounded-t-sm h-[35%]"></div>
                    <div class="w-full bg-zinc-800 rounded-t-sm h-[25%]"></div>
                    <div class="w-full bg-zinc-800 rounded-t-sm h-[50%]"></div>
                    <div class="w-full bg-brand-accent/50 rounded-t-sm h-[70%]"></div>
                    <div class="w-full bg-brand-accent/80 rounded-t-sm h-[85%]"></div>
                    <div class="w-full bg-brand-accent rounded-t-sm h-[100%] shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Side stat cards -->
    <div class="col-span-1 md:col-span-4 flex flex-col gap-6">
        <div class="bg-brand-card rounded-3xl p-6 border border-zinc-800 flex items-center gap-4 hover:border-zinc-700/80 transition-colors shadow-sm relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-brand-accent/5 rounded-full blur-xl"></div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-brand-accent shadow-[0_0_15px_rgba(16,185,129,0.1)]">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <p class="text-zinc-400 text-xs font-medium uppercase tracking-wider mb-0.5"><?php echo __('Total Profit'); ?></p>
                <h3 class="text-2xl font-bold text-zinc-50" x-show="showBalance" x-text="formatCurrency(<?php echo $total_profit; ?>)"></h3>
                <h3 class="text-2xl font-bold text-zinc-50" x-show="!showBalance">••••</h3>
                <p class="text-brand-accent text-xs font-semibold mt-0.5"><i class="fa-solid fa-arrow-up"></i> <?php echo __('All time'); ?></p>
            </div>
        </div>

        <div class="bg-brand-card rounded-3xl p-6 border border-zinc-800 flex items-center gap-4 hover:border-zinc-700/80 transition-colors shadow-sm">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                <i class="fa-solid fa-layer-group text-xl"></i>
            </div>
            <div>
                <p class="text-zinc-400 text-xs font-medium uppercase tracking-wider mb-0.5"><?php echo __('Active Plans'); ?></p>
                <h3 class="text-2xl font-bold text-zinc-50"><?php echo e(count($active_investments)); ?></h3>
                <p class="text-zinc-500 text-xs mt-0.5"><?php echo __('Running now'); ?></p>
            </div>
        </div>
    </div>

    <!-- Active investments section -->
    <div class="col-span-1 md:col-span-12 flex justify-between items-center">
        <h3 class="text-zinc-50 font-bold tracking-tight"><?php echo __('Active Investments'); ?></h3>
        <?php if ($active_investments && count($active_investments)): ?>
            <a href="/user/my-investments" class="text-brand-accent text-sm font-semibold hover:text-emerald-400 transition-colors"><?php echo __('View Portfolio'); ?></a>
        <?php endif; ?>
    </div>

    <div class="col-span-1 md:col-span-12 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-<?php echo count($active_investments) > 1 ? '3' : '2'; ?> gap-6">
        <?php if ($active_investments && count($active_investments)):
            $colors = ['emerald', 'sky', 'amber', 'violet'];
            $color_index = 0;
            foreach ($active_investments as $inv):
                $color = $colors[$color_index % count($colors)];
                $color_index++;

                $start = strtotime($inv['created_at']);
                $end = strtotime($inv['end_date']);
                $now = time();
                $total_duration = $end - $start;
                $elapsed = $now - $start;
                $progress = $total_duration > 0 ? min(100, max(0, ($elapsed / $total_duration) * 100)) : 0;
                $total_days = ceil($total_duration / 86400);
                $elapsed_days = floor($elapsed / 86400);
                $earned = isset($inv['total_profit_earned']) ? $inv['total_profit_earned'] : (isset($inv['total_profit']) ? $inv['total_profit'] : 0);
        ?>
                <div class="bg-brand-card rounded-3xl p-6 border border-zinc-800 hover:border-zinc-700/80 transition-colors shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-<?php echo $color; ?>-500"></div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-<?php echo $color; ?>-500/10 text-<?php echo $color; ?>-500 border border-<?php echo $color; ?>-500/20 mb-2"><?php echo e($inv['plan_name'] ?? __('Investment Plan')); ?></span>
                            <h4 class="text-2xl font-bold text-zinc-50" x-show="showBalance" x-text="formatCurrency(<?php echo $inv['amount']; ?>)"></h4>
                            <h4 class="text-2xl font-bold text-zinc-50" x-show="!showBalance">••••••</h4>
                        </div>
                        <div class="text-right">
                            <small class="block text-zinc-500"><?php echo __('ROI'); ?></small>
                            <span class="font-bold text-brand-accent"><?php echo e($inv['roi_percentage'] ?? '0'); ?>%</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between text-xs mb-2 text-zinc-400">
                            <span><?php echo __('Progress'); ?></span>
                            <span class="font-bold text-<?php echo $color; ?>-500"><?php echo __('Day'); ?> <?php echo e($elapsed_days); ?> <?php echo __('of'); ?> <?php echo e($total_days); ?></span>
                        </div>
                        <div class="h-2 w-full bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-<?php echo $color; ?>-500 rounded-full" style="width: <?php echo e($progress); ?>%"></div>
                        </div>
                    </div>

                    <div class="bg-zinc-900/50 rounded-2xl p-4 flex justify-between items-center border border-zinc-800/60">
                        <div>
                            <small class="text-zinc-500 block text-[10px] uppercase tracking-wider"><?php echo __('Next Payout'); ?></small>
                            <span class="font-bold font-mono text-zinc-200 countdown-timer" data-target="<?php echo e($inv['next_payout_date']); ?>">--:--:--</span>
                        </div>
                        <div class="text-right">
                            <small class="text-zinc-500 block text-[10px] uppercase tracking-wider"><?php echo __('Profit Earned'); ?></small>
                            <span class="font-bold text-brand-accent" x-show="showBalance" x-text="'+' + formatCurrency(<?php echo $earned; ?>)"></span>
                            <span class="font-bold text-brand-accent" x-show="!showBalance">••••</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-brand-card rounded-3xl p-8 border border-zinc-800 flex flex-col items-center justify-center text-center h-64 relative overflow-hidden">
                <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-zinc-800/10 rounded-full blur-xl"></div>
                <div class="w-14 h-14 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                    <i class="fa-solid fa-chart-pie text-xl"></i>
                </div>
                <h4 class="text-zinc-50 font-bold mb-1"><?php echo __('No Active Investments'); ?></h4>
                <p class="text-zinc-500 text-sm"><?php echo __('Start investing to see your portfolio here.'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Open New Investment card -->
        <a href="/user/invest" class="block">
            <div class="bg-zinc-900/50 rounded-3xl p-8 border-2 border-dashed border-zinc-800 flex flex-col items-center justify-center text-center h-64 hover:border-brand-accent/40 hover:bg-zinc-900/80 transition-all group cursor-pointer relative">
                <div class="w-12 h-12 rounded-full bg-brand-accent text-brand-dark flex items-center justify-center mb-4 group-hover:scale-110 transition-transform emerald-glow-strong">
                    <i class="fa-solid fa-plus text-lg"></i>
                </div>
                <h4 class="text-zinc-50 font-bold mb-1"><?php echo __('Open New Investment'); ?></h4>
                <p class="text-zinc-500 text-sm mb-5"><?php echo __('Choose a plan to start earning.'); ?></p>
                <span class="px-6 py-2 bg-brand-accent text-brand-dark font-semibold rounded-full text-sm hover:bg-emerald-400 transition-colors shadow-lg"><?php echo __('View Plans'); ?></span>
            </div>
        </a>
    </div>

    <!-- Profit Growth chart -->
    <div class="col-span-1 md:col-span-8 bg-brand-card rounded-3xl p-6 sm:p-8 border border-zinc-800 flex flex-col emerald-glow relative min-h-[380px]">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 bg-brand-accent/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex justify-between items-center mb-6 relative z-10">
            <h3 class="text-zinc-50 font-bold tracking-tight"><?php echo __('Profit Growth'); ?></h3>
            <?php if ($has_profit_chart_data && count($series_30d) > 0): ?>
                <select id="chartPeriod" class="bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs font-semibold rounded-lg px-3 py-2 focus:outline-none focus:border-brand-accent" onchange="updateChartPeriod(this.value)">
                    <option value="7"><?php echo __('Last 7 Days'); ?></option>
                    <option value="30"><?php echo __('Last 30 Days'); ?></option>
                </select>
            <?php endif; ?>
        </div>

        <?php if ($has_profit_chart_data): ?>
            <div id="profitChart" class="flex-1 relative z-10"
                data-labels-7d='<?php echo json_encode($labels_7d); ?>'
                data-series-7d='<?php echo json_encode($series_7d); ?>'
                data-labels-30d='<?php echo json_encode($labels_30d); ?>'
                data-series-30d='<?php echo json_encode($series_30d); ?>'>
            </div>
        <?php else: ?>
            <div class="flex-1 flex flex-col items-center justify-center text-center min-h-[250px] py-10 relative z-10">
                <div class="w-16 h-16 rounded-2xl bg-zinc-800/40 border border-zinc-700/40 flex items-center justify-center text-zinc-600 mb-4">
                    <i class="fa-solid fa-arrow-trend-up text-3xl"></i>
                </div>
                <h4 class="text-zinc-100 font-bold mb-1"><?php echo __('No Profit Data Yet'); ?></h4>
                <p class="text-zinc-500 text-sm max-w-xs"><?php echo __('Your profit growth chart will appear here once you start earning.'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="col-span-1 md:col-span-4 bg-brand-card rounded-3xl p-6 border border-zinc-800 flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-zinc-50 font-bold tracking-tight"><?php echo __('Recent Activity'); ?></h3>
            <a href="/user/transactions" class="text-brand-accent text-sm font-semibold hover:text-emerald-400 transition-colors"><?php echo __('View All'); ?></a>
        </div>

        <div class="space-y-3 flex-1">
            <?php if ($recent_transactions): foreach ($recent_transactions as $t):
                    $activity_icons = [
                        'deposit'    => 'fa-arrow-down',
                        'withdrawal' => 'fa-arrow-up',
                        'profit'     => 'fa-magic',
                        'investment' => 'fa-chart-line',
                        'referral'   => 'fa-users',
                    ];
                    $activity_colors = [
                        'deposit'    => 'emerald',
                        'withdrawal' => 'rose',
                        'profit'     => 'indigo',
                        'investment' => 'sky',
                        'referral'   => 'amber',
                    ];
                    $type_color = $activity_colors[$t['type']] ?? 'zinc';
                    $icon_class = $activity_icons[$t['type']] ?? 'fa-magic';

                    $status_colors = [
                        'pending'    => 'amber',
                        'approved'   => 'emerald',
                        'completed'  => 'emerald',
                        'success'    => 'emerald',
                        'rejected'   => 'rose',
                        'failed'     => 'rose',
                        'cancelled'  => 'rose',
                        'processing' => 'sky',
                    ];
                    $status_color = $status_colors[strtolower($t['status'])] ?? 'zinc';

                    $amount_sign = ($t['type'] == 'deposit' || $t['type'] == 'profit' || $t['type'] == 'referral' || $t['type'] == 'refund') ? '+' : '-';
            ?>
                    <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-zinc-800/40 border border-transparent hover:border-zinc-800/80 transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-<?php echo e($type_color); ?>-500/10 text-<?php echo e($type_color); ?>-500 flex items-center justify-center border border-<?php echo e($type_color); ?>-500/10">
                                <i class="fa-solid <?php echo e($icon_class); ?> text-xs"></i>
                            </div>
                            <div>
                                <p class="text-zinc-200 font-medium text-sm"><?php echo __(e(ucfirst($t['type']))); ?></p>
                                <p class="text-zinc-500 text-[11px]"><?php echo e(format_date($t['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-<?php echo e($type_color); ?>-500 font-bold text-sm" x-text="'<?php echo $amount_sign; ?>' + formatCurrency(<?php echo $t['amount']; ?>)"></p>
                            <span class="text-[9px] px-2 py-0.5 bg-<?php echo e($status_color); ?>-500/10 text-<?php echo e($status_color); ?>-500 rounded font-medium border border-<?php echo e($status_color); ?>-500/10"><?php echo __(e($t['status'])); ?></span>
                        </div>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="flex flex-col items-center justify-center text-center py-10">
                    <div class="w-12 h-12 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-3">
                        <i class="fa-solid fa-history text-xl"></i>
                    </div>
                    <p class="text-zinc-500 text-sm mb-0"><?php echo __('No recent activity'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>