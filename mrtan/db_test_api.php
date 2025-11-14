<?php
/**
 * PHP API for testing the database connection and input handling.
 * This script is called directly by n8n or browser for debugging.
 */

// --- ⚠️ 1. Configuration: Database Credentials (MUST BE CHANGED) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'vtiger_idea');
define('DB_PASS', 'e0fc86d8d85868');
define('DB_NAME', 'vtiger_idea'); // Adjust database name if necessary

// --- 2. Setup and Output Header ---
header('Content-Type: application/json');

// Get JSON data sent by the client (n8n or testing tool)
$input = json_decode(file_get_contents('php://input'), true);

// --- 3. Database Connection Test ---
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    // CONNECTION FAILED: Return detailed error information
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Database Connection Failed',
        'error_detail' => $mysqli->connect_error,
        'config_used' => [
            'host' => DB_HOST,
            'user' => DB_USER
        ]
    ]);
    exit;
}

// --- 4. Success Response ---
// If the script reaches this point, the connection is successful.
$mysqli->close();
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => '✅ Database Connection Successful!',
    'test_input_received' => $input,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>