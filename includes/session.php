<?php
/**
 * Session Management for Yogee System
 */

require_once __DIR__ . '/../config/neon-auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
}

class SessionManager {
      // Start user session
    public static function startUserSession($user_data) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['neon_auth_id'] = $user_data['auth_id'];
        $_SESSION['user_email'] = $user_data['email'];
        $_SESSION['user_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return true;
    }
    
    // Start admin session
    public static function startAdminSession($admin_data) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin_data['admin_id'];
        $_SESSION['admin_username'] = $admin_data['username'];
        $_SESSION['admin_email'] = $admin_data['email'];
        $_SESSION['admin_name'] = $admin_data['full_name'];
        $_SESSION['admin_role'] = $admin_data['role'];
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return true;
    }
    
    // Check if admin is logged in
    public static function isAdminLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    // Get current admin ID
    public static function getCurrentAdminId() {
        return isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
    }
    
    // Get current admin data
    public static function getCurrentAdmin() {
        if (self::isAdminLoggedIn()) {
            return [
                'admin_id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'email' => $_SESSION['admin_email'],
                'full_name' => $_SESSION['admin_name'],
                'role' => $_SESSION['admin_role']
            ];
        }
        return null;
    }
    
    // Check if user is logged in
    public static function isUserLoggedIn() {
        if (empty($_SESSION['user_logged_in']) || empty($_SESSION['neon_auth_cookie'])) return false;
        static $valid = null;
        if ($valid !== null) return $valid;
        try {
            $session = neonAuthRequest('get-session');
            $valid = !empty($session['user']['id']) && $session['user']['id'] === ($_SESSION['neon_auth_id'] ?? null);
            if ($valid) {
                require_once __DIR__ . '/../config/database.php';
                $stmt = getDB()->prepare('SELECT 1 FROM users WHERE user_id = ? AND auth_id = ? AND is_active = 1');
                $stmt->execute([$_SESSION['user_id'], $_SESSION['neon_auth_id']]);
                $valid = (bool)$stmt->fetchColumn();
            }
        } catch (Throwable $e) { $valid = false; }
        return $valid;
    }
    
    // Get current user ID
    public static function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
      // Get current user data
    public static function getCurrentUser() {
        if (self::isUserLoggedIn()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name']
            ];
        }
        return null;
    }
    
    // Update user session with fresh data
    public static function updateUserSession($user_id) {
        require_once __DIR__ . '/../classes/User.php';
        $user = new User();
        $userData = $user->getUserById($user_id);
        
        if ($userData) {
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
            return true;
        }
        return false;
    }
    
    // Logout user
    public static function logout() {
        if (!empty($_SESSION['neon_auth_cookie'])) {
            try { neonAuthRequest('sign-out', []); } catch (Throwable $e) { error_log('Neon Auth sign-out failed'); }
        }
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        return true;
    }
    
    // Check session timeout (optional - 2 hours)
    public static function checkSessionTimeout($timeout_duration = 7200) {
        if (isset($_SESSION['login_time'])) {
            if ((time() - $_SESSION['login_time']) > $timeout_duration) {
                self::logout();
                return false;
            }
        }
        return true;
    }
    
    // Set flash message
    public static function setFlashMessage($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    // Get and clear flash message
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
      // Require login (redirect if not logged in)
    public static function requireLogin($redirect_url = 'Login.html') {
        if (!self::isUserLoggedIn()) {
            header("Location: " . $redirect_url);
            exit();
        }    }
}

// Global helper functions
function isLoggedIn() {
    return SessionManager::isUserLoggedIn();
}

function getCurrentUser() {
    return SessionManager::getCurrentUser();
}

function requireLogin($redirect_url = 'Login.html') {
    SessionManager::requireLogin($redirect_url);
}

function setFlashMessage($type, $message) {
    SessionManager::setFlashMessage($type, $message);
}

function getFlashMessage() {
    return SessionManager::getFlashMessage();
}
?>
