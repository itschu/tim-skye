<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/currency-conversion.php';
try {
    require_not_maintenance();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: /admin/plans');
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/plans');
    exit;
}

// Verify CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/plans');
    exit;
}

// Validate plan_id
$plan_id = isset($_POST['plan_id']) ? sanitize_input($_POST['plan_id']) : '';
if (empty($plan_id)) {
    $_SESSION['error'] = __('Plan ID is required');
    header('Location: /admin/plans');
    exit;
}

// Fetch existing plan
$existing = db_query("SELECT * FROM investment_plans WHERE id = ?", [$plan_id]);
if (empty($existing)) {
    $_SESSION['error'] = __('Plan not found');
    header('Location: /admin/plans');
    exit;
}

// Sanitize inputs
$name = sanitize_input($_POST['name'] ?? '');
$roi_percentage = floatval($_POST['roi_percentage'] ?? 0);
$duration_days = intval($_POST['duration_days'] ?? 0);
$min_amount = floatval($_POST['min_amount'] ?? 0);
$max_amount = floatval($_POST['max_amount'] ?? 0);
$payout_interval = sanitize_input($_POST['payout_interval'] ?? '');
$payout_interval_type = isset($_POST['payout_interval_type']) ? sanitize_input($_POST['payout_interval_type']) : null;
$payout_interval_value = isset($_POST['payout_interval_value']) ? intval($_POST['payout_interval_value']) : null;
$is_compounding = isset($_POST['is_compounding']) ? 1 : 0;
$capital_return = isset($_POST['capital_return']) ? 1 : 0;
$is_featured = isset($_POST['is_featured']) ? 1 : 0;
$status = sanitize_input($_POST['status'] ?? 'active');
$waiting_period_value = intval($_POST['waiting_period_value'] ?? 0);
$waiting_period_unit = sanitize_input($_POST['waiting_period_unit'] ?? 'days');
$country = sanitize_input($_POST['country'] ?? '');

// Convert display amounts (local currency) to USD when provided
if (isset($_POST['display_min_amount']) || isset($_POST['display_max_amount'])) {
    $display_min = $_POST['display_min_amount'] ?? '0';
    $display_max = $_POST['display_max_amount'] ?? '0';
    if ($country !== '') {
        $local_currency = get_user_local_currency($country);
        if ($local_currency) {
            $rate = get_rate_for_currency_raw($local_currency);
            if ($rate !== null && (float)$rate > 0) {
                $converted = db_query(
                    "SELECT CAST(? AS DECIMAL(65,30)) / CAST(? AS DECIMAL(65,30)) AS min_usd, CAST(? AS DECIMAL(65,30)) / CAST(? AS DECIMAL(65,30)) AS max_usd",
                    [(string)$display_min, $rate, (string)$display_max, $rate]
                );
                $min_amount = $converted[0]['min_usd'];
                $max_amount = $converted[0]['max_usd'];
            }
        }
    } else {
        // Global plan: display amounts are already in USD
        $min_amount = (string)$display_min;
        $max_amount = (string)$display_max;
    }
}

// Validate required fields
if (empty($name) || empty($payout_interval)) {
    $_SESSION['error'] = __('Name and payout interval are required');
    header('Location: /admin/plans');
    exit;
}

// Validate ROI percentage
if ($roi_percentage < 0 || $roi_percentage > 100) {
    $_SESSION['error'] = __('ROI percentage must be between 0 and 100');
    header('Location: /admin/plans');
    exit;
}

// Validate duration
if ($duration_days <= 0) {
    $_SESSION['error'] = __('Duration must be greater than 0');
    header('Location: /admin/plans');
    exit;
}

// Validate amounts
if ($min_amount < 0 || $max_amount < 0) {
    $_SESSION['error'] = __('Amounts must be non-negative');
    header('Location: /admin/plans');
    exit;
}

if ($max_amount > 0 && $max_amount < $min_amount) {
    $_SESSION['error'] = __('Maximum amount must be greater than or equal to minimum amount');
    header('Location: /admin/plans');
    exit;
}

// Validate payout_interval
if (!in_array($payout_interval, ['hourly', 'daily', 'end_of_term', 'custom'])) {
    $_SESSION['error'] = __('Invalid payout interval');
    header('Location: /admin/plans');
    exit;
}

// If custom, validate multi-unit interval
if ($payout_interval === 'custom') {
    $allowed_types = ['minutes', 'hours', 'days', 'weeks', 'months'];
    if (empty($payout_interval_type) || !in_array($payout_interval_type, $allowed_types)) {
        $_SESSION['error'] = __('Interval type is required for custom intervals');
        header('Location: /admin/plans');
        exit;
    }
    if ($payout_interval_value === null || $payout_interval_value <= 0) {
        $_SESSION['error'] = __('Interval value must be a positive integer');
        header('Location: /admin/plans');
        exit;
    }

    // convert to days for comparison
    switch ($payout_interval_type) {
        case 'minutes':
            $interval_days = $payout_interval_value / 1440;
            break;
        case 'hours':
            $interval_days = $payout_interval_value / 24;
            break;
        case 'days':
            $interval_days = $payout_interval_value;
            break;
        case 'weeks':
            $interval_days = $payout_interval_value * 7;
            break;
        case 'months':
            $interval_days = $payout_interval_value * 30;
            break;
        default:
            $interval_days = $payout_interval_value;
    }

    if ($duration_days > 0 && $interval_days > $duration_days) {
        $_SESSION['error'] = __('Interval cannot exceed plan duration');
        header('Location: /admin/plans');
        exit;
    }
}

// Compounding rule: only allowed for end_of_term payout
if ($is_compounding === 1 && $payout_interval !== 'end_of_term') {
    $_SESSION['error'] = __('Compounding is only available for end of term plans');
    header('Location: /admin/plans');
    exit;
}

// Validate status
if (!in_array($status, ['active', 'archived'])) {
    $_SESSION['error'] = __('Invalid status');
    header('Location: /admin/plans');
    exit;
}

// Sanitize waiting period: clamp value to >= 0, default invalid unit to 'days'
$waiting_period_value = max(0, intval($waiting_period_value));
if (!in_array($waiting_period_unit, ['seconds', 'minutes', 'hours', 'days', 'weeks'])) {
    $waiting_period_unit = 'days';
}

// Validate country: empty string = global; otherwise must be a valid accepted country code
if ($country !== '') {
    $accepted_countries = get_accepted_countries();
    if (!in_array($country, $accepted_countries, true)) {
        $_SESSION['error'] = __('Invalid country selected');
        header('Location: /admin/plans');
        exit;
    }
} else {
    $country = null; // Store NULL for global plans in database
}

try {
    // Update plan
    db_update(
        'investment_plans',
        [
            'name' => $name,
            'roi_percentage' => $roi_percentage,
            'duration_days' => $duration_days,
            'min_amount' => $min_amount,
            'max_amount' => $max_amount,
            'payout_interval' => $payout_interval,
            'payout_interval_type' => $payout_interval === 'custom' ? $payout_interval_type : null,
            'payout_interval_value' => $payout_interval === 'custom' ? $payout_interval_value : null,
            'is_compounding' => $is_compounding,
            'capital_return' => $capital_return,
            'is_featured' => $is_featured,
            'status' => $status,
            'waiting_period_value' => $waiting_period_value,
            'waiting_period_unit' => $waiting_period_unit,
            'country' => $country,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$plan_id]
    );

    $_SESSION['success'] = __('Investment plan updated successfully');
    header('Location: /admin/plans');
    exit;
} catch (Exception $e) {
    error_log('Plan update error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while updating the plan');
    header('Location: /admin/plans');
    exit;
}
