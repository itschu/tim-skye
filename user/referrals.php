<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/referral-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Referral Program');
$user_id = $_SESSION['user_id'];

$user = db_query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;
$code = $user['referral_code'] ?? '';
$stats = get_referral_detailed_stats($user_id);
$referred = get_referral_list($user_id);
$referral_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/register?ref=' . $code;

// Get referral settings
$referral_bonus_type = get_setting('referral_bonus_type', 'percentage');
$referral_bonus_amount = get_setting('referral_bonus_amount', 5);
$referral_bonus_trigger = get_setting('referral_bonus_trigger', 'registration');

// Determine messaging based on trigger
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

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Referral Program'); ?></h3>
            <p class="text-secondary mb-0 small"><?php echo __('Invite friends and earn commissions'); ?></p>
        </div>
    </div>

    <div class="d-flex gap-3">
        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
        </button>
        <button class="btn btn-primary shadow-sm rounded-pill px-4 py-2 fw-bold" onclick="document.getElementById('shareSection').scrollIntoView({behavior: 'smooth'})">
            <i class="fas fa-user-plus me-2"></i><?php echo __('Invite Friends'); ?>
        </button>
    </div>
</div>

<!-- Alpine.js Component -->
<div x-data="{ 
    activeTab: 'all',
    referralLink: '<?php echo e($referral_url); ?>',
    copied: false,
     copyToClipboard() {
        const text = this.referralLink;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }).catch(() => {
                this.fallbackCopy(text);
            });
        } else {
            this.fallbackCopy(text);
        }
    },
    fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        } catch (e) {
            
        }
        document.body.removeChild(ta);
    }
}">
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Earnings -->
        <div class="col-md-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100 p-4 stat-card" style="border-radius: 1.25rem;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h6 class="text-secondary fw-bold mb-0"><?php echo __('Total Earnings'); ?></h6>
                </div>
                <h2 class="fw-bold text-dark mb-0 mt-2" x-text="formatCurrency(<?php echo $stats['total'] ?? 0; ?>)"><?php echo format_money($stats['total'] ?? 0); ?></h2>
                <?php if (($stats['total'] ?? 0) > 0): ?>
                    <div class="d-flex align-items-center mt-2 small text-success fw-bold">
                        <i class="fas fa-arrow-trend-up me-2"></i>
                        <span><?php echo __('Keep inviting!'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Commission Rate -->
        <div class="col-md-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100 p-4 position-relative overflow-hidden stat-card" style="border-radius: 1.25rem;">
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="fas fa-percentage fa-4x text-primary"></i>
                </div>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h6 class="text-secondary fw-bold mb-0"><?php echo __('Commission Rate'); ?></h6>
                </div>
                <h2 class="fw-bold text-dark mb-0 mt-2" <?php if ($referral_bonus_type !== 'percentage'): ?>x-text="formatCurrency(<?php echo $referral_bonus_amount; ?>)" <?php endif; ?>><?php echo $referral_bonus_type === 'percentage' ? $referral_bonus_amount . '%' : format_money($referral_bonus_amount); ?></h2>
                <p class="text-secondary small mt-1 mb-0">
                    <?php echo $current_messages['commission_desc']; ?>
                </p>
            </div>
        </div>

        <!-- Successful Referrals -->
        <div class="col-md-4 col-lg-4">
            <div class="card border-0 shadow-sm h-100 p-4 stat-card" style="border-radius: 1.25rem;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h6 class="text-secondary fw-bold mb-0"><?php echo __('Successful Invites'); ?></h6>
                </div>
                <h2 class="fw-bold text-dark mb-0 mt-2"><?php echo $stats['successful'] ?? 0; ?></h2>
                <p class="text-secondary small mt-1 mb-0"><?php echo __('Users who invested'); ?></p>
            </div>
        </div>

        <!-- Active Referrals -->
        <div class="col-md-4 col-lg-4">
            <div class="card border-0 shadow-sm h-100 p-4 stat-card" style="border-radius: 1.25rem;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h6 class="text-secondary fw-bold mb-0"><?php echo __('Active'); ?></h6>
                </div>
                <h2 class="fw-bold text-dark mb-0 mt-2"><?php echo $stats['active'] ?? 0; ?></h2>
                <p class="text-secondary small mt-1 mb-0"><?php echo __('Users who deposited'); ?></p>
            </div>
        </div>

        <!-- Pending Referrals -->
        <div class="col-md-4 col-lg-4">
            <div class="card border-0 shadow-sm h-100 p-4 stat-card" style="border-radius: 1.25rem;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h6 class="text-secondary fw-bold mb-0"><?php echo __('Pending'); ?></h6>
                </div>
                <h2 class="fw-bold text-dark mb-0 mt-2"><?php echo $stats['pending'] ?? 0; ?></h2>
                <p class="text-secondary small mt-1 mb-0"><?php echo __('Registered only'); ?></p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column - How it works & Share -->
        <div class="col-lg-5" id="shareSection">
            <!-- How it works Card -->
            <div class="card border-0 text-white overflow-hidden position-relative mb-4" style="border-radius: 1.25rem; background: var(--gradient-card);">
                <div class="position-absolute" style="top: -30%; right: -10%; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                <div class="position-absolute" style="bottom: -20%; left: -5%; width: 150px; height: 150px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>

                <div class="card-body p-4 position-relative">
                    <h5 class="fw-bold mb-4"><?php echo __('How it works'); ?></h5>

                    <div class="d-flex gap-3 mb-3">
                        <div class="step-circle">1</div>
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo __('Invite Friends'); ?></h6>
                            <p class="text-white-50 small mb-0"><?php echo __('Share your link with friends.'); ?></p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <div class="step-circle">2</div>
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo $current_messages['step2']; ?></h6>
                            <p class="text-white-50 small mb-0"><?php echo $current_messages['step2_desc']; ?></p>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="step-circle">3</div>
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo $current_messages['step3']; ?></h6>
                            <p class="text-white-50 small mb-0"><?php echo $current_messages['step3_desc']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Share Link Card -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 1.25rem;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?php echo __('Share Your Link'); ?></h5>
                    <p class="text-secondary small mb-4"><?php echo __('Copy your unique referral link and share it with your network to start earning.'); ?></p>

                    <div class="copy-input-group mb-4">
                        <div class="ps-3 text-secondary opacity-50"><i class="fas fa-link"></i></div>
                        <input type="text" class="copy-input" x-model="referralLink" readonly />
                        <button class="d-flex btn rounded-pill px-4 fw-bold shadow-sm" @click="copyToClipboard()" :class="{ 'btn-success': copied, 'btn-primary': !copied }">
                            <span x-show="!copied"><?php echo __('Copy'); ?></span>
                            <span x-show="copied" style="display: none">
                                <!-- <i class="fas fa-check me-1"></i> -->
                                <?php echo __('Copied'); ?></span>
                        </button>
                    </div>

                    <p class="text-secondary small fw-bold mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem"><?php echo __('Share via'); ?></p>
                    <div class="d-flex gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" target="_blank" class="btn-social bg-facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>

                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode(__('Join me on this investment platform!')); ?>" target="_blank" class="btn-social bg-twitter">
                            <i class="fa-brands fa-x"></i>
                        </a>

                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode(__('Join me on this investment platform!') . ' ' . $referral_url); ?>" target="_blank" class="btn-social bg-whatsapp">
                            <i class="fab fa-whatsapp"></i>
                        </a>

                        <a href="https://t.me/share/url?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode(__('Join me on this investment platform!')); ?>" target="_blank" class="btn-social bg-telegram">
                            <i class="fab fa-telegram-plane"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Referral History -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1.25rem;">
                <div class="card-header bg-transparent border-bottom p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="fw-bold mb-0"><?php echo __('Referral History'); ?></h5>

                    <div class="nav nav-pills nav-pills-custom">
                        <button class="nav-link" :class="{ 'active': activeTab === 'all' }" @click="activeTab = 'all'"><?php echo __('All'); ?></button>
                        <button class="nav-link" :class="{ 'active': activeTab === 'successful' }" @click="activeTab = 'successful'"><?php echo __('Successful'); ?></button>
                        <button class="nav-link" :class="{ 'active': activeTab === 'active' }" @click="activeTab = 'active'"><?php echo __('Active'); ?></button>
                        <button class="nav-link" :class="{ 'active': activeTab === 'pending' }" @click="activeTab = 'pending'"><?php echo __('Pending'); ?></button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($referred):
                            foreach ($referred as $r):
                                $status = $r['status'] ?? 'pending';
                                $initials = strtoupper(substr($r['referred_name'] ?? 'U', 0, 1));

                                // Status badge styling
                                $status_config = [
                                    'successful' => [
                                        'badge_class' => 'bg-success bg-opacity-10 text-success',
                                        'badge_text' => __('Invested'),
                                        'amount_class' => 'text-success',
                                        'icon' => 'fa-check-circle'
                                    ],
                                    'active' => [
                                        'badge_class' => 'bg-info bg-opacity-10 text-info',
                                        'badge_text' => __('Deposited'),
                                        'amount_class' => 'text-info',
                                        'icon' => 'fa-coins'
                                    ],
                                    'pending' => [
                                        'badge_class' => 'bg-warning bg-opacity-10 text-warning',
                                        'badge_text' => __('Registered'),
                                        'amount_class' => 'text-muted',
                                        'icon' => 'fa-clock'
                                    ]
                                ];
                                $config = $status_config[$status] ?? $status_config['pending'];
                        ?>
                                <div class="list-group-item p-4 border-bottom-light referral-item"
                                    x-show="activeTab === 'all' || activeTab === '<?php echo $status; ?>'"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-0"><?php echo e($r['referred_name'] ?? __('Unknown')); ?></h6>
                                                <span class="small text-secondary"><?php echo format_date($r['registered_at'] ?? null); ?></span>
                                                <?php if ($r['has_deposits']): ?>
                                                    <div class="small text-info">
                                                        <i class="fas fa-coins me-1"></i>
                                                        <?php echo __('Deposit:'); ?> <?php echo format_money($r['deposit_total']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($r['has_investments']): ?>
                                                    <div class="small text-success">
                                                        <i class="fas fa-chart-line me-1"></i>
                                                        <?php echo __('Invested:'); ?> <?php echo format_money($r['investment_total']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <h6 class="fw-bold <?php echo $config['amount_class']; ?> mb-0">
                                                <?php echo ($r['bonus_amount'] ?? 0) > 0 ? '+' : ''; ?><?php echo format_money($r['bonus_amount'] ?? 0); ?>
                                            </h6>
                                            <span class="badge <?php echo $config['badge_class']; ?> rounded-pill px-2 py-1" style="font-size: 0.7rem">
                                                <i class="fas <?php echo $config['icon']; ?> me-1"></i><?php echo $config['badge_text']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;
                        else: ?>
                            <div class="p-5 text-center">
                                <div class="bg-light rounded-circle p-4 mb-3 d-inline-flex">
                                    <i class="fas fa-users fa-2x text-secondary opacity-25"></i>
                                </div>
                                <h5 class="fw-bold text-dark"><?php echo __('No referrals yet'); ?></h5>
                                <p class="text-muted small"><?php echo __('Start sharing your link to earn commissions!'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Stats Card */
    .stat-card {
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    /* Icon Box */
    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    /* Copy Field */
    .copy-input-group {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 0.5rem;
        display: flex;
        align-items: center;
    }

    .copy-input {
        border: none;
        background: transparent;
        font-weight: 600;
        color: var(--text-muted);
        flex-grow: 1;
        padding: 0.5rem 1rem;
        outline: none;
    }

    /* Social Share Buttons */
    .btn-social {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        transition: transform 0.2s;
        border: none;
        text-decoration: none;
    }

    .btn-social:hover {
        transform: scale(1.1);
        color: white;
    }

    .bg-facebook {
        background: #1877f2;
    }

    .bg-twitter {
        background: #000000;
    }

    .bg-whatsapp {
        background: #25d366;
    }

    .bg-telegram {
        background: #0088cc;
    }

    /* Step Circles */
    .step-circle {
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    /* Custom Tabs */
    .nav-pills-custom .nav-link {
        color: var(--text-muted);
        font-weight: 600;
        padding: 0.5rem 1.2rem;
        border-radius: 50rem;
        font-size: 0.9rem;
        border: none;
        background: transparent;
    }

    .nav-pills-custom .nav-link.active {
        background: #eff6ff;
        color: var(--primary);
    }

    /* Referral Item */
    .referral-item {
        transition: background 0.2s;
    }

    .referral-item:hover {
        background: #f8fafc;
    }

    /* Transitions */
    .transition {
        transition: all 0.2s ease;
    }
</style>

<?php require ROOT . '/includes/footer.php'; ?>
</body>

</html>