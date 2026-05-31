<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/translation-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => __('Invalid request method')]);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => __('Invalid security token')]);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => __('Invalid user ID')]);
    exit;
}

$user_check = db_query('SELECT id FROM users WHERE id = ?', [$user_id]);
if (empty($user_check)) {
    echo json_encode(['success' => false, 'message' => __('User not found')]);
    exit;
}

try {
    // Query 1: count of users referred by this user
    $users_referred_result = db_query('SELECT COUNT(*) as count FROM users WHERE referred_by = ?', [$user_id]);
    $users_referred_count = isset($users_referred_result[0]['count']) ? intval($users_referred_result[0]['count']) : 0;

    // Query 2: credited referral bonuses
    $referrals_result = db_query(
        "SELECT COUNT(*) as count, SUM(bonus_amount) as total FROM referrals WHERE referrer_id = ? AND status = 'credited'",
        [$user_id]
    );
    $credited_bonus_events_count = isset($referrals_result[0]['count']) ? intval($referrals_result[0]['count']) : 0;
    $total_earnings_raw = $referrals_result[0]['total'] ?? 0;
    $total_earnings = number_format(floatval($total_earnings_raw), 2, '.', '');

    // Query 3: direct upline
    $target_user_result = db_query('SELECT referred_by FROM users WHERE id = ?', [$user_id]);
    $target_user = $target_user_result[0] ?? null;
    $upline = null;
    if ($target_user && !is_null($target_user['referred_by'])) {
        $upline_result = db_query('SELECT * FROM users WHERE id = ?', [intval($target_user['referred_by'])]);
        if (!empty($upline_result)) {
            $upline = $upline_result[0];
        }
    }

    // Query 4: upline of upline
    $upline_of_upline = null;
    if ($upline && !is_null($upline['referred_by'])) {
        $upline_of_upline_result = db_query('SELECT id, name, referred_by FROM users WHERE id = ?', [intval($upline['referred_by'])]);
        if (!empty($upline_of_upline_result)) {
            $upline_of_upline = $upline_of_upline_result[0];
        }
    }

    echo json_encode([
        'success' => true,
        'users_referred_count' => $users_referred_count,
        'credited_bonus_events_count' => $credited_bonus_events_count,
        'total_earnings' => $total_earnings,
        'upline' => $upline,
        'upline_of_upline' => $upline_of_upline,
    ]);
    exit;
} catch (Exception $e) {
    error_log('Get user referral error: ' . $e->getMessage(), 3, ROOT . '/logs/db-errors.log');
    echo json_encode(['success' => false, 'message' => __('An error occurred while fetching referral data')]);
    exit;
}
