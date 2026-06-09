<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Helper functions for the investment platform
 */

/**
 * Get all settings from database
 * @return array Associative array of settings
 */
function get_all_settings()
{
    // Check if settings are already cached
    if (!isset($GLOBALS['settings'])) {
        try {
            $settings_data = db_query("SELECT setting_key, setting_value FROM settings");
            $GLOBALS['settings'] = [];
            foreach ($settings_data as $row) {
                $GLOBALS['settings'][$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Failed to load settings: " . $e->getMessage());
            $GLOBALS['settings'] = [];
        }
    }

    return $GLOBALS['settings'];
}

/**
 * Get a specific setting value
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function get_setting($key, $default = null)
{
    $settings = get_all_settings();
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Get the public URL for the site logo.
 *
 * Checks the 'site_logo' setting and returns a full URL to the configured logo
 * if the file exists inside the project. If the setting contains an absolute
 * URL (http/https) that URL is returned as-is. Otherwise the function falls
 * back to a default asset path.
 *
 * Behavior mirrors:
 *   $site_logo = get_setting('site_logo', '');
 *   $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../' . $site_logo))
 *       ? $site_url . '/' . ltrim($site_logo, '/')
 *       : $site_url . '/assets/images/logo.png';
 *
 * @param string $fallback_path Relative fallback path (default: '/assets/images/logo.png')
 * @return string Full URL to the site logo
 */
function get_site_logo_url(string $fallback_path = '/assets/images/logo.png'): string
{
    $site_logo = get_setting('site_logo', '');
    $site_url = get_site_url();

    if (!empty($site_logo)) {
        // If it's already a full URL, return it
        if (preg_match('#^https?://#i', $site_logo)) {
            return $site_logo;
        }

        $relative = ltrim($site_logo, '/');
        $file_path = __DIR__ . '/../' . $relative;

        if (file_exists($file_path)) {
            return rtrim($site_url, '/') . '/' . $relative;
        }
    }

    return rtrim($site_url, '/') . '/' . ltrim($fallback_path, '/');
}

/**
 * Update a setting value
 * @param string $key Setting key
 * @param mixed $value New value
 * @return bool Success status
 */
function update_setting($key, $value)
{
    try {
        // Check if setting exists
        $existing = db_query("SELECT id FROM settings WHERE setting_key = ?", [$key]);

        if ($existing) {
            // Update existing setting
            db_update('settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'setting_key = ?', [$key]);
        } else {
            // Insert new setting
            db_insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Clear cached settings
        unset($GLOBALS['settings']);
        // Also clear cached currency code so changes take effect immediately
        if (isset($GLOBALS['currency_code'])) {
            unset($GLOBALS['currency_code']);
        }
        return true;
    } catch (Exception $e) {
        error_log("Failed to update setting $key: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool Verification result
 */
function verify_csrf($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape output for HTML
 * @param string $text Text to escape
 * @return string Escaped text
 */
function e($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a translated string safely.
 *
 * Supports both sprintf-style placeholders (e.g. "%s") and named tokens
 * using the %name% pattern (e.g. "%minutes%"). If the translated string
 * contains sprintf tokens, `vsprintf` is used. Otherwise named tokens are
 * replaced from an associative array or (for a single scalar) the first
 * %...% token is replaced.
 *
 * @param string $template Translated template string
 * @param mixed $args Scalar or array of replacement values
 * @return string Formatted string
 */
function format_translated($template, $args)
{
    $template = (string)$template;

    // Normalize args
    if (!is_array($args)) {
        $args_arr = [$args];
    } else {
        $args_arr = $args;
    }

    // If template contains sprintf-like placeholders, prefer vsprintf
    if (preg_match('/%[0-9\$]*[bcdeEufFgGosxX]/', $template)) {
        // Ensure numeric-indexed array for vsprintf
        $vals = array_values($args_arr);
        $result = @vsprintf($template, $vals);
        if ($result !== false) {
            return $result;
        }
        // fallback to original template on failure
        return $template;
    }

    // Named token replacement: look for %name% patterns
    // If associative array provided, replace matching keys
    $has_named = preg_match_all('/%([a-zA-Z0-9_]+)%/', $template, $matches);
    if ($has_named && is_array($args)) {
        // If args is associative, replace keys
        $is_assoc = array_keys($args_arr) !== range(0, count($args_arr) - 1);
        if ($is_assoc) {
            foreach ($args_arr as $k => $v) {
                $template = str_replace('%' . $k . '%', $v, $template);
            }
            return $template;
        }
    }

    // If template has named tokens and we have a single scalar arg, replace the first token
    if ($has_named && count($args_arr) === 1) {
        $value = $args_arr[0];
        // Replace all occurrences of the first found token
        $first_token = $matches[0][0] ?? null; // e.g. "%minutes%"
        if ($first_token) {
            return str_replace($first_token, $value, $template);
        }
    }

    // Nothing matched; if single scalar, append it
    if (count($args_arr) === 1) {
        return $template . ' ' . $args_arr[0];
    }

    return $template;
}

/**
 * Format date
 * @param string $datetime Datetime string
 * @param string $format Date format
 * @return string Formatted date
 */
function format_date($datetime, $format = 'Y-m-d H:i:s')
{
    return date($format, strtotime($datetime));
}

/**
 * Get human-readable time difference
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function time_ago($datetime)
{
    // If input is a numeric string or integer, treat as Unix timestamp
    if (is_numeric($datetime)) {
        $time = (int)$datetime;
    } else {
        // Otherwise, parse as a date string
        $time = strtotime($datetime);
        if ($time === false) {
            return __('Unknown');
        }
    }

    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return $diff . " seconds ago";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . " days ago";
    } elseif ($diff < 31536000) {
        return floor($diff / 2592000) . " months ago";
    } else {
        return floor($diff / 31536000) . " years ago";
    }
}

/**
 * Format money amount with intelligent currency handling.
 * Accepts either a currency symbol (e.g. '$') or an ISO 4217 code (e.g. 'USD').
 * If `$currency` is null the admin-configured currency is used.
 * @param float $amount Amount to format
 * @param string|null $currency Currency symbol or ISO 4217 code (e.g., '$', 'USD', 'EUR'). If null, uses admin-configured currency.
 * @return string Formatted money string
 */
function format_money($amount, $currency = null)
{
    // Determine symbol to use
    if ($currency === null) {
        // If multicurrency is enabled and a user is logged in, use their local currency
        if (is_multicurrency_enabled() && isset($_SESSION['user_id'])) {
            $symbol = get_currency_symbol(get_user_currency_code());
        } else {
            $symbol = get_currency_symbol();
        }
    } else {
        // If currency looks like a 3-letter code, convert to symbol
        if (preg_match('/^[A-Z]{3}$/i', $currency)) {
            $symbol = get_currency_symbol($currency);
        } else {
            // Assume it's already a symbol
            $symbol = $currency;
        }
    }

    return $symbol . number_format((float)$amount, 2);
}

/**
 * Format percentage value
 * @param float $value Percentage value
 * @return string Formatted percentage
 */
function format_percentage($value)
{
    return number_format($value, 2) . '%';
}

/**
 * Get the configured currency code from settings.
 * Cached in $GLOBALS['currency_code'] to avoid repeated DB queries.
 * @return string Uppercase ISO 4217 currency code (e.g., 'USD')
 */
function get_currency_code()
{
    if (isset($GLOBALS['currency_code'])) {
        return $GLOBALS['currency_code'];
    }

    $code = get_setting('currency', 'USD');
    $code = is_string($code) ? strtoupper($code) : 'USD';
    $GLOBALS['currency_code'] = $code;
    return $code;
}

/**
 * Check whether multicurrency support is enabled in settings.
 * @return bool True if 'multicurrency_enabled' is set to 'yes'
 */
function is_multicurrency_enabled(): bool
{
    return get_setting('multicurrency_enabled', 'no') === 'yes';
}

/**
 * Get the preferred currency code for a user based on their country.
 * Falls back to the admin-configured currency if multicurrency is off
 * or the user has no country mapping.
 *
 * @param int|null $user_id User ID. If null, uses the currently logged-in user.
 * @return string ISO 4217 currency code
 */
function get_user_currency_code($user_id = null)
{
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    if (is_multicurrency_enabled() && $user_id) {
        $user = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user && !empty($user['country'])) {
            $local = get_user_local_currency($user['country']);
            if ($local) {
                return $local;
            }
        }
    }

    return get_currency_code();
}

/**
 * Get all currencies with their symbols and country codes.
 * Cached in $GLOBALS['currencies'] to avoid rebuilding on repeated calls.
 *
 * Each entry is keyed by ISO 4217 code and contains:
 * - 'symbol' — display symbol (e.g. '$', '€', '₦')
 * - 'country_code' — 2-letter lowercase country/region code for flag CDN (e.g. 'us', 'ng', 'eu')
 *
 * @return array Associative array of currencies
 */
function get_currencies(): array
{
    if (isset($GLOBALS['currencies'])) {
        return $GLOBALS['currencies'];
    }

    $GLOBALS['currencies'] = [
        'USD' => ['symbol' => '$', 'country_code' => 'us'],
        'EUR' => ['symbol' => '€', 'country_code' => 'eu'],
        'GBP' => ['symbol' => '£', 'country_code' => 'gb'],
        'JPY' => ['symbol' => '¥', 'country_code' => 'jp'],
        'CNY' => ['symbol' => '¥', 'country_code' => 'cn'],
        'INR' => ['symbol' => '₹', 'country_code' => 'in'],
        'AUD' => ['symbol' => 'A$', 'country_code' => 'au'],
        'CAD' => ['symbol' => 'C$', 'country_code' => 'ca'],
        'CHF' => ['symbol' => 'CHF', 'country_code' => 'ch'],
        'SEK' => ['symbol' => 'kr', 'country_code' => 'se'],
        'NOK' => ['symbol' => 'kr', 'country_code' => 'no'],
        'DKK' => ['symbol' => 'kr', 'country_code' => 'dk'],
        'RUB' => ['symbol' => '₽', 'country_code' => 'ru'],
        'BRL' => ['symbol' => 'R$', 'country_code' => 'br'],
        'ZAR' => ['symbol' => 'R', 'country_code' => 'za'],
        'MXN' => ['symbol' => '$', 'country_code' => 'mx'],
        'SGD' => ['symbol' => 'S$', 'country_code' => 'sg'],
        'HKD' => ['symbol' => 'HK$', 'country_code' => 'hk'],
        'NZD' => ['symbol' => 'NZ$', 'country_code' => 'nz'],
        'KRW' => ['symbol' => '₩', 'country_code' => 'kr'],
        'TRY' => ['symbol' => '₺', 'country_code' => 'tr'],
        'PLN' => ['symbol' => 'zł', 'country_code' => 'pl'],
        'THB' => ['symbol' => '฿', 'country_code' => 'th'],
        'BTC' => ['symbol' => '₿', 'country_code' => 'xx'],
        'IDR' => ['symbol' => 'Rp', 'country_code' => 'id'],
        'MYR' => ['symbol' => 'RM', 'country_code' => 'my'],
        'PHP' => ['symbol' => '₱', 'country_code' => 'ph'],
        'VND' => ['symbol' => '₫', 'country_code' => 'vn'],
        'AED' => ['symbol' => 'د.إ', 'country_code' => 'ae'],
        'SAR' => ['symbol' => '﷼', 'country_code' => 'sa'],
        'EGP' => ['symbol' => 'E£', 'country_code' => 'eg'],
        'NGN' => ['symbol' => '₦', 'country_code' => 'ng'],
        'KES' => ['symbol' => 'KSh', 'country_code' => 'ke'],
        'GHS' => ['symbol' => 'GH₵', 'country_code' => 'gh'],
        'CUP' => ['symbol' => '$', 'country_code' => 'cu'],
        'GMD' => ['symbol' => 'D', 'country_code' => 'gm'],
        'XOF' => ['symbol' => 'CFA', 'country_code' => 'sn'],
        'XAF' => ['symbol' => 'FCFA', 'country_code' => 'cm'],
        'TZS' => ['symbol' => 'TSh', 'country_code' => 'tz'],
        'UGX' => ['symbol' => 'USh', 'country_code' => 'ug'],
        'ETB' => ['symbol' => 'Br', 'country_code' => 'et'],
        'AOA' => ['symbol' => 'Kz', 'country_code' => 'ao'],
        'MZN' => ['symbol' => 'MT', 'country_code' => 'mz'],
        'BWP' => ['symbol' => 'P', 'country_code' => 'bw'],
        'NAD' => ['symbol' => '$', 'country_code' => 'na'],
        'MUR' => ['symbol' => '₨', 'country_code' => 'mu'],
        'ZMW' => ['symbol' => 'ZK', 'country_code' => 'zm'],
        'ZWL' => ['symbol' => 'Z$', 'country_code' => 'zw'],
        'LSL' => ['symbol' => 'L', 'country_code' => 'ls'],
        'SZL' => ['symbol' => 'E', 'country_code' => 'sz'],
        'SLL' => ['symbol' => 'Le', 'country_code' => 'sl'],
        'LRD' => ['symbol' => '$', 'country_code' => 'lr'],
        'GNF' => ['symbol' => 'FG', 'country_code' => 'gn'],
        'CDF' => ['symbol' => 'FC', 'country_code' => 'cd'],
        'RWF' => ['symbol' => 'FRw', 'country_code' => 'rw'],
        'BIF' => ['symbol' => 'FBu', 'country_code' => 'bi'],
        'SSP' => ['symbol' => '£', 'country_code' => 'ss'],
        'SDG' => ['symbol' => '£', 'country_code' => 'sd'],
        'MRU' => ['symbol' => 'UM', 'country_code' => 'mr'],
        'MAD' => ['symbol' => 'د.م.', 'country_code' => 'ma'],
        'DZD' => ['symbol' => 'د.ج', 'country_code' => 'dz'],
        'TND' => ['symbol' => 'د.ت', 'country_code' => 'tn'],
        'LYD' => ['symbol' => 'ل.د', 'country_code' => 'ly'],
        'DJF' => ['symbol' => 'Fdj', 'country_code' => 'dj'],
        'ERN' => ['symbol' => 'Nfk', 'country_code' => 'er'],
        'SOS' => ['symbol' => 'Sh', 'country_code' => 'so'],
        'MWK' => ['symbol' => 'MK', 'country_code' => 'mw'],
        'SCR' => ['symbol' => '₨', 'country_code' => 'sc'],
        'CVE' => ['symbol' => '$', 'country_code' => 'cv'],
        'STN' => ['symbol' => 'Db', 'country_code' => 'st'],
        'KMF' => ['symbol' => 'CF', 'country_code' => 'km'],
    ];

    return $GLOBALS['currencies'];
}

/**
 * Get all countries as an alphabetically-sorted array of ISO 2-code => Country Name.
 * Cached in $GLOBALS['countries'] to avoid rebuilding on repeated calls.
 *
 * @return array Associative array of ['ISO_2_CODE' => 'Country Name']
 */
function get_countries(): array
{
    if (isset($GLOBALS['countries'])) {
        return $GLOBALS['countries'];
    }

    $countries = [
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'DE' => 'Germany',
        'FR' => 'France',
        'NG' => 'Nigeria',
        'ZA' => 'South Africa',
        'KE' => 'Kenya',
        'GH' => 'Ghana',
        'IN' => 'India',
        'BR' => 'Brazil',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'EG' => 'Egypt',
        'JP' => 'Japan',
        'CN' => 'China',
        'SG' => 'Singapore',
        'MY' => 'Malaysia',
        'ID' => 'Indonesia',
        'TH' => 'Thailand',
        'PH' => 'Philippines',
        'VN' => 'Vietnam',
        'KR' => 'South Korea',
        'RU' => 'Russia',
        'TR' => 'Turkey',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'IE' => 'Ireland',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'NO' => 'Norway',
        'NZ' => 'New Zealand',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'EC' => 'Ecuador',
        'UY' => 'Uruguay',
        'PY' => 'Paraguay',
        'BO' => 'Bolivia',
        'TW' => 'Taiwan',
        'HK' => 'Hong Kong',
        'CU' => 'Cuba',
        'GM' => 'Gambia',
        'SN' => 'Senegal',
        'ML' => 'Mali',
        'CI' => 'Côte d\'Ivoire',
        'CM' => 'Cameroon',
        'TZ' => 'Tanzania',
        'UG' => 'Uganda',
        'ET' => 'Ethiopia',
        'AO' => 'Angola',
        'MZ' => 'Mozambique',
        'BW' => 'Botswana',
        'NA' => 'Namibia',
        'MU' => 'Mauritius',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'LS' => 'Lesotho',
        'SZ' => 'Eswatini',
        'SL' => 'Sierra Leone',
        'LR' => 'Liberia',
        'GN' => 'Guinea',
        'BJ' => 'Benin',
        'TG' => 'Togo',
        'BF' => 'Burkina Faso',
        'NE' => 'Niger',
        'TD' => 'Chad',
        'CG' => 'Congo',
        'CD' => 'Democratic Republic of Congo',
        'GA' => 'Gabon',
        'RW' => 'Rwanda',
        'BI' => 'Burundi',
        'SS' => 'South Sudan',
        'SD' => 'Sudan',
        'MR' => 'Mauritania',
        'MA' => 'Morocco',
        'DZ' => 'Algeria',
        'TN' => 'Tunisia',
        'LY' => 'Libya',
        'DJ' => 'Djibouti',
        'ER' => 'Eritrea',
        'SO' => 'Somalia',
        'MW' => 'Malawi',
        'SC' => 'Seychelles',
        'CV' => 'Cape Verde',
        'ST' => 'São Tomé and Príncipe',
        'KM' => 'Comoros',
    ];

    // Sort by country name
    asort($countries);

    $GLOBALS['countries'] = $countries;
    return $countries;
}

/**
 * Map an ISO 4217 currency code to a 2-letter country/region code usable for flag icons.
 * If the currency is not recognized this function will return a best-effort fallback
 * (the first two letters of the currency code, lowercased).
 *
 * Examples:
 *  - 'USD' => 'us'
 *  - 'NGN' => 'ng'
 *  - 'EUR' => 'eu'
 *
 * @param string $currency_code ISO 4217 currency code or mixed input (e.g. 'USD')
 * @return string 2-letter country/region code (lowercase) suitable for flag CDN paths
 */
function get_country_code_from_currency($currency_code)
{
    if (empty($currency_code) || !is_string($currency_code)) {
        return 'us';
    }

    $code = strtoupper(trim($currency_code));

    $currencies = get_currencies();

    if (isset($currencies[$code])) {
        return $currencies[$code]['country_code'];
    }

    // Fallback: use first two alphabetic characters of the code
    preg_match('/[A-Z]{2}/', $code, $m);
    if (!empty($m[0])) {
        return strtolower($m[0]);
    }

    return strtolower(substr($code, 0, 2));
}

/**
 * Return a flag image URL for a given currency code or a 2-letter country code.
 * This provides a convenient helper for templates that previously used
 * `getCountryIconFromCountryCode()` and expects a FlagCDN URL.
 *
 * @param string $currency_or_country Either an ISO 4217 currency code (e.g. 'USD')
 *                                     or a 2-letter country code (e.g. 'us').
 * @return string Full URL to 48x36 flag image on flagcdn.com
 */
function get_country_flag_url_from_currency($currency_or_country)
{
    $input = trim((string)$currency_or_country);

    // If it's a 2-letter country code already, use it directly
    if (preg_match('/^[A-Za-z]{2}$/', $input)) {
        $country = strtolower($input);
    } else {
        $country = get_country_code_from_currency($input);
    }

    return "https://flagcdn.com/48x36/{$country}.png";
}

/**
 * Map an ISO 4217 currency code to a display symbol.
 * If the provided code is not recognized, the code itself is returned.
 * Handles all codes defined in get_currencies() gracefully.
 *
 * @param string|null $currency_code Currency code (e.g., 'USD'). If null, uses configured currency.
 * @return string Currency symbol or fallback code
 */
function get_currency_symbol($currency_code = null)
{
    $code = $currency_code === null ? get_currency_code() : (is_string($currency_code) ? strtoupper($currency_code) : (string)$currency_code);

    $currencies = get_currencies();

    if (isset($currencies[$code])) {
        return $currencies[$code]['symbol'];
    }

    return $code;
}

/**
 * Format payout interval display with proper singular/plural forms
 * @param string $interval_type Type: 'minutes', 'hours', 'days', 'weeks', 'months'
 * @param int $interval_value Numeric value (e.g., 2 for "every 2 days")
 * @param float $roi_percentage ROI percentage to include in display
 * @param bool $include_roi Whether to include ROI in the output
 * @return string Formatted interval string
 */
function format_payout_interval($interval_type, $interval_value, $roi_percentage = null, $include_roi = false)
{
    $value = intval($interval_value);

    // Determine singular/plural unit name
    switch (strtolower($interval_type)) {
        case 'minutes':
            $unit = $value === 1 ? __('minute') : __('minutes');
            break;
        case 'hours':
            $unit = $value === 1 ? __('hour') : __('hours');
            break;
        case 'days':
            $unit = $value === 1 ? __('day') : __('days');
            break;
        case 'weeks':
            $unit = $value === 1 ? __('week') : __('weeks');
            break;
        case 'months':
            $unit = $value === 1 ? __('month') : __('months');
            break;
        default:
            $unit = $value === 1 ? __('day') : __('days');
    }

    if ($include_roi && $roi_percentage !== null) {
        return sprintf(__('%s%% every %d %s'), $roi_percentage, $value, $unit);
    }

    return sprintf(__('every %d %s'), $value, $unit);
}


/**
 * Debug helper: print one or more values and terminate execution.
 *
 * Accepts multiple arguments (variadic). Each argument is printed in a
 * readable format similar to a spread debug output. Useful for quick
 * inspection during development.
 *
 * Examples:
 *   show($var);
 *   show($a, $b, $c);
 *
 * @param mixed ...$data One or more values to dump
 * @return void
 */
function show(mixed ...$data): void
{
    echo "<pre>";

    if (empty($data)) {
        var_dump(null);
        echo "</pre>";
        die;
    }

    foreach ($data as $i => $item) {
        echo "[{$i}] ";
        if (is_scalar($item) || $item === null) {
            var_export($item);
        } else {
            print_r($item);
        }
        if ($i !== array_key_last($data)) {
            echo "\n\n";
        }
    }

    echo "</pre>";
    die;
}

/**
 * Get a valid redirect URL after login
 * 
 * Validates the intended URL to prevent open redirect vulnerabilities
 * and ensures the target file exists before redirecting.
 * 
 * @param string|null $intended_url The URL stored in session
 * @param string $user_role The user's role (admin or user)
 * @return string Safe redirect URL
 */
function get_valid_redirect_url(?string $intended_url, string $user_role = 'user'): string
{
    // Default redirects based on role
    $default_redirect = $user_role === 'admin' ? '/admin/dashboard' : '/user/dashboard';

    // If no intended URL, return default
    if (empty($intended_url)) {
        return $default_redirect;
    }

    // Parse the URL
    $parsed = parse_url($intended_url);

    // Reject URLs with scheme or host (prevent external redirects)
    if (isset($parsed['scheme']) || isset($parsed['host'])) {
        return $default_redirect;
    }

    // Get the path
    $path = $parsed['path'] ?? '/';

    // Sanitize the path
    $path = filter_var($path, FILTER_SANITIZE_URL);

    // Remove any directory traversal attempts
    $path = str_replace(['..', '//'], '', $path);

    // Ensure path starts with /
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    // List of valid user pages (whitelist approach) - Pretty URLs (no .php extension)
    $valid_user_pages = [
        '/user/dashboard',
        '/user/invest',
        '/user/my-investments',
        '/user/transactions',
        '/user/deposit',
        '/user/withdraw',
        '/user/profile',
        '/user/referrals',
        '/user/tickets',
        '/user/view-kyc',
        '/user/view-proof',
        // Legacy redirects (for backwards compatibility)
        '/user/dashboard.php',
        '/user/invest.php',
        '/user/my-investments.php',
        '/user/transactions.php',
        '/user/deposit.php',
        '/user/withdraw.php',
        '/user/profile.php',
        '/user/referrals.php',
    ];

    // List of valid admin pages - Pretty URLs (no .php extension)
    $valid_admin_pages = [
        '/admin/dashboard',
        '/admin/pending-deposits',
        '/admin/pending-withdrawals',
        '/admin/users',
        '/admin/plans',
        '/admin/kyc-review',
        '/admin/settings',
        '/admin/view-kyc',
        // Legacy with .php
        '/admin/dashboard.php',
        '/admin/pending-deposits.php',
        '/admin/pending-withdrawals.php',
        '/admin/users.php',
        '/admin/plans.php',
        '/admin/kyc-review.php',
        '/admin/settings.php',
        '/admin/view-kyc.php',
    ];

    // Select appropriate whitelist based on role
    $valid_pages = $user_role === 'admin'
        ? array_merge($valid_user_pages, $valid_admin_pages)
        : $valid_user_pages;

    // Check if the exact path is in whitelist
    if (in_array($path, $valid_pages, true)) {
        // Check if file actually exists (could be pretty URL or .php file)
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        $php_path = $file_path . '.php';

        if (file_exists($file_path) || file_exists($php_path)) {
            // Reconstruct URL with query string if present
            $redirect = $path;
            if (isset($parsed[PHP_URL_QUERY]) && !empty($parsed[PHP_URL_QUERY])) {
                $redirect .= '?' . (string)$parsed[PHP_URL_QUERY];
            }
            return $redirect;
        }
    }

    // Check if it's an index.php in a directory
    $index_path = rtrim($path, '/') . '/index.php';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $index_path)) {
        return $index_path . (isset($parsed[PHP_URL_QUERY]) ? '?' . (string)$parsed[PHP_URL_QUERY] : '');
    }

    // If all checks fail, return default
    return $default_redirect;
}

/**
 * Get the configured site URL from environment
 * Used for building absolute URLs in email templates
 * @return string Site URL (e.g., https://example.com)
 */
function get_site_url()
{
    // Load env if not already loaded
    if (empty($_ENV['SITE_URL'])) {
        load_env();
    }

    $site_url = $_ENV['SITE_URL'] ?? '';

    // Fallback to HTTP_HOST if SITE_URL not set
    if (empty($site_url)) {
        $site_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    // Remove trailing slash for consistency
    return rtrim($site_url, '/');
}

/**
 * Build an absolute URL from a path
 * @param string $path Path to append (e.g., /user/dashboard)
 * @return string Full URL (e.g., https://example.com/user/dashboard)
 */
function site_url($path = '')
{
    $base = get_site_url();
    $path = ltrim($path, '/');
    return $path ? $base . '/' . $path : $base;
}


/**
 * Get client IP address (single place to adjust proxy handling later)
 * @return string IP address
 */
function get_client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


/**
 * Check whether an IP is currently rate-limited for login attempts.
 * @param string $ip
 * @param int $max_attempts
 * @param int $window_minutes
 * @return bool
 */
function is_login_rate_limited($ip, $max_attempts = 5, $window_minutes = 15)
{
    try {
        $row = db_query("SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)", [$ip, $window_minutes]);
        if ($row && isset($row[0]['cnt'])) {
            return (int)$row[0]['cnt'] >= $max_attempts;
        }
    } catch (Exception $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
    }

    return false;
}


/**
 * Return remaining lockout seconds for the given IP based on the oldest attempt in the window.
 * @param string $ip
 * @param int $window_minutes
 * @return int Remaining seconds (minimum 1) or 0 if not applicable
 */
function get_login_lockout_remaining($ip, $window_minutes = 15)
{
    try {
        $row = db_query("SELECT MIN(attempted_at) AS oldest FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)", [$ip, $window_minutes]);
        if ($row && !empty($row[0]['oldest'])) {
            $oldest = $row[0]['oldest'];
            $elapsed = time() - strtotime($oldest);
            $remaining = ($window_minutes * 60) - $elapsed;
            return $remaining > 0 ? (int)$remaining : 0;
        }
    } catch (Exception $e) {
        error_log('Lockout remaining check failed: ' . $e->getMessage());
    }

    return 0;
}


/**
 * Record a failed login attempt for ip+email
 * @param string $ip
 * @param string $email
 * @return int|false Insert ID or false
 */
function record_failed_login($ip, $email)
{
    try {
        return db_insert('login_attempts', ['ip' => $ip, 'email' => $email, 'attempted_at' => date('Y-m-d H:i:s')]);
    } catch (Exception $e) {
        error_log('Failed to record login attempt: ' . $e->getMessage());
        return false;
    }
}


/**
 * Clear login attempts for an IP or email (call on successful authentication)
 * @param string $ip
 * @param string $email
 * @return int Number of deleted rows
 */
function clear_login_attempts($ip, $email)
{
    try {
        // Only clear attempts that match both the IP and the email to avoid
        // wiping attempts for other users sharing the same IP.
        return db_delete('login_attempts', 'ip = ? AND email = ?', [$ip, $email]);
    } catch (Exception $e) {
        error_log('Failed to clear login attempts: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get country to currency mapping
 * @return array Associative array of ISO-2 country code => ISO-3 currency code
 */
function get_country_currency_map()
{
    if (!isset($GLOBALS['country_currency_map'])) {
        $GLOBALS['country_currency_map'] = [
            'US' => 'USD',
            'GB' => 'GBP',
            'CA' => 'CAD',
            'AU' => 'AUD',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'NG' => 'NGN',
            'ZA' => 'ZAR',
            'KE' => 'KES',
            'GH' => 'GHS',
            'IN' => 'INR',
            'BR' => 'BRL',
            'AE' => 'AED',
            'SA' => 'SAR',
            'EG' => 'EGP',
            'JP' => 'JPY',
            'CN' => 'CNY',
            'SG' => 'SGD',
            'MY' => 'MYR',
            'ID' => 'IDR',
            'TH' => 'THB',
            'PH' => 'PHP',
            'VN' => 'VND',
            'KR' => 'KRW',
            'RU' => 'RUB',
            'TR' => 'TRY',
            'ES' => 'EUR',
            'IT' => 'EUR',
            'NL' => 'EUR',
            'BE' => 'EUR',
            'SE' => 'SEK',
            'CH' => 'CHF',
            'AT' => 'EUR',
            'PL' => 'PLN',
            'PT' => 'EUR',
            'IE' => 'EUR',
            'DK' => 'DKK',
            'FI' => 'EUR',
            'NO' => 'NOK',
            'NZ' => 'NZD',
            'MX' => 'MXN',
            'AR' => 'ARS',
            'CL' => 'CLP',
            'CO' => 'COP',
            'PE' => 'PEN',
            'VE' => 'VES',
            'EC' => 'USD',
            'UY' => 'UYU',
            'PY' => 'PYG',
            'BO' => 'BOB',
            'TW' => 'TWD',
            'HK' => 'HKD',
            'CU' => 'CUP',
            'GM' => 'GMD',
            'SN' => 'XOF',
            'ML' => 'XOF',
            'CI' => 'XOF',
            'CM' => 'XAF',
            'TZ' => 'TZS',
            'UG' => 'UGX',
            'ET' => 'ETB',
            'AO' => 'AOA',
            'MZ' => 'MZN',
            'BW' => 'BWP',
            'NA' => 'NAD',
            'MU' => 'MUR',
            'ZM' => 'ZMW',
            'ZW' => 'ZWL',
            'LS' => 'LSL',
            'SZ' => 'SZL',
            'SL' => 'SLL',
            'LR' => 'LRD',
            'GN' => 'GNF',
            'BJ' => 'XOF',
            'TG' => 'XOF',
            'BF' => 'XOF',
            'NE' => 'XOF',
            'TD' => 'XAF',
            'CG' => 'XAF',
            'CD' => 'CDF',
            'GA' => 'XAF',
            'RW' => 'RWF',
            'BI' => 'BIF',
            'SS' => 'SSP',
            'SD' => 'SDG',
            'MR' => 'MRU',
            'MA' => 'MAD',
            'DZ' => 'DZD',
            'TN' => 'TND',
            'LY' => 'LYD',
            'DJ' => 'DJF',
            'ER' => 'ERN',
            'SO' => 'SOS',
            'MW' => 'MWK',
            'SC' => 'SCR',
            'CV' => 'CVE',
            'ST' => 'STN',
            'KM' => 'KMF'
        ];
    }

    return $GLOBALS['country_currency_map'];
}

/**
 * Get local currency for a country
 * @param string $country_code ISO-2 country code
 * @return string|null ISO-3 currency code, or null if not found
 */
function get_user_local_currency($country_code)
{
    $map = get_country_currency_map();
    $country_code = strtoupper(trim($country_code));
    return isset($map[$country_code]) ? $map[$country_code] : null;
}

/**
 * Get exchange rates from cache
 * @return array Exchange rates array (keys: 'base', 'rates', 'timestamp'), or [] on failure
 */
function get_exchange_rates()
{
    if (isset($GLOBALS['exchange_rates'])) {
        return $GLOBALS['exchange_rates'];
    }

    $cache_file = ROOT . '/cache/exchange-rates.php';

    if (!file_exists($cache_file)) {
        $GLOBALS['exchange_rates'] = [];
        return [];
    }

    // Primary load attempt
    $result = @include $cache_file;
    if (is_array($result)) {
        $GLOBALS['exchange_rates'] = $result;
        return $result;
    }

    // Handle serialized string results from include
    if (is_string($result)) {
        $unserialized = @unserialize($result);
        if (is_array($unserialized)) {
            $GLOBALS['exchange_rates'] = $unserialized;
            return $unserialized;
        }
    }

    // Fallback: read raw file contents and unserialize
    $raw = @file_get_contents($cache_file);
    if ($raw !== false) {
        $result = @unserialize($raw);
        if (is_array($result)) {
            $GLOBALS['exchange_rates'] = $result;
            return $result;
        }
    }

    $GLOBALS['exchange_rates'] = [];
    return [];
}

/**
 * Get exchange rate for a specific currency (raw string, no float cast).
 *
 * This preserves exact decimal precision for MySQL DECIMAL arithmetic.
 *
 * @param string $currency_code ISO-3 currency code (e.g. 'NGN')
 * @return string|null Rate as a string, or null if not found
 */
function get_rate_for_currency_raw($currency_code)
{
    $rates = get_exchange_rates();
    $currency_code = strtoupper(trim($currency_code));

    if (!empty($rates) && isset($rates['rates'][$currency_code])) {
        $val = $rates['rates'][$currency_code];
        return is_string($val) ? $val : (string) $val;
    }

    return null;
}

/**
 * Get exchange rate for a specific currency (float, for display / JS use).
 *
 * @param string $currency_code ISO-3 currency code (e.g. 'NGN')
 * @return float|null Exchange rate relative to base, or null if not found
 */
function get_rate_for_currency($currency_code)
{
    $raw = get_rate_for_currency_raw($currency_code);
    return $raw !== null ? (float) $raw : null;
}

/**
 * Get accepted countries from settings
 * @return array Array of ISO-2 country codes, or [] if setting is empty or invalid
 */
function get_accepted_countries()
{
    if (isset($GLOBALS['accepted_countries'])) {
        return $GLOBALS['accepted_countries'];
    }

    $value = get_setting('accepted_countries', '[]');
    $decoded = json_decode($value, true);

    if (is_array($decoded) && !empty($decoded)) {
        $GLOBALS['accepted_countries'] = $decoded;
    } else {
        $GLOBALS['accepted_countries'] = [];
    }

    return $GLOBALS['accepted_countries'];
}

/**
 * Check if the current user is an admin
 * @return bool True if user is admin, false otherwise
 */
function is_admin(): bool
{
    return isset($GLOBALS['current_user']) && $GLOBALS['current_user']['role'] == "admin";
}
