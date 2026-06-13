<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Deposit Funds');
$active_nav = 'deposit';
$user_id = $_SESSION['user_id'];

$currency_symbol = get_currency_symbol();
$base_currency_code = get_currency_code();

// Get user country and local currency
$user = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null;
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

// Get payment methods from settings
$payment_methods_json = get_setting('payment_methods', '[]');
$payment_methods = json_decode($payment_methods_json, true) ?: [];

function normalize_payment_method_type($type)
{
    $type = strtolower(trim($type));
    if (strpos($type, 'crypto') !== false || strpos($type, 'bitcoin') !== false || strpos($type, 'usdt') !== false) {
        return 'crypto';
    }
    if (strpos($type, 'bank') !== false) {
        return 'bank';
    }
    if (strpos($type, 'mobile') !== false || strpos($type, 'momo') !== false) {
        return 'momo';
    }
    if (strpos($type, 'wallet') !== false || strpos($type, 'e-wallet') !== false) {
        return 'ewallet';
    }
    return 'other';
}

foreach ($payment_methods as $key => $method) {
    $payment_methods[$key]['instructions'] = __($method['instructions']);
    $payment_methods[$key]['normalized_type'] = normalize_payment_method_type($method['type']);
}

// Get deposit fee
$deposit_fee_percentage = (float)get_setting('deposit_fee_percentage', 0);

$recent_deposits = db_query("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user_id]);

// Capture page-specific CSS and scripts before head include
ob_start();
?>
<style type="text/tailwindcss">
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    input[type="number"] { -moz-appearance: textfield; }
    .premium-input {
        @apply w-full bg-brand-dark border border-zinc-800 text-zinc-100 text-lg font-semibold rounded-xl px-4 py-3.5 focus:outline-none focus:border-brand-accent/50 focus:ring-1 focus:ring-brand-accent/50 transition-all placeholder:text-zinc-700;
    }
    .method-card { transition: all 0.2s ease; }
</style>
<?php
$extra_css = ob_get_clean();

ob_start();
?>
<script>
    window.depositData = {
        translations: {
            willBeCredited: <?php echo json_encode(sprintf(__('will be credited to your %s wallet'), $base_currency_code)); ?>,
            rateLabel: <?php echo json_encode(sprintf(__('Rate: 1 %s = '), $base_currency_code)); ?>,
            updatedLabel: <?php echo json_encode(__(' · Updated ')); ?>,
            youSend: <?php echo json_encode(__('You Send')); ?>,
            estimatedLocalReceipt: <?php echo json_encode(__('estimated local receipt')); ?>,
            enterValidAmount: <?php echo json_encode(__('Please enter a valid amount')); ?>,
            uploadProof: <?php echo json_encode(__('Please upload proof of payment')); ?>
        },
        baseCurrencyCode: <?php echo json_encode($base_currency_code); ?>
    };

    function depositApp() {
        return {
            amount: '',
            loading: false,
            selectedMethod: <?php echo json_encode(!empty($payment_methods) ? key($payment_methods) : ''); ?>,
            copied: false,
            fileName: null,
            previewUrl: null,
            depositFeePercent: <?php echo json_encode($deposit_fee_percentage); ?>,
            _currencySymbol: <?php echo json_encode($currency_symbol); ?>,
            hasLocalCurrency: <?php echo json_encode($has_local_currency); ?>,
            localCurrencyCode: <?php echo json_encode($local_currency_code ?? ''); ?>,
            localCurrencySymbol: <?php echo json_encode($local_currency_symbol ?? ''); ?>,
            exchangeRate: <?php echo json_encode($exchange_rate ?? 0); ?>,
            localCurrencyAmount: '',
            methods: <?php echo json_encode($payment_methods); ?>,

            init() {
                // console.log('Payment methods loaded:', this.methods);
                // console.log('Selected method:', this.selectedMethod);
                // console.log('Selected method data:', this.selectedMethodData);
            },

            get localAmount() {
                return parseFloat(this.amount) || 0;
            },

            get numericAmount() {
                if (this.hasLocalCurrency && this.exchangeRate) {
                    return (this.localAmount / this.exchangeRate);
                }
                return parseFloat(this.amount) || 0;
            },

            get usdEstimate() {
                if (this.hasLocalCurrency && this.exchangeRate) {
                    return (this.localAmount / this.exchangeRate).toFixed(2);
                }
                return this.numericAmount.toFixed(2);
            },

            get currencySymbol() {
                return this.hasLocalCurrency ? this.localCurrencySymbol : this._currencySymbol;
            },

            get feeAmount() {
                return (this.numericAmount * (this.depositFeePercent / 100)).toFixed(2);
            },

            get totalAmount() {
                return (this.numericAmount - parseFloat(this.feeAmount)).toFixed(2);
            },

            updateLocalEstimate() {
                if (this.hasLocalCurrency) {
                    this.localCurrencyAmount = this.localAmount.toFixed(2);
                }
            },

            get selectedMethodData() {
                return this.methods[this.selectedMethod] || {};
            },

            selectMethod(methodKey) {
                this.selectedMethod = methodKey;
                this.fileName = null;
                this.previewUrl = null;
                document.getElementById('proofInput').value = '';

                if (window.innerWidth < 992) {
                    setTimeout(() => {
                        this.$refs.paymentDetails.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 100);
                }
            },

            copyAddress(field) {
                const value = this.selectedMethodData[field] || '';
                navigator.clipboard.writeText(value);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            },

            handleFileChange(event) {
                const file = event.target.files[0];
                if (file) {
                    this.fileName = file.name;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewUrl = e.target.result;
                    };
                    reader.readAsDataURL(file);

                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.proofFileHidden.files = dt.files;
                }
            },

            removeFile() {
                this.fileName = null;
                this.previewUrl = null;
                document.getElementById('proofInput').value = '';
                this.$refs.proofFileHidden.value = '';
            },

            submitDeposit() {
                if (!this.amount || this.numericAmount <= 0) {
                    alert(window.depositData.translations.enterValidAmount);
                    return;
                }

                if (!this.previewUrl && this.selectedMethodData.type !== 'auto') {
                    alert(window.depositData.translations.uploadProof);
                    return;
                }

                // Manually set form values before submission (x-model doesn't sync reliably on programmatic submit)
                const form = this.$refs.depositFormHidden;
                form.querySelector('input[name="payment_method"]').value = this.selectedMethod;
                form.querySelector('input[name="amount"]').value = this.numericAmount;
                form.querySelector('input[name="local_currency_amount"]').value = this.hasLocalCurrency ? this.localAmount : '';

                // Copy file from visible input to hidden form
                const fileInput = document.getElementById('proofInput');
                if (fileInput && fileInput.files.length > 0) {
                    const dt = new DataTransfer();
                    dt.items.add(fileInput.files[0]);
                    this.$refs.proofFileHidden.files = dt.files;
                }

                this.loading = true;
                this.$nextTick(() => {
                    form.submit();
                });
            }
        };
    }
</script>
<?php
$extra_scripts = ob_get_clean();

require ROOT . '/includes/new-head.php';
require ROOT . '/includes/new-header.php';
require_once ROOT . '/includes/currency-conversion.php';

if (get_maintenance_mode()): ?>
    <div class="mb-4 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-amber-400 flex items-start justify-between shadow-sm" role="alert">
        <span class="text-sm font-medium"><?php echo __('Platform is temporarily under maintenance. Deposits, withdrawals, and investments are disabled.'); ?></span>
    </div>
<?php endif; ?>

<!-- Page Header -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-zinc-900 pb-6">
    <div>
        <h1 class="text-2xl md:text-4xl font-bold text-zinc-50 mb-1 tracking-tight"><?php echo __('Deposit Funds'); ?></h1>
        <p class="text-zinc-400 text-sm"><?php echo __('Securely add funds to your wallet'); ?></p>
    </div>
    <a href="/contact" class="w-full sm:w-auto px-4 py-2.5 bg-zinc-900 border border-zinc-800 hover:border-zinc-700 rounded-xl text-zinc-300 hover:text-white flex items-center justify-center gap-2 transition-colors text-sm font-medium">
        <i class="fa-solid fa-headset text-xs"></i> <?php echo __('Help'); ?>
    </a>
</header>

<?php if (empty($payment_methods)): ?>
    <div class="bg-brand-card rounded-3xl p-8 border border-zinc-800 flex flex-col items-center justify-center text-center h-64 relative overflow-hidden">
        <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-zinc-800/10 rounded-full blur-xl"></div>
        <div class="w-14 h-14 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-4">
            <i class="fa-solid fa-credit-card text-xl"></i>
        </div>
        <h4 class="text-zinc-50 font-bold mb-1"><?php echo __('No Payment Methods Available'); ?></h4>
        <p class="text-zinc-500 text-sm"><?php echo __('Please check back later for available deposit options.'); ?></p>
    </div>
<?php else: ?>
    <!-- Alpine.js Component -->
    <div x-data="depositApp()" x-init="init()">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Column - Method Selection & Amount -->
            <div class="col-span-1 lg:col-span-7 space-y-6">
                <!-- Method Selection -->
                <div class="bg-brand-card border border-zinc-800/80 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-6 h-6 rounded-full bg-brand-accent/10 border border-brand-accent/30 text-brand-accent text-xs font-bold flex items-center justify-center">1</span>
                        <h3 class="text-sm font-bold text-zinc-300 uppercase tracking-wider"><?php echo __('Select Payment Method'); ?></h3>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($payment_methods as $key => $method):
                            $icon = 'fa-credit-card';
                            $color = 'zinc';
                            if (stripos($method['type'], 'crypto') !== false || stripos($method['type'], 'bitcoin') !== false || stripos($method['type'], 'usdt') !== false) {
                                $icon = 'fa-coins';
                                $color = 'amber';
                            } elseif (stripos($method['type'], 'bank') !== false) {
                                $icon = 'fa-university';
                                $color = 'sky';
                            } elseif (stripos($method['type'], 'mobile') !== false) {
                                $icon = 'fa-mobile-alt';
                                $color = 'emerald';
                            } elseif (stripos($method['type'], 'wallet') !== false) {
                                $icon = 'fa-wallet';
                                $color = 'violet';
                            }
                        ?>
                            <div class="method-card bg-brand-dark border-2 border-zinc-800 rounded-2xl p-4 flex items-center justify-between cursor-pointer"
                                @click="selectMethod('<?php echo e($key); ?>')"
                                :class="{ 'border-brand-accent shadow-[0_0_20px_rgba(16,185,129,0.05)]': selectedMethod === '<?php echo e($key); ?>', 'border-zinc-800 hover:border-zinc-700': selectedMethod !== '<?php echo e($key); ?>' }">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-<?php echo $color; ?>-500/10 border border-<?php echo $color; ?>-500/20 text-<?php echo $color; ?>-500 flex items-center justify-center text-xl">
                                        <i class="fa-solid <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-white font-bold text-base"><?php echo e($method['name']); ?></h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700 uppercase tracking-wider mt-0.5"><?php echo e(__($method['type'])); ?></span>
                                    </div>
                                </div>
                                <div x-show="selectedMethod === '<?php echo e($key); ?>'" class="w-5 h-5 rounded-full bg-brand-accent text-brand-dark flex items-center justify-center text-[10px] font-bold">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Amount -->
                <div class="bg-brand-card border border-zinc-800/80 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-6 h-6 rounded-full bg-brand-accent/10 border border-brand-accent/30 text-brand-accent text-xs font-bold flex items-center justify-center">2</span>
                        <h3 class="text-sm font-bold text-zinc-300 uppercase tracking-wider">
                            <?php if ($has_local_currency): ?>
                                <?php echo __('Enter Amount (Your Local Currency)'); ?>
                            <?php else: ?>
                                <?php echo __('Enter Amount'); ?>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <form @submit.prevent="submitDeposit()" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="payment_method" x-model="selectedMethod">
                        <?php if ($has_local_currency): ?>
                            <input type="hidden" name="local_currency_amount" x-model="localCurrencyAmount">
                        <?php endif; ?>

                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-zinc-500 font-bold text-lg select-none">
                                <?php if ($has_local_currency): ?><?php echo e($local_currency_code); ?><?php else: ?><?php echo e($currency_symbol); ?><?php endif; ?>
                            </span>
                            <input type="number" name="amount" class="premium-input pl-20" x-model="amount" @input="updateLocalEstimate()" placeholder="0.00" min="1" step="0.01" required style="padding-left: 55px !important;" />
                        </div>

                        <?php if ($has_local_currency): ?>
                            <!-- Local Currency Estimate -->
                            <div class="p-3.5 bg-zinc-900/60 border border-zinc-800/80 rounded-xl flex items-center justify-between text-xs">
                                <span class="text-zinc-500 flex items-center gap-2"><i class="fa-solid fa-circle-info text-brand-accent"></i> <?php echo __('Estimated Credit'); ?></span>
                                <span class="font-mono text-zinc-300" x-text="'≈ ' + '<?php echo e($currency_symbol); ?>' + usdEstimate + ' ' + window.depositData.translations.willBeCredited"></span>
                            </div>

                            <!-- Rate Note -->
                            <div class="text-zinc-500 text-xs flex items-center gap-1">
                                <i class="fa-solid fa-exchange-alt"></i>
                                <span>
                                    <?php echo sprintf(__('Rate: 1 %s = '), $base_currency_code); ?><?php echo e($local_currency_symbol); ?><span x-text="exchangeRate.toFixed(2)"></span><?php echo __(' · Updated '); ?><?php echo $rate_updated_at ? time_ago($rate_updated_at) : __('Unknown'); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Summary -->
                        <div class="bg-zinc-900/30 border border-zinc-800/60 rounded-2xl p-4 space-y-3 font-medium text-sm">
                            <?php if ($has_local_currency): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-zinc-500"><?php echo __('You Send'); ?></span>
                                    <span class="text-zinc-300 font-semibold font-mono" x-text="localCurrencySymbol + localAmount.toFixed(2)"></span>
                                </div>
                            <?php else: ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-zinc-500"><?php echo __('Amount'); ?></span>
                                    <span class="text-zinc-300 font-semibold font-mono" x-text="'<?php echo e($currency_symbol); ?>' + numericAmount.toFixed(2)"></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($deposit_fee_percentage > 0): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-zinc-500"><?php echo __('Processing Fee'); ?> (<span x-text="depositFeePercent + '%'"></span>)</span>
                                    <span class="text-zinc-300 font-semibold font-mono" x-text="'<?php echo e($currency_symbol); ?>' + feeAmount"></span>
                                </div>
                            <?php endif; ?>

                            <div class="h-px bg-zinc-800/60 w-full"></div>

                            <div class="flex justify-between items-center">
                                <span class="text-zinc-400 font-semibold"><?php echo __('You Receive'); ?></span>
                                <span class="text-brand-accent font-bold text-base font-mono" x-text="'<?php echo e($currency_symbol); ?>' + totalAmount"></span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column - Payment Details -->
            <div class="col-span-1 lg:col-span-5" x-ref="paymentDetails">
                <div class="bg-gradient-to-b from-brand-card to-zinc-900 border border-zinc-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden flex flex-col">
                    <div class="absolute -right-12 -top-12 w-36 h-36 bg-brand-accent/5 rounded-full blur-2xl pointer-events-none"></div>

                    <h3 class="text-lg font-bold text-white tracking-tight mb-2"><?php echo __('Complete Payment'); ?></h3>

                    <!-- Instructions -->
                    <div class="mb-4" x-show="selectedMethodData.instructions">
                        <div class="p-3.5 bg-brand-accent/10 border border-brand-accent/20 rounded-xl text-brand-accent text-xs flex items-start gap-2">
                            <i class="fa-solid fa-circle-info mt-0.5"></i>
                            <span x-text="selectedMethodData.instructions"></span>
                        </div>
                    </div>

                    <!-- QR Code for Crypto -->
                    <div x-show="selectedMethodData.normalized_type === 'crypto'" class="text-center mb-6" x-transition>
                        <div x-show="selectedMethodData.qr_code" class="bg-white p-3 rounded-2xl border border-zinc-800 inline-block mb-3">
                            <img :src="selectedMethodData.qr_code" alt="<?php echo e(__('QR Code')); ?>" class="w-36 h-36 object-contain rounded-xl">
                        </div>
                        <p class="text-xs text-zinc-400 mb-0" x-show="selectedMethodData.qr_code"><?php echo __('Scan QR code or copy address below'); ?></p>
                        <p class="text-xs text-amber-400 mb-0" x-show="!selectedMethodData.qr_code"><i class="fa-solid fa-exclamation-circle me-1"></i><?php echo __('QR code not available - contact admin'); ?></p>
                    </div>

                    <!-- Type-Specific Payment Details -->
                    <div class="mb-6 space-y-4">
                        <!-- Cryptocurrency -->
                        <div x-show="selectedMethodData.normalized_type === 'crypto'" x-transition>
                            <div x-show="selectedMethodData.wallet_address" class="bg-brand-dark/80 border border-zinc-800 rounded-2xl p-4">
                                <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1.5"><?php echo __('Wallet Address'); ?></p>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-mono font-bold text-brand-accent break-all" x-text="selectedMethodData.wallet_address"></span>
                                    <button type="button" class="shrink-0 p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('wallet_address')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 p-3.5 bg-amber-500/10 border border-amber-500/20 rounded-xl text-amber-400 text-xs flex gap-2">
                                <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                                <div>
                                    <?php echo __('Please ensure you are sending via the correct network.'); ?>
                                    <span x-show="selectedMethodData.network"><strong>(<span x-text="selectedMethodData.network"></span>)</strong></span>
                                    <?php echo __('Transactions sent to the wrong network may not be recoverable.'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div x-show="selectedMethodData.normalized_type === 'bank'" x-transition>
                            <div class="bg-brand-dark/80 border border-zinc-800 rounded-2xl p-4 space-y-4">
                                <div x-show="selectedMethodData.bank_name" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Bank Name'); ?></p>
                                        <p class="text-sm font-bold text-white" x-text="selectedMethodData.bank_name"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('bank_name')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.account_name" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Account Name'); ?></p>
                                        <p class="text-sm font-bold text-white" x-text="selectedMethodData.account_name"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('account_name')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.account_number" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Account Number'); ?></p>
                                        <p class="text-sm font-mono font-bold text-white" x-text="selectedMethodData.account_number"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('account_number')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.swift_code" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('SWIFT / Routing'); ?></p>
                                        <p class="text-sm font-mono font-bold text-white" x-text="selectedMethodData.swift_code"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('swift_code')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 p-3.5 bg-sky-500/10 border border-sky-500/20 rounded-xl text-sky-400 text-xs flex gap-2">
                                <i class="fa-solid fa-circle-info mt-0.5"></i>
                                <div><?php echo __('Use your Username as the payment reference/memo.'); ?></div>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div x-show="selectedMethodData.normalized_type === 'ewallet'" x-transition>
                            <div class="bg-brand-dark/80 border border-zinc-800 rounded-2xl p-4 space-y-4">
                                <div x-show="selectedMethodData.provider" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Provider'); ?></p>
                                        <p class="text-sm font-bold text-white" x-text="selectedMethodData.provider"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('provider')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.wallet_id" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Wallet ID / Email'); ?></p>
                                        <p class="text-sm font-mono font-bold text-white" x-text="selectedMethodData.wallet_id"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('wallet_id')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.account_name" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Account Holder'); ?></p>
                                        <p class="text-sm font-bold text-white" x-text="selectedMethodData.account_name"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('account_name')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Money -->
                        <div x-show="selectedMethodData.normalized_type === 'momo'" x-transition>
                            <div class="bg-brand-dark/80 border border-zinc-800 rounded-2xl p-4 space-y-4">
                                <div x-show="selectedMethodData.provider" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Provider'); ?></p>
                                        <p class="text-sm font-bold text-white" x-text="selectedMethodData.provider"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('provider')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.phone_number" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Phone Number'); ?></p>
                                        <p class="text-sm font-mono font-bold text-brand-accent" x-text="selectedMethodData.phone_number"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('phone_number')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                                <div x-show="selectedMethodData.account_name" class="flex justify-between items-center border-t border-zinc-800/60 pt-3">
                                    <div>
                                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-0.5"><?php echo __('Account Holder'); ?></p>
                                        <p class="text-xs font-bold text-zinc-300" x-text="selectedMethodData.account_name"></p>
                                    </div>
                                    <button type="button" class="p-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-zinc-400 hover:text-white transition-colors" @click="copyAddress('account_name')">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Other -->
                        <div x-show="selectedMethodData.normalized_type === 'other' && selectedMethodData.details" x-transition>
                            <div class="bg-brand-dark/80 border border-zinc-800 rounded-2xl p-4">
                                <p class="text-sm text-zinc-300 leading-relaxed" x-text="selectedMethodData.details"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Proof -->
                    <div class="mt-auto pt-2 space-y-4">
                        <label class="block text-xs font-bold text-zinc-400 uppercase tracking-wider px-1"><?php echo __('Upload Proof of Payment'); ?></label>

                        <div class="border-2 border-dashed border-zinc-800 hover:border-brand-accent/40 bg-brand-dark/40 rounded-2xl p-6 text-center cursor-pointer group transition-all relative overflow-hidden min-h-[160px] flex items-center justify-center"
                            :class="{ 'border-brand-accent/40': previewUrl }">
                            <input type="file" id="proofInput" class="absolute inset-0 opacity-0 cursor-pointer z-10" @change="handleFileChange($event)" accept="image/*" />

                            <div x-show="!previewUrl" class="flex flex-col items-center justify-center">
                                <div class="w-12 h-12 rounded-full bg-zinc-900 text-zinc-500 group-hover:text-brand-accent border border-zinc-800 flex items-center justify-center mx-auto mb-3 transition-colors">
                                    <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                                </div>
                                <h4 class="text-sm font-semibold text-zinc-300 group-hover:text-white transition-colors"><?php echo __('Click to Upload Screenshot'); ?></h4>
                                <p class="text-xs text-zinc-600 mt-1"><?php echo __('Max size 5MB (JPG/PNG)'); ?></p>
                            </div>

                            <div x-show="previewUrl" class="w-full h-full absolute inset-0" style="display: none">
                                <img :src="previewUrl" class="w-full h-full object-cover opacity-80" alt="<?php echo e(__('Payment proof preview')); ?>">
                                <div class="absolute bottom-0 left-0 w-full p-2 bg-black/70 flex items-center justify-between" style="border-radius: 0 0 1rem 1rem;">
                                    <span class="text-white text-xs truncate max-w-[70%]" x-text="fileName"></span>
                                    <button type="button" class="p-1.5 bg-rose-500/20 border border-rose-500/30 rounded-lg text-rose-400 hover:text-rose-300 transition-colors" @click.stop="removeFile()">
                                        <i class="fa-solid fa-times text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm Deposit -->
                        <button type="button" @click="submitDeposit()" class="w-full py-4 mt-2 bg-brand-accent hover:bg-emerald-400 text-brand-dark font-bold text-base rounded-xl transition-all shadow-[0_4px_25px_rgba(16,185,129,0.25)] hover:shadow-[0_4px_30px_rgba(16,185,129,0.4)] flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                            :disabled="loading || (!previewUrl && selectedMethodData.type !== 'auto')">
                            <span x-show="!loading"><i class="fa-solid fa-circle-check"></i> <?php echo __('Confirm Deposit'); ?></span>
                            <span x-show="loading" style="display:none"><i class="fa-solid fa-spinner fa-spin"></i> <?php echo __('Processing…'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden real submission form -->
        <form id="depositForm" action="/actions/deposit-submit.php" method="POST" enctype="multipart/form-data" class="hidden" x-ref="depositFormHidden">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="payment_method" :value="selectedMethod">
            <input type="hidden" name="amount" :value="amount">
            <input type="hidden" name="local_currency_amount" x-ref="localAmountInput" :value="numericAmount">
            <input type="file" name="proof" x-ref="proofFileHidden" class="hidden">
        </form>

        <!-- Recent Deposits -->
        <div class="pt-8">
            <h3 class="text-lg font-bold text-zinc-50 mb-4 tracking-tight px-1"><?php echo __('Recent Deposits'); ?></h3>
            <div class="bg-brand-card rounded-3xl border border-zinc-800/80 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-800/80 text-[10px] font-bold text-zinc-500 uppercase tracking-widest bg-zinc-900/20">
                                <th class="p-4 pl-6 whitespace-nowrap"><?php echo __('Date'); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo __('Method'); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo __('You Sent'); ?></th>
                                <th class="p-4 whitespace-nowrap"><?php echo __('Credited'); ?></th>
                                <th class="p-4 pr-6 text-right whitespace-nowrap"><?php echo __('Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium">
                            <?php if ($recent_deposits): foreach ($recent_deposits as $d):
                                    $status = strtolower($d['status']);
                                    if (in_array($status, ['pending', 'processing'])) {
                                        $status_color = 'amber';
                                    } elseif (in_array($status, ['approved', 'completed', 'success'])) {
                                        $status_color = 'emerald';
                                    } elseif (in_array($status, ['rejected', 'failed', 'cancelled'])) {
                                        $status_color = 'rose';
                                    } else {
                                        $status_color = 'zinc';
                                    }
                                    if (in_array($status, ['approved', 'completed', 'success'])) {
                                        $status_label = __('Completed');
                                    } else {
                                        $status_label = __(ucfirst($d['status']));
                                    }
                            ?>
                                    <tr class="border-b border-zinc-800/40 hover:bg-zinc-900/40 transition-colors">
                                        <td class="p-4 pl-6 text-zinc-300 font-mono"><?php echo format_date($d['created_at']); ?></td>
                                        <td class="p-4 text-zinc-400"><?php echo e($d['payment_method']); ?></td>
                                        <td class="p-4 text-zinc-400 font-mono">
                                            <?php if (!empty($d['local_currency_amount'])): ?>
                                                <?php echo get_currency_symbol($d['local_currency_code']); ?><?php echo number_format($d['local_currency_amount'], 2); ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-brand-accent font-bold font-mono">
                                            <?php if ($d['status'] === 'pending'): ?>
                                                <?php echo __('Pending'); ?>
                                            <?php else: ?>
                                                <?php if (!empty($d['fee_amount']) && floatval($d['fee_amount']) > 0): ?>
                                                    <?php echo format_money($d['net_amount']); ?>
                                                <?php else: ?>
                                                    <?php echo format_money($d['amount']); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 pr-6 text-right">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-<?php echo $status_color; ?>-500/10 text-<?php echo $status_color; ?>-500 border border-<?php echo $status_color; ?>-500/20 uppercase tracking-wider">
                                                <?php echo e($status_label); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-zinc-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-12 h-12 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-500 mb-3">
                                                <i class="fa-solid fa-history text-xl"></i>
                                            </div>
                                            <p class="text-zinc-500 text-sm mb-0"><?php echo __('No deposits yet.'); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require ROOT . '/includes/new-footer.php'; ?>