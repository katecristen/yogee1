<?php
/**
 * Registration Handler for Yogee System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/User.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['firstName', 'lastName', 'email', 'password', 'confirmPassword'];
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

// Validate email format
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate password match
if ($input['password'] !== $input['confirmPassword']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

// Validate password length
if (strlen($input['password']) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
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
if (!empty($input['dateOfBirth'])) {
    $dob = DateTime::createFromFormat('Y-m-d', $input['dateOfBirth']);
    if (!$dob || $dob->format('Y-m-d') !== $input['dateOfBirth']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth format']);
        exit();
    }
    
    // Check if user is at least 13 years old
    $today = new DateTime();
    $age = $today->diff($dob)->y;
    if ($age < 13) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You must be at least 13 years old to register']);
        exit();
    }
}

// Sanitize input data
$data = [
    'firstName' => trim($input['firstName']),
    'lastName' => trim($input['lastName']),
    'email' => $email,
    'phone' => isset($input['phone']) ? $input['phone'] : null,
    'dateOfBirth' => !empty($input['dateOfBirth']) ? $input['dateOfBirth'] : null,
    'password' => $input['password']
];

try {
    $user = new User();
    
    // Check if email already exists
    if ($user->emailExists($data['email'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    
    $result = $user->register($data);
    
    if ($result['success']) {
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
