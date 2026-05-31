<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Load environment variables
require_once __DIR__ . '/env.php';

/**
 * Create PDO database connection
 * @return PDO
 */
function db_connect() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'tradeonix_db';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    return $pdo;
}

// Global database instance
$db = db_connect();

/**
 * Execute a database query with optional parameters
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return array|PDOStatement
 */
function db_query($sql, $params = []) {
    global $db;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // For SELECT queries, return fetched results
        if (stripos(trim($sql), 'SELECT') === 0) {
            return $stmt->fetchAll();
        }

        // For other queries, return the statement object
        return $stmt;
    } catch (PDOException $e) {
        // Log error to file
        error_log("Database error: " . $e->getMessage() . " Query: $sql Params: " . json_encode($params), 3, __DIR__ . '/../logs/db-errors.log');
        throw $e;
    }
}

/**
 * Insert data into a table
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @return string Last inserted ID
 */
function db_insert($table, $data) {
    global $db;

    $columns = array_keys($data);
    $values = array_values($data);

    $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . str_repeat('?,', count($values)-1) . "?)";

    db_query($sql, $values);

    return $db->lastInsertId();
}

/**
 * Update data in a table
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @param string $where WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function db_update($table, $data, $where, $params = []) {
    $columns = array_keys($data);
    $values = array_values($data);

    $set_clause = "";
    foreach ($columns as $column) {
        $set_clause .= "`$column` = ?, ";
    }
    $set_clause = rtrim($set_clause, ', ');

    $sql = "UPDATE `$table` SET $set_clause WHERE $where";

    $all_params = array_merge($values, $params);

    $stmt = db_query($sql, $all_params);

    return $stmt->rowCount();
}

/**
 * Delete data from a table
 * @param string $table Table name
 * @param string $where WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function db_delete($table, $where, $params = []) {
    $sql = "DELETE FROM `$table` WHERE $where";

    $stmt = db_query($sql, $params);

    return $stmt->rowCount();
}

