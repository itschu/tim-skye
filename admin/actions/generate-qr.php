<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
/**
 * Generate QR Code for cryptocurrency address
 * Used by admin/settings.php for auto-generating QR codes
 */

require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/vendor/autoload.php';
require_once ROOT . '/includes/translation-functions.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get user from database
$user_id = $_SESSION['user_id'];
$user = db_query("SELECT * FROM users WHERE id = ?", [$user_id])[0] ?? null;

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get address from request
$address = $_POST['address'] ?? '';
$network = $_POST['network'] ?? '';

if (empty($address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Address is required']);
    exit;
}

try {
    // Create QR code with the address - pass size and margin to constructor
    $qrCode = new QrCode(
        data: $address,
        size: 300,
        margin: 10
    );

    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    // Get base64 encoded image
    $base64 = base64_encode($result->getString());

    echo json_encode([
        'success' => true,
        'qr_code' => 'data:image/png;base64,' . $base64
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate QR code: ' . $e->getMessage()]);
    exit;
}
