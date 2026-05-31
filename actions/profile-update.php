<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Profile update handler
 * Updates user profile information including name, phone, country, and profile picture.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/profile');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/profile');
    exit;
}

$user_id = $_SESSION['user_id'];
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$country = isset($_POST['country']) ? trim($_POST['country']) : '';

// Validate name
if (empty($name)) {
    $_SESSION['error'] = __('Full name is required');
    header('Location: /user/profile');
    exit;
}

if (strlen($name) < 3) {
    $_SESSION['error'] = __('Full name must be at least 3 characters');
    header('Location: /user/profile');
    exit;
}

if (strlen($name) > 100) {
    $_SESSION['error'] = __('Full name must not exceed 100 characters');
    header('Location: /user/profile');
    exit;
}

// Validate phone if provided
if (!empty($phone)) {
    if (!validate_phone($phone)) {
        $_SESSION['error'] = __('Invalid phone number format');
        header('Location: /user/profile');
        exit;
    }
}

// Validate country if provided
if (!empty($country)) {
    $_SESSION['error'] = __('You cannot change your country once set. Please contact support if you need to update it.');
    header('Location: /user/profile');
    exit;
}

// Handle profile picture upload
$profile_picture = null;
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = upload_file($_FILES['profile_picture'], 'profile');
    if ($upload === false) {
        $_SESSION['error'] = __('Failed to upload profile picture. Please ensure the file is an image (JPG, PNG) and under 5MB.');
        header('Location: /user/profile');
        exit;
    }

    // Get current profile picture to delete old one
    $current_user = db_query('SELECT profile_picture FROM users WHERE id = ?', [$user_id]);
    if (!empty($current_user[0]['profile_picture'])) {
        delete_file($current_user[0]['profile_picture']);
    }

    $profile_picture = $upload;
}

try {
    // Build update data
    $updateData = [
        'name' => sanitize_input($name),
        'phone' => empty($phone) ? null : sanitize_input($phone),
        'country' => empty($country) ? null : $country,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Add profile picture if uploaded
    if ($profile_picture !== null) {
        $updateData['profile_picture'] = $profile_picture;
    }

    // Update user profile
    db_update('users', $updateData, 'id = ?', [$user_id]);

    $_SESSION['success'] = __('Profile updated successfully');
    header('Location: /user/profile');
    exit;
} catch (Exception $e) {
    error_log('[Profile Update Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to update profile. Please try again later.');
    header('Location: /user/profile');
    exit;
}
