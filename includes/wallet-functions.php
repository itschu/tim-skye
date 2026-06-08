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
 * Wallet management functions
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Get user balance from users table
 * @param int $user_id
 * @return float
 */
function get_user_balance($user_id)
{
    $row = db_query("SELECT balance FROM users WHERE id = ?", [$user_id]);
    if (!$row || count($row) === 0) {
        return 0.00;
    }
    return (float)$row[0]['balance'];
}

/**
 * Get locked balance (pending withdrawals)
 * @param int $user_id
 * @return float
 */
function get_locked_balance($user_id)
{
    $row = db_query("SELECT COALESCE(SUM(amount),0) AS locked FROM withdrawals WHERE user_id = ? AND status = 'pending'", [$user_id]);
    if (!$row || count($row) === 0) {
        return 0.00;
    }
    return (float)$row[0]['locked'];
}

/**
 * Get available (spendable) balance
 * @param int $user_id
 * @return float
 */
function get_available_balance($user_id)
{
    $balance = get_user_balance($user_id);
    $locked = get_locked_balance($user_id);
    return $balance - $locked;
}

/**
 * Check if user has sufficient available balance
 * @param int $user_id
 * @param float $amount
 * @return bool
 */
function has_sufficient_balance($user_id, $amount)
{
    return get_available_balance($user_id) >= (float)$amount;
}

/**
 * Create a transaction record
 * @param int $user_id
 * @param string $type
 * @param float $amount
 * @param string $status
 * @param string|null $details
 * @param int|null $source_id  // optional link to deposits/withdrawals
 * @return int|false
 */
function create_transaction($user_id, $type, $amount, $status = 'pending', $details = null, $source_id = null)
{
    $valid_types = ['deposit', 'withdrawal', 'profit', 'referral', 'investment', 'refund', 'cancellation_penalty'];
    if (!in_array($type, $valid_types)) {
        error_log("[" . date('c') . "] Invalid transaction type: $type\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
    $data = [
        'user_id' => $user_id,
        'type' => $type,
        'amount' => number_format((float)$amount, 15, '.', ''),
        'status' => $status,
        'details' => $details,
        'source_id' => $source_id,
        'created_at' => date('Y-m-d H:i:s')
    ];
    try {
        return db_insert('transactions', $data);
    } catch (Exception $e) {
        error_log("[" . date('c') . "] Transaction insert error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Credit wallet and log transaction
 * @param int $user_id
 * @param float $amount
 * @param string $type
 * @param string|null $details
 * @param PDO|null $existing_db Optional existing database connection for nested transactions
 * @return int|false transaction id
 */
function credit_wallet($user_id, $amount, $type, $details = null, $existing_db = null)
{
    if ($amount <= 0) {
        return false;
    }
    $valid_types = ['deposit', 'profit', 'referral', 'refund'];
    if (!in_array($type, $valid_types)) {
        error_log("[" . date('c') . "] Invalid credit type: $type\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
    $db = $existing_db ?? db_connect();
    $is_nested = ($existing_db !== null);
    try {
        if (!$is_nested) {
            $db->beginTransaction();
        }
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([number_format((float)$amount, 15, '.', ''), $user_id]);
        $tx_id = create_transaction($user_id, $type, $amount, 'completed', $details);
        if (!$is_nested) {
            $db->commit();
        }
        return $tx_id;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Credit wallet error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Debit wallet and log transaction
 * @param int $user_id
 * @param float $amount
 * @param string $type
 * @param string|null $details
 * @param PDO|null $existing_db Optional existing database connection for nested transactions
 * @return int|false transaction id
 */
function debit_wallet($user_id, $amount, $type, $details = null, $existing_db = null)
{
    if ($amount <= 0) {
        return false;
    }
    if (!has_sufficient_balance($user_id, $amount)) {
        return false;
    }
    $valid_types = ['withdrawal', 'investment'];
    if (!in_array($type, $valid_types)) {
        error_log("[" . date('c') . "] Invalid debit type: $type\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
    $db = $existing_db ?? db_connect();
    $is_nested = ($existing_db !== null);
    try {
        if (!$is_nested) {
            $db->beginTransaction();
        }
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([number_format((float)$amount, 15, '.', ''), $user_id]);
        $tx_id = create_transaction($user_id, $type, $amount, 'completed', $details);
        if (!$is_nested) {
            $db->commit();
        }
        return $tx_id;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Debit wallet error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}
