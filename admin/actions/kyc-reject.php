<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/kyc-review');
    exit;
}

// Verify CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/kyc-review');
    exit;
}

// Validate document_id
$document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
if ($document_id <= 0) {
    $_SESSION['error'] = __('Invalid document ID');
    header('Location: /admin/kyc-review');
    exit;
}

// Get and validate rejection reason
$rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
if (empty($rejection_reason)) {
    $_SESSION['error'] = __('Rejection reason is required');
    header('Location: /admin/kyc-review');
    exit;
}

try {
    // Fetch KYC document
    $doc_query = "SELECT * FROM kyc_documents WHERE id = ? AND status = 'pending'";
    $doc_result = db_query($doc_query, [$document_id]);

    if (empty($doc_result)) {
        $_SESSION['error'] = __('Document not found or already processed');
        header('Location: /admin/kyc-review');
        exit;
    }

    $document = $doc_result[0];
    $user_id = $document['user_id'];

    // Fetch user
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_result = db_query($user_query, [$user_id]);

    if (empty($user_result)) {
        $_SESSION['error'] = __('User not found');
        header('Location: /admin/kyc-review');
        exit;
    }

    $user = $user_result[0];

    // Update document status
    db_update(
        'kyc_documents',
        [
            'status' => 'rejected',
            'rejection_reason' => $rejection_reason,
            'admin_id' => $_SESSION['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$document_id]
    );

    // Update user KYC status to rejected
    db_update(
        'users',
        [
            'kyc_status' => 'rejected',
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$user_id]
    );

    // Send rejection email (if enabled)
    $email_kyc_rejected_user = get_setting('email_kyc_rejected_user', 'yes');
    if ($email_kyc_rejected_user === 'yes') {
        $site_url = get_site_url();
        $site_name = get_setting('site_name', 'Investment Platform');
        $site_logo = get_setting('site_logo', '');
        $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../../' . $site_logo))
            ? $site_url . '/' . ltrim($site_logo, '/')
            : $site_url . '/assets/images/logo.png';
        $support_email = get_setting('support_email', 'support@example.com');
        $resubmit_url = $site_url . '/user/profile';


        try {
            send_template_email(
                $user['email'],
                'kyc-rejected',
                [
                    'user_name' => e($user['name']),
                    'guidance' => e($rejection_reason),
                    'reason' => e($rejection_reason),
                    'kyc_level' => isset($document['kyc_level']) ? e($document['kyc_level']) : 'Basic',
                    'resubmit_url' => e($resubmit_url),
                    'site_name' => e($site_name),
                    'site_logo' => $site_logo_url,
                    'logo_url' => $site_logo_url,
                    'site_url' => $site_url,
                    'current_year' => date('Y'),
                    'support_email' => e($support_email),

                    'company_address' => get_setting('company_address', '')
                ],
                get_user_language($user['id'])
            );
        } catch (Exception $e) {
            error_log("Failed to send KYC rejection email: " . $e->getMessage(), 3, __DIR__ . '/../../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('KYC document rejected');
    header('Location: /admin/kyc-review');
    exit;
} catch (Exception $e) {
    error_log('KYC reject error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while rejecting the document');
    header('Location: /admin/kyc-review');
    exit;
}
