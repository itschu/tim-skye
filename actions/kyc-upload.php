<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

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

// Ensure all three files are present
if (
    !isset($_FILES['id_passport']) || $_FILES['id_passport']['error'] === UPLOAD_ERR_NO_FILE
    || !isset($_FILES['proof_address']) || $_FILES['proof_address']['error'] === UPLOAD_ERR_NO_FILE
    || !isset($_FILES['selfie']) || $_FILES['selfie']['error'] === UPLOAD_ERR_NO_FILE
) {
    $_SESSION['error'] = __('Please upload ID/Passport, Proof of Address and Selfie in a single submission.');
    header('Location: /user/profile#kyc');
    exit;
}

// Validate no upload errors
$files = ['id_passport' => $_FILES['id_passport'], 'proof_address' => $_FILES['proof_address'], 'selfie' => $_FILES['selfie']];
foreach ($files as $k => $f) {
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = __('One or more files failed to upload. Please try again.');
        header('Location: /user/profile#kyc');
        exit;
    }
}

try {
    // Upload each file
    $uploaded_id = upload_file($files['id_passport'], 'kyc');
    $uploaded_proof = upload_file($files['proof_address'], 'kyc');
    $uploaded_selfie = upload_file($files['selfie'], 'kyc');

    if ($uploaded_id === false || $uploaded_proof === false || $uploaded_selfie === false) {
        $_SESSION['error'] = __('Failed to upload one or more files. Please try again.');
        header('Location: /user/profile#kyc');
        exit;
    }

    // Check if a KYC row exists for this user
    $existing = db_query('SELECT id FROM kyc_documents WHERE user_id = ?', [$user_id]);
    $now = date('Y-m-d H:i:s');
    if ($existing && count($existing)) {
        $row_id = $existing[0]['id'];
        db_update('kyc_documents', [
            'id_passport_path' => $uploaded_id,
            'proof_address_path' => $uploaded_proof,
            'selfie_path' => $uploaded_selfie,
            'status' => 'pending',
            'updated_at' => $now
        ], 'id = ?', [$row_id]);
    } else {
        db_insert('kyc_documents', [
            'user_id' => $user_id,
            'id_passport_path' => $uploaded_id,
            'proof_address_path' => $uploaded_proof,
            'selfie_path' => $uploaded_selfie,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    // Set user's kyc_status to pending
    db_update('users', ['kyc_status' => 'pending', 'updated_at' => $now], 'id = ?', [$user_id]);

    // Send admin notification (preserve existing behavior)
    $email_kyc_submitted_admin = get_setting('email_kyc_submitted_admin', 'yes');
    if ($email_kyc_submitted_admin === 'yes') {
        $user = db_query("SELECT name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user) {
            $admin_email = get_setting('contact_email', 'admin@example.com');
            $admin_subject = sprintf(__('New KYC Submission - %s'), get_setting('site_name', 'Investment Platform'));
            $admin_body = sprintf(
                __("A user has submitted KYC documents (ID/Passport, Proof of Address, Selfie):\n\nUser: %s (%s)\n\nPlease review and approve/reject this submission in the admin panel."),
                $user['name'],
                $user['email']
            );

            try {
                send_email($admin_email, $admin_subject, nl2br($admin_body), true);
            } catch (Exception $e) {
                error_log("Failed to send admin KYC notification: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    $_SESSION['success'] = __('KYC documents uploaded successfully and submitted for review.');
    header('Location: /user/profile#kyc');
    exit;
} catch (Exception $e) {
    error_log('[KYC Upload Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
    $_SESSION['error'] = __('Failed to process KYC upload. Please try again.');
    header('Location: /user/profile#kyc');
    exit;
}
