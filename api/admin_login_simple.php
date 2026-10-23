<?php
/**
 * Simplified Admin Login Test
 */

// Clean any output buffer to prevent HTML in JSON response
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($input['username']) || empty($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

// Sanitize input
$username = trim($input['username']);
$password = trim($input['password']);

try {
    // Include database config
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    
    // Query admin_users table
    $query = "SELECT admin_id, username, email, password_hash, full_name, role, is_active 
              FROM admin_users 
              WHERE username = ? AND is_active = 1";

    $stmt = $db->prepare($query);
    $stmt->execute([$username]);

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch();
        
        if (password_verify($password, $admin['password_hash'])) {
            // Success - don't start session for now, just return success
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
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
