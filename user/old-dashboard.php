<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Dashboard');
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

// BTC conversion removed — currency is admin-configured

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Welcome Header with Currency Toggle -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Welcome back!'); ?> 👋</h3>
        <p class="text-secondary mb-0 small"><?php echo __("Here's your portfolio performance."); ?></p>
    </div>
    <div>
        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
        </button>
    </div>
</div>



<!-- Main Dashboard Content -->
<div class="row g-3 g-md-4 mb-5">
    <!-- Wallet Card -->
    <div class="col-lg-6 col-xl-5">
        <div class="wallet-card h-100 d-flex flex-column justify-content-between">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-white-50 text-uppercase small fw-bold" style="letter-spacing: 1px"><?php echo __('Total Balance'); ?></span>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <h2 class="fw-bold mb-0 wallet-balance-text" x-show="showBalance" x-text="formatCurrency(<?php echo $balance; ?>)"></h2>
                        <h2 class="fw-bold mb-0 wallet-balance-text" x-show="!showBalance">••••••••</h2>
                        <button class="btn btn-sm text-white-50 p-0 ms-2" @click="toggleBalance()">
                            <i class="fas" :class="showBalance ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div class="bg-white bg-opacity-25 rounded-circle px-3 py-4 d-flex">
                    <i class="fas fa-wallet fa-lg"></i>
                </div>
            </div>

            <div class="mt-4">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="/user/deposit" class="btn btn-light w-100 fw-bold text-primary shadow-sm py-2 text-decoration-none">
                            <i class="fas fa-arrow-down me-2"></i> <?php echo __('Deposit'); ?>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/user/withdraw" class="btn btn-outline-light w-100 fw-bold border-2 py-2 text-decoration-none">
                            <i class="fas fa-arrow-up me-2"></i> <?php echo __('Withdraw'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="col-lg-6 col-xl-7">
        <div class="row g-3 g-md-4 h-100">
            <!-- Total Profit -->
            <div class="col-sm-6 col-md-6">
                <div class="card-custom d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 text-success p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.5rem">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-secondary small mb-0 fw-bold text-uppercase text-truncate"><?php echo __('Total Profit'); ?></p>
                        <h3 class="fw-bold text-dark mb-0 text-truncate" x-show="showBalance" x-text="formatCurrency(<?php echo $total_profit; ?>)"></h3>
                        <h3 class="fw-bold text-dark mb-0 text-truncate" x-show="!showBalance">••••</h3>
                        <small class="text-success fw-bold"><i class="fas fa-arrow-up"></i> <?php echo __('All time'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Active Investments -->
            <div class="col-sm-6 col-md-6">
                <div class="card-custom d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-warning bg-opacity-10 text-warning p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.5rem">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <p class="text-secondary small mb-0 fw-bold text-uppercase text-truncate"><?php echo __('Active Plans'); ?></p>
                        <h3 class="fw-bold text-dark mb-0"><?php echo count($active_investments); ?></h3>
                        <small class="text-muted"><?php echo __('Running now'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Referral Program -->
            <div class="col-12">
                <div class="card-custom bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle px-3 py-4 d-flex">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0"><?php echo __('Referral Program'); ?></h6>
                            <small class="text-secondary"><?php echo __('You earned'); ?> <span class="text-success fw-bold" x-text="formatCurrency(<?php echo $referral_earnings; ?>)"></span> <?php echo __('from'); ?> <?php echo $referral_count; ?> <?php echo __('invites'); ?></small>
                        </div>
                    </div>
                    <a href="/user/referrals" class="btn btn-sm btn-outline-primary rounded-pill px-3 ms-auto"><?php echo __('View Link'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Investments Section -->
<div class="d-flex justify-content-between align-items-center mb-3 px-1">
    <h5 class="fw-bold text-dark mb-0"><?php echo __('Active Investments'); ?></h5>
    <?php if ($active_investments && count($active_investments)): ?>
        <a href="/user/my-investments" class="btn btn-sm btn-outline-primary rounded-pill px-3"><?php echo __('View Portfolio'); ?></a>
    <?php endif; ?>
</div>
<div class="row g-3 g-md-4 mb-5">
    <?php if ($active_investments && count($active_investments)):
        $colors = ['primary', 'info', 'success', 'warning'];
        $color_index = 0;
        foreach ($active_investments as $inv):
            $color = $colors[$color_index % count($colors)];
            $color_index++;

            // Calculate progress
            $start = strtotime($inv['created_at']);
            $end = strtotime($inv['end_date']);
            $now = time();
            $total_duration = $end - $start;
            $elapsed = $now - $start;
            $progress = $total_duration > 0 ? min(100, max(0, ($elapsed / $total_duration) * 100)) : 0;

            // Calculate days
            $total_days = ceil($total_duration / 86400);
            $elapsed_days = floor($elapsed / 86400);

            // Calculate earned profit (use total_profit_earned column; keep fallback)
            $earned = isset($inv['total_profit_earned']) ? $inv['total_profit_earned'] : (isset($inv['total_profit']) ? $inv['total_profit'] : 0);
    ?>
            <div class="col-md-6 col-xl-4">
                <div class="card-custom border-top border-4 border-<?php echo $color; ?> pt-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> mb-2"><?php echo e($inv['plan_name'] ?? __('Investment Plan')); ?></span>
                            <h4 class="fw-bold" x-show="showBalance" x-text="formatCurrency(<?php echo $inv['amount']; ?>)"></h4>
                            <h4 class="fw-bold" x-show="!showBalance">••••••</h4>
                        </div>
                        <div class="text-end">
                            <small class="d-block text-secondary"><?php echo __('ROI'); ?></small>
                            <span class="fw-bold text-success"><?php echo e($inv['roi_percentage'] ?? '0'); ?>%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-secondary"><?php echo __('Progress'); ?></span>
                            <span class="fw-bold text-<?php echo $color; ?>"><?php echo __('Day'); ?> <?php echo $elapsed_days; ?> <?php echo __('of'); ?> <?php echo $total_days; ?></span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar bg-<?php echo $color; ?> rounded-pill" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="bg-light rounded p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-secondary d-block" style="font-size: 0.7rem"><?php echo __('NEXT PAYOUT'); ?></small>
                            <span class="fw-bold font-monospace countdown-timer" data-target="<?php echo $inv['next_payout_date']; ?>">--h --m --s</span>
                        </div>
                        <div class="text-end">
                            <small class="text-secondary d-block" style="font-size: 0.7rem"><?php echo __('PROFIT EARNED'); ?></small>
                            <span class="fw-bold text-success" x-show="showBalance" x-text="'+' + formatCurrency(<?php echo $earned; ?>)"></span>
                            <span class="fw-bold text-success" x-show="!showBalance">••••</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- Empty State Card -->
        <div class="col-md-6 col-xl-4">
            <div class="card-custom d-flex flex-column align-items-center justify-content-center text-center p-5 h-100">
                <div class="bg-light rounded-circle p-3 mb-3 text-secondary d-flex">
                    <i class="fas fa-chart-pie fa-2x opacity-25"></i>
                </div>
                <h6 class="fw-bold text-dark"><?php echo __('No Active Investments'); ?></h6>
                <p class="small text-muted mb-0"><?php echo __('Start investing to see your portfolio here.'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Add New Investment Card -->
    <div class="col-md-6 col-xl-4">
        <a href="/user/invest" class="text-decoration-none">
            <div class="card-custom border-dashed border-2 d-flex flex-column align-items-center justify-content-center text-center p-5 h-100" style="background: transparent; border-style: dashed; border-color: #cbd5e1">
                <div class="bg-white rounded-circle shadow-sm px-3 py-4 mb-3 text-primary d-flex">
                    <i class="fas fa-plus fa-lg"></i>
                </div>
                <h6 class="fw-bold text-dark"><?php echo __('Open New Investment'); ?></h6>
                <p class="small text-muted mb-3"><?php echo __('Choose a plan to start earning.'); ?></p>
                <button class="btn btn-primary rounded-pill px-4 btn-sm fw-bold"><?php echo __('View Plans'); ?></button>
            </div>
        </a>
    </div>
</div>

<!-- Bottom Section: Chart and Activity -->
<div class="row g-4">
    <!-- Profit Chart -->
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold m-0"><?php echo __('Profit Growth'); ?></h6>
                <?php if ($has_profit_chart_data && count($series_30d) > 0): ?>
                    <select id="chartPeriod" class="form-select form-select-sm w-auto border-0 bg-light fw-bold text-secondary" onchange="updateChartPeriod(this.value)">
                        <option value="7"><?php echo __('Last 7 Days'); ?></option>
                        <option value="30"><?php echo __('Last 30 Days'); ?></option>
                    </select>
                <?php endif; ?>
            </div>
            <?php if ($has_profit_chart_data): ?>
                <div id="profitChart" style="min-height: 300px"
                    data-labels-7d='<?php echo json_encode($labels_7d); ?>'
                    data-series-7d='<?php echo json_encode($series_7d); ?>'
                    data-labels-30d='<?php echo json_encode($labels_30d); ?>'
                    data-series-30d='<?php echo json_encode($series_30d); ?>'>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center text-center py-5">
                    <div class="bg-light rounded-circle p-3 mb-3 text-secondary d-flex">
                        <i class="fas fa-chart-line fa-2x opacity-25"></i>
                    </div>
                    <h6 class="fw-bold text-dark"><?php echo __('No Profit Data Yet'); ?></h6>
                    <p class="small text-muted mb-0"><?php echo __('Your profit growth chart will appear here once you start earning.'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-4">
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold m-0"><?php echo __('Recent Activity'); ?></h6>
                <a href="/user/transactions" class="small text-decoration-none fw-bold"><?php echo __('View All'); ?></a>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php if ($recent_transactions): foreach ($recent_transactions as $t):
                        $icon_class = 'fa-magic';
                        $bg_class = 'bg-primary';
                        $text_class = 'text-primary';

                        switch ($t['type']) {
                            case 'deposit':
                                $icon_class = 'fa-arrow-down';
                                $bg_class = 'bg-success';
                                $text_class = 'text-success';
                                break;
                            case 'withdrawal':
                                $icon_class = 'fa-arrow-up';
                                $bg_class = 'bg-danger';
                                $text_class = 'text-danger';
                                break;
                            case 'profit':
                                $icon_class = 'fa-magic';
                                $bg_class = 'bg-primary';
                                $text_class = 'text-primary';
                                break;
                            case 'investment':
                                $icon_class = 'fa-chart-line';
                                $bg_class = 'bg-info';
                                $text_class = 'text-info';
                                break;
                            case 'referral':
                                $icon_class = 'fa-users';
                                $bg_class = 'bg-warning';
                                $text_class = 'text-warning';
                                break;
                        }

                        $is_last = $t === end($recent_transactions);
                        $border_class = $is_last ? '' : 'border-bottom border-light pb-3';
                ?>
                        <?php
                        // Determine status badge color - light background with dark text same color
                        $status_bg = 'bg-light';
                        $status_text = 'text-secondary';

                        switch (strtolower($t['status'])) {
                            case 'pending':
                                $status_bg = 'bg-warning bg-opacity-10';
                                $status_text = 'text-warning';
                                break;
                            case 'approved':
                            case 'completed':
                            case 'success':
                                $status_bg = 'bg-success bg-opacity-10';
                                $status_text = 'text-success';
                                break;
                            case 'rejected':
                            case 'failed':
                            case 'cancelled':
                                $status_bg = 'bg-danger bg-opacity-10';
                                $status_text = 'text-danger';
                                break;
                            case 'processing':
                                $status_bg = 'bg-info bg-opacity-10';
                                $status_text = 'text-info';
                                break;
                        }
                        ?>
                        <div class="d-flex align-items-center justify-content-between <?php echo $border_class; ?> activity-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="<?php echo $bg_class; ?> bg-opacity-10 <?php echo $text_class; ?> rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <div style="line-height: 1.2" class="overflow-hidden">
                                    <span class="d-block fw-bold small text-truncate"><?php echo __(e(ucfirst($t['type']))); ?></span>
                                    <small class="text-muted x-small"><?php echo format_date($t['created_at']); ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold <?php echo $text_class; ?> small" x-text="'<?php echo ($t['type'] == 'deposit' || $t['type'] == 'profit' || $t['type'] == 'referral' || $t['type'] == 'refund') ? '+' : '-'; ?>' + formatCurrency(<?php echo $t['amount']; ?>)">
                                </span>
                                <br />
                                <span class="badge <?php echo $status_bg; ?> <?php echo $status_text; ?> fw-normal" style="font-size: 0.65rem"><?php echo __(e($t['status'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach;
                else: ?>
                    <div class="text-muted text-center py-4">
                        <i class="fas fa-history fa-2x mb-2 opacity-25"></i>
                        <p class="mb-0 small"><?php echo __('No recent activity'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

    // Update chart period
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

    // Helper function to check dark mode status
    function getIsDarkMode() {
        return document.body.classList.contains('dark-mode');
    }

    // Helper function to get chart options based on dark mode
    function getChartOptions(isDark) {
        return {
            theme: {
                mode: isDark ? 'dark' : 'light'
            },
            xaxis: {
                labels: {
                    style: {
                        colors: isDark ? '#94a3b8' : '#64748b',
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: isDark ? '#94a3b8' : '#64748b'
                    }
                }
            },
            grid: {
                borderColor: isDark ? '#334155' : '#f1f5f9'
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light'
            }
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Wait for any dark mode class to be applied
        setTimeout(function() {
            // Chart Configuration
            const chartEl = document.querySelector('#profitChart');
            if (chartEl) {
                const isDark = getIsDarkMode();
                const darkModeOptions = getChartOptions(isDark);

                var options = {
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
                        fontFamily: 'Plus Jakarta Sans, sans-serif',
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
                    theme: darkModeOptions.theme,
                    xaxis: {
                        categories: chartData['7'].labels,
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        labels: darkModeOptions.xaxis.labels,
                    },
                    yaxis: {
                        show: false,
                        labels: darkModeOptions.yaxis.labels,
                    },
                    grid: {
                        borderColor: darkModeOptions.grid.borderColor,
                        strokeDashArray: 4,
                        padding: {
                            left: 10,
                            right: 0
                        },
                    },
                    tooltip: {
                        theme: darkModeOptions.tooltip.theme,
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

                // Listen for system theme changes
                const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                darkModeQuery.addEventListener('change', function(e) {
                    // For system mode, trust the OS preference (e.matches)
                    const newIsDark = e.matches;
                    const newOptions = getChartOptions(newIsDark);

                    profitChart.updateOptions({
                        theme: newOptions.theme,
                        xaxis: {
                            labels: newOptions.xaxis.labels
                        },
                        yaxis: {
                            labels: newOptions.yaxis.labels
                        },
                        grid: newOptions.grid,
                        tooltip: newOptions.tooltip
                    });
                });
            }
        }, 100); // Small delay to ensure dark mode class is applied

        // Countdown timers
        const timers = document.querySelectorAll('.countdown-timer');
        timers.forEach(el => {
            const targetDate = el.getAttribute('data-target');
            if (targetDate && typeof createCountdownTimer === 'function') {
                createCountdownTimer(targetDate, (time, expired) => {
                    if (expired) {
                        el.textContent = <?php echo json_encode(__("Due")); ?>;
                        el.classList.add('text-success');
                    } else {
                        el.textContent = time;
                        el.classList.remove('text-success');
                    }
                });
            }
        });
    });
</script>

<?php require ROOT . '/includes/footer.php'; ?>