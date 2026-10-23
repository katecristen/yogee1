<?php
/**
 * Admin Statistics API
 * Provides dashboard statistics for the admin panel
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
    
    // Get total reservations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations");
    $stmt->execute();
    $totalReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get confirmed reservations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE status = 'confirmed'");
    $stmt->execute();
    $confirmedReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get pending reservations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'");
    $stmt->execute();
    $pendingReservations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stats = [
        'total_reservations' => $totalReservations,
        'confirmed_reservations' => $confirmedReservations,
        'pending_reservations' => $pendingReservations,
        'total_users' => $totalUsers
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving statistics'
    ]);
}
?>
