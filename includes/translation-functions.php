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
 * Translation Functions Library
 * 
 * Manages Gettext-based translations with language switching,
 * language persistence, and multi-language support for the platform.
 * 
 * Includes fallback to PHP array-based translations for Windows/XAMPP
 * where Gettext may not work properly due to missing locale support.
 */

// Global storage for PHP array-based translations
$GLOBALS['translations'] = [];
$GLOBALS['current_language'] = 'en_US';
$GLOBALS['use_translation_fallback'] = false;

/**
 * Load translations from PHP array
 * 
 * Fallback for when Gettext is not available or not working
 * 
 * @param string $language Language code
 * @return array Associative array of translations
 */
function load_translations_from_array($language)
{
    $translations = [];
    $po_file = __DIR__ . '/../locale/' . $language . '/LC_MESSAGES/messages.po';

    if (!file_exists($po_file)) {
        error_log("[Translation] PO file not found: $po_file", 3, __DIR__ . '/../logs/upload-errors.log');
        return $translations;
    }

    $content = file_get_contents($po_file);
    if (!$content) {
        return $translations;
    }

    // Parse .po file format
    $lines = explode("\n", $content);
    $current_msgid = null;
    $current_msgstr = '';
    $in_msgid = false;
    $in_msgstr = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            // Save previous translation if we have one
            if ($current_msgid !== null && !empty($current_msgstr)) {
                $translations[$current_msgid] = $current_msgstr;
            }
            $current_msgid = null;
            $current_msgstr = '';
            $in_msgid = false;
            $in_msgstr = false;
            continue;
        }

        // Parse msgid
        if (strpos($line, 'msgid "') === 0) {
            $in_msgid = true;
            $in_msgstr = false;
            $current_msgid = substr($line, 7, -1);
            $current_msgstr = '';
            // Handle multi-line msgid
            if ($current_msgid === '') {
                $current_msgid = null;
            }
        }
        // Parse msgstr
        elseif (strpos($line, 'msgstr "') === 0) {
            $in_msgid = false;
            $in_msgstr = true;
            $current_msgstr = substr($line, 8, -1);
        }
        // Handle string continuation
        elseif (strpos($line, '"') === 0 && ($in_msgid || $in_msgstr)) {
            $continuation = stripslashes(substr($line, 1, -1));
            if ($in_msgid && $current_msgid !== null) {
                $current_msgid .= $continuation;
            } elseif ($in_msgstr) {
                $current_msgstr .= $continuation;
            }
        }
    }

    // Save last translation
    if ($current_msgid !== null && !empty($current_msgstr)) {
        $translations[$current_msgid] = $current_msgstr;
    }

    return $translations;
}

/**
 * Check if Google Translate mode is enabled
 * 
 * @return bool True if Google Translate is enabled, false for local translations
 */
function is_google_translate_enabled()
{
    // Don't load from database during early initialization
    // Check if get_setting function exists and database is available
    if (!function_exists('get_setting')) {
        return false;
    }

    try {
        return get_setting('translation_mode', 'local') === 'google';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Initialize Gettext translation system for specified language
 * 
 * Sets up Gettext with locale, text domain, and codeset.
 * If language is null, retrieves from user session or defaults to 'en_US'.
 * Falls back to PHP array-based translations on Windows/XAMPP.
 * Skips initialization if Google Translate mode is enabled.
 * 
 * @param string|null $language Language code (e.g., 'en_US', 'fr_FR'). If null, uses session or default.
 * @return bool True on success, false on failure
 */
function init_translation($language = null)
{
    // Skip local translation initialization if Google Translate is enabled
    if (is_google_translate_enabled()) {
        $GLOBALS['use_translation_fallback'] = false;
        $GLOBALS['translations'] = [];
        return true;
    }

    try {
        // Get language from parameter, session, or default
        if ($language === null) {
            if (isset($_SESSION['user_id'])) {
                $language = get_user_language($_SESSION['user_id']);
            } else {
                $language = $_SESSION['language'] ?? 'en_US';
            }
        }

        // Validate language code format
        if (!validate_language_code($language)) {
            error_log("[Translation] Invalid language code: $language", 3, __DIR__ . '/../logs/upload-errors.log');
            $language = 'en_US';
        }

        // Store current language in globals
        $GLOBALS['current_language'] = $language;

        // Always load PHP array translations as fallback first
        $GLOBALS['translations'] = load_translations_from_array($language);

        // If English, no need for Gettext (strings are already in English)
        if ($language === 'en_US') {
            $GLOBALS['use_translation_fallback'] = false;
            return true;
        }

        // Try Gettext as well (for systems where it might work)
        if (extension_loaded('gettext')) {
            // Map language codes to locale names
            $locale_mappings = [
                'fr_FR' => ['French', 'french', 'fr_FR', 'fr_FR.UTF-8'],
                'en_US' => ['English', 'english', 'en_US', 'en_US.UTF-8'],
                'es_ES' => ['Spanish', 'spanish', 'es_ES', 'es_ES.UTF-8'],
                'de_DE' => ['German', 'german', 'de_DE', 'de_DE.UTF-8'],
                'pt_BR' => ['Portuguese', 'portuguese', 'pt_BR', 'pt_BR.UTF-8'],
            ];

            $locale_attempts = $locale_mappings[$language] ?? [$language, $language . '.UTF-8'];
            $locale_set = @setlocale(LC_ALL, ...$locale_attempts);

            // Always bind Gettext domain to the project's locale directory so
            // that Gettext can find compiled .mo files even if setlocale() fails
            $locale_dir = __DIR__ . '/../locale';
            @bindtextdomain('messages', $locale_dir);
            @bind_textdomain_codeset('messages', 'UTF-8');
            @textdomain('messages');

            if ($locale_set) {
                @putenv('LC_ALL=' . $locale_set);
                error_log("[Translation] Set locale to: $locale_set for language: $language", 3, __DIR__ . '/../logs/upload-errors.log');
            }
        }

        // Probe whether Gettext returns real translations. Iterate through
        // PHP-array translations until we find a msgid/msgstr pair where
        // the msgstr differs from the msgid, then test gettext() on that
        // msgid. If gettext returns a different value, prefer Gettext.
        $GLOBALS['use_translation_fallback'] = true;
        if (extension_loaded('gettext') && !empty($GLOBALS['translations'])) {
            foreach ($GLOBALS['translations'] as $msgid => $msgstr) {
                if ($msgstr !== null && $msgstr !== '' && $msgstr !== $msgid) {
                    $g = @gettext($msgid);
                    if ($g !== false && $g !== $msgid) {
                        $GLOBALS['use_translation_fallback'] = false;
                    }
                    // Break after first differing pair to avoid extra work
                    break;
                }
            }
        } else {
            // No gettext extension available or no translations loaded
            $GLOBALS['use_translation_fallback'] = true;
        }

        if ($GLOBALS['use_translation_fallback']) {
            error_log("[Translation] Using PHP array fallback for: $language", 3, __DIR__ . '/../logs/upload-errors.log');
        }

        return true;
    } catch (Exception $e) {
        error_log("[Translation] Exception: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        $GLOBALS['use_translation_fallback'] = true;
        return false;
    }
}

/**
 * Translation function
 * 
 * Uses Gettext if available, falls back to PHP array.
 * Returns original text if Google Translate mode is enabled.
 * 
 * @param string $text Text to translate
 * @return string Translated text or original if no translation found
 */
// Translate via MyMemory API as final fallback
function translate_via_api($text, $target_lang)
{
    try {
        $lang_tag = str_replace('_', '-', $target_lang);
        $lang_tag = trim($lang_tag);
        if ($lang_tag === '') {
            return false;
        }

        $q = urlencode($text);
        $url = "https://api.mymemory.translated.net/get?q={$q}&langpair=en|{$lang_tag}";

        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            error_log("[Translation API] Network error when calling MyMemory: {$url}", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data)) {
            error_log("[Translation API] Invalid JSON response for: {$url}", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        if (!isset($data['responseStatus']) || (int)$data['responseStatus'] !== 200) {
            error_log("[Translation API] Non-200 response: " . ($data['responseStatus'] ?? 'unknown') . " for: {$url}", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        $translated = $data['responseData']['translatedText'] ?? '';
        if (!is_string($translated) || trim($translated) === '') {
            error_log("[Translation API] Empty translation for: {$url}", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        if (trim($translated) === trim($text)) {
            // No useful translation returned
            return false;
        }

        return $translated;
    } catch (Exception $e) {
        error_log("[Translation API] Exception: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }
}

// Append a msgid/msgstr pair to a .po file, avoiding duplicates
function append_to_po($po_path, $msgid, $msgstr)
{
    try {
        if (!is_string($po_path) || trim($po_path) === '') {
            return false;
        }

        $content = '';
        if (file_exists($po_path)) {
            $content = file_get_contents($po_path) ?: '';
        } else {
            // Ensure directory exists
            $dir = dirname($po_path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        // Escape msgid for duplicate check
        $escaped_msgid = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $msgid);
        if (strpos($content, 'msgid "' . $escaped_msgid . '"') !== false) {
            // Already present
            return true;
        }

        // Build entry with escaped sequences
        $e_msgid = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $msgid);
        $e_msgstr = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $msgstr);

        $entry = "\nmsgid \"{$e_msgid}\"\nmsgstr \"{$e_msgstr}\"\n";

        $res = file_put_contents($po_path, $entry, FILE_APPEND | LOCK_EX);
        if ($res === false) {
            error_log("[Translation] Failed to append to PO: {$po_path}", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        error_log("[Translation] Appended msgid to PO: {$po_path}", 3, __DIR__ . '/../logs/upload-errors.log');
        return true;
    } catch (Exception $e) {
        error_log("[Translation] Exception in append_to_po: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }
}

function __($text, $useApi = false)  // Using __ instead of _ to avoid conflict with gettext's _()
{
    // If Google Translate is enabled, return text as-is for client-side translation
    if (is_google_translate_enabled()) {
        return $text;
    }

    // Normalize input
    $text = (string)$text;

    $result = $text;

    // Fallback array branch
    if (!empty($GLOBALS['use_translation_fallback'])) {
        if (isset($GLOBALS['translations'][$text]) && !empty($GLOBALS['translations'][$text])) {
            $result = $GLOBALS['translations'][$text];
        } else {
            $result = $text;
        }
    } else {
        // Gettext branch
        if (function_exists('gettext')) {
            $g = @gettext($text);
            if ($g !== false && $g !== $text) {
                $result = $g;
            } elseif (isset($GLOBALS['translations'][$text]) && !empty($GLOBALS['translations'][$text])) {
                $result = $GLOBALS['translations'][$text];
            } else {
                $result = $text;
            }
        } else {
            // Final branch: use PHP array if available
            if (isset($GLOBALS['translations'][$text]) && !empty($GLOBALS['translations'][$text])) {
                $result = $GLOBALS['translations'][$text];
            } else {
                $result = $text;
            }
        }
    }

    $append_to_po = false;

    // API fallback: only when nothing else changed the text, and not English
    if ($result === $text && ($GLOBALS['current_language'] ?? 'en_US') !== 'en_US' && trim($text) !== '' && $useApi) {
        $translated = translate_via_api($text, $GLOBALS['current_language'] ?? 'en_US');
        if ($translated !== false && trim($translated) !== '' && trim($translated) !== trim($text) && $append_to_po) {
            // Cache in-memory for this request
            $GLOBALS['translations'][$text] = $translated;

            // Append to PO file for operator review
            $po_path = ROOT . '/locale/' . ($GLOBALS['current_language'] ?? 'en_US') . '/LC_MESSAGES/messages.po';
            append_to_po($po_path, $text, $translated);
        }

        $result = $translated;
    }

    return $result;
}

// Provide _() as an alias for __() for backward compatibility
if (!function_exists('_')) {
    function _($text)
    {
        return __($text);
    }
} else {
    // Gettext's _() exists, but we need to make sure it uses our fallback
    // Store the original and create a wrapper
    $GLOBALS['___use_translation_fallback'] = &$GLOBALS['use_translation_fallback'];
    $GLOBALS['___translations'] = &$GLOBALS['translations'];
}

/**
 * Get array of available languages with labels
 * 
 * Returns all available languages: always includes English (en_US),
 * plus secondary language from settings if configured.
 * 
 * @return array Associative array: ['en_US' => 'English', 'fr_FR' => 'Français', ...]
 */
function get_available_languages()
{
    // Primary language is always English
    $languages = [
        'en_US' => 'English'
    ];

    // Get secondary language from settings
    $secondary = get_setting('secondary_language', 'fr_FR');

    // Map language codes to labels
    $language_labels = [
        'en_US' => 'English',
        'fr_FR' => 'Français',
        'es_ES' => 'Español',
        'de_DE' => 'Deutsch',
        'pt_BR' => 'Português',
        'ar_SA' => 'العربية'
    ];

    // Add secondary language if valid and not duplicate
    if ($secondary && $secondary !== 'en_US' && isset($language_labels[$secondary])) {
        $languages[$secondary] = $language_labels[$secondary];
    }

    return $languages;
}

/**
 * Switch active language for a user
 * 
 * Updates user's language preference in database, session, and
 * reinitializes translation system.
 * 
 * @param int $user_id User ID to update
 * @param string $language Language code (e.g., 'fr_FR')
 * @return bool True on success, false on failure
 */
function switch_language($user_id, $language)
{
    try {
        // Validate language is available
        $available = get_available_languages();
        if (!isset($available[$language])) {
            error_log("[Translation] Language not available: $language", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        // Update user's language in database
        $db = db_connect();
        $result = db_update('users', ['language' => $language], 'id = ?', [$user_id]);

        if ($result === false) {
            error_log("[Translation] Failed to update user language in database", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        // Update session language
        $_SESSION['language'] = $language;

        // Reinitialize translation system
        init_translation($language);

        return true;
    } catch (Exception $e) {
        error_log("[Translation] Exception: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }
}

/**
 * Get currently active language
 * 
 * Returns the language code currently in use by Gettext system.
 * Defaults to 'en_US' if not initialized.
 * 
 * @return string Language code (e.g., 'en_US', 'fr_FR')
 */
function get_current_language()
{
    return $GLOBALS['current_language'] ?? 'en_US';
}

/**
 * Get user's preferred language from database
 * 
 * Retrieves a user's language preference from the users table.
 * Returns default 'en_US' if user not found or language not set.
 * 
 * @param int $user_id User ID to fetch language for
 * @return string Language code (e.g., 'en_US', 'fr_FR')
 */
function get_user_language($user_id)
{
    try {
        $db = db_connect();
        $rows = db_query('SELECT language FROM users WHERE id = ?', [$user_id]);

        if ($rows && count($rows) && !empty($rows[0]['language'])) {
            return $rows[0]['language'];
        }

        return 'en_US';
    } catch (Exception $e) {
        error_log("[Translation] Exception in get_user_language: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return 'en_US';
    }
}

/**
 * Validate language code format
 * 
 * Checks if language code follows expected format (e.g., 'en_US', 'fr_FR').
 * Pattern: {2 lowercase letters}_{2 uppercase letters}
 * 
 * @param string $language Language code to validate
 * @return bool True if valid format, false otherwise
 */
function validate_language_code($language)
{
    return preg_match('/^[a-z]{2}_[A-Z]{2}$/', $language) === 1;
}

/**
 * Get list of all available languages for Google Translate
 * 
 * @return array Associative array of language codes and names
 */
function get_google_translate_languages()
{
    return [
        'en' => 'English',
        'af' => 'Afrikaans',
        'sq' => 'Albanian',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'hy' => 'Armenian',
        'az' => 'Azerbaijani',
        'eu' => 'Basque',
        'be' => 'Belarusian',
        'bn' => 'Bengali',
        'bs' => 'Bosnian',
        'bg' => 'Bulgarian',
        'ca' => 'Catalan',
        'ceb' => 'Cebuano',
        'zh-CN' => 'Chinese (Simplified)',
        'zh-TW' => 'Chinese (Traditional)',
        'co' => 'Corsican',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'nl' => 'Dutch',
        'eo' => 'Esperanto',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'fy' => 'Frisian',
        'gl' => 'Galician',
        'ka' => 'Georgian',
        'de' => 'German',
        'el' => 'Greek',
        'gu' => 'Gujarati',
        'ht' => 'Haitian Creole',
        'ha' => 'Hausa',
        'haw' => 'Hawaiian',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hmn' => 'Hmong',
        'hu' => 'Hungarian',
        'is' => 'Icelandic',
        'ig' => 'Igbo',
        'id' => 'Indonesian',
        'ga' => 'Irish',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'jw' => 'Javanese',
        'kn' => 'Kannada',
        'kk' => 'Kazakh',
        'km' => 'Khmer',
        'ko' => 'Korean',
        'ku' => 'Kurdish',
        'ky' => 'Kyrgyz',
        'lo' => 'Lao',
        'la' => 'Latin',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'lb' => 'Luxembourgish',
        'mk' => 'Macedonian',
        'mg' => 'Malagasy',
        'ms' => 'Malay',
        'ml' => 'Malayalam',
        'mt' => 'Maltese',
        'mi' => 'Maori',
        'mr' => 'Marathi',
        'mn' => 'Mongolian',
        'my' => 'Myanmar (Burmese)',
        'ne' => 'Nepali',
        'no' => 'Norwegian',
        'ny' => 'Nyanja (Chichewa)',
        'ps' => 'Pashto',
        'fa' => 'Persian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'pa' => 'Punjabi',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sm' => 'Samoan',
        'gd' => 'Scots Gaelic',
        'sr' => 'Serbian',
        'st' => 'Sesotho',
        'sn' => 'Shona',
        'sd' => 'Sindhi',
        'si' => 'Sinhala',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'so' => 'Somali',
        'es' => 'Spanish',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'sv' => 'Swedish',
        'tl' => 'Tagalog (Filipino)',
        'tg' => 'Tajik',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'vi' => 'Vietnamese',
        'cy' => 'Welsh',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'yo' => 'Yoruba',
        'zu' => 'Zulu',
        // Additional African languages supported by Google Translate
        'ak' => 'Akan',
        'bm' => 'Bambara',
        'ff' => 'Fulani',
        'lg' => 'Ganda',
        'ln' => 'Lingala',
        'ny' => 'Chichewa',
        'om' => 'Oromo',
        'rw' => 'Kinyarwanda',
        'tn' => 'Setswana',
        'ts' => 'Tsonga',
        'tw' => 'Twi',
        'wo' => 'Wolof'
    ];
}

/**
 * Output Google Translate widget HTML and JavaScript
 * 
 * Custom styled dropdown that matches the site's language switcher design.
 * Loads Google Translate script asynchronously to avoid blocking.
 * 
 * @param string $style 'sidebar' or 'navbar' or 'auth' - determines the styling
 * @return string HTML/JS code for Google Translate widget
 */
function get_google_translate_widget($style = 'sidebar')
{
    if (!is_google_translate_enabled()) {
        return '';
    }

    $languages = get_google_translate_languages();
    $widget_id = 'gt_widget_' . uniqid();

    // Validate that we have languages
    if (empty($languages)) {
        return '';
    }

    // Different styling based on placement
    if ($style === 'auth') {
        $trigger_class = 'flex items-center gap-2 text-sm font-medium text-gray-300 hover:text-white transition-all py-2 px-3 rounded-xl bg-dark-800/80 border border-gray-700 hover:border-neon-cyan/50 backdrop-blur-sm';
        $dropdown_class = 'absolute right-0 mt-2 w-56 max-h-80 overflow-y-auto bg-dark-800/95 backdrop-blur-xl rounded-xl border border-gray-700 shadow-xl overflow-hidden z-50';
        $option_class = 'w-full text-left px-4 py-2.5 text-sm flex items-center gap-3 hover:bg-white/5 transition-colors text-gray-300';
        $active_class = 'text-neon-cyan bg-neon-cyan/10';
        $icon_color = 'text-neon-cyan';
        $language_icon_class = "";
        $language_label_class = "";
        $language_chevron_class = "";
        $i_chevron_class = "text-xs transition-transform ml-auto";
        $rotate_class = "rotate-180";
    } elseif ($style === 'navbar') {
        $trigger_class = 'flex items-center gap-2 text-sm font-medium text-gray-300 hover:text-white transition-all py-2 px-3 rounded-lg hover:bg-white/5';
        $dropdown_class = 'absolute right-0 mt-2 w-56 max-h-80 overflow-y-auto glass-panel rounded-xl border border-gray-700 shadow-xl overflow-hidden z-50';
        $option_class = 'w-full text-left px-4 py-2.5 text-sm flex items-center gap-2 hover:bg-white/10 transition-colors text-gray-300';
        $active_class = 'text-neon-cyan bg-neon-cyan/10';
        $icon_color = 'text-neon-cyan';
        $language_icon_class = "";
        $language_label_class = "";
        $language_chevron_class = "";
        $i_chevron_class = "text-xs transition-transform ml-auto";
        $rotate_class = "rotate-180";
    } else {
        // sidebar style - improved styling for better compatibility
        $trigger_class = 'language-switcher-btn';
        $language_icon_class = "language-icon";
        $language_label_class = "language-label";
        $language_chevron_class = "language-chevron";
        $dropdown_class = 'language-dropdown';
        $option_class = 'language-option';
        $active_class = 'active bg-primary text-white';
        $icon_color = 'text-white';
        $i_chevron_class = "";
        $rotate_class = "rotate";
    }

    // Ensure proper JSON encoding with Unicode support
    $languages_json = json_encode($languages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    // Escape for HTML attribute usage
    $languages_json = htmlspecialchars($languages_json, ENT_QUOTES, 'UTF-8');

    // Special admin sidebar style: render as a compact <select> to match admin local switcher
    if ($style === 'admin' || $style === 'admin_sidebar') {
        $options = '';
        foreach ($languages as $code => $label) {
            $code_esc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $label_esc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $selected = $code === 'en' ? ' selected' : '';
            $options .= "<option value=\"{$code_esc}\"{$selected}>{$label_esc}</option>";
        }

        $widget_id_esc = htmlspecialchars($widget_id, ENT_QUOTES, 'UTF-8');
        $html = "<!-- Google Translate Admin Select -->\n" .
            "<div class=\"mb-0\">\n" .
            "  <select class=\"form-select form-select-sm form-select-dark\" onchange=\"translatePage(this.value); localStorage.setItem('gt_selected_language', this.value);\">\n" .
            $options .
            "  </select>\n" .
            "</div>\n" .
            "<div id=\"google_translate_element\" style=\"position: absolute; left: -9999px; visibility: hidden;\"></div>\n" .
            "<script type=\"text/javascript\">\n" .
            "(function() {\n" .
            "    if (window.googleTranslateLoading) { return; }\n" .
            "    window.googleTranslateLoading = true;\n" .
            "    function loadGoogleTranslate() {\n" .
            "        var script = document.createElement('script');\n" .
            "        script.type = 'text/javascript';\n" .
            "        script.async = true;\n" .
            "        script.defer = true;\n" .
            "        script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';\n" .
            "        document.body.appendChild(script);\n" .
            "    }\n" .
            "    if (document.readyState === 'complete') { loadGoogleTranslate(); } else { window.addEventListener('load', loadGoogleTranslate); }\n" .
            "})();\n" .
            "function googleTranslateElementInit() {\n" .
            "    try { new google.translate.TranslateElement({ pageLanguage: 'en', layout: google.translate.TranslateElement.InlineLayout.SIMPLE, autoDisplay: false }, 'google_translate_element'); window.googleTranslateReady = true; document.dispatchEvent(new CustomEvent('google-translate-ready')); } catch(e) { console.error(e); }\n" .
            "}\n" .
            "function translatePage(langCode) {\n" .
            "    localStorage.setItem('gt_selected_language', langCode);\n" .
            "    var select = document.querySelector('.goog-te-combo');\n" .
            "    if (!select) select = document.querySelector('select.goog-te-combo');\n" .
            "    if (select) { try { select.value = langCode; select.dispatchEvent(new Event('change')); return; } catch (e) { console.error(e); } }\n" .
            "    // fallback cookie method\n" .
            "    var cookieValue = '/en/' + langCode; document.cookie = 'googtrans=' + cookieValue + '; path=/'; document.cookie = 'googtrans=' + cookieValue + '; domain=.' + document.domain + '; path=/'; window.location.reload();\n" .
            "}\n" .
            "</script>\n" .
            "<style>\n" .
            ".goog-te-banner-frame { display: none !important; } .goog-logo-link { display: none !important; } .skiptranslate { display: none !important; }\n" .
            "</style>\n";

        return $html;
    }

    // Mobile style: render an inline, non-absolute dropdown with search and
    // a scrollable list. This avoids clipping inside scrollable mobile menus
    // while remaining well-styled for many Google Translate languages.
    if ($style === 'mobile') {
        $languages_json_esc = $languages_json;
        $mobile_widget = <<<HTML
<div x-data="{ open: false, currentCode: localStorage.getItem('gt_selected_language') || 'en', filter: '', languages: {$languages_json_esc} }">
    <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-medium text-gray-300 hover:text-white transition-all py-2 px-3 rounded-lg hover:bg-white/5 w-full">
        <i class="fas fa-globe text-neon-cyan"></i>
        <span x-text="languages[currentCode] || 'English'"></span>
        <i class="fas fa-chevron-down text-xs ml-auto" :class="{ 'rotate-180': open }"></i>
    </button>
    <div x-show="open" x-cloak class="mt-2 glass-panel rounded-xl border border-gray-700 shadow-xl overflow-hidden">
        <div class="p-2">
            <input x-model="filter" type="search" placeholder="Search languages" class="w-full px-3 py-2 bg-dark-800/60 border border-gray-700 rounded-lg text-sm text-gray-300" />
        </div>
        <div class="max-h-56 overflow-y-auto px-2 pb-2 grid grid-cols-2 gap-2">
            <template x-for="[code,name] in Object.entries(languages)" :key="code">
                <button type="button"
                    x-show="(!filter || name.toLowerCase().includes(filter.toLowerCase()) || code.toLowerCase().includes(filter.toLowerCase()))"
                    @click="currentCode = code; translatePage(code); localStorage.setItem('gt_selected_language', code); open = false"
                    :class="currentCode === code ? 'bg-neon-cyan/20 text-neon-cyan border border-neon-cyan/30' : 'text-gray-300 hover:bg-white/5'"
                    class="text-left px-3 py-2 text-sm rounded-lg flex items-center gap-2 transition-colors w-full">
                    <span x-show="currentCode === code"><i class="fas fa-check text-xs"></i></span>
                    <span x-text="name"></span>
                </button>
            </template>
        </div>
    </div>
</div>
HTML;

        return $mobile_widget;
    }

    // Build the HTML output using heredoc to avoid quote escaping issues
    $widget_id_esc = htmlspecialchars($widget_id, ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<!-- Google Translate Custom Widget -->
<div class="relative" x-data="{
    langOpen: false,
    currentLang: 'English',
    currentCode: 'en',
    languages: {$languages_json},
    init() {
        // Check for saved language in localStorage
        const savedLang = localStorage.getItem('gt_selected_language');
        if (savedLang && savedLang !== 'en') {
            // Get language name from languages object
            const languageNames = {$languages_json};
            if (languageNames[savedLang]) {
                this.currentLang = languageNames[savedLang];
                this.currentCode = savedLang;
            }
        }
    }
}" @click.outside="langOpen = false" id="{$widget_id_esc}" x-cloak
     x-init="setTimeout(() => {
        // console.log('Google Translate widget initialized for element:', '#{$widget_id_esc}');
        // Set up event listener for language updates
        const widgetElement = document.getElementById('{$widget_id_esc}');
        if (widgetElement) {
            widgetElement.addEventListener('alpine:update-language', (event) => {
                // Update Alpine.js data using the Alpine.js instance
                if (widgetElement._x_dataStack) {
                    widgetElement._x_dataStack[0].currentLang = event.detail.currentLang;
                    widgetElement._x_dataStack[0].currentCode = event.detail.currentCode;
                   // console.log('Widget updated via event:', event.detail.currentCode, event.detail.currentLang);
                }
            });
        }

        // Ensure dropdown is positioned correctly
        const dropdown = document.querySelector('#{$widget_id_esc} .language-dropdown');
        if (dropdown) {
            dropdown.style.position = 'absolute';
            dropdown.style.zIndex = '1000';
           // console.log('Dropdown positioned correctly');
        } else {
          // console.log('Dropdown element not found');
        }
     }, 100)">
    <button type="button"
        class="{$trigger_class}"
        @click="langOpen = !langOpen">
        <span class="{$language_icon_class}"><i class="fas fa-globe {$icon_color}"></i></span>
        <span class="{$language_label_class}" x-text="currentLang"></span>
        <span class="{$language_chevron_class}" :class="{ '{$rotate_class}': langOpen }"><i class="fas fa-chevron-down {$i_chevron_class}"></i></span>
    </button>
    <div x-show="langOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        class="{$dropdown_class}"
        x-cloak
        style="display: none; max-height: 300px;">
        <template x-for="[code, name] in Object.entries(languages)" :key="code">
            <button type="button"
                :class="'{$option_class} ' + (currentCode === code ? '{$active_class}' : '')"
                @click="currentLang = name; currentCode = code; langOpen = false; translatePage(code); localStorage.setItem('gt_selected_language', code);">
                <span class="language-check" x-show="currentCode === code"><i class="fas fa-check"></i></span>
                <span x-text="name"></span>
            </button>
        </template>
    </div>
</div>

<!-- Hidden Google Translate Element -->
<div id="google_translate_element" style="position: absolute; left: -9999px; visibility: hidden;"></div>

<script type="text/javascript">
// Async Google Translate loader with debugging
(function() {
    // console.log('Initializing Google Translate loader');

    // Prevent multiple loads
    if (window.googleTranslateLoading) {
        // console.log('Google Translate already loading');
        return;
    }
    window.googleTranslateLoading = true;
    // console.log('Setting up Google Translate loading');

    // Load script after page is fully loaded
    if (document.readyState === "complete") {
        // console.log('Document already complete, loading GT');
        loadGoogleTranslate();
    } else {
        // console.log('Adding load event listener for GT');
        window.addEventListener("load", loadGoogleTranslate);
    }

    function loadGoogleTranslate() {
        // console.log('loadGoogleTranslate function called');
        // Small delay to ensure page content is rendered first
        setTimeout(function() {
            // console.log('Creating Google Translate script element');
            var script = document.createElement("script");
            script.type = "text/javascript";
            script.async = true;
            script.defer = true;
            script.src = "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit";
            script.onload = function() {
                // console.log('Google Translate script loaded successfully');
            };
            script.onerror = function() {
                console.error('Google Translate script failed to load');
            };
            document.body.appendChild(script);
        }, 100);
    }
})();

function googleTranslateElementInit() {
    // Ensure the container element exists
    var container = document.getElementById("google_translate_element");
    if (!container) {
        console.error('Google Translate container element not found');
        return;
    }

    new google.translate.TranslateElement({
        pageLanguage: "en",
        includedLanguages: "af,sq,am,ar,hy,az,eu,be,bn,bs,bg,ca,ceb,zh-CN,zh-TW,co,hr,cs,da,nl,en,eo,et,fi,fr,fy,gl,ka,de,el,gu,ht,ha,haw,he,hi,hmn,hu,is,ig,id,ga,it,ja,jw,kn,kk,km,ko,ku,ky,lo,la,lv,lt,lb,mk,mg,ms,ml,mt,mi,mr,mn,my,ne,no,ny,ps,fa,pl,pt,pa,ro,ru,sm,gd,sr,st,sn,sd,si,sk,sl,so,es,su,sw,sv,tl,tg,ta,te,th,tr,uk,ur,uz,vi,cy,xh,yi,yo,zu",
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, "google_translate_element");

    // Store reference that widget is ready
    window.googleTranslateReady = true;

    // Dispatch custom event to notify that Google Translate is ready
    document.dispatchEvent(new CustomEvent('google-translate-ready'));
    // console.log('Google Translate widget initialized');
}

// Function to trigger translation using Google Translate API (with automatic reload when needed)
function translatePage(langCode) {
    // console.log('Translating page to:', langCode);

    // Update the Alpine.js component if it exists
    updateAlpineLanguage(langCode);

    // Store selection in localStorage for persistence immediately
    localStorage.setItem("gt_selected_language", langCode);

    // Try to find the Google Translate combo box
    var select = document.querySelector(".goog-te-combo");
    if (!select) {
        select = document.querySelector("select.goog-te-combo");
    }

    if (select) {
        // console.log('Google Translate select found, translating...');
        try {
            // Set the language and trigger change event
            select.value = langCode;
            select.dispatchEvent(new Event("change"));

            // console.log('Translation initiated for:', langCode);
        } catch (e) {
            console.error('Translation failed:', e);
            // Fallback to cookie method with automatic reload
            fallbackToCookieMethod(langCode);
        }
    } else {
        // console.log('Google Translate select not found, using fallback with auto-refresh...');
        // Fallback to cookie method with automatic reload
        fallbackToCookieMethod(langCode);
    }
}

// Update Alpine.js language variables
function updateAlpineLanguage(langCode) {
    // console.log('Updating Alpine.js language to:', langCode);

    // Get language name from the languages object
    var languageNames = {
        'en': 'English',
        'af': 'Afrikaans',
        'sq': 'Albanian',
        'am': 'Amharic',
        'ar': 'Arabic',
        'hy': 'Armenian',
        'az': 'Azerbaijani',
        'eu': 'Basque',
        'be': 'Belarusian',
        'bn': 'Bengali',
        'bs': 'Bosnian',
        'bg': 'Bulgarian',
        'ca': 'Catalan',
        'ceb': 'Cebuano',
        'zh-CN': 'Chinese (Simplified)',
        'zh-TW': 'Chinese (Traditional)',
        'co': 'Corsican',
        'hr': 'Croatian',
        'cs': 'Czech',
        'da': 'Danish',
        'nl': 'Dutch',
        'eo': 'Esperanto',
        'et': 'Estonian',
        'fi': 'Finnish',
        'fr': 'French',
        'fy': 'Frisian',
        'gl': 'Galician',
        'ka': 'Georgian',
        'de': 'German',
        'el': 'Greek',
        'gu': 'Gujarati',
        'ht': 'Haitian Creole',
        'ha': 'Hausa',
        'haw': 'Hawaiian',
        'he': 'Hebrew',
        'hi': 'Hindi',
        'hmn': 'Hmong',
        'hu': 'Hungarian',
        'is': 'Icelandic',
        'ig': 'Igbo',
        'id': 'Indonesian',
        'ga': 'Irish',
        'it': 'Italian',
        'ja': 'Japanese',
        'jw': 'Javanese',
        'kn': 'Kannada',
        'kk': 'Kazakh',
        'km': 'Khmer',
        'ko': 'Korean',
        'ku': 'Kurdish',
        'ky': 'Kyrgyz',
        'lo': 'Lao',
        'la': 'Latin',
        'lv': 'Latvian',
        'lt': 'Lithuanian',
        'lb': 'Luxembourgish',
        'mk': 'Macedonian',
        'mg': 'Malagasy',
        'ms': 'Malay',
        'ml': 'Malayalam',
        'mt': 'Maltese',
        'mi': 'Maori',
        'mr': 'Marathi',
        'mn': 'Mongolian',
        'my': 'Myanmar (Burmese)',
        'ne': 'Nepali',
        'no': 'Norwegian',
        'ny': 'Nyanja (Chichewa)',
        'ps': 'Pashto',
        'fa': 'Persian',
        'pl': 'Polish',
        'pt': 'Portuguese',
        'pa': 'Punjabi',
        'ro': 'Romanian',
        'ru': 'Russian',
        'sm': 'Samoan',
        'gd': 'Scots Gaelic',
        'sr': 'Serbian',
        'st': 'Sesotho',
        'sn': 'Shona',
        'sd': 'Sindhi',
        'si': 'Sinhala',
        'sk': 'Slovak',
        'sl': 'Slovenian',
        'so': 'Somali',
        'es': 'Spanish',
        'su': 'Sundanese',
        'sw': 'Swahili',
        'sv': 'Swedish',
        'tl': 'Tagalog (Filipino)',
        'tg': 'Tajik',
        'ta': 'Tamil',
        'te': 'Telugu',
        'th': 'Thai',
        'tr': 'Turkish',
        'uk': 'Ukrainian',
        'ur': 'Urdu',
        'uz': 'Uzbek',
        'vi': 'Vietnamese',
        'cy': 'Welsh',
        'xh': 'Xhosa',
        'yi': 'Yiddish',
        'yo': 'Yoruba',
        'zu': 'Zulu',
        'ak': 'Akan',
        'bm': 'Bambara',
        'ff': 'Fulani',
        'lg': 'Ganda',
        'ln': 'Lingala',
        'om': 'Oromo',
        'rw': 'Kinyarwanda',
        'tn': 'Setswana',
        'ts': 'Tsonga',
        'tw': 'Twi',
        'wo': 'Wolof'
    };

    var langName = languageNames[langCode] || 'English';

    // Update all Google Translate widgets using custom events
    var widgets = document.querySelectorAll('[id^=\"gt_widget_\"]');
    widgets.forEach(function(widget) {
        try {
            // Update the Alpine.js data using dispatch event
            var event = new CustomEvent('alpine:update-language', {
                detail: { currentLang: langName, currentCode: langCode }
            });
            widget.dispatchEvent(event);
            // console.log('Dispatched language update event:', langCode, langName);
        } catch (e) {
            // console.log('Could not dispatch language update event:', e);
        }
    });
}

// Fallback method using cookies with automatic page refresh
function fallbackToCookieMethod(langCode) {
    // console.log('Using fallback cookie method for language:', langCode);

    // Update Alpine.js language before refresh
    updateAlpineLanguage(langCode);

    // Set Google Translate cookies
    var cookieValue = "/en/" + langCode;
    document.cookie = "googtrans=" + cookieValue + "; path=/";
    document.cookie = "googtrans=" + cookieValue + "; domain=." + document.domain + "; path=/";

    // Store selection in localStorage for persistence
    localStorage.setItem("gt_selected_language", langCode);

    // Automatically refresh the page to apply translation
    // console.log('Refreshing page with new language:', langCode);
    window.location.reload();
}

// Debug Alpine.js availability
document.addEventListener('alpine:init', () => {
    // console.log('Alpine.js initialized, Google Translate widget should work');
});

// Check if Alpine.js is already available
if (typeof Alpine !== 'undefined') {
    // console.log('Google Translate widget loaded, Alpine.js available');
} else {
    // console.log('Google Translate widget loaded, Alpine.js not yet available');
}

// Apply saved language on page load (if any)
(function() {
    // console.log('Checking for saved Google Translate language');
    var savedLang = localStorage.getItem("gt_selected_language");
    if (savedLang && savedLang !== "en") {
        // console.log('Found saved language, applying:', savedLang);

        // Update Alpine.js components with saved language
        setTimeout(function() {
            updateAlpineLanguage(savedLang);
        }, 500);

        // Apply immediately using cookie method
        try {
            var cookieValue = "/en/" + savedLang;
            document.cookie = "googtrans=" + cookieValue + "; path=/";
            document.cookie = "googtrans=" + cookieValue + "; domain=." + document.domain + "; path=/";
        } catch (e) {
            // console.log('Could not set saved language cookie:', e);
        }
    }
})();
</script>

<style>
/* Hide Google Translate default elements */
.goog-te-banner-frame { display: none !important; }
body { top: 0 !important; }
.goog-logo-link { display: none !important; }
.goog-te-gadget { color: transparent !important; height: 0 !important; overflow: hidden !important; }
.goog-te-gadget > div { display: none !important; }
.skiptranslate { display: none !important; }
#goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
.goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }

/* Custom scrollbar for language dropdown */
.language-dropdown::-webkit-scrollbar {
    width: 6px;
}
.language-dropdown::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}
.language-dropdown::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}
.language-dropdown::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

/* Ensure widget dropdown is properly positioned and visible */
[x-cloak] { display: none !important; }
.relative { position: relative; }
.language-dropdown {
    position: absolute !important;
    z-index: 1000 !important;
    left: 0 !important;
    right: 0 !important;
    min-width: 200px !important;
    max-height: 300px !important;
    display: block !important;
}
.language-dropdown[style*="display: none"] {
    display: none !important;
}

/* Language option styling */
.language-option {
    transition: background-color 0.2s ease;
}
.language-option:hover {
    background-color: rgba(255,255,255,0.1) !important;
}
</style>
<!-- End Google Translate Custom Widget -->
HTML;

    return $html;
}

/**
 * Render Google Translate widget
 * 
 * Echoes the Google Translate widget directly
 * 
 * @param string $style 'sidebar', 'navbar', or 'auth'
 */
function render_google_translate_widget($style = 'sidebar')
{
    echo get_google_translate_widget($style);
}
