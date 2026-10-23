<?php
/**
 * Session Check Handler for Yogee System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../classes/User.php';

try {
    // Check session timeout
    if (!SessionManager::checkSessionTimeout()) {
        echo json_encode([
            'success' => false,
            'message' => 'Session expired',
            'user' => null
        ]);
        exit();
    }

    if (SessionManager::isUserLoggedIn()) {
        // Get full user data from database
        $user = new User();
        $user_id = SessionManager::getCurrentUserId();
        $userData = $user->getUserById($user_id);

        if ($userData) {
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user' => $userData
            ]);
        } else {
            // User not found in database, logout
            SessionManager::logout();
            echo json_encode([
                'success' => false,
                'logged_in' => false,
                'message' => 'User not found',
                'user' => null
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false,
            'user' => null
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'user' => null
    ]);
}
?>
