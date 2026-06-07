<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT . '/includes/session.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard');
    exit;
}

require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

// Get available languages for the switcher
$languages = get_available_languages();
$current_lang = get_current_language();
$current_lang_label = $languages[$current_lang] ?? 'English';
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

$site_name = get_setting('site_name', 'Investment Platform');
$site_logo = get_setting('site_logo', '');
$site_description = get_setting('site_description', '');
$site_keywords = get_setting('site_keywords', '');
$referral_code = isset($_GET['ref']) ? sanitize_input($_GET['ref']) : '';
$accepted_countries = get_accepted_countries();
$all_countries      = get_countries();
$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);
$require_referral_code = get_setting('require_referral_code', 'no');

$meta_description = isset($page_description) && $page_description ? $page_description : $site_description;
$meta_keywords = isset($page_keywords) && $page_keywords ? $page_keywords : $site_keywords;

// Page title helper
$full_title = 'Create Account - ' . e($site_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $full_title; ?></title>

    <?php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $current_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    $og_image = '';
    if (!empty($favicon_path)) {
        if (filter_var($favicon_path, FILTER_VALIDATE_URL) || preg_match('#^https?://#i', $favicon_path)) {
            $og_image = $favicon_path;
        } else {
            $og_image = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . ltrim($favicon_path, '/');
        }
    }
    ?>

    <?php if (!empty($meta_description)): ?>
        <meta name="description" content="<?php echo e($meta_description); ?>" />
    <?php endif; ?>
    <?php if (!empty($meta_keywords)): ?>
        <meta name="keywords" content="<?php echo e($meta_keywords); ?>" />
    <?php endif; ?>

    <link rel="canonical" href="<?php echo e($current_url); ?>" />
    <meta name="robots" content="index, follow" />

    <meta property="og:site_name" content="<?php echo e($site_name); ?>" />
    <meta property="og:title" content="<?php echo e($full_title); ?>" />
    <?php if (!empty($meta_description)): ?>
        <meta property="og:description" content="<?php echo e($meta_description); ?>" /><?php endif; ?>
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo e($current_url); ?>" />
    <?php if (!empty($og_image)): ?>
        <meta property="og:image" content="<?php echo e($og_image); ?>" /><?php endif; ?>

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo e($full_title); ?>" />
    <?php if (!empty($meta_description)): ?>
        <meta name="twitter:description" content="<?php echo e($meta_description); ?>" /><?php endif; ?>
    <?php if (!empty($og_image)): ?>
        <meta name="twitter:image" content="<?php echo e($og_image); ?>" /><?php endif; ?>

    <link rel="icon" type="image/png" href="<?php echo e(SITE_ICON); ?>">

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Mono:wght@300;400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link href="/assets/css/public-style.css?hash=<?php echo filemtime(ROOT . '/assets/css/public-style.css'); ?>" rel="stylesheet" />

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
</head>

<body class="auth-page">

    <div class="auth-left">
        <div class="auth-left-bg"></div>
        <div class="auth-left-overlay"></div>
        <div class="auth-left-glow"></div>
        <div class="auth-left-inner">
            <?php if ($site_logo && file_exists(ROOT . '/' . $site_logo)): ?>
                <img src="/<?php echo e($site_logo); ?>" alt="<?php echo e($site_name); ?>" style="height:40px; width:auto; margin-bottom:1.5rem; opacity:0.9;">
            <?php else: ?>
                <div class="logo" style="margin-bottom:1.5rem;"><?php echo e($site_name); ?><em>.</em></div>
            <?php endif; ?>
            <h2><?php echo __('Join the'); ?> <em><?php echo __('Revolution'); ?></em></h2>
            <p><?php echo __('Create your account in less than 2 minutes and start building your portfolio today.'); ?></p>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-top-bar">
            <a href="/" class="auth-back" title="<?php echo __('Back to Home'); ?>">←</a>

            <?php if (is_google_translate_enabled()): ?>
                <div style="display:none;"><?php render_google_translate_widget('auth'); ?></div>
            <?php else: ?>
                <div class="relative" x-data="{ langOpen: false }" @click.away="langOpen = false">
                    <form method="POST" action="/actions/switch-language-public.php" id="authLangForm">
                        <input type="hidden" name="redirect" value="<?php echo e($current_page); ?>">
                        <input type="hidden" name="lang" id="authSelectedLang" value="<?php echo e($current_lang); ?>">
                        <button type="button"
                            style="background:none;border:none;color:var(--muted-light);font-family:'DM Mono',monospace;font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;gap:0.4rem;"
                            @click="langOpen = !langOpen">
                            <span><?php echo e($current_lang_label); ?></span>
                            <span style="font-size:0.5rem;transition:transform 0.2s;" :style="langOpen ? 'transform:rotate(180deg)' : ''">▼</span>
                        </button>
                        <div x-show="langOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95 translate-y-2"
                            x-transition:enter-end="opacity-100 transform scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 transform scale-95 translate-y-2"
                            style="display:none;position:absolute;right:0;top:120%;min-width:140px;background:var(--bg-card);border:0.5px solid var(--border);padding:0.4rem 0;z-index:300;"
                            @click="langOpen = false">
                            <?php foreach ($languages as $code => $label): ?>
                                <button type="button"
                                    style="display:block;width:100%;text-align:left;padding:0.4rem 0.8rem;background:none;border:none;color:<?php echo $code === $current_lang ? 'var(--gold)' : 'var(--muted-light)'; ?>;font-family:'Outfit',sans-serif;font-size:0.8rem;cursor:pointer;"
                                    @click="document.getElementById('authSelectedLang').value = '<?php echo e($code); ?>'; document.getElementById('authLangForm').submit();">
                                    <?php echo e($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="auth-form-wrap">
            <div class="auth-card">
                <h2><?php echo __('Create Account'); ?></h2>
                <p class="auth-lead"><?php echo __('Start your journey with us'); ?></p>

                <?php if ($error): ?>
                    <div x-data="{ show: true }" x-show="show" x-transition class="alert alert-red">
                        <span><?php echo e($error); ?></span>
                        <button @click="show = false" class="alert-close">✕</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($accepted_countries)): ?>
                    <form x-data="{ loading: false }" @submit="loading = true" action="/actions/register-submit" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <?php if (!empty($_SESSION['redirect_after_login'])): ?>
                            <input type="hidden" name="redirect" value="<?php echo e($_SESSION['redirect_after_login']); ?>">
                        <?php endif; ?>

                        <div class="auth-row">
                            <div class="auth-ff">
                                <label><?php echo __('First Name'); ?></label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="auth-ff">
                                <label><?php echo __('Last Name'); ?></label>
                                <input type="text" name="last_name" required>
                            </div>
                        </div>

                        <div class="auth-ff">
                            <label><?php echo __('Email Address'); ?></label>
                            <input type="email" name="email" required>
                        </div>

                        <div class="auth-ff">
                            <label><?php echo __('Country'); ?></label>
                            <select name="country" required>
                                <option value=""><?php echo __('Select your country'); ?></option>
                                <?php foreach ($accepted_countries as $code):
                                    $name     = $all_countries[$code] ?? $code;
                                    $flag     = mb_chr(0x1F1E6 + (ord(strtoupper($code[0])) - ord('A')))
                                        . mb_chr(0x1F1E6 + (ord(strtoupper($code[1])) - ord('A')));
                                    $selected = (isset($_POST['country']) && $_POST['country'] === $code) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo e($code); ?>" <?php echo $selected; ?>>
                                        <?php echo $flag . ' ' . e($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="hint">🔒 <?php echo __('Cannot be changed after registration'); ?></p>
                        </div>

                        <div class="auth-ff">
                            <label><?php echo __('Password'); ?></label>
                            <div class="input-wrap" x-data="{ show: false }">
                                <input :type="show ? 'text' : 'password'" name="password" required minlength="6">
                                <button type="button" @click="show = !show" class="toggle-pass" aria-label="Toggle password">
                                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg x-show="show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p class="hint"><?php echo __('Minimum 6 characters'); ?></p>
                        </div>

                        <div class="auth-ff">
                            <label><?php echo __('Confirm Password'); ?></label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>

                        <div class="auth-ff">
                            <label><?php echo $require_referral_code === 'yes' ? __('Referral Code (Required)') : __('Referral Code (Optional)'); ?></label>
                            <div class="input-wrap">
                                <span class="input-icon">🎁</span>
                                <input type="text" name="referral_code" value="<?php echo e($referral_code); ?>" <?php echo $referral_code ? 'readonly' : ''; ?> <?php echo $require_referral_code === 'yes' ? 'required' : ''; ?>>
                            </div>
                        </div>

                        <div class="auth-check">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                <?php echo __('I agree to the'); ?> <a href="/terms"><?php echo __('Terms of Service'); ?></a> & <a href="/privacy"><?php echo __('Privacy Policy'); ?></a>
                            </label>
                        </div>

                        <button type="submit" :disabled="loading" class="auth-btn">
                            <span x-show="!loading"><?php echo __('Create Account'); ?></span>
                            <span x-show="loading" style="display:none;"><?php echo __('Processing…'); ?></span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-yellow">
                        <span><?php echo __('Registration is currently unavailable. Please contact support.'); ?></span>
                    </div>
                <?php endif; ?>

                <p class="auth-footer-text">
                    <?php echo __('Already have an account?'); ?> <a href="/login"><?php echo __('Sign in here'); ?></a>
                </p>
            </div>
        </div>
    </div>

    <?php require_once ROOT . '/includes/auth-footer.php'; ?>

</body>

</html>