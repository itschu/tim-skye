<?php

/**
 * Bootstrap file - defines ROOT constant for access protection
 * 
 * This file should be included at the top of all entry points
 * (index.php, user/*.php, admin/*.php, etc.)
 */

// Prevent multiple definitions
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

// Define additional constants for path resolution
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT . '/includes');
}

if (!defined('ACTIONS_PATH')) {
    define('ACTIONS_PATH', ROOT . '/actions');
}

if (!defined('USER_PATH')) {
    define('USER_PATH', ROOT . '/user');
}

if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', ROOT . '/admin');
}

if (!defined('SITE_ICON')) {
    define('SITE_ICON', '/assets/images/logo.png');
}
