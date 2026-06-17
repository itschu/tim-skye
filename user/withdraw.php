<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Withdraw Funds');
$active_nav = 'withdraw';
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();

$available = get_available_balance($user_id);
$locked = get_locked_balance($user_id);
$fee_percent = (float)get_setting('withdrawal_fee_percentage', 2);
$minimum_withdrawal = (float)get_setting('minimum_withdrawal', 1);

// Referral withdrawal support
$is_referral_withdrawal = ($_GET['source'] ?? '') === 'referral';
$rfw_mode = get_setting('referral_fund_withdraw_mode', 'exact');
$rfw_exact = (float)get_setting('referral_exact_amount', 0);
$rfw_min = (float)get_setting('referral_min_amount', 0);
$rfw_max = (float)get_setting('referral_max_amount', 0);

if ($is_referral_withdrawal) {
    $page_title = __('Withdraw Referral Funds');
    $available = get_available_referral_balance($user_id);
    $locked = get_locked_referral_balance($user_id);
}

// Get withdrawal methods from settings
$withdrawal_methods_json = get_setting('withdrawal_methods', '');
$withdrawal_methods = [];
if (!empty($withdrawal_methods_json)) {
    $withdrawal_methods = json_decode($withdrawal_methods_json, true) ?: [];
}

// Filter only enabled methods
$enabled_methods = array_filter($withdrawal_methods, function ($m) {
    return ($m['enabled'] ?? false);
});

// Get first method key for default selection
$first_method_key = array_key_first($enabled_methods);
$first_method = $enabled_methods[$first_method_key] ?? null;

// Get withdrawal history
$history = db_query(
    "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user_id]
);

// Get user profile for pre-filling and T4 local currency
$user = db_query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;

// T4: Get local currency for withdrawal estimates
$user_country = $user['country'] ?? null;
$local_currency_code = null;
$exchange_rate = null;
$rate_updated_at = null;

if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code) {
        $exchange_rate = get_rate_for_currency($local_currency_code);
        if ($exchange_rate) {
            $rates = get_exchange_rates();
            $rate_updated_at = $rates['updated_at'] ?? null;
        }
    }
}

$local_currency_symbol = $local_currency_code ? get_currency_symbol($local_currency_code) : null;
$has_local_currency = ($exchange_rate !== null && $local_currency_code !== null);

$account_name = $user['name'] ?? '';
$methods_json = json_encode($enabled_methods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

ob_start();
?>
<style>
    .premium-input {
        background: transparent;
        border: none;
        color: #fafafa;
        width: 100%;
    }

    .premium-input:focus {
        outline: none;
    }

    .premium-input::placeholder {
        color: #52525b;
    }

    .premium-select {
        background: transparent;
        border: none;
        color: #fafafa;
        width: 100%;
        appearance: none;
        -webkit-appearance: none;
        cursor: pointer;
    }

    .premium-select option,
    .premium-select optgroup {
        background: #18181b;
        color: #fafafa;
    }

    .glass-canvas {
        background: linear-gradient(180deg, rgba(24, 24, 27, 0.8) 0%, rgba(15, 15, 17, 0.8) 100%);
        backdrop-filter: blur(24px);
        border: 1px solid rgba(63, 63, 70, 0.25);
    }

    .glow-line {
        transition: all 0.2s ease;
    }

    .glow-line:focus-within {
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.4);
    }

    .method-segment {
        transition: all 0.15s ease;
    }

    .method-segment.active {
        background: rgba(24, 24, 27, 0.9);
        border-color: #3f3f46;
        color: #fafafa;
    }

    .amount-input::-webkit-inner-spin-button,
    .amount-input::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .amount-input[type='number'] {
        -moz-appearance: textfield;
    }

    .local-currency-extra-padding {
        padding-right: 4rem;
    }
</style>
<?php
$extra_css = ob_get_clean();

ob_start();
$cc = get_currency_code();
?>
<script>
    // PHP data passed to JavaScript
    window.withdrawData = {
        availableBalance: <?php echo json_encode((float)$available); ?>,
        currencySymbol: "<?php echo e($currency_symbol); ?>",
        firstMethodKey: <?php echo json_encode($first_method_key); ?>,
        withdrawalFeePercent: <?php echo json_encode($fee_percent); ?>,
        minimumWithdrawal: <?php echo json_encode($minimum_withdrawal); ?>,
        accountName: <?php echo json_encode($account_name); ?>,
        methods: <?php echo $methods_json; ?>,
        hasLocalCurrency: <?php echo json_encode($has_local_currency); ?>,
        localCurrencyCode: "<?php echo e($local_currency_code ?? ''); ?>",
        localCurrencySymbol: "<?php echo e($local_currency_symbol ?? ''); ?>",
        exchangeRate: <?php echo json_encode($exchange_rate ?? 0); ?>,
        isReferral: <?php echo json_encode($is_referral_withdrawal); ?>,
        referralMode: <?php echo json_encode($rfw_mode); ?>,
        referralExact: <?php echo json_encode((float)$rfw_exact); ?>,
        referralMin: <?php echo json_encode((float)$rfw_min); ?>,
        referralMax: <?php echo json_encode((float)$rfw_max); ?>,
        baseCurrency: "<?php echo e($cc); ?>",
        translations: {
            noAddress: <?php echo json_encode(__('No address entered')); ?>,
            enterBank: <?php echo json_encode(__('Enter bank details')); ?>,
            enterMobile: <?php echo json_encode(__('Enter mobile details')); ?>,
            enterWallet: <?php echo json_encode(__('Enter wallet details')); ?>,
            network: <?php echo json_encode(__('Network')); ?>,
            estimatedLocalReceipt: <?php echo json_encode(__('estimated local receipt')); ?>
        }
    };

    const currencySymbol = window.withdrawData.currencySymbol;

    function withdrawFormData() {
        return {
            availableBalance: window.withdrawData.availableBalance,
            displayAmount: '',
            baseAmount: '',
            selectedMethod: window.withdrawData.firstMethodKey,
            withdrawalFeePercent: window.withdrawData.withdrawalFeePercent,
            minimumWithdrawal: window.withdrawData.minimumWithdrawal,
            hasLocalCurrency: window.withdrawData.hasLocalCurrency,
            localCurrencyCode: window.withdrawData.localCurrencyCode,
            localCurrencySymbol: window.withdrawData.localCurrencySymbol,
            exchangeRate: window.withdrawData.exchangeRate,
            currencySymbol: window.withdrawData.currencySymbol,
            isReferral: window.withdrawData.isReferral,
            referralMode: window.withdrawData.referralMode,
            referralExact: window.withdrawData.referralExact,
            referralMin: window.withdrawData.referralMin,
            referralMax: window.withdrawData.referralMax,
            loading: false,
            isLocalCurrency: false,
            _suppressWatcher: false,

            // Crypto fields
            address: '',
            network: '',

            // Bank fields
            bankName: '',
            accountName: window.withdrawData.accountName,
            accountNumber: '',

            // Mobile Money fields
            mobileProvider: '',
            mobileNumber: '',
            mobileName: '',
            mobileReference: '',

            // E-Wallet fields
            ewalletProvider: '',
            ewalletId: '',

            methods: window.withdrawData.methods,

            init() {
                const appRoot = document.getElementById('app-root');
                const root = appRoot?._x_dataStack?.[0];
                if (root && this.localCurrencyCode && root.currency === this.localCurrencyCode) {
                    this.isLocalCurrency = true;
                }
                this._lastCurrency = this.getRootCurrency();
                setInterval(() => {
                    const current = this.getRootCurrency();
                    if (current !== this._lastCurrency) {
                        this._lastCurrency = current;
                        this.convertOnCurrencyToggle();
                    }
                }, 500);

                this.$watch('displayAmount', (value) => {
                    if (this._suppressWatcher) return;
                    const num = parseFloat(value);
                    if (isNaN(num)) {
                        this.baseAmount = '';
                        return;
                    }
                    if (this.isLocalCurrency && this.exchangeRate) {
                        this.baseAmount = parseFloat((num / this.exchangeRate).toFixed(15));
                    } else {
                        this.baseAmount = num;
                    }
                });
            },

            getRootCurrency() {
                const appRoot = document.getElementById('app-root');
                const root = appRoot?._x_dataStack?.[0];
                return root ? root.currency : window.withdrawData.baseCurrency;
            },

            formatCurrency(amount) {
                const appRoot = document.getElementById('app-root');
                const root = appRoot?._x_dataStack?.[0];
                if (root && typeof root.formatCurrency === 'function') {
                    return root.formatCurrency(amount);
                }
                let val = parseFloat(amount);
                if (isNaN(val)) return this.currencySymbol + '0.00';
                return this.currencySymbol + val.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },

            convertOnCurrencyToggle() {
                const appRoot = document.getElementById('app-root');
                const root = appRoot?._x_dataStack?.[0];
                const nowLocal = root && root.currency === this.localCurrencyCode;
                const wasLocal = this.isLocalCurrency;
                this.isLocalCurrency = nowLocal;
                this._suppressWatcher = true;
                if (nowLocal && !wasLocal) {
                    this.displayAmount = this.baseAmount ? parseFloat((this.baseAmount * this.exchangeRate).toFixed(2)) : '';
                } else if (!nowLocal && wasLocal) {
                    this.displayAmount = this.baseAmount ? this.baseAmount : '';
                }
                this.$nextTick(() => {
                    this._suppressWatcher = false;
                });
            },

            get maxAmount() {
                let maxAmount = this.availableBalance;
                if (this.isReferral) {
                    if (this.referralMode === 'exact') {
                        maxAmount = Math.min(maxAmount, parseFloat(this.referralExact));
                    } else if (parseFloat(this.referralMax) > 0) {
                        maxAmount = Math.min(maxAmount, parseFloat(this.referralMax));
                    }
                }
                if (this.isLocalCurrency && this.exchangeRate) {
                    return parseFloat((maxAmount * this.exchangeRate).toFixed(2));
                }
                return maxAmount;
            },

            get feeAmount() {
                let val = parseFloat(this.baseAmount);
                return isNaN(val) ? 0 : (val * (this.withdrawalFeePercent / 100)).toFixed(2);
            },

            get netAmount() {
                let val = parseFloat(this.baseAmount);
                let fee = parseFloat(this.feeAmount);
                return isNaN(val) ? 0 : (val - fee).toFixed(2);
            },

            get localNetAmount() {
                if (!this.hasLocalCurrency || !this.exchangeRate) return '0.00';
                let net = parseFloat(this.netAmount);
                return isNaN(net) ? '0.00' : (net * this.exchangeRate).toFixed(2);
            },

            get localFeeAmount() {
                if (!this.hasLocalCurrency || !this.exchangeRate) return '0.00';
                let fee = parseFloat(this.feeAmount);
                return isNaN(fee) ? '0.00' : (fee * this.exchangeRate).toFixed(2);
            },

            get localAmountEstimate() {
                if (!this.hasLocalCurrency || !this.exchangeRate) return '0.00';
                let val = parseFloat(this.baseAmount);
                return isNaN(val) ? '0.00' : (val * this.exchangeRate).toFixed(2);
            },

            get isValid() {
                let val = parseFloat(this.baseAmount);
                let validAmount;
                if (this.isReferral) {
                    validAmount = val > 0 && val <= this.availableBalance;
                    if (this.referralMode === 'exact') {
                        validAmount = validAmount && val === parseFloat(this.referralExact);
                    } else {
                        validAmount = validAmount && val >= parseFloat(this.referralMin);
                        if (parseFloat(this.referralMax) > 0) {
                            validAmount = validAmount && val <= parseFloat(this.referralMax);
                        }
                    }
                } else {
                    validAmount = val > 0 && val >= this.minimumWithdrawal && val <= this.availableBalance;
                }

                if (!validAmount) return false;

                let methodType = this.getMethodType();

                if (methodType === 'crypto') {
                    return this.address.length > 5 && this.network !== '';
                } else if (methodType === 'fiat') {
                    return this.bankName.length > 2 && this.accountName.length > 2 && this.accountNumber.length > 5;
                } else if (methodType === 'momo') {
                    return this.mobileProvider !== '' && this.mobileNumber.length > 4 && this.mobileName.length > 2;
                } else if (methodType === 'ewallet') {
                    return this.ewalletProvider !== '' && this.ewalletId.length > 3;
                }
                return false;
            },

            getMethodType() {
                return this.methods[this.selectedMethod]?.type || 'other';
            },

            getMethodName() {
                return this.methods[this.selectedMethod]?.name || '';
            },

            getMethodIcon() {
                // Hardcoded icons based on method type
                const type = this.getMethodType();
                switch (type) {
                    case 'crypto':
                        return 'fa-brands fa-bitcoin';
                    case 'fiat':
                        return 'fa-solid fa-building-columns';
                    case 'momo':
                        return 'fa-solid fa-mobile-screen';
                    case 'ewallet':
                        return 'fa-solid fa-wallet';
                    default:
                        return 'fa-solid fa-money-bill';
                }
            },

            getNetworks() {
                const method = this.methods[this.selectedMethod];
                // Use networks from admin if available
                if (method.networks && Array.isArray(method.networks) && method.networks.length > 0) {
                    return method.networks;
                }
                // Default networks based on method key/name
                const methodName = (method.name || '').toLowerCase();
                if (methodName.includes('btc') || methodName.includes('bitcoin')) {
                    return ['Bitcoin', 'Lightning', 'BEP20'];
                }
                if (methodName.includes('usdt') || methodName.includes('tether')) {
                    return ['TRC20', 'ERC20', 'BEP20'];
                }
                // Generic crypto networks
                return ['TRC20', 'ERC20', 'BEP20'];
            },

            getProviders() {
                const method = this.methods[this.selectedMethod];
                // Use providers from admin if available
                if (method.providers && Array.isArray(method.providers) && method.providers.length > 0) {
                    return method.providers;
                }
                // Default mobile money providers
                return ['MTN', 'Airtel', 'M-Pesa', 'Orange', 'Wave', 'Galaxy'];
            },

            setMax() {
                let maxAmount = this.availableBalance;
                if (this.isReferral) {
                    if (this.referralMode === 'exact') {
                        maxAmount = Math.min(maxAmount, parseFloat(this.referralExact));
                    } else if (parseFloat(this.referralMax) > 0) {
                        maxAmount = Math.min(maxAmount, parseFloat(this.referralMax));
                    }
                }
                this.baseAmount = maxAmount;
                this._suppressWatcher = true;
                if (this.isLocalCurrency && this.exchangeRate) {
                    this.displayAmount = parseFloat((maxAmount * this.exchangeRate).toFixed(2));
                } else {
                    this.displayAmount = maxAmount;
                }
                this.$nextTick(() => {
                    this._suppressWatcher = false;
                });
            },

            selectMethod(key) {
                this.selectedMethod = key;
                this.network = '';

                if (window.innerWidth < 992) {
                    setTimeout(() => {
                        this.$refs.withdrawalDetails?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 100);
                }
            }
        };
    }

    function initWithdrawForm(data) {
        // Initialization if needed
    }
</script>
<?php
$extra_scripts = ob_get_clean();

require_once ROOT . '/includes/currency-conversion.php';
require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
?>

<?php if (get_maintenance_mode()): ?>
    <div class="mb-6 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-amber-400 text-sm font-medium flex items-start gap-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
        <span><?php echo __('Platform is temporarily under maintenance. Deposits, withdrawals, and investments are disabled.'); ?></span>
    </div>
<?php endif; ?>

<!-- Page Header -->
<header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-4xl font-bold text-white tracking-tight">
            <?php echo $is_referral_withdrawal ? __('Withdraw Referral Funds') : __('Withdraw Funds'); ?>
        </h1>
        <p class="text-zinc-400 text-sm">
            <?php echo $is_referral_withdrawal ? __('Transfer your referral earnings to your external account') : __('Transfer earnings to your external account'); ?>
        </p>
    </div>
</header>

<?php if (empty($enabled_methods)): ?>
    <!-- No Methods Available State -->
    <div class="glass-canvas rounded-3xl p-8 mb-8 overflow-hidden">
        <div class="py-12 flex flex-col items-center justify-center text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-600">
                <i class="fa-solid fa-building-columns text-2xl"></i>
            </div>
            <div class="space-y-1">
                <h5 class="text-zinc-50 font-bold text-lg"><?php echo __('No Withdrawal Methods Available'); ?></h5>
                <p class="text-zinc-500 text-sm max-w-xs"><?php echo __('Please contact support for withdrawal options.'); ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Alpine.js Component -->
    <div x-data="withdrawFormData()" x-init="initWithdrawForm($data)" class="space-y-8">

        <!-- Balance Hero Card -->
        <div class="glass-canvas rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-xl relative overflow-hidden">
            <div class="space-y-1">
                <span class="inline-flex items-center text-[11px] font-bold text-brand-accent uppercase tracking-widest bg-brand-accent/10 px-2.5 py-1 rounded-md">
                    <?php echo $is_referral_withdrawal ? __('Referral Balance') : __('Wallet Balance'); ?>
                </span>
                <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                    <?php echo  __('Funds Breakdown'); ?>
                </h2>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-2xl p-4 flex items-center gap-4 min-w-[200px]">
                    <div class="w-10 h-10 rounded-xl bg-zinc-900 flex items-center justify-center text-zinc-400 border border-zinc-800">
                        <i class="fa-solid fa-wallet text-lg"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                            <?php echo $is_referral_withdrawal ? __('Available Referral Balance') : __('Available Balance'); ?>
                        </p>
                        <p class="text-xl font-bold text-white font-mono tracking-tight" x-text="formatCurrency(availableBalance)"></p>
                    </div>
                </div>

                <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-2xl p-4 flex items-center gap-4 min-w-[200px]">
                    <div class="w-10 h-10 rounded-xl bg-zinc-900 flex items-center justify-center text-zinc-500 border border-zinc-800">
                        <i class="fa-solid fa-lock text-lg"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo __('Locked Balance'); ?></p>
                        <p class="text-xl font-bold text-zinc-400 font-mono tracking-tight" x-text="formatCurrency(<?php echo json_encode((float)$locked); ?>)"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Withdrawal Engine -->
        <div class="glass-canvas rounded-3xl overflow-hidden shadow-2xl">
            <!-- Method Selection -->
            <div class="bg-zinc-950/40 border-b border-zinc-800/80 p-4">
                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-3 px-2"><?php echo __('Withdrawal Method'); ?></p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 bg-zinc-950 p-1.5 rounded-2xl border border-zinc-900">
                    <?php foreach ($enabled_methods as $key => $method):
                        $icon_class = match ($method['type'] ?? '') {
                            'crypto' => 'fa-brands fa-bitcoin',
                            'fiat' => 'fa-solid fa-building-columns',
                            'momo' => 'fa-solid fa-mobile-screen',
                            'ewallet' => 'fa-solid fa-wallet',
                            default => 'fa-solid fa-money-bill'
                        };
                        $type = $method['type'] ?? '';
                    ?>
                        <button type="button"
                            class="method-segment border border-transparent rounded-xl py-3 px-3 flex items-center justify-center gap-2.5 text-zinc-500 hover:text-zinc-300"
                            :class="{ 'active shadow-md': selectedMethod === '<?php echo e($key); ?>' }"
                            @click="selectMethod('<?php echo e($key); ?>')">
                            <i class="<?php echo e($icon_class); ?> text-sm"
                                :class="selectedMethod === '<?php echo e($key); ?>' ? 'text-brand-accent' : 'text-zinc-600'"></i>
                            <span class="text-xs font-semibold truncate"><?php echo e($method['name']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Form Body -->
            <div class="p-6 sm:p-8 space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Destination Details -->
                    <div class="space-y-4" x-ref="withdrawalDetails">

                        <!-- Crypto Fields -->
                        <div x-show="getMethodType() === 'crypto'" x-transition x-cloak class="space-y-4" style="display: none;">
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-2"><?php echo __('Select Network'); ?></label>
                                <div class="relative">
                                    <select class="premium-select text-sm font-bold pr-8" x-model="network">
                                        <option value="" disabled selected><?php echo __('Select Network...'); ?></option>
                                        <template x-for="net in getNetworks()" :key="net">
                                            <option :value="net" x-text="net"></option>
                                        </template>
                                    </select>
                                    <div class="absolute right-0 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Wallet Address'); ?></label>
                                <input type="text" x-model="address" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('Paste your wallet address here')); ?>" />
                            </div>

                            <div class="flex gap-3 text-amber-500/90 text-xs bg-amber-500/5 border border-amber-500/10 p-3 rounded-xl">
                                <i class="fa-solid fa-shield-halved text-sm mt-0.5 shrink-0"></i>
                                <p class="leading-relaxed font-medium">
                                    <?php echo __('Ensure the address matches the selected'); ?> <strong><span x-text="network || window.withdrawData.translations.network"></span></strong>. <?php echo __('Transfers to the wrong network cannot be recovered.'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Bank Transfer Fields -->
                        <div x-show="getMethodType() === 'fiat'" x-transition x-cloak class="space-y-4" style="display: none;">
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Bank Name'); ?></label>
                                <input type="text" x-model="bankName" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('e.g. Chase Bank')); ?>" />
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Account Name'); ?></label>
                                <input type="text" x-model="accountName" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('e.g. John Doe')); ?>" />
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Account Number'); ?></label>
                                <input type="text" x-model="accountNumber" class="premium-input text-sm font-semibold pt-1" placeholder="0000000000" />
                            </div>
                        </div>

                        <!-- Mobile Money Fields -->
                        <div x-show="getMethodType() === 'momo'" x-transition x-cloak class="space-y-4" style="display: none;">
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-2"><?php echo __('Provider'); ?></label>
                                <div class="relative">
                                    <select class="premium-select text-sm font-bold pr-8" x-model="mobileProvider">
                                        <option value="" disabled selected><?php echo __('Select Provider...'); ?></option>
                                        <template x-for="provider in getProviders()" :key="provider">
                                            <option :value="provider" x-text="provider"></option>
                                        </template>
                                    </select>
                                    <div class="absolute right-0 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Phone Number'); ?></label>
                                <input type="tel" x-model="mobileNumber" class="premium-input text-sm font-semibold pt-1" placeholder="+1234567890" />
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
                                    <?php echo __('Full Name'); ?> <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" x-model="mobileName" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('e.g. John Doe')); ?>" />
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
                                    <?php echo __('Reference / Note'); ?> <small class="text-zinc-600 font-normal">(<?php echo __('Optional'); ?>)</small>
                                </label>
                                <input type="text" x-model="mobileReference" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('e.g. Invoice #123, Family support')); ?>" />
                            </div>
                        </div>

                        <!-- E-Wallet Fields -->
                        <div x-show="getMethodType() === 'ewallet'" x-transition x-cloak class="space-y-4" style="display: none;">
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Provider'); ?></label>
                                <input type="text" x-model="ewalletProvider" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('e.g. PayPal, Skrill, Neteller')); ?>" />
                            </div>
                            <div class="glow-line bg-zinc-950 border border-zinc-800/80 rounded-2xl p-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1"><?php echo __('Wallet ID / Email'); ?></label>
                                <input type="text" x-model="ewalletId" class="premium-input text-sm font-semibold pt-1" placeholder="<?php echo e(__('Enter your wallet ID or email')); ?>" />
                            </div>
                        </div>

                        <?php if ($is_referral_withdrawal): ?>
                            <?php if ($rfw_mode === 'exact'): ?>
                                <div class="flex gap-3 text-sky-400/90 text-xs bg-sky-500/5 border border-sky-500/10 p-3 rounded-xl">
                                    <i class="fa-solid fa-circle-info text-sm mt-0.5 shrink-0"></i>
                                    <p class="leading-relaxed font-medium">
                                        <?php echo sprintf(__('Referral withdrawals must be exactly %s'), format_money($rfw_exact)); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="flex gap-3 text-sky-400/90 text-xs bg-sky-500/5 border border-sky-500/10 p-3 rounded-xl">
                                    <i class="fa-solid fa-circle-info text-sm mt-0.5 shrink-0"></i>
                                    <p class="leading-relaxed font-medium">
                                        <?php echo sprintf(__('Referral withdrawals must be at least %s'), format_money($rfw_min)) . ($rfw_max > 0 ? ' ' . sprintf(__('and at most %s'), format_money($rfw_max)) : ''); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Amount + Summary -->
                    <div class="space-y-4">
                        <!-- Amount Input -->
                        <div class="flex flex-col justify-between bg-zinc-950/40 border border-zinc-800/60 rounded-2xl p-5 relative space-y-4">
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider"><?php echo __('Amount to Withdraw'); ?></label>
                                    <button type="button" @click="setMax()" class="text-[10px] font-bold text-brand-accent bg-brand-accent/10 px-2.5 py-1 rounded-md hover:bg-brand-accent/20 transition-colors"><?php echo __('MAX'); ?></button>
                                </div>
                                <div class="relative flex items-center border-b border-zinc-800 pb-2 focus-within:border-brand-accent/50 transition-colors">
                                    <span class="text-zinc-500 font-bold text-2xl mr-2" x-text="isLocalCurrency ? localCurrencySymbol : currencySymbol"></span>
                                    <input type="number" step="any" x-model="displayAmount" class="amount-input w-full bg-transparent text-3xl font-bold text-white font-mono focus:outline-none placeholder:text-zinc-800" :max="maxAmount" placeholder="0.00" :class="{ 'local-currency-extra-padding': isLocalCurrency }" />
                                </div>
                            </div>

                            <?php if ($has_local_currency): ?>
                                <div x-show="!isLocalCurrency" class="flex gap-3 text-sky-400/90 text-xs bg-sky-500/5 border border-sky-500/10 p-3 rounded-xl" style="display: none;">
                                    <i class="fa-solid fa-circle-info text-sm mt-0.5 shrink-0"></i>
                                    <p class="leading-relaxed font-medium">
                                        <span x-text="'≈ ' + localCurrencySymbol + localAmountEstimate + ' ' + window.withdrawData.translations.estimatedLocalReceipt"></span>
                                    </p>
                                </div>
                                <div x-show="isLocalCurrency" class="flex gap-3 text-sky-400/90 text-xs bg-sky-500/5 border border-sky-500/10 p-3 rounded-xl" style="display: none;">
                                    <i class="fa-solid fa-circle-info text-sm mt-0.5 shrink-0"></i>
                                    <p class="leading-relaxed font-medium">
                                        <span x-text="'≈ ' + currencySymbol + (baseAmount ? parseFloat(baseAmount).toFixed(2) : '0.00') + ' ' + <?php echo htmlspecialchars(json_encode(__('base currency')), ENT_QUOTES, 'UTF-8'); ?>"></span>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div x-show="!isReferral && parseFloat(baseAmount) > 0 && parseFloat(baseAmount) < minimumWithdrawal" class="flex items-center gap-2 text-rose-400 text-xs font-medium" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?php echo __('Minimum withdrawal is'); ?> <span x-text="formatCurrency(minimumWithdrawal)"></span>
                            </div>
                            <div x-show="parseFloat(baseAmount) > availableBalance" class="flex items-center gap-2 text-rose-400 text-xs font-medium" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?php echo __('Insufficient balance'); ?>
                            </div>
                            <div x-show="isReferral && referralMode === 'exact' && parseFloat(baseAmount) > 0 && parseFloat(baseAmount) !== parseFloat(referralExact)" class="flex items-center gap-2 text-rose-400 text-xs font-medium" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?php echo sprintf(__('Amount must be exactly %s'), '<span x-text="formatCurrency(referralExact)"></span>'); ?>
                            </div>
                            <div x-show="isReferral && referralMode === 'range' && parseFloat(baseAmount) > 0 && parseFloat(baseAmount) < parseFloat(referralMin)" class="flex items-center gap-2 text-rose-400 text-xs font-medium" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?php echo sprintf(__('Minimum referral withdrawal is %s'), '<span x-text="formatCurrency(referralMin)"></span>'); ?>
                            </div>
                            <div x-show="isReferral && referralMode === 'range' && parseFloat(referralMax) > 0 && parseFloat(baseAmount) > parseFloat(referralMax)" class="flex items-center gap-2 text-rose-400 text-xs font-medium" style="display: none;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <?php echo sprintf(__('Maximum referral withdrawal is %s'), '<span x-text="formatCurrency(referralMax)"></span>'); ?>
                            </div>
                        </div>

                        <!-- Transaction Summary -->
                        <div class="bg-zinc-950 border border-zinc-900 rounded-2xl p-5">
                            <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-4"><?php echo __('Transaction Summary'); ?></p>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center sm:text-left">
                                <div class="border-b sm:border-b-0 sm:border-r border-zinc-800/60 pb-4 sm:pb-0 sm:pr-4">
                                    <p class="text-xs text-zinc-500 mb-1"><?php echo __('Requested Amount'); ?></p>
                                    <p class="text-xl font-bold font-mono text-zinc-200" x-text="baseAmount ? formatCurrency(parseFloat(baseAmount)) : formatCurrency(0)"></p>
                                </div>
                                <div class="border-b sm:border-b-0 sm:border-r border-zinc-800/60 pb-4 sm:pb-0 sm:pr-4">
                                    <p class="text-xs text-zinc-500 mb-1"><?php echo __('Processing Fee'); ?> (<span x-text="withdrawalFeePercent + '%'"></span>)</p>
                                    <p class="text-xl font-bold font-mono text-rose-400/90" x-text="baseAmount ? '-' + formatCurrency(parseFloat(feeAmount)) : formatCurrency(0)"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-400 font-medium mb-1 flex items-center justify-center sm:justify-start gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-accent animate-pulse"></span>
                                        <?php echo __('You Will Receive'); ?>
                                    </p>
                                    <p class="text-2xl font-black font-mono text-brand-accent" x-text="baseAmount ? formatCurrency(parseFloat(netAmount)) : formatCurrency(0)"></p>
                                </div>
                            </div>

                            <?php if ($has_local_currency): ?>
                                <div class="mt-5 pt-4 border-t border-zinc-900/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs bg-zinc-900/20 p-3 rounded-xl">
                                    <span class="text-zinc-500 font-medium"><?php echo __('≈ Estimated Local Receipt'); ?></span>
                                    <span class="font-mono text-zinc-300" x-text="baseAmount ? localCurrencySymbol + localNetAmount + ' ' + localCurrencyCode : '—'"></span>
                                </div>
                            <?php endif; ?>

                            <!-- Recipient Preview -->
                            <div class="mt-5 pt-4 border-t border-zinc-900/80">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-2"><?php echo __('Recipient Details'); ?></label>
                                <div class="flex items-center gap-3 bg-zinc-900/50 border border-zinc-800/60 p-3 rounded-xl">
                                    <div class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-brand-accent flex-shrink-0">
                                        <i :class="getMethodIcon()"></i>
                                    </div>
                                    <div class="overflow-hidden flex-1 min-w-0">
                                        <div class="text-sm font-bold text-zinc-200" x-text="getMethodName()"></div>
                                        <div class="text-xs text-zinc-500 truncate">
                                            <span x-show="getMethodType() === 'crypto'" x-text="address || window.withdrawData.translations.noAddress"></span>
                                            <span x-show="getMethodType() === 'fiat'" x-text="accountNumber ? bankName + ' - ' + accountNumber : window.withdrawData.translations.enterBank"></span>
                                            <span x-show="getMethodType() === 'momo'" x-text="mobileNumber ? mobileName + ' - ' + mobileProvider + ' - ' + mobileNumber + (mobileReference ? ' (' + mobileReference + ')' : '') : window.withdrawData.translations.enterMobile"></span>
                                            <span x-show="getMethodType() === 'ewallet'" x-text="ewalletId || window.withdrawData.translations.enterWallet"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submission -->
                <div class="pt-2">
                    <form action="<?php echo $is_referral_withdrawal ? '/actions/referral-withdraw.php' : '/actions/withdraw-submit.php'; ?>" method="POST" @submit="loading = true">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <?php if ($is_referral_withdrawal): ?>
                            <input type="hidden" name="source" value="referral">
                        <?php endif; ?>
                        <input type="hidden" name="method" :value="selectedMethod">
                        <input type="hidden" name="amount" :value="baseAmount">
                        <input type="hidden" name="local_currency_amount" :value="isLocalCurrency ? displayAmount : ''">
                        <input type="hidden" name="network" :value="network">
                        <input type="hidden" name="crypto_address" :value="address">
                        <input type="hidden" name="bank_name" :value="bankName">
                        <input type="hidden" name="account_name" :value="accountName">
                        <input type="hidden" name="account_number" :value="accountNumber">
                        <input type="hidden" name="mobile_provider" :value="mobileProvider">
                        <input type="hidden" name="mobile_number" :value="mobileNumber">
                        <input type="hidden" name="mobile_name" :value="mobileName">
                        <input type="hidden" name="mobile_reference" :value="mobileReference">
                        <input type="hidden" name="ewallet_provider" :value="ewalletProvider">
                        <input type="hidden" name="ewallet_id" :value="ewalletId">

                        <button type="submit" class="w-full py-4 rounded-2xl font-bold text-base tracking-wide transition-all flex items-center justify-center gap-2.5"
                            :class="loading || !isValid ? 'bg-zinc-800 text-zinc-500 cursor-not-allowed shadow-inner' : 'bg-brand-accent hover:bg-emerald-400 text-brand-dark shadow-[0_0_30px_rgba(16,185,129,0.2)] hover:shadow-[0_0_35px_rgba(16,185,129,0.45)] transform hover:-translate-y-0.5'"
                            :disabled="loading || !isValid">
                            <span x-show="!loading && isValid"><i class="fa-solid fa-bolt-lightning me-2"></i> <?php echo __('Confirm Withdrawal'); ?></span>
                            <span x-show="!loading && !isValid"><?php echo __('Complete Form to Continue'); ?></span>
                            <span x-show="loading" style="display: none;"><i class="fa-solid fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                        </button>
                    </form>
                    <div class="flex items-center justify-center gap-2 text-xs text-zinc-600 mt-3.5 tracking-wide">
                        <i class="fa-solid fa-lock text-[10px]"></i> <?php echo __('Secure Transaction'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Withdrawal History -->
<div class="mt-10 space-y-4">
    <div class="flex items-center justify-between px-2">
        <h3 class="text-zinc-50 font-bold tracking-tight"><?php echo __('Recent Withdrawals'); ?></h3>
    </div>

    <div class="glass-canvas rounded-2xl overflow-hidden p-4">
        <?php if ($history && count($history) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs font-bold text-zinc-500 uppercase tracking-wider border-b border-zinc-800">
                        <tr>
                            <th class="px-4 py-3"><?php echo __('Date'); ?></th>
                            <th class="px-4 py-3"><?php echo __('Amount'); ?></th>
                            <th class="px-4 py-3"><?php echo __('Fee'); ?></th>
                            <th class="px-4 py-3"><?php echo __('Net'); ?></th>
                            <th class="px-4 py-3"><?php echo __('Method'); ?></th>
                            <th class="px-4 py-3"><?php echo __('Status'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <?php foreach ($history as $w):
                            $net = $w['amount'] - $w['fee_amount'];
                            $method_display = $enabled_methods[$w['payment_method']]['name'] ?? ucfirst($w['payment_method']);
                            $status = strtolower($w['status']);
                            $status_classes = [
                                'pending'    => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                                'approved'   => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                                'completed'  => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                                'success'    => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                                'rejected'   => 'bg-rose-500/10 text-rose-500 border-rose-500/20',
                                'failed'     => 'bg-rose-500/10 text-rose-500 border-rose-500/20',
                                'cancelled'  => 'bg-rose-500/10 text-rose-500 border-rose-500/20',
                            ];
                            $status_class = $status_classes[$status] ?? 'bg-zinc-500/10 text-zinc-500 border-zinc-500/20';
                        ?>
                            <tr class="hover:bg-zinc-800/30 transition-colors">
                                <td class="px-4 py-4 text-zinc-300"><?php echo e(format_date($w['created_at'])); ?></td>
                                <td class="px-4 py-4 font-bold text-zinc-200" x-text="formatCurrency(<?php echo json_encode((float)$w['amount']); ?>)"></td>
                                <td class="px-4 py-4 text-zinc-400" x-text="'-' + formatCurrency(<?php echo json_encode((float)$w['fee_amount']); ?>)"></td>
                                <td class="px-4 py-4 font-bold text-brand-accent" x-text="formatCurrency(<?php echo json_encode((float)$net); ?>)"></td>
                                <td class="px-4 py-4 text-zinc-300"><?php echo e($method_display); ?></td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border <?php echo e($status_class); ?>">
                                        <?php echo e(__(ucfirst($w['status']))); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="py-12 flex flex-col items-center justify-center text-center space-y-3">
                <div class="w-14 h-14 rounded-2xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-700 shadow-inner">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-bold text-zinc-400"><?php echo __('No Recent Activity'); ?></p>
                    <p class="text-xs text-zinc-600 max-w-xs"><?php echo __('You have no recent withdrawal transactions.'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT . '/includes/new-footer.php'; ?>