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
 * Admin Authentication Guard
 * 
 * This file should be included at the top of all admin pages.
 * It first validates the general user session via auth.php,
 * then checks that the user has admin role.
 */

// First, validate basic user session
require_once __DIR__ . '/auth.php';

// Check if user is admin
if (!isset($GLOBALS['current_user']) || $GLOBALS['current_user']['role'] !== 'admin') {
    // Not an admin, redirect with error
    $_SESSION['error'] = 'You do not have permission to access the admin panel.';
    header('Location: /user/dashboard');
    exit;
}


