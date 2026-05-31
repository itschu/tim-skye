<?php
require_once __DIR__ . '/includes/bootstrap.php';
/**
 * Translation Test Script
 * 
 * Run this to diagnose translation issues
 */

require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

echo "<h1>Translation Diagnostic</h1>";

// Test 1: Check if gettext extension is loaded
echo "<h2>1. Gettext Extension</h2>";
if (extension_loaded('gettext')) {
    echo "<p style='color:green'>✓ Gettext extension is loaded</p>";
} else {
    echo "<p style='color:red'>✗ Gettext extension is NOT loaded</p>";
}

// Test 2: Check locale directory
echo "<h2>2. Locale Directory</h2>";
$locale_dir = __DIR__ . '/locale';
echo "<p>Locale dir: $locale_dir</p>";
echo "<p>Exists: " . (is_dir($locale_dir) ? 'Yes' : 'No') . "</p>";

// Test 3: Check .mo files
echo "<h2>3. Translation Files</h2>";
$languages = ['en_US', 'fr_FR'];
foreach ($languages as $lang) {
    $mo_file = "$locale_dir/$lang/LC_MESSAGES/messages.mo";
    $po_file = "$locale_dir/$lang/LC_MESSAGES/messages.po";
    echo "<p>$lang MO: " . (file_exists($mo_file) ? "✓ Found" : "✗ Not found") . "</p>";
    echo "<p>$lang PO: " . (file_exists($po_file) ? "✓ Found" : "✗ Not found") . "</p>";
}

// Test 4: Load and show some translations
echo "<h2>4. PHP Array Translations</h2>";
$translations = load_translations_from_array('fr_FR');
echo "<p>Loaded " . count($translations) . " translations</p>";
echo "<p>Sample translations:</p>";
echo "<ul>";
$samples = ['Create Account', 'Welcome Back', 'Dashboard', 'Total Balance'];
foreach ($samples as $sample) {
    $translated = isset($translations[$sample]) ? $translations[$sample] : '(not found)';
    echo "<li>$sample =&gt; $translated</li>";
}
echo "</ul>";

// Test 5: Try to initialize translation
echo "<h2>5. Translation Initialization</h2>";
try {
    $result = init_translation('fr_FR');
    echo "<p>init_translation('fr_FR'): " . ($result ? "✓ Success" : "✗ Failed") . "</p>";
    echo "<p>Current language: " . get_current_language() . "</p>";
    echo "<p>Use fallback: " . ($GLOBALS['use_translation_fallback'] ? 'Yes' : 'No') . "</p>";
    echo "<p>Translations loaded: " . count($GLOBALS['translations'] ?? []) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Exception: " . $e->getMessage() . "</p>";
}

// Test 6: Test actual translation with __()
echo "<h2>6. Translation Test with __()</h2>";
echo "<p>Testing __('Create Account'): " . __('Create Account') . "</p>";
echo "<p>Testing __('Welcome Back'): " . __('Welcome Back') . "</p>";
echo "<p>Testing __('Dashboard'): " . __('Dashboard') . "</p>";
echo "<p>Testing __('Total Balance'): " . __('Total Balance') . "</p>";

// Test 7: Test actual translation with _()
echo "<h2>7. Translation Test with _()</h2>";
echo "<p>Testing _('Create Account'): " . _('Create Account') . "</p>";
echo "<p>Testing _('Welcome Back'): " . _('Welcome Back') . "</p>";
echo "<p>Testing _('Dashboard'): " . _('Dashboard') . "</p>";
echo "<p>Testing _('Total Balance'): " . _('Total Balance') . "</p>";

// Test 8: Check global state
echo "<h2>8. Global State</h2>";
echo "<p>current_language: " . ($GLOBALS['current_language'] ?? 'not set') . "</p>";
echo "<p>use_translation_fallback: " . ($GLOBALS['use_translation_fallback'] ? 'true' : 'false') . "</p>";
echo "<p>translations count: " . count($GLOBALS['translations'] ?? []) . "</p>";

// Test 9: Check if _() function is working
echo "<h2>9. Function Check</h2>";
echo "<p>gettext function exists: " . (function_exists('gettext') ? 'Yes' : 'No') . "</p>";
echo "<p>_() function exists: " . (function_exists('_') ? 'Yes' : 'No') . "</p>";
echo "<p>__() function exists: " . (function_exists('__') ? 'Yes' : 'No') . "</p>";

echo "<hr><p><a href='/compile-translations.php'>Recompile Translations</a> | <a href='/dashboard.php'>Go to Dashboard</a></p>";
