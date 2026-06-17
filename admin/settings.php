<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Admin Settings Page
 * Comprehensive settings management with 8 tabbed sections
 */

require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/upload-functions.php';
require_once ROOT . '/includes/translation-functions.php';

$page_title = __('Platform Settings');

// Load all settings
$all_settings = get_all_settings();

// Extract settings with defaults
$site_name = isset($all_settings['site_name']) ? $all_settings['site_name'] : 'Investment Platform';
$site_logo = isset($all_settings['site_logo']) ? $all_settings['site_logo'] : '';
$contact_email = isset($all_settings['contact_email']) ? $all_settings['contact_email'] : 'admin@example.com';
$secondary_language = isset($all_settings['secondary_language']) ? $all_settings['secondary_language'] : 'fr_FR';
$translation_mode = isset($all_settings['translation_mode']) ? $all_settings['translation_mode'] : 'local';

// Contact phone and address
$contact_phone = isset($all_settings['contact_phone']) ? $all_settings['contact_phone'] : '+1 (555) 123-4567';
$contact_address = isset($all_settings['contact_address']) ? $all_settings['contact_address'] : "123 Business St, Suite 100\nFinancial District, NY 10001";

// Social links (stored as JSON in settings)
$social_links = isset($all_settings['social_links']) ? json_decode($all_settings['social_links'], true) : [];
if (!is_array($social_links)) {
    $social_links = [];
}
$facebook_url = isset($social_links['facebook']) ? $social_links['facebook'] : '';
$twitter_url = isset($social_links['twitter']) ? $social_links['twitter'] : '';
$instagram_url = isset($social_links['instagram']) ? $social_links['instagram'] : '';
$linkedin_url = isset($social_links['linkedin']) ? $social_links['linkedin'] : '';
$youtube_url = isset($social_links['youtube']) ? $social_links['youtube'] : '';
$telegram_url = isset($social_links['telegram']) ? $social_links['telegram'] : '';

// Instant messaging/snippet code (e.g., tawk.to script)
$instant_message_code = isset($all_settings['instant_message_code']) ? $all_settings['instant_message_code'] : '';

// SEO Settings
$site_description = isset($all_settings['site_description']) ? $all_settings['site_description'] : '';
$site_keywords = isset($all_settings['site_keywords']) ? $all_settings['site_keywords'] : '';
$default_mode = isset($all_settings['default_mode']) ? $all_settings['default_mode'] : 'system';

// Currency Setting
$currency = isset($all_settings['currency']) ? $all_settings['currency'] : 'USD';

// KYC Settings
$kyc_required = isset($all_settings['kyc_required']) ? $all_settings['kyc_required'] : 'no';
$kyc_timing = isset($all_settings['kyc_timing']) ? $all_settings['kyc_timing'] : 'before_withdrawal';
$kyc_always_show_message = isset($all_settings['kyc_always_show_message']) ? $all_settings['kyc_always_show_message'] : 'no';
$kyc_banner_dismissible = isset($all_settings['kyc_banner_dismissible']) ? $all_settings['kyc_banner_dismissible'] : 'yes';

// Withdrawal Settings
$withdrawal_fee_percentage = isset($all_settings['withdrawal_fee_percentage']) ? $all_settings['withdrawal_fee_percentage'] : '2';
$minimum_withdrawal = isset($all_settings['minimum_withdrawal']) ? $all_settings['minimum_withdrawal'] : '1';

// Deposit Settings
$deposit_fee_percentage = isset($all_settings['deposit_fee_percentage']) ? $all_settings['deposit_fee_percentage'] : '0';

// Referral Settings
$referral_bonus_type = isset($all_settings['referral_bonus_type']) ? $all_settings['referral_bonus_type'] : 'flat';
$referral_bonus_amount = isset($all_settings['referral_bonus_amount']) ? $all_settings['referral_bonus_amount'] : '10';
$referral_bonus_trigger = isset($all_settings['referral_bonus_trigger']) ? $all_settings['referral_bonus_trigger'] : 'first_deposit';
$referral_fund_withdraw_mode = isset($all_settings['referral_fund_withdraw_mode']) ? $all_settings['referral_fund_withdraw_mode'] : 'exact';
$referral_exact_amount = isset($all_settings['referral_exact_amount']) ? $all_settings['referral_exact_amount'] : '0';
$referral_min_amount = isset($all_settings['referral_min_amount']) ? $all_settings['referral_min_amount'] : '0';
$referral_max_amount = isset($all_settings['referral_max_amount']) ? $all_settings['referral_max_amount'] : '0';

// Cancellation Settings
$cancellation_penalty_mode = isset($all_settings['cancellation_penalty_mode']) ? $all_settings['cancellation_penalty_mode'] : 'percentage';
$cancellation_penalty_percentage = isset($all_settings['cancellation_penalty_percentage']) ? $all_settings['cancellation_penalty_percentage'] : '10';
$cancellation_penalty_flat = isset($all_settings['cancellation_penalty_flat']) ? $all_settings['cancellation_penalty_flat'] : '5.00';
$cancellation_forfeit_profits = isset($all_settings['cancellation_forfeit_profits']) ? $all_settings['cancellation_forfeit_profits'] : 'no';
$cancellation_block_after_waiting = isset($all_settings['cancellation_block_after_waiting']) ? $all_settings['cancellation_block_after_waiting'] : 'no';

// Post-Registration Settings
$post_registration_action = isset($all_settings['post_registration_action']) ? $all_settings['post_registration_action'] : 'dashboard';
$require_email_verification = isset($all_settings['require_email_verification']) ? $all_settings['require_email_verification'] : 'no';
$require_referral_code = isset($all_settings['require_referral_code']) ? $all_settings['require_referral_code'] : 'no';

// Email Notification Settings - User Account
$email_user_registration = isset($all_settings['email_user_registration']) ? $all_settings['email_user_registration'] : 'yes';
$email_password_reset = isset($all_settings['email_password_reset']) ? $all_settings['email_password_reset'] : 'yes';

// Email Notification Settings - Deposit
$email_deposit_submitted_user = isset($all_settings['email_deposit_submitted_user']) ? $all_settings['email_deposit_submitted_user'] : 'yes';
$email_deposit_approved_user = isset($all_settings['email_deposit_approved_user']) ? $all_settings['email_deposit_approved_user'] : 'yes';
$email_deposit_rejected_user = isset($all_settings['email_deposit_rejected_user']) ? $all_settings['email_deposit_rejected_user'] : 'yes';
$email_deposit_submitted_admin = isset($all_settings['email_deposit_submitted_admin']) ? $all_settings['email_deposit_submitted_admin'] : 'yes';

// Email Notification Settings - Withdrawal
$email_withdrawal_submitted_user = isset($all_settings['email_withdrawal_submitted_user']) ? $all_settings['email_withdrawal_submitted_user'] : 'yes';
$email_withdrawal_approved_user = isset($all_settings['email_withdrawal_approved_user']) ? $all_settings['email_withdrawal_approved_user'] : 'yes';
$email_withdrawal_rejected_user = isset($all_settings['email_withdrawal_rejected_user']) ? $all_settings['email_withdrawal_rejected_user'] : 'yes';
$email_withdrawal_submitted_admin = isset($all_settings['email_withdrawal_submitted_admin']) ? $all_settings['email_withdrawal_submitted_admin'] : 'yes';

// Email Notification Settings - Investment
$email_investment_created_user = isset($all_settings['email_investment_created_user']) ? $all_settings['email_investment_created_user'] : 'yes';
$email_investment_completed_user = isset($all_settings['email_investment_completed_user']) ? $all_settings['email_investment_completed_user'] : 'yes';
$email_investment_cancelled_user = isset($all_settings['email_investment_cancelled_user']) ? $all_settings['email_investment_cancelled_user'] : 'yes';
$email_profit_payout_user = isset($all_settings['email_profit_payout_user']) ? $all_settings['email_profit_payout_user'] : 'yes';

// Email Notification Settings - KYC
$email_kyc_approved_user = isset($all_settings['email_kyc_approved_user']) ? $all_settings['email_kyc_approved_user'] : 'yes';
$email_kyc_rejected_user = isset($all_settings['email_kyc_rejected_user']) ? $all_settings['email_kyc_rejected_user'] : 'yes';
$email_kyc_submitted_admin = isset($all_settings['email_kyc_submitted_admin']) ? $all_settings['email_kyc_submitted_admin'] : 'yes';

// Email Notification Settings - Referral
$email_referral_bonus_user = isset($all_settings['email_referral_bonus_user']) ? $all_settings['email_referral_bonus_user'] : 'yes';

// Payment Methods
$payment_methods = isset($all_settings['payment_methods']) ? $all_settings['payment_methods'] : '[]';
if (!is_array($payment_methods)) {
    $payment_methods = json_decode($payment_methods, true) ?: [];
}

// Withdrawal Methods
$withdrawal_methods = isset($all_settings['withdrawal_methods']) ? $all_settings['withdrawal_methods'] : '[]';
if (!is_array($withdrawal_methods)) {
    $withdrawal_methods = json_decode($withdrawal_methods, true) ?: [];
}

// Cron Status
$lock_file = __DIR__ . '/../cron/process-investments.lock';
$cron_lock_exists = file_exists($lock_file);
$cron_log_file = __DIR__ . '/../logs/cron.log';
$last_cron_run = 'N/A';
if (file_exists($cron_log_file)) {
    $lines = array_slice(file($cron_log_file), -1);
    if (!empty($lines)) {
        $last_cron_run = trim($lines[0]);
    }
}

require_once ROOT . '/includes/admin-header.php';
?>

<div class="container-fluid settings-wrapper" x-data="{
    currentTab: 'site',
    tabsExpanded: false,
    isDesktop: window.innerWidth >= 992,
    kyc_required: '<?php echo e($kyc_required); ?>',
    init() {
        const hash = window.location.hash.substring(1);
        const savedTab = localStorage.getItem('admin_settings_tab');
        
        if (hash) {
            this.currentTab = hash;
            localStorage.setItem('admin_settings_tab', hash);
        } else if (savedTab) {
            this.currentTab = savedTab;
            window.history.replaceState(null, '', '#' + savedTab);
        } else {
            this.currentTab = 'site';
            window.history.replaceState(null, '', '#site');
            localStorage.setItem('admin_settings_tab', 'site');
        }

        // Track window resize for responsive behavior
        window.addEventListener('resize', () => {
            this.isDesktop = window.innerWidth >= 992;
        });

        this.$watch('currentTab', (value) => {
            if (window.location.hash.substring(1) !== value) {
                window.location.hash = value;
                localStorage.setItem('admin_settings_tab', value);
            }
            // Collapse tabs after selection on mobile
            if (!this.isDesktop) {
                this.tabsExpanded = false;
            }
        });

        window.addEventListener('hashchange', () => {
            const newHash = window.location.hash.substring(1);
            if (newHash && newHash !== this.currentTab) {
                this.currentTab = newHash;
                localStorage.setItem('admin_settings_tab', newHash);
            }
        });
    }
}"
    <!-- Page Header -->
    <div class="mb-5 border-bottom border-subtle pb-3">
        <h2 class="h4 fw-semibold mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-gears" style="color: #a1a1aa; opacity: 0.8;"></i>
            <span style="color: var(--text-main);"><?php echo __('Platform Settings'); ?></span>
        </h2>
        <p class="small mb-0" style="color: #a1a1aa;">
            <?php echo __('Manage global configurations, payment methods, and system settings.'); ?>
        </p>
    </div>

    <div class="settings-grid">
        <!-- Navigation Sidebar -->
        <nav class="settings-nav-container settings-sticky" :class="{ 'expanded': tabsExpanded }">
            <!-- Mobile Toggle Button -->
            <button type="button" class="settings-tabs-toggle d-lg-none" @click="tabsExpanded = !tabsExpanded" :class="{ 'active': tabsExpanded }">
                <i class="fas fa-bars me-2"></i>
                <span x-text="tabsExpanded ? '<?php echo __('Hide Menu'); ?>' : '<?php echo __('Show Menu'); ?>'"></span>
                <i class="fas ms-2" :class="tabsExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            </button>

            <!-- Tabs List -->
            <div class="settings-tabs-list" x-show="tabsExpanded || isDesktop" x-transition.opacity>
                <!-- General Section -->
                <div class="settings-section-title d-none d-lg-block"><?php echo __('General'); ?></div>
                <a href="#site" @click.prevent="currentTab = 'site'" class="settings-nav-link" :class="{ 'active': currentTab === 'site' }">
                    <span><i class="fas fa-cog me-2 d-none d-lg-inline"></i><?php echo __('Site Settings'); ?></span>
                </a>
                <a href="#seo" @click.prevent="currentTab = 'seo'" class="settings-nav-link" :class="{ 'active': currentTab === 'seo' }">
                    <span><i class="fas fa-search me-2 d-none d-lg-inline"></i><?php echo __('SEO'); ?></span>
                </a>

                <!-- Financial Section -->
                <div class="settings-section-title d-none d-lg-block mt-4"><?php echo __('Financial'); ?></div>
                <a href="#payment" @click.prevent="currentTab = 'payment'" class="settings-nav-link" :class="{ 'active': currentTab === 'payment' }">
                    <span><i class="fas fa-credit-card me-2 d-none d-lg-inline"></i><?php echo __('Payment'); ?></span>
                </a>
                <a href="#referral" @click.prevent="currentTab = 'referral'" class="settings-nav-link" :class="{ 'active': currentTab === 'referral' }">
                    <span><i class="fas fa-users me-2 d-none d-lg-inline"></i><?php echo __('Referral'); ?></span>
                </a>
                <a href="#withdrawal" @click.prevent="currentTab = 'withdrawal'" class="settings-nav-link" :class="{ 'active': currentTab === 'withdrawal' }">
                    <span><i class="fas fa-money-bill-transfer me-2 d-none d-lg-inline"></i><?php echo __('Withdrawal'); ?></span>
                </a>

                <!-- Compliance Section -->
                <div class="settings-section-title d-none d-lg-block mt-4"><?php echo __('Compliance'); ?></div>
                <a href="#kyc" @click.prevent="currentTab = 'kyc'" class="settings-nav-link" :class="{ 'active': currentTab === 'kyc' }">
                    <span><i class="fas fa-id-card me-2 d-none d-lg-inline"></i><?php echo __('KYC'); ?></span>
                </a>
                <a href="#cancellation" @click.prevent="currentTab = 'cancellation'" class="settings-nav-link" :class="{ 'active': currentTab === 'cancellation' }">
                    <span><i class="fas fa-ban me-2 d-none d-lg-inline"></i><?php echo __('Cancellation'); ?></span>
                </a>

                <!-- System Section -->
                <div class="settings-section-title d-none d-lg-block mt-4"><?php echo __('System'); ?></div>
                <a href="#registration" @click.prevent="currentTab = 'registration'" class="settings-nav-link" :class="{ 'active': currentTab === 'registration' }">
                    <span><i class="fas fa-user-plus me-2 d-none d-lg-inline"></i><?php echo __('Registration'); ?></span>
                </a>
                <a href="#countries" @click.prevent="currentTab = 'countries'" class="settings-nav-link" :class="{ 'active': currentTab === 'countries' }">
                    <span><i class="fas fa-globe me-2 d-none d-lg-inline"></i><?php echo __('Countries'); ?></span>
                </a>
                <a href="#notifications" @click.prevent="currentTab = 'notifications'" class="settings-nav-link" :class="{ 'active': currentTab === 'notifications' }">
                    <span><i class="fas fa-bell me-2 d-none d-lg-inline"></i><?php echo __('Notifications'); ?></span>
                </a>
                <a href="#cron" @click.prevent="currentTab = 'cron'" class="settings-nav-link" :class="{ 'active': currentTab === 'cron' }">
                    <span><i class="fas fa-clock me-2 d-none d-lg-inline"></i><?php echo __('Cron'); ?></span>
                </a>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="settings-content">

            <!-- TAB 1: Site Settings -->
            <div x-show="currentTab === 'site'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Site Configuration'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="site">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label for="site_name" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-heading text-primary me-1"></i><?php echo __('Site Name'); ?>
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="site_name" name="site_name" value="<?php echo e($site_name); ?>" required>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Public name of your investment platform'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="contact_email" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-envelope text-primary me-1"></i><?php echo __('Contact Email'); ?>
                                    </label>
                                    <input type="email" class="form-control form-control-custom" id="contact_email" name="contact_email" value="<?php echo e($contact_email); ?>" required>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Admin contact email for user inquiries'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="contact_phone" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-phone text-primary me-1"></i><?php echo __('Contact Phone'); ?>
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="contact_phone" name="contact_phone" value="<?php echo e($contact_phone); ?>">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Public phone number displayed in footer and contact page'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="contact_address" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i><?php echo __('Contact Address'); ?>
                                    </label>
                                    <textarea class="form-control form-control-custom" id="contact_address" name="contact_address" rows="2"><?php echo e($contact_address); ?></textarea>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Office address displayed in footer and contact page'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="secondary_language" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-language text-primary me-1"></i><?php echo __('Secondary Language'); ?>
                                    </label>
                                    <select class="form-select form-select-custom" id="secondary_language" name="secondary_language">
                                        <option value="en_US" <?php echo $secondary_language === 'en_US' ? 'selected' : ''; ?>>English</option>
                                        <option value="fr_FR" <?php echo $secondary_language === 'fr_FR' ? 'selected' : ''; ?>>French</option>
                                        <option value="es_ES" <?php echo $secondary_language === 'es_ES' ? 'selected' : ''; ?>>Spanish</option>
                                        <option value="de_DE" <?php echo $secondary_language === 'de_DE' ? 'selected' : ''; ?>>German</option>
                                    </select>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Primary language is always English'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="translation_mode" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-globe text-primary me-1"></i><?php echo __('Translation Mode'); ?>
                                    </label>
                                    <select class="form-select form-select-custom" id="translation_mode" name="translation_mode">
                                        <option value="local" <?php echo $translation_mode === 'local' ? 'selected' : ''; ?>><?php echo __('Local Translations (PO/MO files)'); ?></option>
                                        <option value="google" <?php echo $translation_mode === 'google' ? 'selected' : ''; ?>><?php echo __('Google Translate Widget'); ?></option>
                                    </select>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Choose between local translation files or Google Translate widget for automatic translations'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="currency" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-dollar-sign text-primary me-1"></i><?php echo __('Currency Code'); ?>
                                    </label>
                                    <select class="form-control form-control-custom" id="currency" name="currency" required>
                                        <?php
                                        $currencies = get_currencies();
                                        foreach ($currencies as $code => $data) {
                                            $selected = ($currency === $code) ? 'selected' : '';
                                            $symbol = isset($data['symbol']) ? ' (' . e($data['symbol']) . ')' : '';
                                            echo '<option value="' . e($code) . '" ' . $selected . '>' . e($code) . $symbol . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Select the platform base currency. Changing this will trigger an immediate exchange rate refresh.'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium d-block mb-3">
                                        <i class="fas fa-image text-primary me-1"></i><?php echo __('Site Logo'); ?>
                                    </label>
                                    <?php if ($site_logo && file_exists('../' . $site_logo)): ?>
                                        <div class="mb-3">
                                            <img src="/<?php echo e($site_logo); ?>" alt="Site Logo" style="max-width: 200px; max-height: 100px; border-radius: 4px; border: 1px solid #ddd; padding: 4px;">
                                            <p class="small mt-2" style="color: #a1a1aa;">Current logo</p>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control form-control-custom" id="site_logo" name="site_logo" accept="image/*">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Upload a new logo (PNG, JPG, GIF - max 2MB)'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-share-alt text-primary me-1"></i><?php echo __('Social Links'); ?>
                                    </label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <input type="url" class="form-control form-control-custom" name="facebook_url" placeholder="Facebook URL" value="<?php echo e($facebook_url); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="url" class="form-control form-control-custom" name="twitter_url" placeholder="Twitter URL" value="<?php echo e($twitter_url); ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <input type="url" class="form-control form-control-custom" name="instagram_url" placeholder="Instagram URL" value="<?php echo e($instagram_url); ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <input type="url" class="form-control form-control-custom" name="linkedin_url" placeholder="LinkedIn URL" value="<?php echo e($linkedin_url); ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <input type="url" class="form-control form-control-custom" name="youtube_url" placeholder="YouTube URL" value="<?php echo e($youtube_url); ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <input type="url" class="form-control form-control-custom" name="telegram_url" placeholder="Telegram URL" value="<?php echo e($telegram_url); ?>">
                                        </div>
                                    </div>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Only links you set will appear in the public footer'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-comments text-primary me-1"></i><?php echo __('Instant Message / Chat Snippet'); ?>
                                    </label>
                                    <textarea class="form-control form-control-custom" name="instant_message_code" rows="4" placeholder="Paste your chat widget script (e.g., tawk.to)"><?php echo e($instant_message_code); ?></textarea>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('This HTML snippet will be injected into all footers. Leave empty to disable.'); ?></small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i><?php echo __('Save Site Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB X: SEO Settings -->
            <div x-show="currentTab === 'seo'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('SEO Settings'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="seo">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label for="site_description" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-paragraph text-primary me-1"></i><?php echo __('Site Description'); ?>
                                    </label>
                                    <textarea class="form-control form-control-custom" id="site_description" name="site_description" rows="3"><?php echo e($site_description); ?></textarea>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Description used in meta description tags across the public site'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="site_keywords" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-tags text-primary me-1"></i><?php echo __('Site Keywords'); ?>
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="site_keywords" name="site_keywords" value="<?php echo e($site_keywords); ?>">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Comma-separated keywords used in meta keywords'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="default_mode" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-adjust text-primary me-1"></i><?php echo __('Default Theme Mode'); ?>
                                    </label>
                                    <select class="form-select form-select-custom" id="default_mode" name="default_mode">
                                        <option value="system" <?php echo $default_mode === 'system' ? 'selected' : ''; ?>><?php echo __('System'); ?></option>
                                        <option value="light" <?php echo $default_mode === 'light' ? 'selected' : ''; ?>><?php echo __('Light'); ?></option>
                                        <option value="dark" <?php echo $default_mode === 'dark' ? 'selected' : ''; ?>><?php echo __('Dark'); ?></option>
                                    </select>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Choose the default theme mode for public pages'); ?></small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i><?php echo __('Save SEO Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Payment Methods -->
            <div x-show="currentTab === 'payment'" x-transition.opacity x-data="paymentMethodsApp()">
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Payment Method Management'); ?></h6>

                            <!-- Display existing payment methods -->
                            <template x-if="Object.keys(methods).length > 0">
                                <div class="mb-4">
                                    <h6 class="text-white mb-3"><?php echo __('Active Payment Methods'); ?></h6>
                                    <div class="row g-3">
                                        <template x-for="(method, key) in methods" :key="key">
                                            <div class="col-md-6">
                                                <div class="card glass-card h-100 border-subtle">
                                                    <div class="card-body glass-card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="fas fa-lg"
                                                                    :class="{
                                                            'fa-coins text-warning': method.type === 'Cryptocurrency',
                                                            'fa-university text-primary': method.type === 'Bank Transfer',
                                                            'fa-wallet text-info': method.type === 'E-Wallet',
                                                            'fa-mobile-alt text-success': method.type === 'Mobile Money',
                                                            'fa-credit-card text-secondary': method.type === 'Other'
                                                        }"></i>
                                                                <div>
                                                                    <h6 class="mb-0 fw-bold text-white" x-text="method.name"></h6>
                                                                    <span class="badge bg-secondary border" x-text="method.type" style="color: var(--text-main);"></span>
                                                                </div>
                                                            </div>
                                                            <div class="dropdown method-dropdown" x-data="{ open: false }" @click.outside="open = false">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button" @click="open = !open">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <div class="dropdown-menu-custom" x-show="open" x-transition.opacity style="display: none;">
                                                                    <button class="dropdown-item-custom" @click="editMethod(key); open = false">
                                                                        <i class="fas fa-edit me-2 text-muted-custom"></i><?php echo __('Edit'); ?>
                                                                    </button>
                                                                    <button class="dropdown-item-custom text-danger" @click="deleteMethod(key); open = false">
                                                                        <i class="fas fa-trash me-2 text-danger"></i><?php echo __('Delete'); ?>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Type-specific details preview (only show relevant type) -->
                                                        <div class="small mt-2" style="color: #a1a1aa;">
                                                            <template x-if="method.type === 'Cryptocurrency'">
                                                                <div class="d-flex flex-column gap-1">
                                                                    <span><strong><?php echo __('Network'); ?>:</strong> <span x-text="method.network || '-'"></span></span>
                                                                    <span class="font-monospace text-truncate"><strong><?php echo __('Address'); ?>:</strong> <span x-text="method.wallet_address || '-'"></span></span>
                                                                    <span x-show="method.qr_code" class="text-success"><i class="fas fa-check-circle"></i> <?php echo __('QR code generated'); ?></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="method.type === 'Bank Transfer'">
                                                                <div class="d-flex flex-column gap-1">
                                                                    <span><strong><?php echo __('Bank'); ?>:</strong> <span x-text="method.bank_name || '-'"></span></span>
                                                                    <span><strong><?php echo __('Account'); ?>:</strong> <span x-text="method.account_name || '-'"></span></span>
                                                                    <span class="font-monospace"><strong><?php echo __('Number'); ?>:</strong> <span x-text="method.account_number || '-'"></span></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="method.type === 'E-Wallet'">
                                                                <div class="d-flex flex-column gap-1">
                                                                    <span><strong><?php echo __('Provider'); ?>:</strong> <span x-text="method.provider || '-'"></span></span>
                                                                    <span class="font-monospace"><strong><?php echo __('ID'); ?>:</strong> <span x-text="method.wallet_id || '-'"></span></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="method.type === 'Mobile Money'">
                                                                <div class="d-flex flex-column gap-1">
                                                                    <span><strong><?php echo __('Provider'); ?>:</strong> <span x-text="method.provider || '-'"></span></span>
                                                                    <span><strong><?php echo __('Phone'); ?>:</strong> <span x-text="method.phone_number || '-'"></span></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="method.type === 'Other'">
                                                                <div>
                                                                    <span class="text-truncate d-block" style="max-height: 3em; overflow: hidden;" x-text="method.details || '-'"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Add a message when no methods exist -->
                            <div x-show="Object.keys(methods).length === 0" class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo __('No payment methods configured. Add your first payment method below.'); ?>
                            </div>

                            <!-- Add/Edit form -->
                            <div class="card glass-card mb-4 border-subtle">
                                <div class="card-body glass-card-body">
                                    <h6 class="text-white mb-3">
                                        <i class="fas fa-plus-circle me-2" x-show="editingKey === null"></i>
                                        <i class="fas fa-edit me-2" x-show="editingKey !== null"></i>
                                        <span x-show="editingKey === null"><?php echo __('Add New Payment Method'); ?></span>
                                        <span x-show="editingKey !== null"><?php echo __('Edit Payment Method'); ?></span>
                                    </h6>

                                    <div class="alert alert-info mb-3" style="font-size: 0.85rem; background-color: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <i class="fas fa-circle-info me-2"></i>
                                        <span>
                                            <?php echo __('After entering payment details, wait for the QR code to generate before clicking "Add Method" or "Update Method".'); ?>
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-muted-custom small fw-medium"><?php echo __('Method Name'); ?></label>
                                        <input type="text" class="form-control form-control-custom" x-model="formData.name" placeholder="<?php echo __('e.g., USDT (TRC20), Bank Transfer'); ?>" @input="generateKey()">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-muted-custom small fw-medium"><?php echo __('Type'); ?></label>
                                        <select class="form-select form-select-custom" x-model="formData.type" @change="setDefaultInstructions()">
                                            <option value=""><?php echo __('Select Type'); ?></option>
                                            <option value="Cryptocurrency"><?php echo __('Cryptocurrency'); ?></option>
                                            <option value="Bank Transfer"><?php echo __('Bank Transfer'); ?></option>
                                            <option value="E-Wallet"><?php echo __('E-Wallet'); ?></option>
                                            <option value="Mobile Money"><?php echo __('Mobile Money'); ?></option>
                                            <option value="Other"><?php echo __('Other'); ?></option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-muted-custom small fw-medium"><?php echo __('Instructions for Users'); ?></label>
                                        <textarea class="form-control form-control-custom" x-model="formData.instructions" rows="2" placeholder="<?php echo __('Instructions will be auto-filled based on type...'); ?>"></textarea>
                                        <small style="color: #a1a1aa;"><?php echo __('Auto-filled based on type. You can customize this message.'); ?></small>
                                    </div>

                                    <!-- TYPE-SPECIFIC FIELDS -->

                                    <!-- <?php echo __('Cryptocurrency Fields'); ?> -->
                                    <div x-show="formData.type === 'Cryptocurrency'">
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Network'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.crypto.network" placeholder="<?php echo __('e.g., TRC20, ERC20, BEP20'); ?>">
                                            <small style="color: #a1a1aa;"><?php echo __('The blockchain network for this cryptocurrency'); ?></small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Wallet Address'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom font-monospace" x-model="formData.crypto.wallet_address" placeholder="<?php echo __('Enter wallet address'); ?>" @input="generateQROnInput()">
                                        </div>
                                        <div class="mb-3" x-show="formData.qr_code">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('QR Code (Auto-generated)'); ?></label>
                                            <div class="text-center p-3 bg-card border-subtle rounded">
                                                <img :src="formData.qr_code" style="max-width: 150px;" class="img-thumbnail">
                                                <div class="small mt-1" style="color: #a1a1aa;"><?php echo __('Scan to copy address'); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- <?php echo __('Bank Transfer Fields'); ?> -->
                                    <div x-show="formData.type === 'Bank Transfer'">
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Bank Name'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.bank.bank_name" placeholder="<?php echo __('e.g., Chase Bank'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Account Name'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.bank.account_name" placeholder="<?php echo __('e.g., John Doe'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Account Number'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.bank.account_number" placeholder="<?php echo __('Enter account number'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('SWIFT/BIC Code'); ?></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.bank.swift_code" placeholder="<?php echo __('e.g., CHASUS33'); ?>">
                                            <small style="color: #a1a1aa;"><?php echo __('Required for international transfers'); ?></small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Branch Code / Routing Number'); ?></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.bank.branch_code" placeholder="<?php echo __('e.g., 021000021'); ?>">
                                        </div>
                                    </div>

                                    <!-- <?php echo __('E-Wallet Fields'); ?> -->
                                    <div x-show="formData.type === 'E-Wallet'">
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Provider'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.ewallet.provider" placeholder="<?php echo __('e.g., PayPal, Skrill, Neteller'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Wallet ID / Email'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.ewallet.wallet_id" placeholder="<?php echo __('e.g., user@example.com'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Account Holder Name'); ?></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.ewallet.account_name" placeholder="<?php echo __('e.g., John Doe'); ?>">
                                        </div>
                                    </div>

                                    <!-- <?php echo __('Mobile Money Fields'); ?> -->
                                    <div x-show="formData.type === 'Mobile Money'">
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Provider'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.mobile.provider" placeholder="<?php echo __('e.g., M-Pesa, MTN Mobile Money'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Phone Number'); ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.mobile.phone_number" placeholder="<?php echo __('e.g., +254712345678'); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Account Holder Name'); ?></label>
                                            <input type="text" class="form-control form-control-custom" x-model="formData.mobile.account_name" placeholder="<?php echo __('e.g., John Doe'); ?>">
                                        </div>
                                    </div>

                                    <!-- <?php echo __('Other Fields'); ?> -->
                                    <div x-show="formData.type === 'Other'">
                                        <div class="mb-3">
                                            <label class="form-label text-muted-custom small fw-medium"><?php echo __('Payment Details'); ?> <span class="text-danger">*</span></label>
                                            <textarea class="form-control form-control-custom" x-model="formData.other.details" rows="4" placeholder="<?php echo __('Enter payment details...'); ?>"></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-accent" @click="saveMethod()">
                                            <i class="fas fa-check"></i>
                                            <span x-show="editingKey === null"><?php echo __('Add Method'); ?></span>
                                            <span x-show="editingKey !== null"><?php echo __('Update Method'); ?></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" x-show="editingKey !== null" @click="cancelEdit()">
                                            <i class="fas fa-times"></i> <?php echo __('Cancel'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Deposit Fee Setting -->
                            <h6 class="text-white mb-3"><?php echo __('Deposit Fee'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST" class="mb-4">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="deposit">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="deposit_fee_percentage" class="form-label text-muted-custom small fw-medium"><?php echo __('Deposit Fee (%)'); ?></label>
                                        <input type="number" class="form-control form-control-custom" id="deposit_fee_percentage" name="deposit_fee_percentage" value="<?php echo e($deposit_fee_percentage); ?>" min="0" max="100" step="0.01" required>
                                        <small class="form-text" style="color: #a1a1aa;"><?php echo __('Percentage charged on deposits (0 = no fee)'); ?></small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save me-1"></i><?php echo __('Save Deposit Fee'); ?>
                                    </button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <!-- Save payment methods form -->
                            <div class="border-top border-subtle pt-4 mt-4">
                                <form action="/admin/actions/settings-update" method="POST" @submit.prevent="$refs.payment_methods_input.value = methodsJSON; $el.submit()">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="section" value="payment">
                                    <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">
                                    <input type="hidden" name="payment_methods" x-ref="payment_methods_input" value="">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-accent px-4">
                                            <i class="fas fa-save me-1"></i><?php echo __('Save Payment Methods'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Referral Settings -->
            <div x-show="currentTab === 'referral'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Referral Configuration'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="referral">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-money-bill text-info"></i> Referral Bonus Type
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="referral_bonus_type" id="bonus_flat" value="flat" <?php echo $referral_bonus_type === 'flat' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="bonus_flat">
                                            <?php echo __('Fixed Amount'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="referral_bonus_type" id="bonus_percentage" value="percentage" <?php echo $referral_bonus_type === 'percentage' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="bonus_percentage">
                                            <?php echo __('Percentage'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="referral_bonus_amount" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-calculator text-info"></i>
                                        <span id="bonus_label"><?php echo __('Bonus Amount'); ?></span>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="referral_bonus_amount" name="referral_bonus_amount" value="<?php echo e($referral_bonus_amount); ?>" step="any" min="0" required>
                                    <small class="form-text d-block mt-1" id="bonus_hint" style="color: #a1a1aa;"><?php echo __('Amount to reward for each successful referral'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="referral_bonus_trigger" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-flag-checkered text-info"></i> <?php echo __('Referral Bonus Trigger'); ?>
                                    </label>
                                    <select class="form-select form-select-custom" id="referral_bonus_trigger" name="referral_bonus_trigger" required>
                                        <option value="registration" <?php echo $referral_bonus_trigger === 'registration' ? 'selected' : ''; ?> data-requires-flat="true"><?php echo __('On Registration'); ?></option>
                                        <option value="first_deposit" <?php echo $referral_bonus_trigger === 'first_deposit' ? 'selected' : ''; ?>><?php echo __('On First Deposit'); ?></option>
                                        <option value="first_investment" <?php echo $referral_bonus_trigger === 'first_investment' ? 'selected' : ''; ?>><?php echo __('On First Investment'); ?></option>
                                        <option value="every_deposit" <?php echo $referral_bonus_trigger === 'every_deposit' ? 'selected' : ''; ?>><?php echo __('On Every Deposit'); ?></option>
                                        <option value="every_investment" <?php echo $referral_bonus_trigger === 'every_investment' ? 'selected' : ''; ?>><?php echo __('On Every Investment'); ?></option>
                                    </select>
                                    <small class="form-text d-block mt-1" id="trigger_help_text" style="color: #a1a1aa;"><?php echo __('When to award the referral bonus'); ?></small>
                                    <div class="alert alert-warning mt-2 d-none" id="percentage_registration_warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?php echo __('"On Registration" requires Fixed bonus type because there is no transaction amount to calculate percentage from.'); ?>
                                    </div>
                                </div>

                                <script>
                                    (function() {
                                        const bonusTypeRadios = document.querySelectorAll('input[name="referral_bonus_type"]');
                                        const triggerSelect = document.getElementById('referral_bonus_trigger');
                                        const flatRequiredOptions = triggerSelect.querySelectorAll('option[data-requires-flat="true"]');
                                        const warningAlert = document.getElementById('percentage_registration_warning');

                                        function updateTriggerOptions() {
                                            const selectedType = document.querySelector('input[name="referral_bonus_type"]:checked').value;
                                            const isPercentage = selectedType === 'percentage';

                                            if (isPercentage) {
                                                // Hide/disable options that require flat amount (registration)
                                                flatRequiredOptions.forEach(opt => {
                                                    opt.disabled = true;
                                                    opt.style.display = 'none';
                                                });

                                                // If a flat-required option was selected, switch to first_deposit
                                                const selectedOpt = triggerSelect.querySelector('option[value="' + triggerSelect.value + '"]');
                                                if (selectedOpt && selectedOpt.dataset.requiresFlat === 'true') {
                                                    triggerSelect.value = 'first_deposit';
                                                    warningAlert.classList.remove('d-none');
                                                }
                                            } else {
                                                // Show/enable all options
                                                flatRequiredOptions.forEach(opt => {
                                                    opt.disabled = false;
                                                    opt.style.display = '';
                                                });
                                                warningAlert.classList.add('d-none');
                                            }
                                        }

                                        bonusTypeRadios.forEach(radio => {
                                            radio.addEventListener('change', updateTriggerOptions);
                                        });
                                        // Run on page load
                                        updateTriggerOptions();
                                    })();
                                </script>

                                <hr class="my-4">
                                <h6 class="card-title mb-4 text-white"><?php echo __('Referral Wallet Limits'); ?></h6>

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-sliders-h text-info"></i> <?php echo __('Limit Mode'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="referral_fund_withdraw_mode" id="rfw_exact" value="exact" <?php echo $referral_fund_withdraw_mode === 'exact' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rfw_exact">
                                            <?php echo __('Exact Amount'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="referral_fund_withdraw_mode" id="rfw_range" value="range" <?php echo $referral_fund_withdraw_mode === 'range' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rfw_range">
                                            <?php echo __('Minimum / Maximum'); ?>
                                        </label>
                                    </div>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('When Exact is chosen, users can only fund/withdraw the exact amount. When Range is chosen, they can choose any amount within the min/max bounds.'); ?></small>
                                </div>

                                <!-- Exact amount -->
                                <div class="mb-4" id="rfw_exact_amount_wrapper" <?php echo $referral_fund_withdraw_mode !== 'exact' ? 'style="display:none;"' : ''; ?>>
                                    <label for="referral_exact_amount" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-bullseye text-info"></i> <?php echo __('Exact Amount'); ?>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="referral_exact_amount" name="referral_exact_amount" value="<?php echo e($referral_exact_amount); ?>" step="any" min="0">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Users must fund/withdraw exactly this amount each time'); ?></small>
                                </div>

                                <!-- Min / Max -->
                                <div class="mb-4" id="rfw_range_wrapper" <?php echo $referral_fund_withdraw_mode !== 'range' ? 'style="display:none;"' : ''; ?>>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="referral_min_amount" class="form-label text-muted-custom small fw-medium">
                                                <i class="fas fa-arrow-down text-info"></i> <?php echo __('Minimum Amount'); ?>
                                            </label>
                                            <input type="number" class="form-control form-control-custom" id="referral_min_amount" name="referral_min_amount" value="<?php echo e($referral_min_amount); ?>" step="any" min="0">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="referral_max_amount" class="form-label text-muted-custom small fw-medium">
                                                <i class="fas fa-arrow-up text-info"></i> <?php echo __('Maximum Amount'); ?>
                                            </label>
                                            <input type="number" class="form-control form-control-custom" id="referral_max_amount" name="referral_max_amount" value="<?php echo e($referral_max_amount); ?>" step="any" min="0">
                                        </div>
                                    </div>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Set max to 0 for no upper limit'); ?></small>
                                </div>

                                <script>
                                (function() {
                                    const modeRadios = document.querySelectorAll('input[name="referral_fund_withdraw_mode"]');
                                    const exactWrapper = document.getElementById('rfw_exact_amount_wrapper');
                                    const rangeWrapper = document.getElementById('rfw_range_wrapper');
                                    function updateMode() {
                                        const mode = document.querySelector('input[name="referral_fund_withdraw_mode"]:checked').value;
                                        if (mode === 'exact') {
                                            exactWrapper.style.display = '';
                                            rangeWrapper.style.display = 'none';
                                        } else {
                                            exactWrapper.style.display = 'none';
                                            rangeWrapper.style.display = '';
                                        }
                                    }
                                    modeRadios.forEach(r => r.addEventListener('change', updateMode));
                                    updateMode();
                                })();
                                </script>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Referral Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: Withdrawal Settings -->
            <div x-show="currentTab === 'withdrawal'" x-transition.opacity>
                <div class="mt-4">
                    <!-- Fee Configuration -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Withdrawal Fee Configuration'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="withdrawal">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label for="withdrawal_fee_percentage" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-percent text-info"></i> <?php echo __('Withdrawal Fee (Percentage)'); ?>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="withdrawal_fee_percentage" name="withdrawal_fee_percentage" value="<?php echo e($withdrawal_fee_percentage); ?>" min="0" max="100" step="0.01" required>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Percentage of withdrawal amount charged as fee (0-100)'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label for="minimum_withdrawal" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-money-bill text-info"></i> <?php echo __('Minimum Withdrawal'); ?>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="minimum_withdrawal" name="minimum_withdrawal" value="<?php echo e($minimum_withdrawal); ?>" min="0" step="0.01" required>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Minimum amount a user must withdraw per request'); ?></small>
                                </div>

                                <div class="alert alert-info mb-4" role="alert" x-transition.opacity>
                                    <i class="fas fa-circle-info"></i> <strong><?php echo __('Note:'); ?></strong> <?php echo __('Users will not be able to submit a withdrawal request below this amount on both the client and server side.'); ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Fee Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Withdrawal Methods Management -->
                    <div class="card bg-card border-subtle" x-data="withdrawalMethodsApp()">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Withdrawal Methods'); ?></h6>
                            <p class="mb-4" style="color: #a1a1aa;"><?php echo __('Enable or disable the available withdrawal methods for users. Icons are automatically displayed based on method type.'); ?></p>

                            <form action="/admin/actions/settings-update" method="POST" @submit.prevent="$refs.withdrawal_methods_input.value = JSON.stringify(methods); $el.submit()">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="withdrawal_methods">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">
                                <input type="hidden" name="withdrawal_methods" x-ref="withdrawal_methods_input" value="">

                                <!-- Methods List -->
                                <div class="list-group mb-4">
                                    <template x-for="(method, key) in methods" :key="key">
                                        <div class="list-group-item py-3" style="background-color: var(--bg-card); border-color: var(--border-color);">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-3">
                                                    <i :class="getMethodIcon(method.type)" class="fa-lg" style="color: #a1a1aa;"></i>
                                                    <div>
                                                        <span class="fw-bold" x-text="method.name"></span>
                                                        <small style="color: #a1a1aa;" x-text="getMethodTypeLabel(method.type)"></small>
                                                    </div>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" :id="'enable_' + key" x-model="method.enabled">
                                                    <label class="form-check-label" :for="'enable_' + key">
                                                        <span class="badge" :class="method.enabled ? 'bg-success' : 'bg-secondary'" x-text="method.enabled ? '<?php echo __('Enabled'); ?>' : '<?php echo __('Disabled'); ?>'"></span>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Crypto Networks -->
                                            <div x-show="method.enabled && method.type === 'crypto'" class="mt-3 pt-3 border-top" style="border-color: var(--border-color);">
                                                <label class="form-label text-muted-custom small fw-medium mb-1"><?php echo __('Networks'); ?></label>
                                                <input type="text" class="form-control form-control-custom" :value="getNetworksString(key)" @blur="setNetworksFromString(key, $event.target.value)" placeholder="<?php echo __('e.g. TRC20, ERC20, BEP20'); ?>">
                                                <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Comma-separated list of available networks for this cryptocurrency'); ?></small>
                                            </div>

                                            <!-- Mobile Money Providers -->
                                            <div x-show="method.enabled && method.type === 'momo'" class="mt-3 pt-3 border-top" style="border-color: var(--border-color);">
                                                <label class="form-label text-muted-custom small fw-medium mb-1"><?php echo __('Providers'); ?></label>
                                                <input type="text" class="form-control form-control-custom" :value="getProvidersString(key)" @blur="setProvidersFromString(key, $event.target.value)" placeholder="<?php echo __('e.g. MTN, Airtel, M-Pesa, Orange'); ?>">
                                                <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Comma-separated list of available mobile money providers'); ?></small>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Changes'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: KYC Settings -->
            <div x-show="currentTab === 'kyc'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('KYC Configuration'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="kyc">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-shield-alt text-info"></i> <?php echo __('KYC Required'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_required" id="kyc_yes" value="yes" @change="kyc_required = 'yes'" <?php echo $kyc_required === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_yes">
                                            <?php echo __('Yes, require KYC'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_required" id="kyc_no" value="no" @change="kyc_required = 'no'" <?php echo $kyc_required === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_no">
                                            <?php echo __('No, KYC optional'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4" id="kyc_timing_div" x-show="kyc_required === 'yes'">
                                    <label for="kyc_timing" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-clock text-info"></i> <?php echo __('KYC Timing'); ?>
                                    </label>
                                    <select class="form-select form-select-custom" id="kyc_timing" name="kyc_timing" required>
                                        <option value="before_withdrawal" <?php echo $kyc_timing === 'before_withdrawal' ? 'selected' : ''; ?>><?php echo __('Before First Withdrawal'); ?></option>
                                        <option value="before_investment" <?php echo $kyc_timing === 'before_investment' ? 'selected' : ''; ?>><?php echo __('Before First Investment'); ?></option>
                                        <option value="immediately" <?php echo $kyc_timing === 'immediately' ? 'selected' : ''; ?>><?php echo __('Immediately After Registration'); ?></option>
                                    </select>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('When KYC verification is required'); ?></small>
                                </div>

                                <div class="mb-4" id="kyc_banner_div" x-show="kyc_required === 'yes'">
                                    <label for="kyc_always_show_message" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-bell text-warning"></i> <?php echo __('Always Show KYC Banner'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_always_show_message" id="kyc_always_show_yes" value="yes" <?php echo $kyc_always_show_message === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_always_show_yes">
                                            <?php echo __('Yes, always show'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_always_show_message" id="kyc_always_show_no" value="no" <?php echo $kyc_always_show_message === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_always_show_no">
                                            <?php echo __('No, show based on timing'); ?>
                                        </label>
                                    </div>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('When enabled, users will always see the KYC reminder banner on every page, regardless of the timing setting.'); ?></small>
                                </div>

                                <div class="mb-4" id="kyc_dismissible_div" x-show="kyc_required === 'yes'">
                                    <label for="kyc_banner_dismissible" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-times-circle text-secondary"></i> <?php echo __('Allow Users to Dismiss Banner'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_banner_dismissible" id="kyc_dismissible_yes" value="yes" <?php echo $kyc_banner_dismissible === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_dismissible_yes">
                                            <?php echo __('Yes, allow dismiss'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kyc_banner_dismissible" id="kyc_dismissible_no" value="no" <?php echo $kyc_banner_dismissible === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="kyc_dismissible_no">
                                            <?php echo __('No, always visible'); ?>
                                        </label>
                                    </div>
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('When enabled, users can close the KYC banner. When disabled, the banner is always visible until KYC is approved.'); ?></small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save KYC Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 6: Investment Cancellation -->
            <div x-show="currentTab === 'cancellation'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Investment Cancellation Policy'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="cancellation">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-money-bill text-info"></i> <?php echo __('Cancellation Penalty Mode'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cancellation_penalty_mode" id="penalty_percentage" value="percentage" <?php echo $cancellation_penalty_mode === 'percentage' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="penalty_percentage">
                                            <?php echo __('Percentage of Investment Amount'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check my-2">
                                        <input class="form-check-input" type="radio" name="cancellation_penalty_mode" id="penalty_flat" value="flat" <?php echo $cancellation_penalty_mode === 'flat' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="penalty_flat">
                                            <?php echo __('Fixed Amount'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cancellation_penalty_mode" id="penalty_none" value="none" <?php echo $cancellation_penalty_mode === 'none' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="penalty_none">
                                            <?php echo __('No Penalty'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4" id="penalty_percentage_div" style="<?php echo $cancellation_penalty_mode === 'percentage' ? '' : 'display: none;'; ?>">
                                    <label for="cancellation_penalty_percentage" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-percent text-info"></i> <?php echo __('Penalty Percentage'); ?>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="cancellation_penalty_percentage" name="cancellation_penalty_percentage" value="<?php echo e($cancellation_penalty_percentage); ?>" min="0" max="100" step="0.01">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Percentage of investment amount charged as penalty (0-100)'); ?></small>
                                </div>

                                <div class="mb-4" id="penalty_flat_div" style="<?php echo $cancellation_penalty_mode === 'flat' ? '' : 'display: none;'; ?>">
                                    <label for="cancellation_penalty_flat" class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-money-bill text-info"></i> <?php echo __('Flat Penalty Amount'); ?>
                                    </label>
                                    <input type="number" class="form-control form-control-custom" id="cancellation_penalty_flat" name="cancellation_penalty_flat" value="<?php echo e($cancellation_penalty_flat); ?>" min="0" step="0.01">
                                    <small class="form-text d-block mt-1" style="color: #a1a1aa;"><?php echo __('Fixed amount charged as penalty'); ?></small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-clock text-info"></i> <?php echo __('Block Cancellation After First Payout Due'); ?>
                                    </label>
                                    <p class="small mb-3" style="color: #a1a1aa;"><?php echo __('When enabled, users cannot cancel an investment once the first payout date has been reached.'); ?></p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cancellation_block_after_waiting" id="block_yes" value="yes" <?php echo $cancellation_block_after_waiting === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="block_yes">
                                            <?php echo __('Block cancellation after first payout date'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check my-2">
                                        <input class="form-check-input" type="radio" name="cancellation_block_after_waiting" id="block_no" value="no" <?php echo $cancellation_block_after_waiting === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="block_no">
                                            <?php echo __('Allow cancellation anytime'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4" id="earned_profits_div">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-coins text-info"></i> <?php echo __('Earned Profits on Cancellation'); ?>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cancellation_forfeit_profits" id="forfeit_yes" value="yes" <?php echo $cancellation_forfeit_profits === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="forfeit_yes">
                                            <?php echo __('User forfeits all earned profits when cancelling'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check my-2">
                                        <input class="form-check-input" type="radio" name="cancellation_forfeit_profits" id="forfeit_no" value="no" <?php echo $cancellation_forfeit_profits === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="forfeit_no">
                                            <?php echo __('User keeps earned profits'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Cancellation Policy'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 7: Post-Registration Behavior -->
            <div x-show="currentTab === 'registration'" x-transition.opacity>
                <div class="mt-4">
                    <!-- Email Verification Settings -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Email Verification'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_verification">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-shield-alt text-info"></i> <?php echo __('Require Email Verification'); ?>
                                    </label>
                                    <p class="small mb-3" style="color: #a1a1aa;"><?php echo __('When enabled, new users must verify their email address before they can log in.'); ?></p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="require_email_verification" id="email_verify_yes" value="yes" <?php echo $require_email_verification === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_verify_yes">
                                            <i class="fas fa-check-circle text-success"></i> <?php echo __('Yes, require email verification'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="require_email_verification" id="email_verify_no" value="no" <?php echo $require_email_verification === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_verify_no">
                                            <i class="fas fa-times-circle text-danger"></i> <?php echo __('No, allow login without verification'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-info mb-4" role="alert" x-transition.opacity>
                                    <i class="fas fa-circle-info"></i> <strong><?php echo __('Note:'); ?></strong>
                                    <?php echo __('When enabled, new registrations will receive a verification email. Users must click the link in the email before they can access their account.'); ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Email Verification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Referral Code Requirement Settings -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Referral Code Requirement'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="registration">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-user-tag text-info"></i> <?php echo __('Require Referral Code'); ?>
                                    </label>
                                    <p class="small mb-3" style="color: #a1a1aa;"><?php echo __('When enabled, new users must provide a valid referral code to complete registration.'); ?></p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="require_referral_code" id="referral_req_yes" value="yes" <?php echo $require_referral_code === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="referral_req_yes">
                                            <i class="fas fa-check-circle text-success"></i> <?php echo __('Yes, require a valid referral code'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="require_referral_code" id="referral_req_no" value="no" <?php echo $require_referral_code === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="referral_req_no">
                                            <i class="fas fa-times-circle text-danger"></i> <?php echo __('No, referral code is optional'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-info mb-4" role="alert" x-transition.opacity>
                                    <i class="fas fa-circle-info"></i> <strong><?php echo __('Note:'); ?></strong>
                                    <?php echo __('When enabled, users who do not provide a valid referral code during registration will be rejected. The registration form will clearly indicate that a referral code is required.'); ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Referral Requirement Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Post-Registration Redirect Settings -->
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Post-Registration Behavior'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="registration">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium mb-3">
                                        <i class="fas fa-arrow-right text-info"></i> <?php echo __('After Registration Redirect'); ?>
                                    </label>
                                    <p class="small mb-3" style="color: #a1a1aa;"><?php echo __('Where should users be redirected immediately after successful registration?'); ?></p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="post_registration_action" id="action_dashboard" value="dashboard" <?php echo $post_registration_action === 'dashboard' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="action_dashboard">
                                            <i class="fas fa-chart-line"></i> <?php echo __('Dashboard'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="post_registration_action" id="action_deposit" value="deposit" <?php echo $post_registration_action === 'deposit' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="action_deposit">
                                            <i class="fas fa-arrow-down"></i> <?php echo __('Deposit Page'); ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="post_registration_action" id="action_invest" value="invest" <?php echo $post_registration_action === 'invest' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="action_invest">
                                            <i class="fas fa-chart-bar"></i> <?php echo __('Investment Page'); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Post-Registration Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 8: Countries Management -->
            <div x-show="currentTab === 'countries'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4" id="countries-settings-component" x-data="{ showModal: false, affectedUsers: [], submitting: false, defaultCountryMismatch: false }">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Countries Settings'); ?></h6>
                            <?php
                            $accepted_countries = get_accepted_countries();
                            $all_countries = get_countries();
                            $default_country_setting = get_setting('default_country', '');
                            ?>
                            <form id="countries-form" action="/admin/actions/settings-update" method="POST" @submit.prevent="submitCountriesForm()">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="countries">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <!-- Default Country Section -->
                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium" for="default_country">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i><?php echo __('Default Country'); ?>
                                    </label>
                                    <small class="form-text d-block mb-2" style="color: #a1a1aa;"><?php echo __('Users from deselected countries will be reassigned to this country.'); ?></small>
                                    <select name="default_country" id="default_country" class="form-select form-select-custom">
                                        <option value=""><?php echo __('Select a country...'); ?></option>
                                        <?php foreach ($all_countries as $code => $country): ?>
                                            <option value="<?php echo e($code); ?>" <?php echo $code === $default_country_setting ? 'selected' : ''; ?>><?php echo e($country); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="default-country-error" class="text-danger small mt-2" style="display:none;">
                                        <?php echo __('The default country must be in the accepted list.'); ?>
                                    </div>
                                </div>

                                <!-- Accepted Countries Section -->
                                <div class="mb-4">
                                    <label class="form-label text-muted-custom small fw-medium">
                                        <i class="fas fa-check-square text-primary me-1"></i><?php echo __('Accepted Countries'); ?>
                                    </label>
                                    <small class="form-text d-block mb-3" style="color: #a1a1aa;"><?php echo __('Only users from these countries can register.'); ?></small>
                                    <div class="row g-2">
                                        <?php foreach ($all_countries as $code => $country): ?>
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="accepted_countries[]" value="<?php echo e($code); ?>" id="country_<?php echo e($code); ?>" <?php echo in_array($code, $accepted_countries) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="country_<?php echo e($code); ?>">
                                                        <?php echo e($country); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div id="countries-error" class="text-danger small mt-2" style="display:none;">
                                        <?php echo __('At least one country must be accepted.'); ?>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-accent" :disabled="submitting">
                                        <span x-show="!submitting">
                                            <i class="fas fa-save"></i> <?php echo __('Save Countries Settings'); ?>
                                        </span>
                                        <span x-show="submitting">
                                            <i class="fas fa-spinner fa-spin"></i> <?php echo __('Saving...'); ?>
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <!-- Reassignment Modal -->
                            <div class="modal-backdrop" x-show="showModal" style="display:none;" @click="showModal = false" x-transition.opacity></div>
                            <div class="modal" x-show="showModal" style="display:none;" x-transition.opacity>
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo __('Users Will Be Reassigned'); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" @click="showModal = false"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-3"><?php echo __('The following users are from countries that will no longer be accepted. They will be reassigned to the default country:'); ?></p>
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm table-dark">
                                            <thead>
                                                <tr>
                                                    <th><?php echo __('Name'); ?></th>
                                                    <th><?php echo __('Email'); ?></th>
                                                    <th><?php echo __('Current Country'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="user in affectedUsers" :key="user.id">
                                                    <tr>
                                                        <td x-text="user.name"></td>
                                                        <td x-text="user.email"></td>
                                                        <td x-text="user.country"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" @click="showModal = false"><?php echo __('Cancel'); ?></button>
                                    <button type="button" class="btn btn-danger" @click="proceedReassign()" :disabled="submitting">
                                        <span x-show="!submitting"><?php echo __('Proceed & Reassign'); ?></span>
                                        <span x-show="submitting"><?php echo __('Processing...'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 9: Email Notifications -->
            <div x-show="currentTab === 'notifications'" x-transition.opacity>
                <div class="mt-4">
                    <!-- Bulk Actions -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Bulk Notification Settings'); ?></h6>
                            <p class="mb-4" style="color: #a1a1aa;"><?php echo __('Quickly enable or disable all notification emails across all categories.'); ?></p>
                            <div class="bulk-actions-container">
                                <button type="button" class="btn btn-success" onclick="toggleAllNotifications(true)">
                                    <i class="fas fa-check-circle"></i> <?php echo __('Turn On All Notifications & Save'); ?>
                                </button>
                                <button type="button" class="btn btn-danger" onclick="toggleAllNotifications(false)">
                                    <i class="fas fa-times-circle"></i> <?php echo __('Turn Off All Notifications & Save'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- User Account Notifications -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('User Account Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_account">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_user_registration" id="email_user_registration" value="yes" <?php echo $email_user_registration === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_user_registration">
                                                <?php echo __('Registration Welcome Email'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Send welcome email after registration'); ?></small>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_password_reset" id="email_password_reset" value="yes" <?php echo $email_password_reset === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_password_reset">
                                                <?php echo __('Password Reset'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Send password reset emails'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Account Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Deposit Notifications -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Deposit Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_deposit">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('User Notifications'); ?></h6>
                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_deposit_submitted_user" id="email_deposit_submitted_user" value="yes" <?php echo $email_deposit_submitted_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_deposit_submitted_user">
                                                <?php echo __('Deposit Submitted'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when deposit is submitted'); ?></small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_deposit_approved_user" id="email_deposit_approved_user" value="yes" <?php echo $email_deposit_approved_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_deposit_approved_user">
                                                <?php echo __('Deposit Approved'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when deposit is approved'); ?></small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_deposit_rejected_user" id="email_deposit_rejected_user" value="yes" <?php echo $email_deposit_rejected_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_deposit_rejected_user">
                                                <?php echo __('Deposit Rejected'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when deposit is rejected'); ?></small>
                                    </div>
                                </div>

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('Admin Notifications'); ?></h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_deposit_submitted_admin" id="email_deposit_submitted_admin" value="yes" <?php echo $email_deposit_submitted_admin === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_deposit_submitted_admin">
                                                <?php echo __('New Deposit Submitted'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify admin when new deposit is submitted'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Deposit Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Withdrawal Notifications -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Withdrawal Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_withdrawal">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('User Notifications'); ?></h6>
                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_withdrawal_submitted_user" id="email_withdrawal_submitted_user" value="yes" <?php echo $email_withdrawal_submitted_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_withdrawal_submitted_user">
                                                <?php echo __('Withdrawal Submitted'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when withdrawal is submitted'); ?></small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_withdrawal_approved_user" id="email_withdrawal_approved_user" value="yes" <?php echo $email_withdrawal_approved_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_withdrawal_approved_user">
                                                <?php echo __('Withdrawal Approved'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when withdrawal is approved'); ?></small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_withdrawal_rejected_user" id="email_withdrawal_rejected_user" value="yes" <?php echo $email_withdrawal_rejected_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_withdrawal_rejected_user">
                                                <?php echo __('Withdrawal Rejected'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when withdrawal is rejected'); ?></small>
                                    </div>
                                </div>

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('Admin Notifications'); ?></h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_withdrawal_submitted_admin" id="email_withdrawal_submitted_admin" value="yes" <?php echo $email_withdrawal_submitted_admin === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_withdrawal_submitted_admin">
                                                <?php echo __('New Withdrawal Submitted'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify admin when new withdrawal is submitted'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Withdrawal Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Investment Notifications -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Investment Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_investment">
                                <input type="hidden" name="redirect_tab" value="" x-bind:value="currentTab">

                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_investment_created_user" id="email_investment_created_user" value="yes" <?php echo $email_investment_created_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_investment_created_user">
                                                <?php echo __('Investment Created'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when investment is created'); ?></small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_investment_completed_user" id="email_investment_completed_user" value="yes" <?php echo $email_investment_completed_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_investment_completed_user">
                                                <?php echo __('Investment Completed'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when investment completes'); ?></small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_investment_cancelled_user" id="email_investment_cancelled_user" value="yes" <?php echo $email_investment_cancelled_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_investment_cancelled_user">
                                                <?php echo __('Investment Cancelled'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when investment is cancelled'); ?></small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_profit_payout_user" id="email_profit_payout_user" value="yes" <?php echo $email_profit_payout_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_profit_payout_user">
                                                <?php echo __('Profit Payout'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user on each profit payout'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Investment Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- KYC Notifications -->
                    <div class="card border-subtle mb-4" style="background-color: var(--bg-card);">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('KYC Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_kyc">

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('User Notifications'); ?></h6>
                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_kyc_approved_user" id="email_kyc_approved_user" value="yes" <?php echo $email_kyc_approved_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_kyc_approved_user">
                                                <?php echo __('KYC Approved'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when KYC is approved'); ?></small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_kyc_rejected_user" id="email_kyc_rejected_user" value="yes" <?php echo $email_kyc_rejected_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_kyc_rejected_user">
                                                <?php echo __('KYC Rejected'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when KYC is rejected'); ?></small>
                                    </div>
                                </div>

                                <h6 class="mb-3" style="color: #a1a1aa;"><?php echo __('Admin Notifications'); ?></h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_kyc_submitted_admin" id="email_kyc_submitted_admin" value="yes" <?php echo $email_kyc_submitted_admin === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_kyc_submitted_admin">
                                                <?php echo __('KYC Submitted'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify admin when KYC is submitted'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save KYC Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Referral Notifications -->
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Referral Notifications'); ?></h6>
                            <form action="/admin/actions/settings-update" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="section" value="email_notifications_referral">

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="email_referral_bonus_user" id="email_referral_bonus_user" value="yes" <?php echo $email_referral_bonus_user === 'yes' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_referral_bonus_user">
                                                <?php echo __('Referral Bonus Credited'); ?>
                                            </label>
                                        </div>
                                        <small style="color: #a1a1aa;"><?php echo __('Notify user when referral bonus is credited'); ?></small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="fas fa-save"></i> <?php echo __('Save Referral Notification Settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 9: Cron Management -->
            <div x-show="currentTab === 'cron'" x-transition.opacity>
                <div class="mt-4">
                    <div class="card bg-card border-subtle">
                        <div class="card-body p-4">
                            <h6 class="card-title mb-4 text-white"><?php echo __('Cron Job Management'); ?></h6>

                            <!-- Cron Status -->
                            <div class="mb-4">
                                <h6 class="mb-3"><?php echo __('Status'); ?></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-card border-subtle">
                                            <div class="card-body" style="background-color: var(--bg-card);">
                                                <p class="small mb-1" style="color: #a1a1aa;"><?php echo __('Last Cron Run'); ?></p>
                                                <p class="mb-0"><strong><?php echo e($last_cron_run); ?></strong></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-card border-subtle">
                                            <div class="card-body" style="background-color: var(--bg-card);">
                                                <p class="small mb-1" style="color: #a1a1aa;"><?php echo __('Lock Status'); ?></p>
                                                <p class="mb-0">
                                                    <?php if ($cron_lock_exists): ?>
                                                        <span class="badge bg-danger"><i class="fas fa-lock"></i> <?php echo __('Locked'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><i class="fas fa-unlock"></i> <?php echo __('Unlocked'); ?></span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Clear Lock Button -->
                            <?php if ($cron_lock_exists): ?>
                                <div class="mb-4">
                                    <form action="/admin/actions/clear-cron-lock" method="POST" onsubmit="return confirm('<?php echo __('Are you sure you want to clear the cron lock?'); ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-unlock"></i> <?php echo __('Clear Cron Lock'); ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- Cron Setup Instructions -->
                            <div class="mb-4">
                                <h6 class="mb-3"><?php echo __('Cron Job Setup'); ?></h6>
                                <p class="small mb-2" style="color: #a1a1aa;"><?php echo __('Add this command to your server\'s crontab to run the investment profit calculation every minute:'); ?></p>
                                <div class="cron-code-block">
                                    * * * * * curl -s "https://<?php echo e($_SERVER['HTTP_HOST']); ?>/cron/process-investments.php?key=YOUR_KEY" >/dev/null 2>&1
                                </div>
                                <small style="color: #a1a1aa;"><?php echo __('Replace YOUR_KEY with your CRON_API_KEY from .env file'); ?></small>
                            </div>

                            <!-- Cron Endpoint -->
                            <div class="mb-4">
                                <h6 class="mb-3"><?php echo __('Cron Endpoint'); ?></h6>
                                <p class="small mb-2" style="color: #a1a1aa;"><?php echo __('Endpoint URL for manual testing:'); ?></p>
                                <div class="bg-card border-subtle p-3 rounded mb-2" style="font-family: monospace; font-size: 0.85rem; word-break: break-all;">
                                    /cron/process-investments.php?key=YOUR_KEY
                                </div>
                            </div>

                            <!-- Help Text -->
                            <div class="alert alert-info" role="alert" x-transition.opacity>
                                <i class="fas fa-circle-info"></i>
                                <strong><?php echo __('About Cron Jobs:'); ?></strong> <?php echo __('The cron job processes pending investments and calculates profits. This should run every minute. If the process is taking too long, a lock file is created to prevent concurrent execution.'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Payment Methods Alpine.js Component
    // Default instructions for each payment type
    const defaultInstructions = {
        'Cryptocurrency': <?php echo json_encode(__('Send only the specified cryptocurrency to the address below. Transactions on the wrong networks cannot be recovered. Please verify the network before sending.')); ?>,
        'Bank Transfer': <?php echo json_encode(__('Transfer the exact amount to the bank account details below. Use your username as the payment reference. Deposits may take 1-3 business days to process.')); ?>,
        'E-Wallet': <?php echo json_encode(__('Send payment to the e-wallet address below. Include your username in the payment note for faster processing.')); ?>,
        'Mobile Money': <?php echo json_encode(__('Send payment to the mobile money number below. Include your username as the reference. Instant processing.')); ?>,
        'Other': <?php echo json_encode(__('Follow the payment instructions below carefully. Contact support if you need assistance.')); ?>
    };

    function paymentMethodsApp() {
        return {
            methods: <?php echo json_encode(!empty($payment_methods) && is_array($payment_methods) ? $payment_methods : new stdClass()); ?>,
            editingKey: null,
            qrTimeout: null,
            formData: {
                key: '',
                name: '',
                type: '',
                instructions: '',
                qr_code: '',
                // Type-specific data structures
                crypto: {
                    network: '',
                    wallet_address: ''
                },
                bank: {
                    bank_name: '',
                    account_name: '',
                    account_number: '',
                    swift_code: '',
                    branch_code: ''
                },
                ewallet: {
                    provider: '',
                    wallet_id: '',
                    account_name: ''
                },
                mobile: {
                    provider: '',
                    phone_number: '',
                    account_name: ''
                },
                other: {
                    details: ''
                }
            },
            get methodsJSON() {
                return JSON.stringify(this.methods);
            },
            generateKey() {
                if (this.editingKey === null && this.formData.name) {
                    this.formData.key = this.formData.name
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '')
                        .substring(0, 30);
                }
            },
            setDefaultInstructions() {
                if (this.formData.type && !this.formData.instructions) {
                    this.formData.instructions = defaultInstructions[this.formData.type] || '';
                }
                // Clear QR code if switching away from crypto
                if (this.formData.type !== 'Cryptocurrency') {
                    this.formData.qr_code = '';
                }
            },
            // Generate QR code for crypto address
            generateQROnInput() {
                if (this.formData.type !== 'Cryptocurrency') return;
                if (!this.formData.crypto.wallet_address) {
                    this.formData.qr_code = '';
                    return;
                }

                // Debounce the QR generation
                clearTimeout(this.qrTimeout);
                this.qrTimeout = setTimeout(() => {
                    this.generateQRCode();
                }, 500);
            },
            async generateQRCode() {
                const address = this.formData.crypto.wallet_address;
                if (!address) {
                    this.formData.qr_code = '';
                    return;
                }

                try {
                    // console.log('Generating QR code for address:', address);
                    const formData = new FormData();
                    formData.append('address', address);
                    formData.append('network', this.formData.crypto.network || '');

                    const response = await fetch('/admin/actions/generate-qr', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    const data = await response.json();
                    // console.log('QR code response:', data);

                    if (data.success && data.qr_code) {
                        this.formData.qr_code = data.qr_code;
                    } else if (data.error) {
                        console.error('QR generation error:', data.error);
                        this.formData.qr_code = '';
                    }
                } catch (error) {
                    console.error('Failed to generate QR code:', error);
                    this.formData.qr_code = '';
                }
            },
            // Get method data based on type - only include relevant fields
            getMethodData() {
                const baseData = {
                    name: this.formData.name,
                    type: this.formData.type,
                    instructions: this.formData.instructions
                };

                switch (this.formData.type) {
                    case 'Cryptocurrency':
                        return {
                            ...baseData,
                            network: this.formData.crypto.network,
                                wallet_address: this.formData.crypto.wallet_address,
                                qr_code: this.formData.qr_code
                        };
                    case 'Bank Transfer':
                        return {
                            ...baseData,
                            bank_name: this.formData.bank.bank_name,
                                account_name: this.formData.bank.account_name,
                                account_number: this.formData.bank.account_number,
                                swift_code: this.formData.bank.swift_code,
                                branch_code: this.formData.bank.branch_code
                        };
                    case 'E-Wallet':
                        return {
                            ...baseData,
                            provider: this.formData.ewallet.provider,
                                wallet_id: this.formData.ewallet.wallet_id,
                                account_name: this.formData.ewallet.account_name
                        };
                    case 'Mobile Money':
                        return {
                            ...baseData,
                            provider: this.formData.mobile.provider,
                                phone_number: this.formData.mobile.phone_number,
                                account_name: this.formData.mobile.account_name
                        };
                    case 'Other':
                        return {
                            ...baseData,
                            details: this.formData.other.details
                        };
                    default:
                        return baseData;
                }
            },
            // Load method data into form
            loadMethodData(method) {
                // Reset all type-specific data first
                this.formData.crypto = {
                    network: '',
                    wallet_address: ''
                };
                this.formData.bank = {
                    bank_name: '',
                    account_name: '',
                    account_number: '',
                    swift_code: '',
                    branch_code: ''
                };
                this.formData.ewallet = {
                    provider: '',
                    wallet_id: '',
                    account_name: ''
                };
                this.formData.mobile = {
                    provider: '',
                    phone_number: '',
                    account_name: ''
                };
                this.formData.other = {
                    details: ''
                };

                // Load based on type
                switch (method.type) {
                    case 'Cryptocurrency':
                        this.formData.crypto = {
                            network: method.network || '',
                            wallet_address: method.wallet_address || ''
                        };
                        this.formData.qr_code = method.qr_code || '';
                        break;
                    case 'Bank Transfer':
                        this.formData.bank = {
                            bank_name: method.bank_name || '',
                            account_name: method.account_name || '',
                            account_number: method.account_number || '',
                            swift_code: method.swift_code || '',
                            branch_code: method.branch_code || ''
                        };
                        break;
                    case 'E-Wallet':
                        this.formData.ewallet = {
                            provider: method.provider || '',
                            wallet_id: method.wallet_id || '',
                            account_name: method.account_name || ''
                        };
                        break;
                    case 'Mobile Money':
                        this.formData.mobile = {
                            provider: method.provider || '',
                            phone_number: method.phone_number || '',
                            account_name: method.account_name || ''
                        };
                        break;
                    case 'Other':
                        this.formData.other = {
                            details: method.details || ''
                        };
                        break;
                }
            },
            validateMethod() {
                if (!this.formData.name || !this.formData.type) {
                    alert(<?php echo json_encode(__('Please fill in name and type')); ?>);
                    return false;
                }

                // Validate type-specific required fields
                switch (this.formData.type) {
                    case 'Cryptocurrency':
                        if (!this.formData.crypto.network || !this.formData.crypto.wallet_address) {
                            alert(<?php echo json_encode(__('Please fill in network and wallet address')); ?>);
                            return false;
                        }
                        break;
                    case 'Bank Transfer':
                        if (!this.formData.bank.bank_name || !this.formData.bank.account_name || !this.formData.bank.account_number) {
                            alert(<?php echo json_encode(__('Please fill in bank name, account name, and account number')); ?>);
                            return false;
                        }
                        break;
                    case 'E-Wallet':
                        if (!this.formData.ewallet.provider || !this.formData.ewallet.wallet_id) {
                            alert(<?php echo json_encode(__('Please fill in provider and wallet ID')); ?>);
                            return false;
                        }
                        break;
                    case 'Mobile Money':
                        if (!this.formData.mobile.provider || !this.formData.mobile.phone_number) {
                            alert(<?php echo json_encode(__('Please fill in provider and phone number')); ?>);
                            return false;
                        }
                        break;
                    case 'Other':
                        if (!this.formData.other.details) {
                            alert(<?php echo json_encode(__('Please fill in payment details')); ?>);
                            return false;
                        }
                        break;
                }
                return true;
            },
            saveMethod() {
                if (!this.validateMethod()) return;

                // Generate key if not set
                if (!this.formData.key) {
                    this.generateKey();
                }

                const methodData = this.getMethodData();

                if (this.editingKey !== null) {
                    // Update existing
                    const newMethods = Object.assign({}, this.methods);
                    delete newMethods[this.editingKey];
                    newMethods[this.formData.key] = methodData;
                    this.methods = newMethods;
                    this.editingKey = null;
                } else {
                    // Add new
                    if (this.methods[this.formData.key]) {
                        alert(<?php echo json_encode(__('A method with this key already exists')); ?>);
                        return;
                    }
                    this.methods = Object.assign({}, this.methods, {
                        [this.formData.key]: methodData
                    });
                }
                this.resetForm();
            },
            editMethod(key) {
                this.editingKey = key;
                const method = this.methods[key] || {};
                this.formData.key = key;
                this.formData.name = method.name || '';
                this.formData.type = method.type || '';
                this.formData.instructions = method.instructions || '';
                this.formData.qr_code = method.qr_code || '';
                this.loadMethodData(method);
            },
            deleteMethod(key) {
                if (confirm(<?php echo json_encode(__('Delete this payment method?')); ?>)) {
                    const newMethods = Object.assign({}, this.methods);
                    delete newMethods[key];
                    this.methods = newMethods;
                }
            },
            cancelEdit() {
                this.editingKey = null;
                this.resetForm();
            },
            resetForm() {
                this.formData = {
                    key: '',
                    name: '',
                    type: '',
                    instructions: '',
                    qr_code: '',
                    crypto: {
                        network: '',
                        wallet_address: ''
                    },
                    bank: {
                        bank_name: '',
                        account_name: '',
                        account_number: '',
                        swift_code: '',
                        branch_code: ''
                    },
                    ewallet: {
                        provider: '',
                        wallet_id: '',
                        account_name: ''
                    },
                    mobile: {
                        provider: '',
                        phone_number: '',
                        account_name: ''
                    },
                    other: {
                        details: ''
                    }
                };
                clearTimeout(this.qrTimeout);
            }
        };
    }

    // Referral bonus type label updater
    document.addEventListener('DOMContentLoaded', function() {
        const bonusTypeRadios = document.querySelectorAll('input[name="referral_bonus_type"]');
        const bonusLabel = document.getElementById('bonus_label');
        const bonusHint = document.getElementById('bonus_hint');

        function updateBonusLabel() {
            const type = document.querySelector('input[name="referral_bonus_type"]:checked').value;
            if (type === 'percentage') {
                bonusLabel.textContent = 'Bonus Percentage (%)';
                bonusHint.textContent = 'Percentage of deposit amount to reward';
            } else {
                bonusLabel.textContent = 'Bonus Amount';
                bonusHint.textContent = 'Fixed amount to reward for each successful referral';
            }
        }

        bonusTypeRadios.forEach(radio => {
            radio.addEventListener('change', updateBonusLabel);
        });

        // Initial update
        updateBonusLabel();
    });

    // KYC timing visibility
    document.addEventListener('DOMContentLoaded', function() {
        const kycRadios = document.querySelectorAll('input[name="kyc_required"]');
        const kycTimingDiv = document.getElementById('kyc_timing_div');

        function updateKycVisibility() {
            const kycRequired = document.querySelector('input[name="kyc_required"]:checked').value;
            if (kycTimingDiv) {
                if (kycRequired === 'yes') {
                    kycTimingDiv.style.display = '';
                } else {
                    kycTimingDiv.style.display = 'none';
                }
            }
        }

        kycRadios.forEach(radio => {
            radio.addEventListener('change', updateKycVisibility);
        });

        updateKycVisibility();
    });

    // Cancellation penalty mode visibility and earned profits section visibility
    document.addEventListener('DOMContentLoaded', function() {
        const penaltyRadios = document.querySelectorAll('input[name="cancellation_penalty_mode"]');
        const blockRadios = document.querySelectorAll('input[name="cancellation_block_after_waiting"]');
        const percentageDiv = document.getElementById('penalty_percentage_div');
        const flatDiv = document.getElementById('penalty_flat_div');
        const earnedProfitsDiv = document.getElementById('earned_profits_div');

        function updateCancellationVisibility() {
            const penaltyModeEl = document.querySelector('input[name="cancellation_penalty_mode"]:checked');
            const blockModeEl = document.querySelector('input[name="cancellation_block_after_waiting"]:checked');
            const penaltyMode = penaltyModeEl ? penaltyModeEl.value : 'percentage';
            const blockMode = blockModeEl ? blockModeEl.value : 'no';

            // Update penalty visibility based on mode
            if (percentageDiv) {
                if (penaltyMode === 'percentage') {
                    percentageDiv.style.display = '';
                } else {
                    percentageDiv.style.display = 'none';
                }
            }
            if (flatDiv) {
                if (penaltyMode === 'flat') {
                    flatDiv.style.display = '';
                } else {
                    flatDiv.style.display = 'none';
                }
            }

            // Hide earned profits section when block-after-waiting is enabled
            if (earnedProfitsDiv) {
                if (blockMode === 'yes') {
                    earnedProfitsDiv.style.display = 'none';
                } else {
                    earnedProfitsDiv.style.display = '';
                }
            }
        }

        penaltyRadios.forEach(radio => {
            radio.addEventListener('change', updateCancellationVisibility);
        });

        blockRadios.forEach(radio => {
            radio.addEventListener('change', updateCancellationVisibility);
        });

        updateCancellationVisibility();
    });

    // Bulk toggle all notifications
    function toggleAllNotifications(enable) {
        // Get all notification checkboxes (those with name starting with 'email_')
        const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="email_"]');

        // Toggle each checkbox
        checkboxes.forEach(checkbox => {
            checkbox.checked = enable;
        });

        // Submit all notification forms
        const notificationForms = [
            'email_notifications_account',
            'email_notifications_deposit',
            'email_notifications_withdrawal',
            'email_notifications_investment',
            'email_notifications_kyc',
            'email_notifications_referral'
        ];

        let submittedCount = 0;
        const totalForms = notificationForms.length;

        notificationForms.forEach((section, index) => {
            const form = document.querySelector('input[name="section"][value="' + section + '"]').closest('form');
            if (form) {
                // Use fetch to submit each form asynchronously
                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    submittedCount++;
                    if (submittedCount === totalForms) {
                        // All forms submitted, reload page to show success
                        window.location.reload();
                    }
                }).catch(error => {
                    console.error('Error submitting form:', error);
                    submittedCount++;
                    if (submittedCount === totalForms) {
                        window.location.reload();
                    }
                });
            } else {
                submittedCount++;
            }
        });

        // Show feedback to user
        const message = enable ? <?php echo json_encode(__('Turning on all notifications...')); ?> : <?php echo json_encode(__('Turning off all notifications...')); ?>;

        // Create temporary alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-info alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        alert.style.zIndex = '9999';
        alert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(alert);
    }

    // Withdrawal Methods Management Component
    function withdrawalMethodsApp() {
        return {
            methods: <?php echo json_encode(!empty($withdrawal_methods) && is_array($withdrawal_methods) ? $withdrawal_methods : [
                            'usdt' => ['key' => 'usdt', 'name' => 'USDT', 'type' => 'crypto', 'enabled' => true, 'networks' => ['TRC20', 'ERC20', 'BEP20']],
                            'btc' => ['key' => 'btc', 'name' => 'Bitcoin', 'type' => 'crypto', 'enabled' => true, 'networks' => ['Bitcoin', 'Lightning']],
                            'bank' => ['key' => 'bank', 'name' => 'Bank Transfer', 'type' => 'fiat', 'enabled' => true],
                            'momo' => ['key' => 'momo', 'name' => 'Mobile Money', 'type' => 'momo', 'enabled' => false, 'providers' => ['MTN', 'Airtel', 'M-Pesa', 'Orange', 'Wave', 'Galaxy']],
                            'ewallet' => ['key' => 'ewallet', 'name' => 'E-Wallet', 'type' => 'ewallet', 'enabled' => false]
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,

            getMethodIcon(type) {
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

            getMethodTypeLabel(type) {
                switch (type) {
                    case 'crypto':
                        return '<?php echo __('Cryptocurrency'); ?>';
                    case 'fiat':
                        return '<?php echo __('Bank Transfer'); ?>';
                    case 'momo':
                        return '<?php echo __('Mobile Money'); ?>';
                    case 'ewallet':
                        return '<?php echo __('E-Wallet'); ?>';
                    default:
                        return '<?php echo __('Other'); ?>';
                }
            },

            getNetworksString(key) {
                const method = this.methods[key];
                if (method && Array.isArray(method.networks)) {
                    return method.networks.join(', ');
                }
                return '';
            },

            setNetworksFromString(key, value) {
                const method = this.methods[key];
                if (method) {
                    method.networks = value.split(',').map(s => s.trim()).filter(s => s.length > 0);
                }
            },

            getProvidersString(key) {
                const method = this.methods[key];
                if (method && Array.isArray(method.providers)) {
                    return method.providers.join(', ');
                }
                return '';
            },

            setProvidersFromString(key, value) {
                const method = this.methods[key];
                if (method) {
                    method.providers = value.split(',').map(s => s.trim()).filter(s => s.length > 0);
                }
            }
        };
    }

    // Countries Settings Handler
    async function submitCountriesForm() {
        const wrapper = document.getElementById('countries-settings-component');
        const state = (wrapper && typeof Alpine !== 'undefined') ? Alpine.$data(wrapper) : null;
        const form = document.getElementById('countries-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        const checkboxes = form.querySelectorAll('input[name="accepted_countries[]"]');
        const defaultSelect = form.querySelector('select[name="default_country"]');
        const errorDiv = document.getElementById('countries-error');
        const defaultErrorDiv = document.getElementById('default-country-error');
        const selectedCountries = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
        const defaultCountry = defaultSelect.value;

        // Helper function to reset loading state
        function resetLoadingState() {
            if (state) state.submitting = false;
            if (submitBtn) submitBtn.disabled = false;
        }

        // Set loading state
        if (state) state.submitting = true;
        if (submitBtn) submitBtn.disabled = true;

        // Validate at least one country is checked
        if (selectedCountries.length === 0) {
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
            if (defaultErrorDiv) defaultErrorDiv.style.display = 'none';
            if (state) state.defaultCountryMismatch = false;
            resetLoadingState();
            return;
        }

        if (errorDiv) errorDiv.style.display = 'none';

        // Validate default country is in selected list
        if (defaultCountry && !selectedCountries.includes(defaultCountry)) {
            if (defaultErrorDiv) {
                defaultErrorDiv.style.display = 'block';
                defaultErrorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
            if (state) state.defaultCountryMismatch = true;
            resetLoadingState();
            return;
        }

        if (defaultErrorDiv) defaultErrorDiv.style.display = 'none';
        if (state) state.defaultCountryMismatch = false;

        try {
            ;
            const formData = new FormData(form)
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                if (data.affected && data.affected.length > 0) {
                    if (state) {
                        state.affectedUsers = data.affected;
                        state.showModal = true;
                    }
                    resetLoadingState();
                } else {
                    // Success - no reassignment needed, redirect
                    resetLoadingState();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            } else {
                // Success - redirect
                resetLoadingState();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        } catch (error) {
            console.error('Error submitting countries form:', error);
            resetLoadingState();
            alert('<?php echo __('An error occurred while saving countries settings.'); ?>');
        }
    }

    function proceedReassign() {
        const wrapper = document.getElementById('countries-settings-component');
        const state = (wrapper && typeof Alpine !== 'undefined') ? Alpine.$data(wrapper) : null;
        const form = document.getElementById('countries-form');

        if (state) {
            state.submitting = true;
        }

        const formData = new FormData(form);
        formData.append('confirm_reassign', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (state) {
                state.showModal = false;
                state.submitting = false;
            }
            window.location.reload();
        }).catch(error => {
            console.error('Error reassigning countries:', error);
            alert('<?php echo __('An error occurred while reassigning users.'); ?>');
            if (state) {
                state.submitting = false;
            }
        });
    }
</script>

</div><!-- End settings-content -->
</div><!-- End settings-grid -->
</div><!-- End container-fluid -->

<?php require_once ROOT . '/includes/admin-footer.php'; ?>