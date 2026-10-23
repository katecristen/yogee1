<?php
/**
 * Reservation Handler for Yogee System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/Reservation.php';
require_once __DIR__ . '/../includes/session.php';

$reservation = new Reservation();

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGetRequest() {
    global $reservation;
    
    // Get packages for dropdown
    if (isset($_GET['action']) && $_GET['action'] === 'packages') {
        try {
            $packages = $reservation->getPackages();
            echo json_encode(['success' => true, 'packages' => $packages]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching packages: ' . $e->getMessage()]);
        }
        return;
    }
    
    // Get toppings
    if (isset($_GET['action']) && $_GET['action'] === 'toppings') {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : null;
            $toppings = $reservation->getToppings($type);
            echo json_encode(['success' => true, 'toppings' => $toppings]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching toppings: ' . $e->getMessage()]);
        }
        return;
    }
    
    // Get syrups
    if (isset($_GET['action']) && $_GET['action'] === 'syrups') {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : null;
            $syrups = $reservation->getSyrups($type);
            echo json_encode(['success' => true, 'syrups' => $syrups]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching syrups: ' . $e->getMessage()]);
        }
        return;
    }
    
    // Get fruits
    if (isset($_GET['action']) && $_GET['action'] === 'fruits') {
        try {
            $fruits = $reservation->getFruits();
            echo json_encode(['success' => true, 'fruits' => $fruits]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching fruits: ' . $e->getMessage()]);
        }
        return;
    }
    
    // Get user reservations
    if (isset($_GET['action']) && $_GET['action'] === 'user_reservations') {
        if (!SessionManager::isUserLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            return;
        }
        
        try {
            $user_id = SessionManager::getCurrentUserId();
            $reservations = $reservation->getUserReservations($user_id);
            echo json_encode(['success' => true, 'reservations' => $reservations]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching reservations: ' . $e->getMessage()]);
        }
        return;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid GET request']);
}

function handlePostRequest() {
    global $reservation;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Handle reservation creation
    if (isset($input['action']) && $input['action'] === 'create') {
        createReservation($input);
        return;
    }
    
    // Handle reservation cancellation
    if (isset($input['action']) && $input['action'] === 'cancel') {
        cancelReservation($input);
        return;
    }
    
    // Default: treat as reservation creation
    createReservation($input);
}

function createReservation($input) {
    global $reservation;
    
    // Validate required fields
    $required_fields = ['customer_name', 'contact_number', 'email', 'event_datetime', 'package_id'];
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
        return;
    }
    
    // Validate email format
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate phone number
    $phone = preg_replace('/[^0-9]/', '', $input['contact_number']);
    if (strlen($phone) !== 11 || !preg_match('/^09[0-9]{9}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use 09XXXXXXXXX']);
        return;
    }
    
    // Validate event datetime
    $event_datetime = DateTime::createFromFormat('Y-m-d\TH:i', $input['event_datetime']);
    if (!$event_datetime) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid event date/time format']);
        return;
    }
    
    // Check if event is in the future
    $now = new DateTime();
    if ($event_datetime <= $now) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event date/time must be in the future']);
        return;
    }
    
    // Check if event is at least 3 days in advance
    $min_advance = new DateTime('+3 days');
    if ($event_datetime < $min_advance) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event must be booked at least 3 days in advance']);
        return;
    }
    
    // Validate package ID
    $package_id = (int)$input['package_id'];
    if ($package_id < 1 || $package_id > 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid package selection']);
        return;
    }
    
    // Prepare data
    $data = [
        'user_id' => SessionManager::getCurrentUserId(), // Can be null for guest bookings
        'customer_name' => trim($input['customer_name']),
        'contact_number' => $phone,
        'email' => $email,
        'event_datetime' => $event_datetime->format('Y-m-d H:i:s'),
        'package_id' => $package_id,
        'additional_notes' => isset($input['additional_notes']) ? trim($input['additional_notes']) : null
    ];
    
    try {
        $result = $reservation->createReservation($data);
        
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
}

function cancelReservation($input) {
    global $reservation;
    if (!SessionManager::isUserLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to cancel a reservation.']);
        return;
    }
    
    if (empty($input['reservation_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        return;
    }
    
    $reservation_id = (int)$input['reservation_id'];
    $user_id = SessionManager::getCurrentUserId();
    
    try {
        $result = $reservation->cancelReservation($reservation_id, $user_id);
        
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
}
?>
