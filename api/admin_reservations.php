<?php
/**
 * Admin Reservations API
 * Manages reservations for the admin panel
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
        case 'PUT':
            handlePut($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Admin reservations error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing request'
    ]);
}

function handleGet($db) {
    if (isset($_GET['id'])) {
        // Get specific reservation
        $reservationId = $_GET['id'];
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name, u.email, u.phone, p.package_name,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN packages p ON r.package_id = p.package_id
            WHERE r.reservation_id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reservation) {
            echo json_encode([
                'success' => true,
                'reservation' => $reservation
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Reservation not found'
            ]);
        }
        
    } elseif (isset($_GET['recent'])) {
        // Get recent reservations for dashboard
        $limit = $_GET['recent'];
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name, p.package_name,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN packages p ON r.package_id = p.package_id
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'reservations' => $reservations
        ]);
        
    } else {
        // Get all reservations
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name, u.email, u.phone, p.package_name,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN packages p ON r.package_id = p.package_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'reservations' => $reservations
        ]);
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['reservation_id']) || !isset($input['status'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        return;
    }
    
    $reservationId = $input['reservation_id'];
    $status = $input['status'];
    
    // Validate status
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status'
        ]);
        return;
    }
    
    $stmt = $db->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE reservation_id = ?");
    $result = $stmt->execute([$status, $reservationId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Reservation status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update reservation status'
        ]);
    }
}
?>
