<?php
/**
 * Admin Login Handler for Yogee System
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

// Clean any output buffer to prevent HTML in JSON response
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if files exist before including
$configPath = __DIR__ . '/../config/database.php';
$sessionPath = __DIR__ . '/../includes/session.php';

if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Database config not found']);
    exit();
}

if (!file_exists($sessionPath)) {
    echo json_encode(['success' => false, 'message' => 'Session manager not found']);
    exit();
}

require_once $configPath;
require_once $sessionPath;

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($input['username']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

// Sanitize input
$username = trim($input['username']);
$password = trim($input['password']);

try {
    $db = getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Query admin_users table
    $query = "SELECT admin_id, username, email, password_hash, full_name, role, is_active 
              FROM admin_users 
              WHERE username = :username AND is_active = 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch();
        
        if (password_verify($password, $admin['password_hash'])) {
            // Update last login
            $updateStmt = $db->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE admin_id = :admin_id");
            $updateStmt->bindParam(':admin_id', $admin['admin_id']);
            $updateStmt->execute();
            
            // Start admin session
            SessionManager::startAdminSession([
                'admin_id' => $admin['admin_id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'full_name' => $admin['full_name'],
                'role' => $admin['role']
            ]);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Admin login successful!',
                'admin' => [
                    'admin_id' => $admin['admin_id'],
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'full_name' => $admin['full_name'],
                    'role' => $admin['role']
                ],
                'redirect' => 'admin.html'
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password!'
            ]);
        }
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password!'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
