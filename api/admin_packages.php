<?php
/**
 * Admin Packages API
 * Manages packages for the admin panel
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
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Admin packages error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing request'
    ]);
}

function handleGet($db) {
    if (isset($_GET['id'])) {
        // Get specific package
        $packageId = $_GET['id'];
        $stmt = $db->prepare("SELECT * FROM packages WHERE package_id = ?");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($package) {
            echo json_encode([
                'success' => true,
                'package' => $package
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Package not found'
            ]);
        }
    } else {
        // Get all packages
        $stmt = $db->prepare("SELECT * FROM packages ORDER BY created_at DESC");
        $stmt->execute();
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'packages' => $packages
        ]);
    }
}

function handlePost($db) {
    // Create new package
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['package_name']) || !isset($input['price'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Package name and price are required'
        ]);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO packages (package_name, description, price, max_guests, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $input['package_name'],
        $input['description'] ?? '',
        $input['price'],
        $input['max_guests'] ?? null
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Package created successfully',
            'package_id' => $db->lastInsertId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error creating package'
        ]);
    }
}

function handlePut($db) {
    // Update package
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['package_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Package ID is required'
        ]);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE packages 
        SET package_name = ?, description = ?, price = ?, max_guests = ?, updated_at = NOW()
        WHERE package_id = ?
    ");
    
    $result = $stmt->execute([
        $input['package_name'],
        $input['description'] ?? '',
        $input['price'],
        $input['max_guests'] ?? null,
        $input['package_id']
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Package updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating package'
        ]);
    }
}

function handleDelete($db) {
    // Delete package
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['package_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Package ID is required'
        ]);
        return;
    }
    
    // Check if package is being used in any reservations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE package_id = ?");
    $stmt->execute([$input['package_id']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete package - it is being used in existing reservations'
        ]);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM packages WHERE package_id = ?");
    $result = $stmt->execute([$input['package_id']]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Package deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting package'
        ]);
    }
}
?>
