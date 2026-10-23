<?php
/**
 * Admin Users API
 * Manages users for the admin panel
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Check if admin is logged in
if (!SessionManager::isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit();
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing request'
    ]);
}

function handleGet($db) {
    if (isset($_GET['id'])) {
        // Get specific user
        $userId = $_GET['id'];
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Remove password from response
            unset($user['password']);
            
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
    } else {
        // Get all users
        $stmt = $db->prepare("SELECT user_id, first_name, last_name, email, phone, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing user ID'
        ]);
        return;
    }
    
    $userId = $input['user_id'];
    
    // Check if user has any reservations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $reservationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($reservationCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete user with existing reservations'
        ]);
        return;
    }
    
    // Delete both identities atomically so the email is available for a future signup.
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM users WHERE user_id = ? RETURNING auth_id');
        $stmt->execute([$userId]);
        $authId = $stmt->fetchColumn();
        if ($authId) {
            $stmt = $db->prepare('DELETE FROM neon_auth."user" WHERE id = ?');
            $stmt->execute([$authId]);
        }
        $db->commit();
        $result = (bool)$authId;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete user'
        ]);
    }
}
?>
