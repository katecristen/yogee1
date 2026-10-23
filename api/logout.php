<?php
/**
 * Logout Handler for Yogee System
 */

require_once __DIR__ . '/../includes/session.php';

// Logout user
SessionManager::logout();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully',
    'redirect' => 'landing page.html'
]);
?>
