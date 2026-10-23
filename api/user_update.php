<?php
/**
 * User Profile Update Handler for Yogee System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/session.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!SessionManager::isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['first_name', 'last_name'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Validate phone number if provided
if (!empty($input['phone'])) {
    $phone = preg_replace('/[^0-9]/', '', $input['phone']);
    if (strlen($phone) !== 11 || !preg_match('/^09[0-9]{9}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use 09XXXXXXXXX']);
        exit();
    }
    $input['phone'] = $phone;
}

// Validate date of birth if provided
if (!empty($input['date_of_birth'])) {
    $dob = DateTime::createFromFormat('Y-m-d', $input['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $input['date_of_birth']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth format']);
        exit();
    }
    
    // Check if date is not in the future
    $today = new DateTime();
    if ($dob > $today) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date of birth cannot be in the future']);
        exit();
    }
}

try {
    $user = new User();
    $user_id = SessionManager::getCurrentUserId();
    
    $data = [
        'user_id' => $user_id,
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name']),
        'phone' => $input['phone'] ?? null,
        'date_of_birth' => $input['date_of_birth'] ?? null
    ];
    
    $result = $user->updateProfile($data);
    
    if ($result['success']) {
        // Update session data
        SessionManager::updateUserSession($user_id);
        
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
