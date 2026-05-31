<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Translation Compiler
 * 
 * Compiles .po files to .mo files using PHP (for systems without msgfmt)
 * Run this after editing .po files
 */

/**
 * Simple PO to MO compiler
 * Based on the gettext MO file format specification
 */
function compile_po_to_mo($po_file, $mo_file)
{
    if (!file_exists($po_file)) {
        echo "Error: PO file not found: $po_file\n";
        return false;
    }

    // Parse PO file
    $translations = [];
    $content = file_get_contents($po_file);
    $lines = explode("\n", $content);

    $current_msgid = null;
    $current_msgstr = '';
    $in_msgid = false;
    $in_msgstr = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
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
            $continuation = substr($line, 1, -1);
            // Unescape quoted characters
            $continuation = str_replace('\\"', '"', $continuation);
            $continuation = str_replace('\\\\', '\\', $continuation);
            $continuation = str_replace('\\n', "\n", $continuation);

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

    // Remove empty msgid (header)
    unset($translations['']);

    // Build MO file
    $num_entries = count($translations);

    // MO file header
    $magic = 0x950412de;  // Magic number for MO files
    $revision = 0;
    $num_strings = $num_entries;
    $original_offset = 28;
    $translation_offset = $original_offset + ($num_entries * 8);

    // Calculate string data offset
    $hash_size = 0;
    $hash_offset = $translation_offset + ($num_entries * 8);

    // Build string tables
    $original_table = '';
    $translation_table = '';
    $original_offsets = [];
    $translation_offsets = [];

    $current_offset = 0;
    foreach ($translations as $msgid => $msgstr) {
        $original_offsets[] = strlen($msgid);
        $original_offsets[] = $hash_offset + $current_offset;
        $original_table .= $msgid . "\0";
        $current_offset += strlen($msgid) + 1;
    }

    $current_offset = 0;
    foreach ($translations as $msgid => $msgstr) {
        $translation_offsets[] = strlen($msgstr);
        $translation_offsets[] = $hash_offset + strlen($original_table) + $current_offset;
        $translation_table .= $msgstr . "\0";
        $current_offset += strlen($msgstr) + 1;
    }

    // Write MO file
    $mo_content = pack('VVVVVV', $magic, $revision, $num_strings, $original_offset, $translation_offset, $hash_size);
    $mo_content .= pack('V', $hash_offset);

    // Original strings table (pairs of length, offset)
    for ($i = 0; $i < count($original_offsets); $i += 2) {
        $len = $original_offsets[$i];
        $off = $original_offsets[$i + 1];
        $mo_content .= pack('VV', $len, $off);
    }

    // Translations table (pairs of length, offset)
    for ($i = 0; $i < count($translation_offsets); $i += 2) {
        $len = $translation_offsets[$i];
        $off = $translation_offsets[$i + 1];
        $mo_content .= pack('VV', $len, $off);
    }

    // String data
    $mo_content .= $original_table . $translation_table;

    // Write to file
    file_put_contents($mo_file, $mo_content);

    return true;
}

echo "<h1>Translation Compiler</h1>";

$locale_dir = __DIR__ . '/../locale';
$languages = ['en_US', 'fr_FR'];

foreach ($languages as $lang) {
    $po_file = "$locale_dir/$lang/LC_MESSAGES/messages.po";
    $mo_file = "$locale_dir/$lang/LC_MESSAGES/messages.mo";

    echo "<h2>$lang</h2>";

    if (file_exists($po_file)) {
        if (compile_po_to_mo($po_file, $mo_file)) {
            echo "<p style='color:green'>✓ Compiled: messages.po → messages.mo</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to compile</p>";
        }
    } else {
        echo "<p style='color:red'>✗ PO file not found: $po_file</p>";
    }
}

echo "<hr><p>Translation compilation complete. <a href='/system/test-translation.php'>Run Translation Test</a></p>";
