<?php
/**
 * Admin Session Check Handler for Yogee System
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/session.php';

try {
    // Check session timeout
    if (!SessionManager::checkSessionTimeout()) {
        echo json_encode([
            'success' => false,
            'message' => 'Session expired',
            'error_code' => 'SESSION_EXPIRED'
        ]);
        exit();
    }

    // Check if admin is logged in
    if (SessionManager::isAdminLoggedIn()) {
        $admin = SessionManager::getCurrentAdmin();
        
        if ($admin) {
            echo json_encode([
                'success' => true,
                'message' => 'Admin session active',
                'admin' => $admin
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Admin data not found in session',
                'error_code' => 'NO_ADMIN_DATA'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Admin not logged in',
            'error_code' => 'NOT_LOGGED_IN'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>
