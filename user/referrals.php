<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/referral-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Referral Program');
$active_nav = 'referrals';
$user_id = $_SESSION['user_id'];

$user = db_query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;
$code = $user['referral_code'] ?? '';
$stats = get_referral_detailed_stats($user_id);
$referral_balance = get_user_referral_balance($user_id);
$available_referral = get_available_referral_balance($user_id);

// Status filter
$valid_statuses = ['all', 'successful', 'active', 'pending'];
$status_filter = $_GET['status'] ?? 'all';
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'all';
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 5;
$offset = ($page - 1) * $per_page;
$total_referrals = get_referral_list_count($user_id, $status_filter);
$total_pages = max(1, ceil($total_referrals / $per_page));
$referred = get_referral_list($user_id, $status_filter, $per_page, $offset);

// Referral fund/withdraw settings
$rfw_mode = get_setting('referral_fund_withdraw_mode', 'exact');
$rfw_exact = (float)get_setting('referral_exact_amount', 0);
$rfw_min = (float)get_setting('referral_min_amount', 0);
$rfw_max = (float)get_setting('referral_max_amount', 0);
$referral_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register?ref=' . $code;

// Referral settings
$referral_bonus_type = get_setting('referral_bonus_type', 'percentage');
$referral_bonus_amount = get_setting('referral_bonus_amount', 5);
$referral_bonus_trigger = get_setting('referral_bonus_trigger', 'registration');

// Trigger-based messaging
$trigger_messages = [
    'registration' => [
        'step2' => __('They Register'),
        'step2_desc' => __('Friends sign up using your link.'),
        'step3' => __('You Earn'),
        'step3_desc' => __('Get bonus when they register.'),
        'commission_desc' => $referral_bonus_type === 'percentage'
            ? __('Earn percentage when friends register')
            : __('Fixed bonus for each registration')
    ],
    'first_deposit' => [
        'step2' => __('They Deposit'),
        'step2_desc' => __('Friends make their first deposit.'),
        'step3' => __('You Earn'),
        'step3_desc' => __('Get bonus on their first deposit.'),
        'commission_desc' => $referral_bonus_type === 'percentage'
            ? __('Earn percentage on first deposits')
            : __('Fixed bonus on first deposits')
    ],
    'first_investment' => [
        'step2' => __('They Invest'),
        'step2_desc' => __('Friends make their first investment.'),
        'step3' => __('You Earn'),
        'step3_desc' => __('Get bonus on their first investment.'),
        'commission_desc' => $referral_bonus_type === 'percentage'
            ? __('Earn percentage on first investments')
            : __('Fixed bonus on first investments')
    ],
    'first_profit' => [
        'step2' => __('They Earn'),
        'step2_desc' => __('Friends receive their first profit.'),
        'step3' => __('You Earn'),
        'step3_desc' => __('Get bonus when they earn profit.'),
        'commission_desc' => $referral_bonus_type === 'percentage'
            ? __('Earn percentage on their profits')
            : __('Fixed bonus when they profit')
    ]
];

$current_messages = $trigger_messages[$referral_bonus_trigger] ?? $trigger_messages['registration'];

// Social share copy
$share_message = __('Join me on this investment platform!');

ob_start();
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['status']) || isset($_GET['page'])): ?>
            const section = document.getElementById('referralHistorySection');
            if (section) {
                section.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        <?php endif; ?>
    });
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<!-- Page Header -->
<header class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 border-b border-zinc-900 pb-4">
    <div>
        <h1 class="text-3xl md:text-4xl font-bold text-zinc-50 mb-1 tracking-tight"><?php echo e(__('Referral Program')); ?></h1>
        <p class="text-zinc-400 text-sm"><?php echo e(__('Invite friends and earn commissions')); ?></p>
    </div>

    <div class="flex items-center gap-3">
        <button class="flex items-center gap-2 px-5 py-2.5 bg-brand-accent text-brand-dark text-sm font-bold rounded-xl hover:bg-emerald-400 transition-colors shadow-[0_0_20px_rgba(16,185,129,0.2)]"
            onclick="document.getElementById('referralHistorySection').scrollIntoView({behavior:'smooth', block:'start'})">
            <i class="fa-solid fa-user-plus"></i>
            <?php echo e(__('Invite Friends')); ?>
        </button>
    </div>
</header>

<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Total Earnings -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Total Earnings')); ?></p>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-brand-accent">
                    <i class="fa-solid fa-vault text-sm"></i>
                </div>
            </div>
            <h3 class="text-2xl font-black text-zinc-50 font-mono tracking-tight" x-text="formatCurrency(<?php echo (float)($stats['total'] ?? 0); ?>)"></h3>
            <?php if (($stats['total'] ?? 0) > 0): ?>
                <p class="text-[11px] text-brand-accent font-medium flex items-center gap-1 mt-1">
                    <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
                    <?php echo e(__('Keep inviting!')); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Referral Balance -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Referral Balance')); ?></p>
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-500">
                    <i class="fa-solid fa-piggy-bank text-sm"></i>
                </div>
            </div>
            <h3 class="text-2xl font-black text-zinc-50 font-mono tracking-tight" x-text="formatCurrency(<?php echo (float)$available_referral; ?>)"></h3>
            <?php if ($referral_balance > $available_referral): ?>
                <p class="text-[11px] text-zinc-500 mt-1">
                    <?php echo e(sprintf(__('Total: %s (pending withdrawals locked)'), format_money($referral_balance))); ?>
                </p>
            <?php endif; ?>
            <div class="flex items-center gap-2 mt-3">
                <button class="px-4 py-2 bg-brand-accent hover:bg-emerald-400 text-brand-dark text-xs font-bold rounded-lg transition-colors"
                    @click="$dispatch('open-fund-modal')">
                    <i class="fa-solid fa-wallet mr-1"></i>
                    <?php echo e(__('Fund Wallet')); ?>
                </button>
                <a href="/user/withdraw?source=referral" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-100 text-xs font-bold rounded-lg transition-colors border border-zinc-700">
                    <i class="fa-solid fa-money-bill-transfer mr-1"></i>
                    <?php echo e(__('Withdraw')); ?>
                </a>
            </div>
        </div>

        <!-- Commission Rate -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-brand-accent/5 rounded-full blur-xl"></div>
            <div class="flex items-center justify-between mb-3 relative z-10">
                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Commission Rate')); ?></p>
                <div class="w-10 h-10 rounded-xl bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400">
                    <i class="fa-solid fa-percentage text-sm"></i>
                </div>
            </div>
            <h3 class="text-2xl font-black text-zinc-50 font-mono tracking-tight relative z-10"
                <?php if ($referral_bonus_type !== 'percentage'): ?>x-text="formatCurrency(<?php echo (float)$referral_bonus_amount; ?>)" <?php endif; ?>>
                <?php echo $referral_bonus_type === 'percentage' ? e($referral_bonus_amount) . '%' : ''; ?>
            </h3>
            <p class="text-[11px] text-zinc-500 mt-1 relative z-10"><?php echo e($current_messages['commission_desc']); ?></p>
        </div>

        <!-- Successful Invites -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div>
                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Successful Invites')); ?></p>
                    <p class="text-2xl font-bold text-zinc-100 font-mono mt-0.5"><?php echo e($stats['successful'] ?? 0); ?></p>
                </div>
                <span class="text-[10px] bg-emerald-500/10 text-brand-accent px-2 py-0.5 rounded font-medium self-start sm:self-center">
                    <?php echo e(__('Users who invested')); ?>
                </span>
            </div>
        </div>

        <!-- Active -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div>
                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Active')); ?></p>
                    <p class="text-2xl font-bold text-zinc-100 font-mono mt-0.5"><?php echo e($stats['active'] ?? 0); ?></p>
                </div>
                <span class="text-[10px] bg-sky-500/10 text-sky-400 px-2 py-0.5 rounded font-medium self-start sm:self-center">
                    <?php echo e(__('Users who deposited')); ?>
                </span>
            </div>
        </div>

        <!-- Pending -->
        <div class="bg-brand-card rounded-2xl p-5 border border-zinc-800 hover:border-zinc-700/80 transition-colors">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div>
                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo e(__('Pending')); ?></p>
                    <p class="text-2xl font-bold text-zinc-100 font-mono mt-0.5"><?php echo e($stats['pending'] ?? 0); ?></p>
                </div>
                <span class="text-[10px] bg-amber-500/10 text-amber-400 px-2 py-0.5 rounded font-medium self-start sm:self-center">
                    <?php echo e(__('Registered only')); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Main asymmetric grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left: How it works + Share -->
        <div class="lg:col-span-4 space-y-6">
            <!-- How it works -->
            <div class="relative overflow-hidden rounded-2xl p-5 bg-gradient-to-tr from-emerald-500 to-emerald-700 text-white shadow-lg">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full"></div>
                <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/5 rounded-full"></div>

                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 relative z-10"><?php echo e(__('How it works')); ?></h3>

                <div class="space-y-4 relative z-10">
                    <div class="flex items-start gap-3.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-[10px] font-bold text-white flex items-center justify-center shrink-0">1</div>
                        <div>
                            <h4 class="text-xs font-bold text-white"><?php echo e(__('Invite Friends')); ?></h4>
                            <p class="text-white/70 text-[11px]"><?php echo e(__('Share your link with friends.')); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-[10px] font-bold text-white flex items-center justify-center shrink-0">2</div>
                        <div>
                            <h4 class="text-xs font-bold text-white"><?php echo e($current_messages['step2']); ?></h4>
                            <p class="text-white/70 text-[11px]"><?php echo e($current_messages['step2_desc']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-[10px] font-bold text-white flex items-center justify-center shrink-0">3</div>
                        <div>
                            <h4 class="text-xs font-bold text-white"><?php echo e($current_messages['step3']); ?></h4>
                            <p class="text-white/70 text-[11px]"><?php echo e($current_messages['step3_desc']); ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($rfw_mode === 'exact' && $rfw_exact > 0): ?>
                    <div class="mt-4 rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-[11px] text-white/90 flex items-start gap-2 relative z-10">
                        <i class="fa-solid fa-info-circle mt-0.5"></i>
                        <span><?php echo e(sprintf(__('Referral fund and withdrawal amounts must be exactly %s'), format_money($rfw_exact))); ?></span>
                    </div>
                <?php elseif ($rfw_mode === 'range'): ?>
                    <div class="mt-4 rounded-xl bg-white/10 border border-white/10 px-3 py-2 text-[11px] text-white/90 flex items-start gap-2 relative z-10">
                        <i class="fa-solid fa-info-circle mt-0.5"></i>
                        <span><?php echo e(sprintf(__('Referral fund and withdrawal amounts must be at least %s'), format_money($rfw_min)) . ($rfw_max > 0 ? ' ' . e(sprintf(__('and at most %s'), format_money($rfw_max))) : '')); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Share link -->
            <div class="glass-panel rounded-2xl p-5 space-y-4 shadow-lg"
                x-data="{ copied: false, link: <?php echo htmlspecialchars(json_encode($referral_url), ENT_QUOTES, 'UTF-8'); ?>, copyToClipboard() { const text = this.link; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }).catch(() => this.fallbackCopy(text)); } else { this.fallbackCopy(text); } }, fallbackCopy(text) { const ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px'; document.body.appendChild(ta); ta.focus(); ta.select(); try { document.execCommand('copy'); this.copied = true; setTimeout(() => this.copied = false, 2000); } catch (err) {} document.body.removeChild(ta); } }">
                <div class="space-y-0.5">
                    <h3 class="text-sm font-bold text-zinc-200 uppercase tracking-wider"><?php echo e(__('Share Your Link')); ?></h3>
                    <p class="text-zinc-500 text-xs"><?php echo e(__('Copy your unique referral link and share it with your network to start earning.')); ?></p>
                </div>

                <div class="flex items-center gap-2 bg-zinc-950 border border-zinc-800 rounded-xl p-1.5 pl-3 focus-within:border-brand-accent/40 transition-colors">
                    <i class="fa-solid fa-link text-zinc-600 text-xs"></i>
                    <input type="text" x-model="link" readonly
                        class="w-full bg-transparent text-xs font-mono font-medium text-zinc-300 focus:outline-none select-all truncate"
                        aria-label="<?php echo e(__('Referral link')); ?>">
                    <button @click="copyToClipboard()"
                        class="shrink-0 px-3.5 py-2 rounded-lg text-xs font-bold transition-all active:scale-95"
                        :class="copied ? 'bg-emerald-400 text-brand-dark' : 'bg-brand-accent text-brand-dark hover:bg-emerald-400'">
                        <span x-show="!copied"><?php echo e(__('Copy')); ?></span>
                        <span x-show="copied"><?php echo e(__('Copied')); ?></span>
                    </button>
                </div>

                <div class="space-y-2">
                    <p class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest px-0.5"><?php echo e(__('Share via')); ?></p>
                    <div class="flex items-center gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>"
                            target="_blank" rel="noopener noreferrer"
                            class="w-8 h-8 rounded-lg bg-zinc-950 border border-zinc-800 text-zinc-400 hover:text-blue-400 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode($share_message); ?>"
                            target="_blank" rel="noopener noreferrer"
                            class="w-8 h-8 rounded-lg bg-zinc-950 border border-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center text-xs transition-colors">
                            <i class="fa-brands fa-x"></i>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($share_message . ' ' . $referral_url); ?>"
                            target="_blank" rel="noopener noreferrer"
                            class="w-8 h-8 rounded-lg bg-zinc-950 border border-zinc-800 text-zinc-400 hover:text-emerald-400 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                        <a href="https://t.me/share/url?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode($share_message); ?>"
                            target="_blank" rel="noopener noreferrer"
                            class="w-8 h-8 rounded-lg bg-zinc-950 border border-zinc-800 text-zinc-400 hover:text-sky-400 flex items-center justify-center text-xs transition-colors">
                            <i class="fa-brands fa-telegram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Referral History -->
        <div class="lg:col-span-8" id="referralHistorySection">
            <div class="glass-panel rounded-2xl overflow-hidden shadow-2xl flex flex-col">
                <div class="p-4 bg-zinc-950/50 border-b border-zinc-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <h3 class="text-sm font-bold text-zinc-200 uppercase tracking-wider"><?php echo e(__('Referral History')); ?></h3>
                        <p class="text-[11px] text-zinc-500 font-medium"><?php echo e(__('Track your invited users and bonuses')); ?></p>
                    </div>

                    <div class="flex gap-1 bg-zinc-950 p-1 rounded-xl border border-zinc-900 text-[11px] font-semibold self-start sm:self-auto">
                        <?php foreach ($valid_statuses as $st): ?>
                            <?php $label = $st === 'all' ? __('All') : ($st === 'successful' ? __('Successful') : ($st === 'active' ? __('Active') : __('Pending'))); ?>
                            <a href="?status=<?php echo e($st); ?>"
                                class="px-3 py-1 rounded-lg transition-colors <?php echo $status_filter === $st ? 'bg-zinc-900 border border-zinc-800 text-white' : 'text-zinc-500 hover:text-zinc-300'; ?>">
                                <?php echo e($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="max-h-[515px] overflow-y-auto custom-scrollbar divide-y divide-zinc-800/40 bg-zinc-950/20">
                    <?php if ($referred): ?>
                        <?php foreach ($referred as $r):
                            $status = $r['status'] ?? 'pending';
                            $initials = e(strtoupper(substr($r['referred_name'] ?? 'U', 0, 1)));

                            $status_config = [
                                'successful' => [
                                    'badge_class' => 'bg-emerald-500/10 text-brand-accent border-emerald-500/20',
                                    'badge_text' => __('Invested'),
                                    'amount_class' => 'text-brand-accent',
                                    'icon' => 'fa-check-circle',
                                    'avatar_class' => 'bg-emerald-500/10 border-brand-accent/20 text-brand-accent'
                                ],
                                'active' => [
                                    'badge_class' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
                                    'badge_text' => __('Deposited'),
                                    'amount_class' => 'text-sky-400',
                                    'icon' => 'fa-coins',
                                    'avatar_class' => 'bg-sky-500/10 border-sky-500/20 text-sky-400'
                                ],
                                'pending' => [
                                    'badge_class' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'badge_text' => __('Registered'),
                                    'amount_class' => 'text-zinc-500',
                                    'icon' => 'fa-clock',
                                    'avatar_class' => 'bg-zinc-900 border-zinc-800 text-zinc-400'
                                ]
                            ];
                            $config = $status_config[$status] ?? $status_config['pending'];
                        ?>
                            <div class="p-4 flex items-center justify-between gap-4 hover:bg-zinc-900/20 transition-colors">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 <?php echo e($config['avatar_class']); ?>">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <h4 class="text-sm font-bold text-zinc-200 truncate"><?php echo e($r['referred_name'] ?? __('Unknown')); ?></h4>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-zinc-500 font-medium">
                                            <span class="font-mono"><i class="fa-regular fa-clock text-[9px] mr-1"></i><?php echo e(format_date($r['registered_at'] ?? null)); ?></span>
                                            <?php if (!empty($r['has_deposits'])): ?>
                                                <span class="text-zinc-600">|</span>
                                                <span class="font-mono"><?php echo e(__('Deposit:')); ?> <strong class="text-zinc-400"><?php echo e(format_money($r['deposit_total'] ?? 0)); ?></strong></span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['has_investments'])): ?>
                                                <span class="text-zinc-600">|</span>
                                                <span class="font-mono"><?php echo e(__('Invested:')); ?> <strong class="text-zinc-400"><?php echo e(format_money($r['investment_total'] ?? 0)); ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 space-y-1.5">
                                    <p class="text-sm font-mono font-bold <?php echo e($config['amount_class']); ?>">
                                        <?php echo ($r['bonus_amount'] ?? 0) > 0 ? '+' : ''; ?><?php echo e(format_money($r['bonus_amount'] ?? 0)); ?>
                                    </p>
                                    <span class="inline-block text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded border <?php echo e($config['badge_class']); ?>">
                                        <i class="fas <?php echo e($config['icon']); ?> mr-1"></i><?php echo e($config['badge_text']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 flex flex-col items-center justify-center text-center">
                            <div class="w-14 h-14 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
                                <i class="fa-solid fa-users text-xl"></i>
                            </div>
                            <h4 class="text-zinc-50 font-bold mb-1"><?php echo e(__('No referrals yet')); ?></h4>
                            <p class="text-zinc-500 text-sm"><?php echo e(__('Start sharing your link to earn commissions!')); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="p-3 bg-zinc-950/60 border-t border-zinc-900/60">
                        <nav class="flex items-center justify-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?status=<?php echo e($status_filter); ?>&page=<?php echo $page - 1; ?>"
                                    class="w-9 h-9 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-zinc-100 flex items-center justify-center text-xs transition-colors">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-9 h-9 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-600 flex items-center justify-center text-xs cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="w-9 h-9 rounded-full bg-brand-accent text-brand-dark font-bold flex items-center justify-center text-xs">
                                        <?php echo $p; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?status=<?php echo e($status_filter); ?>&page=<?php echo $p; ?>"
                                        class="w-9 h-9 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-zinc-100 flex items-center justify-center text-xs transition-colors">
                                        <?php echo $p; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?status=<?php echo e($status_filter); ?>&page=<?php echo $page + 1; ?>"
                                    class="w-9 h-9 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-zinc-100 flex items-center justify-center text-xs transition-colors">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-9 h-9 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-600 flex items-center justify-center text-xs cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Fund Wallet Modal -->
<div class="fixed inset-0 z-50 flex items-center justify-center"
    x-data="{ open: false, amount: '', mode: '<?php echo e($rfw_mode); ?>', exact: <?php echo json_encode($rfw_exact); ?>, minAmt: <?php echo json_encode($rfw_min); ?>, maxAmt: <?php echo json_encode($rfw_max); ?>, avail: <?php echo json_encode((float)$available_referral); ?>, get isValid() { let a = parseFloat(this.amount); if (isNaN(a) || a <= 0) return false; if (a > this.avail) return false; if (this.mode === 'exact') { return a === parseFloat(this.exact); } return a >= parseFloat(this.minAmt) && (parseFloat(this.maxAmt) <= 0 || a <= parseFloat(this.maxAmt)); } }"
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @open-fund-modal.window="open = true"
    @keydown.escape.window="open = false"
    style="display: none;">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative w-full max-w-md mx-4 glass-panel rounded-2xl shadow-2xl overflow-hidden"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95">
        <div class="p-5 border-b border-zinc-800/60 flex items-center justify-between">
            <h3 class="text-base font-bold text-zinc-100"><?php echo e(__('Fund Wallet from Referrals')); ?></h3>
            <button type="button" class="text-zinc-500 hover:text-zinc-300" @click="open = false">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-zinc-400 text-sm">
                <?php echo e(__('Available referral balance:')); ?>
                <strong class="text-brand-accent"><?php echo e(format_money($available_referral)); ?></strong>
            </p>
            <form action="/actions/referral-fund.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-zinc-400 uppercase tracking-wider mb-2"><?php echo e(__('Amount')); ?></label>
                    <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-xl overflow-hidden focus-within:border-brand-accent/40 transition-colors">
                        <span class="px-4 py-3 bg-zinc-900 text-zinc-300 text-sm font-bold border-r border-zinc-800"><?php echo e(get_currency_symbol()); ?></span>
                        <input type="number" name="amount" step="0.01" x-model="amount"
                            class="w-full bg-transparent px-4 py-3 text-zinc-100 font-mono font-bold focus:outline-none"
                            placeholder="0.00" required>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500">
                        <span x-show="mode === 'exact'">
                            <?php echo e(__('Must be exactly')); ?> <?php echo e(format_money($rfw_exact)); ?>
                        </span>
                        <span x-show="mode === 'range'">
                            <?php echo e(__('Min:')); ?> <?php echo e(format_money($rfw_min)); ?>
                            <?php if ($rfw_max > 0): ?>
                                | <?php echo e(__('Max:')); ?> <?php echo e(format_money($rfw_max)); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div x-show="parseFloat(amount) > avail" class="text-rose-400 text-[11px] mt-1" style="display: none;">
                        <?php echo e(__('Insufficient referral balance')); ?>
                    </div>
                    <div x-show="!isValid && amount !== ''" class="text-rose-400 text-[11px] mt-1" style="display: none;">
                        <?php echo e(__('Please enter a valid amount.')); ?>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-brand-accent hover:bg-emerald-400 text-brand-dark font-bold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!isValid">
                    <?php echo e(__('Confirm Fund Transfer')); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>