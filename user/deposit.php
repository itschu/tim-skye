<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));
$page_title = __('Deposit Funds');
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

foreach ($payment_methods as $key => $method) {
    $payment_methods[$key]['instructions'] = __($method['instructions']);
}

// Get deposit fee
$deposit_fee_percentage = (float)get_setting('deposit_fee_percentage', 0);

$recent_deposits = db_query("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user_id]);

?>
<?php require ROOT . '/includes/header.php'; ?>

<?php
require_once ROOT . '/includes/currency-conversion.php';
if (get_maintenance_mode()) {
    echo '<div class="alert alert-warning">' . __('Platform is temporarily under maintenance. Deposits, withdrawals, and investments are disabled.') . '</div>';
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 3vw, 2rem)"><?php echo __('Deposit Funds'); ?></h3>
            <p class="text-secondary mb-0 small"><?php echo __('Securely add funds to your wallet'); ?></p>
        </div>
    </div>

    <a href="/contact" class="btn btn-white border shadow-sm rounded-pill px-3 py-2 small fw-bold text-secondary">
        <i class="fas fa-headset me-2"></i><?php echo __('Help'); ?>
    </a>
</div>

<?php if (empty($payment_methods)): ?>
    <div class="card border-0 shadow-sm p-5 text-center" style="border-radius: 1.25rem;">
        <div class="mb-3">
            <i class="fas fa-credit-card fa-3x text-secondary opacity-25"></i>
        </div>
        <h5 class="fw-bold text-dark"><?php echo __('No Payment Methods Available'); ?></h5>
        <p class="text-muted"><?php echo __('Please check back later for available deposit options.'); ?></p>
    </div>
<?php else: ?>
    <!-- Alpine.js Component -->
    <div class="row g-4" x-data="depositApp()" x-init="init()">
        <!-- Left Column - Method Selection & Amount -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
                <div class="card-body p-4">
                    <h6 class="form-label mb-3">
                        <span class="badge bg-primary rounded-circle me-2">1</span>
                        <?php echo __('Select Payment Method'); ?>
                    </h6>

                    <div class="d-grid gap-3 mb-4">
                        <?php foreach ($payment_methods as $key => $method):
                            $icon = 'fa-credit-card';
                            $color = 'bg-secondary';
                            if (stripos($method['type'], 'crypto') !== false || stripos($method['type'], 'bitcoin') !== false || stripos($method['type'], 'usdt') !== false) {
                                $icon = 'fa-coins';
                                $color = 'bg-warning';
                            } elseif (stripos($method['type'], 'bank') !== false) {
                                $icon = 'fa-university';
                                $color = 'bg-primary';
                            } elseif (stripos($method['type'], 'mobile') !== false) {
                                $icon = 'fa-mobile-alt';
                                $color = 'bg-success';
                            } elseif (stripos($method['type'], 'wallet') !== false) {
                                $icon = 'fa-wallet';
                                $color = 'bg-info';
                            }
                        ?>
                            <div class="method-card" @click="selectMethod('<?php echo e($key); ?>')" :class="{ 'active': selectedMethod === '<?php echo e($key); ?>' }">
                                <div class="method-icon <?php echo $color; ?> bg-opacity-10 text-<?php echo str_replace('bg-', '', $color); ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold text-dark mb-0"><?php echo e($method['name']); ?></h6>
                                    <small class="text-muted"><?php echo e($method['type']); ?></small>
                                </div>
                                <div x-show="selectedMethod === '<?php echo e($key); ?>'" class="text-primary">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="border-light my-4" />

                    <h6 class="form-label mb-3">
                        <span class="badge bg-primary rounded-circle me-2">2</span>
                        <?php if ($has_local_currency): ?>
                            <?php echo __('Enter Amount (Your Local Currency)'); ?>
                        <?php else: ?>
                            <?php echo __('Enter Amount'); ?>
                        <?php endif; ?>
                    </h6>

                    <form action="/actions/deposit-submit.php" method="POST" enctype="multipart/form-data" @submit.prevent="submitDeposit()">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="payment_method" x-model="selectedMethod">
                        <?php if ($has_local_currency): ?>
                            <input type="hidden" name="local_currency_amount" x-model="localCurrencyAmount">
                        <?php endif; ?>

                        <div class="mb-3 position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-secondary fw-bold"><?php if ($has_local_currency): ?><?php echo e($local_currency_code); ?><?php else: ?><?php echo e($currency_symbol); ?><?php endif; ?></span>
                            <input type="number" name="amount" class="form-control form-control-lg ps-4 fw-bold fs-4" style="padding-left: 55px !important;" x-model="amount" placeholder="0.00" min="1" step="0.01" required />
                        </div>

                        <?php if ($has_local_currency): ?>
                            <!-- Local Currency Estimate -->
                            <div class="alert alert-info bg-light text-dark border-0 mb-3 small" style="border-radius: 1rem;">
                                <i class="fas fa-info-circle me-2"></i>
                                <span x-text="'≈ ' + '<?php echo e($currency_symbol); ?>' + usdEstimate + ' ' + window.depositData.translations.willBeCredited"></span>
                            </div>

                            <!-- Rate Note -->
                            <div class="text-muted small mb-3">
                                <?php
                                $time_label = $rate_updated_at ? time_ago($rate_updated_at) : __('Unknown');
                                ?>
                                <i class="fas fa-exchange-alt me-1"></i>
                                <span><?php echo sprintf(__('Rate: 1 %s = '), $base_currency_code); ?><?php echo e($local_currency_symbol); ?><span x-text="exchangeRate.toFixed(2)"></span><?php echo __(' · Updated '); ?><?php echo $time_label; ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="bg-light rounded-3 p-3 mb-4">
                            <?php if ($has_local_currency): ?>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-secondary"><?php echo __('You Send'); ?></span>
                                    <span class="fw-bold" x-text="localCurrencySymbol + localAmount.toFixed(2)"></span>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-secondary"><?php echo __('Amount'); ?></span>
                                    <span class="fw-bold" x-text="'<?php echo e($currency_symbol); ?>' + numericAmount.toFixed(2)"></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($deposit_fee_percentage > 0): ?>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-secondary"><?php echo __('Processing Fee'); ?> (<span x-text="depositFeePercent + '%'"></span>)</span>
                                    <span class="fw-bold" x-text="'<?php echo e($currency_symbol); ?>' + feeAmount"></span>
                                </div>
                            <?php endif; ?>
                            <div class="border-top border-secondary border-opacity-10 my-2"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark"><?php echo __('You Receive'); ?></span>
                                <span class="h5 fw-bold text-primary mb-0" x-text="'<?php echo e($currency_symbol); ?>' + totalAmount"></span>
                            </div>
                            <?php if ($deposit_fee_percentage > 0 && false): ?>
                                <div x-show="hasLocalCurrency" style="display: none;" class="mt-3 pt-3 border-top border-secondary border-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small text-secondary"><?php echo sprintf(__('After Fee (%s)'), $base_currency_code); ?></span>
                                        <span class="fw-bold" x-text="'<?php echo e($currency_symbol); ?>' + (usdEstimate * (1 - depositFeePercent / 100)).toFixed(2)"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Mobile: Show submit button here -->
                        <div class="d-lg-none">
                            <button type="button" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow" :disabled="loading || (!previewUrl && selectedMethodData.type !== 'auto')" @click="submitDeposit()">
                                <span x-show="!loading"><i class="fas fa-check-circle me-2"></i> <?php echo __('Confirm Deposit'); ?></span>
                                <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-2"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Payment Details -->
        <div class="col-lg-5" x-ref="paymentDetails">
            <div class="card border-0 text-white position-relative overflow-hidden" style="border-radius: 1.25rem;">
                <!-- Gradient Background -->
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); z-index: 0;"></div>
                <!-- Decorative circles -->
                <div class="position-absolute top-0 end-0 bg-white opacity-10 rounded-circle" style="width: 200px; height: 200px; transform: translate(30%, -30%); z-index: 0; opacity: 0.2;"></div>
                <div class="position-absolute bottom-0 start-0 bg-white opacity-10 rounded-circle" style="width: 150px; height: 150px; transform: translate(-30%, 30%); z-index: 0; opacity: 0.3;"></div>


                <div class="card-body p-4 position-relative d-flex flex-column" style="z-index: 1;">
                    <h5 class="fw-bold mb-4"><?php echo __('Complete Payment'); ?></h5>

                    <!-- Instructions -->
                    <div class="mb-4" x-show="selectedMethodData.instructions">
                        <div class="alert bg-white bg-opacity-10 border-0 text-white small">
                            <i class="fas fa-info-circle me-2"></i>
                            <span x-text="selectedMethodData.instructions"></span>
                        </div>
                    </div>

                    <!-- QR Code for Crypto -->
                    <div x-show="selectedMethodData.type === 'Cryptocurrency'" class="text-center mb-4" x-transition>
                        <div x-show="selectedMethodData.qr_code" class="qr-placeholder d-flex align-items-center justify-content-center mb-3 mx-auto">
                            <img :src="selectedMethodData.qr_code" alt="QR Code" class="img-fluid" style="max-width: 150px; border-radius: 0.5rem;">
                        </div>
                        <p class="small text-white-50 mb-0" x-show="selectedMethodData.qr_code"><?php echo __('Scan QR code or copy address below'); ?></p>
                        <p class="small text-warning mb-0" x-show="!selectedMethodData.qr_code"><i class="fas fa-exclamation-circle me-1"></i><?php echo __('QR code not available - contact admin'); ?></p>
                    </div>

                    <!-- Type-Specific Payment Details -->
                    <div class="mb-4">
                        <!-- Cryptocurrency Fields -->
                        <div x-show="selectedMethodData.type === 'Cryptocurrency'" x-transition>
                            <div class="mb-3" x-show="selectedMethodData.wallet_address">
                                <label class="small fw-bold text-white-50 mb-2 text-uppercase"><?php echo __('Wallet Address'); ?></label>
                                <div class="copy-input-group bg-white bg-opacity-10 border-white border-opacity-25">
                                    <input type="text" class="copy-input text-white" :value="selectedMethodData.wallet_address" readonly />
                                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" @click="copyAddress('wallet_address')" :class="{ 'btn-success text-white': copied, 'btn-light': !copied }">
                                        <span x-show="!copied"><i class="fas fa-copy me-1"></i> <?php echo __('Copy'); ?></span>
                                        <span x-show="copied" style="display: none"><i class="fas fa-check"></i></span>
                                    </button>
                                </div>
                            </div>

                            <div class="alert bg-warning bg-opacity-25 border-0 text-white small d-flex gap-2">
                                <i class="fas fa-exclamation-triangle mt-1"></i>
                                <div>
                                    <?php echo __('Please ensure you are sending via the correct network.'); ?>
                                    <span x-show="selectedMethodData.network"><strong>(<span x-text="selectedMethodData.network"></span>)</span></span>
                                    <?php echo __('Transactions sent to the wrong network may not be recoverable.'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer Fields -->
                        <div x-show="selectedMethodData.type === 'Bank Transfer'" x-transition>
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 mb-4">
                                <div class="mb-3" x-show="selectedMethodData.bank_name">
                                    <label class="small text-white-50 d-block"><?php echo __('Bank Name'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.bank_name"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('bank_name')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3" x-show="selectedMethodData.account_name">
                                    <label class="small text-white-50 d-block"><?php echo __('Account Name'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.account_name"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('account_name')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3" x-show="selectedMethodData.account_number">
                                    <label class="small text-white-50 d-block"><?php echo __('Account Number'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.account_number"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('account_number')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="selectedMethodData.swift_code">
                                    <label class="small text-white-50 d-block"><?php echo __('SWIFT / Routing'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold" x-text="selectedMethodData.swift_code"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('swift_code')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert bg-info bg-opacity-25 border-0 text-white small d-flex gap-2">
                                <i class="fas fa-info-circle mt-1"></i>
                                <div><?php echo __('Use your Username as the payment reference/memo.'); ?></div>
                            </div>
                        </div>

                        <!-- E-Wallet Fields -->
                        <div x-show="selectedMethodData.type === 'E-Wallet'" x-transition>
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 mb-4">
                                <div class="mb-3" x-show="selectedMethodData.provider">
                                    <label class="small text-white-50 d-block"><?php echo __('Provider'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.provider"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('provider')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3" x-show="selectedMethodData.wallet_id">
                                    <label class="small text-white-50 d-block"><?php echo __('Wallet ID / Email'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.wallet_id"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('wallet_id')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="selectedMethodData.account_name">
                                    <label class="small text-white-50 d-block"><?php echo __('Account Holder'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold" x-text="selectedMethodData.account_name"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('account_name')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Money Fields -->
                        <div x-show="selectedMethodData.type === 'Mobile Money'" x-transition>
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 mb-4">
                                <div class="mb-3" x-show="selectedMethodData.provider">
                                    <label class="small text-white-50 d-block"><?php echo __('Provider'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.provider"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('provider')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3" x-show="selectedMethodData.phone_number">
                                    <label class="small text-white-50 d-block"><?php echo __('Phone Number'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold fs-5" x-text="selectedMethodData.phone_number"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('phone_number')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="selectedMethodData.account_name">
                                    <label class="small text-white-50 d-block"><?php echo __('Account Holder'); ?></label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-bold" x-text="selectedMethodData.account_name"></span>
                                        <button type="button" class="btn btn-sm btn-light" @click="copyAddress('account_name')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Fields -->
                        <div x-show="selectedMethodData.type === 'Other' && selectedMethodData.details" x-transition>
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 mb-4">
                                <span x-text="selectedMethodData.details"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Proof -->
                    <div class="mt-auto pt-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-white mb-2"><?php echo __('Upload Proof of Payment'); ?></label>

                            <div class="upload-zone dark-mode">
                                <div x-show="!previewUrl" class="d-flex flex-column align-items-center justify-content-center w-100 h-100 p-3">
                                    <input type="file" id="proofInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 10" @change="handleFileChange($event)" accept="image/*" />
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 opacity-75"></i>
                                    <span class="small fw-bold"><?php echo __('Click to Upload Screenshot'); ?></span>
                                    <span class="text-white-50 small mt-1" style="font-size: 0.7rem"><?php echo __('Max size 5MB (JPG/PNG)'); ?></span>
                                </div>

                                <div x-show="previewUrl" class="w-100 h-100 position-relative" style="display: none">
                                    <img :src="previewUrl" class="w-100 h-100" style="object-fit: cover; opacity: 0.8; border-radius: 0.5rem;" />

                                    <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-75 d-flex align-items-center justify-content-between" style="border-radius: 0 0 0.5rem 0.5rem;">
                                        <span class="text-white small text-truncate" x-text="fileName" style="max-width: 70%"></span>
                                        <button type="button" class="btn btn-sm btn-danger py-0 px-2 small" @click.stop="removeFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop: Submit button -->
                        <div class="d-none d-lg-block">
                            <button type="button" @click="submitDeposit()" class="btn btn-light w-100 py-3 rounded-pill fw-bold text-primary shadow-lg" :disabled="loading || (!previewUrl && selectedMethodData.type !== 'auto')" :class="{ 'opacity-50': !previewUrl }">
                                <span x-show="!loading"><i class="fas fa-check-circle me-2"></i> <?php echo __('Confirm Deposit'); ?></span>
                                <span x-show="loading" style="display:none"><i class="fas fa-spinner fa-spin me-2"></i> <?php echo __('Processing…'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="depositForm" action="/actions/deposit-submit.php" method="POST" enctype="multipart/form-data" class="d-none" x-ref="depositFormHidden">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="payment_method" x-model="selectedMethod">
            <input type="hidden" name="amount" x-model="amount">
            <input type="hidden" name="local_currency_amount" x-ref="localAmountInput">
            <input type="file" name="proof" x-ref="proofFileHidden" class="d-none">
        </form>
    </div>

    <!-- Recent Deposits -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 1.25rem;">
                <div class="card-header bg-transparent border-bottom p-4">
                    <h5 class="fw-bold mb-0"><?php echo __('Recent Deposits'); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem;"><?php echo __('Date'); ?></th>
                                    <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem;"><?php echo __('Method'); ?></th>
                                    <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem;"><?php echo __('You Sent'); ?></th>
                                    <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem;"><?php echo __('Credited'); ?></th>
                                    <th class="py-3 px-4 text-uppercase small fw-bold text-muted" style="font-size: 0.75rem;"><?php echo __('Status'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_deposits): foreach ($recent_deposits as $d): ?>
                                        <tr>
                                            <td class="py-3 px-4"><?php echo format_date($d['created_at']); ?></td>
                                            <td class="py-3 px-4"><?php echo e($d['payment_method']); ?></td>
                                            <td class="py-3 px-4 fw-bold">
                                                <?php if (!empty($d['local_currency_amount'])): ?>
                                                    <?php echo get_currency_symbol($d['local_currency_code']); ?><?php echo number_format($d['local_currency_amount'], 2); ?>
                                                    <!-- <?php echo e($d['local_currency_code']); ?> -->
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 fw-bold">
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
                                            <td class="py-3 px-4">
                                                <?php if ($d['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2"><?php echo __('Pending'); ?></span>
                                                <?php elseif ($d['status'] === 'approved' || $d['status'] === 'completed'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><?php echo __('Completed'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><?php echo __(ucfirst($d['status'])); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="5" class="py-5 text-center text-muted"><?php echo __('No deposits yet.'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Method Card */
    .method-card {
        border: 2px solid #f1f5f9;
        border-radius: 1rem;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 1rem;
        background: white;
    }

    .method-card:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    .method-card.active {
        border-color: var(--primary);
        background: rgba(79, 70, 229, 0.02);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
    }

    .method-icon {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
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
        color: #334155;
        flex-grow: 1;
        padding: 0.5rem 1rem;
        outline: none;
        font-family: monospace;
        font-size: 0.95rem;
    }

    /* Upload Zone */
    .upload-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 1rem;
        padding: 0;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        background: #f8fafc;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .upload-zone:hover {
        border-color: var(--primary);
        background: rgba(79, 70, 229, 0.02);
    }

    .upload-zone.dark-mode {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.25);
        color: white;
    }

    .upload-zone.dark-mode:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: white;
    }

    /* QR Code Placeholder */
    .qr-placeholder {
        width: 160px;
        height: 160px;
        background: white;
        padding: 10px;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        margin: 0 auto;
    }

    /* Form Label */
    .form-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Payment Details Card - ensure bg opacity is visible */
    .bg-white.bg-opacity-10 {
        background-color: rgba(255, 255, 255, 0.15) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Copy buttons in payment details */
    .bg-white.bg-opacity-10 .btn-light {
        background-color: rgba(255, 255, 255, 0.9);
        border-color: rgba(255, 255, 255, 0.9);
        color: #333;
    }

    .bg-white.bg-opacity-10 .btn-light:hover {
        background-color: #fff;
        border-color: #fff;
        color: #000;
    }

    .bg-white.bg-opacity-10 .btn-light:active {
        background-color: #e9ecef;
        border-color: #e9ecef;
    }
</style>

<script>
    window.depositData = {
        translations: {
            willBeCredited: <?php echo json_encode(sprintf(__('will be credited to your %s wallet'), $base_currency_code)); ?>,
            rateLabel: <?php echo json_encode(sprintf(__('Rate: 1 %s = '), $base_currency_code)); ?>,
            updatedLabel: <?php echo json_encode(__(' · Updated ')); ?>,
            youSend: <?php echo json_encode(__('You Send')); ?>,
            estimatedLocalReceipt: <?php echo json_encode(__('estimated local receipt')); ?>
        },
        baseCurrencyCode: <?php echo json_encode($base_currency_code); ?>
    };

    function depositApp() {
        return {
            amount: '',
            loading: false,
            selectedMethod: '<?php echo !empty($payment_methods) ? key($payment_methods) : ''; ?>',
            copied: false,
            fileName: null,
            previewUrl: null,
            depositFeePercent: <?php echo $deposit_fee_percentage; ?>,
            _currencySymbol: '<?php echo e($currency_symbol); ?>',
            hasLocalCurrency: <?php echo json_encode($has_local_currency); ?>,
            localCurrencyCode: '<?php echo e($local_currency_code ?? ''); ?>',
            localCurrencySymbol: '<?php echo e($local_currency_symbol ?? ''); ?>',
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
                    alert(<?php echo json_encode(__('Please enter a valid amount')); ?>);
                    return;
                }

                if (!this.previewUrl && this.selectedMethodData.type !== 'auto') {
                    alert(<?php echo json_encode(__('Please upload proof of payment')); ?>);
                    return;
                }

                // Manually set form values before submission (x-model doesn't sync reliably on programmatic submit)
                const form = this.$refs.depositFormHidden;
                form.querySelector('input[name="payment_method"]').value = this.selectedMethod;
                form.querySelector('input[name="amount"]').value = this.numericAmount;
                form.querySelector('input[name="local_currency_amount"]').value = this.hasLocalCurrency ? this.localAmount : '';

                // Copy file from visible input to hidden form
                const fileInput = document.getElementById('proofInput');
                const hiddenFileInput = this.$refs.proofFileHidden;
                if (fileInput && fileInput.files.length > 0) {
                    const dt = new DataTransfer();
                    dt.items.add(fileInput.files[0]);
                    hiddenFileInput.files = dt.files;
                }

                this.loading = true;
                this.$nextTick(() => {
                    form.submit();
                });
            },
        };
    }
</script>

<?php require ROOT . '/includes/footer.php'; ?>
</body>

</html>