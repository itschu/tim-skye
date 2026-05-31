<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Withdraw Funds');
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();

$available = get_available_balance($user_id);
$locked = get_locked_balance($user_id);
$fee_percent = (float)get_setting('withdrawal_fee_percentage', 2);
$minimum_withdrawal = (float)get_setting('minimum_withdrawal', 1);

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

?>
<?php require ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Withdraw Funds'); ?></h3>
        <p class="text-secondary mb-0 small"><?php echo __('Transfer earnings to your external account'); ?></p>
    </div>
    <div>
        <button class="btn btn-white card-custom border shadow-sm fw-bold d-flex align-items-center gap-2 py-2 px-3 rounded-pill" style="cursor: default;" @click="toggleCurrency()">
            <img :src="currencyFlag" width="20" height="20" class="rounded-circle object-fit-cover" />
            <span x-text="currency"><?php echo e(get_currency_code()); ?></span>
        </button>
    </div>
</div>

<?php if (empty($enabled_methods)): ?>
    <!-- No Methods Available State -->
    <div class="card card-glass border-0 mb-5 overflow-hidden">
        <div class="p-5 text-center">
            <div class="rounded-circle p-4 mb-3 d-inline-flex" style="background: rgba(255,255,255,0.1);">
                <i class="fas fa-university fa-3x opacity-75" style="color: var(--text-secondary, #6c757d);"></i>
            </div>
            <h5 class="fw-bold mb-2 text-dark"><?php echo __('No Withdrawal Methods Available'); ?></h5>
            <p class="mb-0" style="color: var(--text-secondary, #6c757d);"><?php echo __('Please contact support for withdrawal options.'); ?></p>
        </div>
    </div>
<?php else: ?>
    <!-- Alpine.js Component -->
    <?php
    $methods_json = json_encode($enabled_methods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $account_name = $user['name'] ?? '';
    ?>
    <div x-data="withdrawFormData()" x-init="initWithdrawForm($data)">
        <script>
            // PHP data passed to JavaScript
            window.withdrawData = {
                availableBalance: <?php echo json_encode((float)$available); ?>,
                currencySymbol: "<?php echo $currency_symbol; ?>",
                firstMethodKey: <?php echo json_encode($first_method_key); ?>,
                withdrawalFeePercent: <?php echo json_encode($fee_percent); ?>,
                minimumWithdrawal: <?php echo json_encode($minimum_withdrawal); ?>,
                accountName: <?php echo json_encode($account_name); ?>,
                methods: <?php echo $methods_json; ?>,
                hasLocalCurrency: <?php echo json_encode($has_local_currency); ?>,
                localCurrencyCode: "<?php echo e($local_currency_code ?? ''); ?>",
                localCurrencySymbol: "<?php echo e($local_currency_symbol ?? ''); ?>",
                exchangeRate: <?php echo json_encode($exchange_rate ?? 0); ?>,
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
                    amount: '',
                    selectedMethod: window.withdrawData.firstMethodKey,
                    withdrawalFeePercent: window.withdrawData.withdrawalFeePercent,
                    minimumWithdrawal: window.withdrawData.minimumWithdrawal,
                    hasLocalCurrency: window.withdrawData.hasLocalCurrency,
                    localCurrencyCode: window.withdrawData.localCurrencyCode,
                    localCurrencySymbol: window.withdrawData.localCurrencySymbol,
                    exchangeRate: window.withdrawData.exchangeRate,
                    loading: false,

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

                    get feeAmount() {
                        let val = parseFloat(this.amount);
                        return isNaN(val) ? 0 : (val * (this.withdrawalFeePercent / 100)).toFixed(2);
                    },

                    get netAmount() {
                        let val = parseFloat(this.amount);
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
                        let val = parseFloat(this.amount);
                        return isNaN(val) ? '0.00' : (val * this.exchangeRate).toFixed(2);
                    },

                    get isValid() {
                        let val = parseFloat(this.amount);
                        let validAmount = val > 0 && val >= this.minimumWithdrawal && val <= this.availableBalance;

                        if (!validAmount) return false;

                        let methodType = this.getMethodType();

                        if (methodType === 'crypto') {
                            return this.address.length > 5 && this.network !== '';
                        } else if (methodType === 'fiat') {
                            return this.bankName.length > 2 && this.accountName.length > 2 && this.accountNumber.length > 5;
                        } else if (methodType === 'momo') {
                            return this.mobileProvider !== '' && this.mobileNumber.length > 6 && this.mobileName.length > 2;
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
                        this.amount = this.availableBalance;
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
        <div class="row g-4">
            <!-- Left Column: Form -->
            <div class="col-lg-7">
                <div class="card card-glass border-0 h-100">
                    <div class="card-body p-4">
                        <!-- Balance Card -->
                        <div class="balance-card">
                            <div>
                                <span class="d-block text-white-50 small fw-bold text-uppercase ls-1 mb-1"><?php echo __('Available Balance'); ?></span>
                                <h3 class="fw-bold text-white mb-0" x-text="formatCurrency(availableBalance)"></h3>
                            </div>
                            <div class="bg-white bg-opacity-10 text-white rounded-circle p-3">
                                <i class="fas fa-wallet fa-lg"></i>
                            </div>
                        </div>

                        <h6 class="form-label mb-3"><?php echo __('1. Select Withdrawal Method'); ?></h6>

                        <!-- Method Selection -->
                        <div class="method-radio-group mb-4">
                            <?php foreach ($enabled_methods as $key => $method):
                                // Hardcoded icons based on method type
                                $icon_class = match ($method['type'] ?? '') {
                                    'crypto' => 'fa-brands fa-bitcoin',
                                    'fiat' => 'fa-solid fa-building-columns',
                                    'momo' => 'fa-solid fa-mobile-screen',
                                    'ewallet' => 'fa-solid fa-wallet',
                                    default => 'fa-solid fa-money-bill'
                                };
                            ?>
                                <div class="method-radio"
                                    @click="selectMethod('<?php echo $key; ?>')"
                                    :class="{ 'active': selectedMethod === '<?php echo $key; ?>' }">
                                    <i class="<?php echo $icon_class; ?> fa-2x mb-2"
                                        :class="selectedMethod === '<?php echo $key; ?>' ? 'text-primary' : 'text-secondary opacity-50'"></i>
                                    <div class="small fw-bold"><?php echo e($method['name']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h6 class="form-label mb-3"><?php echo __('2. Destination Details'); ?></h6>

                        <!-- Crypto Fields -->
                        <div x-show="getMethodType() === 'crypto'" x-transition>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Select Network'); ?></label>
                                <select class="form-select" x-model="network">
                                    <option value="" disabled selected><?php echo __('Select Network...'); ?></option>
                                    <template x-for="net in getNetworks()" :key="net">
                                        <option :value="net" x-text="net"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Wallet Address'); ?></label>
                                <input type="text" class="form-control" x-model="address" placeholder="<?php echo __('Paste your wallet address here'); ?>" />
                            </div>
                            <div class="warning-box mb-4">
                                <i class="fas fa-exclamation-triangle mt-1"></i>
                                <div>
                                    <?php echo __('Ensure the address matches the selected'); ?> <strong><span x-text="network || <?php echo htmlspecialchars(json_encode(__('Network')), ENT_QUOTES, 'UTF-8'); ?>"></span></strong>. <?php echo __('Transfers to the wrong network cannot be recovered.'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer Fields -->
                        <div x-show="getMethodType() === 'fiat'" style="display: none" x-transition>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Bank Name'); ?></label>
                                <input type="text" class="form-control" x-model="bankName" placeholder="<?php echo __('e.g. Chase Bank'); ?>" />
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Account Name'); ?></label>
                                <input type="text" class="form-control" x-model="accountName" placeholder="<?php echo __('e.g. John Doe'); ?>" />
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Account Number'); ?></label>
                                <input type="text" class="form-control" x-model="accountNumber" placeholder="0000000000" />
                            </div>
                        </div>

                        <!-- Mobile Money Fields -->
                        <div x-show="getMethodType() === 'momo'" style="display: none" x-transition>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Provider'); ?></label>
                                <select class="form-select" x-model="mobileProvider">
                                    <option value="" disabled selected><?php echo __('Select Provider...'); ?></option>
                                    <template x-for="provider in getProviders()" :key="provider">
                                        <option :value="provider" x-text="provider"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Phone Number'); ?></label>
                                <input type="tel" class="form-control" x-model="mobileNumber" placeholder="+1234567890" />
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Full Name'); ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" x-model="mobileName" placeholder="<?php echo __('e.g. John Doe'); ?>" />
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Reference / Note'); ?> <small class="text-muted fw-normal">(<?php echo __('Optional'); ?>)</small></label>
                                <input type="text" class="form-control" x-model="mobileReference" placeholder="<?php echo __('e.g. Invoice #123, Family support'); ?>" />
                            </div>
                        </div>

                        <!-- E-Wallet Fields -->
                        <div x-show="getMethodType() === 'ewallet'" style="display: none" x-transition>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Provider'); ?></label>
                                <input type="text" class="form-control" x-model="ewalletProvider" placeholder="<?php echo __('e.g. PayPal, Skrill, Neteller'); ?>" />
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-1"><?php echo __('Wallet ID / Email'); ?></label>
                                <input type="text" class="form-control" x-model="ewalletId" placeholder="<?php echo __('Enter your wallet ID or email'); ?>" />
                            </div>
                        </div>

                        <h6 class="form-label mb-3"><?php echo __('3. Withdrawal Amount'); ?></h6>
                        <div class="position-relative mb-2">
                            <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-secondary fw-bold"><?php echo e($currency_symbol); ?></span>
                            <input type="number" step="0.01" x-model="amount" class="form-control form-control-lg ps-4 fw-bold fs-4" placeholder="0.00" />
                            <button type="button" class="btn btn-sm btn-light position-absolute top-50 end-0 translate-middle-y me-2 text-primary fw-bold" @click="setMax()"><?php echo __('MAX'); ?></button>
                        </div>
                        <?php if ($has_local_currency): ?>
                            <!-- Local Currency Estimate -->
                            <div class="alert alert-info bg-light text-dark border-0 mb-3 small" style="border-radius: 1rem;">
                                <i class="fas fa-info-circle me-2"></i>
                                <span x-text="'≈ ' + localCurrencySymbol + localAmountEstimate + ' ' + window.withdrawData.translations.estimatedLocalReceipt"></span>
                            </div>
                        <?php endif; ?>
                        <div x-show="parseFloat(amount) > 0 && parseFloat(amount) < minimumWithdrawal" class="text-danger small fw-bold mt-2" style="display: none">
                            <i class="fas fa-times-circle me-1"></i> <?php echo __('Minimum withdrawal is'); ?> <span x-text="formatCurrency(minimumWithdrawal)"></span>
                        </div>
                        <div x-show="parseFloat(amount) > availableBalance" class="text-danger small fw-bold mt-2" style="display: none">
                            <i class="fas fa-times-circle me-1"></i> <?php echo __('Insufficient balance'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary Card -->
            <div class="col-lg-5" x-ref="withdrawalDetails">
                <div class="card border-0 summary-card shadow-sm position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 bg-white bg-opacity-10 rounded-circle" style="width: 150px; height: 150px; transform: translate(30%, -30%);"></div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4"><?php echo __('Transaction Summary'); ?></h5>

                        <div class="bg-white bg-opacity-10 rounded-3 p-4 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-white-50 small"><?php echo __('Requested Amount'); ?></span>
                                <span class="fw-bold fs-5 text-white" x-text="amount ? formatCurrency(parseFloat(amount)) : formatCurrency(0)"></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-white-50 small"><?php echo __('Processing Fee'); ?> (<span x-text="withdrawalFeePercent + '%'"></span>)</span>
                                <span class="text-warning fw-bold" x-text="amount ? '-' + formatCurrency(parseFloat(feeAmount)) : formatCurrency(0)"></span>
                            </div>

                            <div class="border-top border-white border-opacity-25 my-3"></div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-white"><?php echo __('Net Receivable'); ?></span>
                                <span class="display-6 fw-bold text-white" x-text="amount ? formatCurrency(parseFloat(netAmount)) : formatCurrency(0)"></span>
                            </div>
                            <?php if ($has_local_currency): ?>
                                <div class="mt-3 pt-3 border-top border-white border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small text-white-50"><?php echo __('≈ Estimated Local Receipt'); ?></span>
                                        <span class="fw-bold text-white" x-text="amount ? localCurrencySymbol + localNetAmount + ' ' + localCurrencyCode : '—'"></span>
                                    </div>
                                    <p class="small text-white-50 mt-2 mb-0"><i class="fas fa-info-circle me-1"></i><?php echo __('Actual amount may vary by provider'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="small text-white-50 text-uppercase fw-bold mb-2"><?php echo __('Recipient Details'); ?></label>
                            <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-3 rounded">
                                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                                    <i :class="getMethodIcon()"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-white" x-text="getMethodName()"></div>
                                    <div class="small text-white-50 text-truncate">
                                        <span x-show="getMethodType() === 'crypto'" x-text="address || window.withdrawData.translations.noAddress"></span>
                                        <span x-show="getMethodType() === 'fiat'" x-text="accountNumber ? bankName + ' - ' + accountNumber : window.withdrawData.translations.enterBank"></span>
                                        <span x-show="getMethodType() === 'momo'" x-text="mobileNumber ? mobileName + ' - ' + mobileProvider + ' - ' + mobileNumber + (mobileReference ? ' (' + mobileReference + ')' : '') : window.withdrawData.translations.enterMobile"></span>
                                        <span x-show="getMethodType() === 'ewallet'" x-text="ewalletId || window.withdrawData.translations.enterWallet"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <form action="/actions/withdraw-submit.php" method="POST" @submit="loading = true">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="method" x-model="selectedMethod">
                                <input type="hidden" name="amount" x-model="amount">
                                <input type="hidden" name="network" x-model="network">
                                <input type="hidden" name="crypto_address" x-model="address">
                                <input type="hidden" name="bank_name" x-model="bankName">
                                <input type="hidden" name="account_name" x-model="accountName">
                                <input type="hidden" name="account_number" x-model="accountNumber">
                                <input type="hidden" name="mobile_provider" x-model="mobileProvider">
                                <input type="hidden" name="mobile_number" x-model="mobileNumber">
                                <input type="hidden" name="mobile_name" x-model="mobileName">
                                <input type="hidden" name="mobile_reference" x-model="mobileReference">
                                <input type="hidden" name="ewallet_provider" x-model="ewalletProvider">
                                <input type="hidden" name="ewallet_id" x-model="ewalletId">

                                <button type="submit" class="btn btn-white w-100 py-3 rounded-pill fw-bold text-primary shadow-lg" :disabled="loading || !isValid" :class="{ 'opacity-50': !isValid && !loading, 'darkMode-dark-text': isValid && !loading }">
                                    <span x-show="!loading && isValid"><i class="fas fa-check-circle me-2"></i> <?php echo __('Confirm Withdrawal'); ?></span>
                                    <span x-show="!loading && !isValid"><?php echo __('Complete Form to Continue'); ?></span>
                                    <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-1"></i> <?php echo __('Processing…'); ?></span>
                                </button>
                            </form>

                            <p class="text-center text-white-50 small mt-3 mb-0"><i class="fas fa-lock me-1"></i> <?php echo __('Secure Transaction'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Withdrawal History -->
<div class="row mt-5">
    <div class="col-12">
        <h5 class="fw-bold mb-3"><?php echo __('Recent Withdrawals'); ?></h5>
        <div class="card card-glass border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light bg-opacity-10">
                            <tr>
                                <th class="ps-4 py-3 text-secondary"><?php echo __('Date'); ?></th>
                                <th class="py-3 text-secondary"><?php echo __('Amount'); ?></th>
                                <th class="py-3 text-secondary"><?php echo __('Fee'); ?></th>
                                <th class="py-3 text-secondary"><?php echo __('Net Amount'); ?></th>
                                <th class="py-3 text-secondary"><?php echo __('Method'); ?></th>
                                <th class="py-3 text-secondary"><?php echo __('Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history): foreach ($history as $w):
                                    $net = $w['amount'] - $w['fee_amount'];
                                    $method_display = $enabled_methods[$w['payment_method']]['name'] ?? ucfirst($w['payment_method']);
                            ?>
                                    <tr>
                                        <td class="py-4 ps-4"><?php echo format_date($w['created_at']); ?></td>
                                        <td class="py-4 fw-bold" x-text="formatCurrency(<?php echo $w['amount']; ?>)"><?php echo format_money($w['amount']); ?></td>
                                        <td class="py-4 text-secondary" x-text="'-' + formatCurrency(<?php echo $w['fee_amount']; ?>)">-<?php echo format_money($w['fee_amount']); ?></td>
                                        <td class="py-4 fw-bold text-success" x-text="formatCurrency(<?php echo $net; ?>)"><?php echo format_money($net); ?></td>
                                        <td class="py-4"><?php echo e($method_display); ?></td>
                                        <td class="py-4">
                                            <span class="badge rounded-pill <?php echo $w['status'] === 'approved' ? 'bg-success' : ($w['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                <?php echo __(ucfirst($w['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-secondary">
                                        <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                                        <p class="mb-0"><?php echo __('No withdrawals yet.'); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT . '/includes/footer.php'; ?>