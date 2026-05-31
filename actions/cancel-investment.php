<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Investment cancellation handler
 * Processes investment cancellation requests and applies penalties
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/investment-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/my-investments');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/my-investments');
    exit;
}

$user_id = $_SESSION['user_id'];
$investment_id = isset($_POST['investment_id']) ? intval($_POST['investment_id']) : 0;

// Validate investment ID
if ($investment_id <= 0) {
    $_SESSION['error'] = __('Invalid investment ID');
    header('Location: /user/my-investments');
    exit;
}

try {
    // Fetch investment to verify ownership and status
    $investment = db_query("SELECT * FROM investments WHERE id = ? AND user_id = ?", [$investment_id, $user_id]);
    if (empty($investment)) {
        $_SESSION['error'] = __('Investment not found');
        header('Location: /user/my-investments');
        exit;
    }

    $inv = $investment[0];

    // Check if investment is active
    if ($inv['status'] !== 'active') {
        $_SESSION['error'] = __('Cannot cancel completed or already cancelled investment');
        header('Location: /user/my-investments');
        exit;
    }

    // Call cancellation function
    $result = cancel_investment($investment_id, $user_id);

    if ($result === false) {
        $_SESSION['error'] = __('Failed to cancel investment. Please try again.');
        header('Location: /user/my-investments');
        exit;
    }

    // Check for blocked after waiting error
    if (isset($result['error']) && $result['error'] === 'blocked_after_waiting') {
        $_SESSION['error'] = __('Cancellation is blocked — the first payout date has passed.');
        header('Location: /user/my-investments');
        exit;
    }

    // Extract refund and penalty from result array
    $refund_amount = $result['refund'];
    $penalty_amount = $result['penalty'];

    // Fetch plan name
    $plan = db_query("SELECT name FROM investment_plans WHERE id = ?", [$inv['plan_id']])[0] ?? null;
    $plan_name = $plan['name'] ?? '';

    // Send email notification (if enabled)
    $email_investment_cancelled_user = get_setting('email_investment_cancelled_user', 'yes');
    if ($email_investment_cancelled_user === 'yes') {
        $user = db_query("SELECT name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user) {
            $site_url = get_site_url();
            $site_logo_url = get_site_logo_url();

            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $user['name'],
                'investment_id' => $investment_id,
                'amount' => number_format((float)$inv['amount'], 2),
                'plan_name' => $plan_name,
                'penalty_amount' => number_format($penalty_amount, 2),
                'penalty' => number_format($penalty_amount, 2),
                'refund_amount' => number_format($refund_amount, 2),
                'original_amount' => number_format((float)$inv['amount'], 2),
                'investment_reference' => 'INV-' . $investment_id,
                'dashboard_url' => $site_url . '/user/dashboard',
                'currency' => get_setting('currency', 'USD'),
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),

                'company_address' => get_setting('company_address', '')
            ];

            try {
                send_template_email($user['email'], 'investment-cancelled', $email_vars, get_user_language($_SESSION['user_id']));
            } catch (Exception $e) {
                error_log("Failed to send investment cancelled email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    $_SESSION['success'] = __('Investment cancelled successfully. Refund amount: ') . format_money($refund_amount);
    header('Location: /user/my-investments?success=1');
    exit;
} catch (Exception $e) {
    error_log('[Cancel Investment Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to cancel investment. Please try again later.');
    header('Location: /user/my-investments');
    exit;
}
