<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/neon-auth.php';

class User {
    private $conn;
    public function __construct() { $this->conn = getDB(); }
    public function register($data) {
        if (!empty($data['dateOfBirth'])) {
            $date = DateTime::createFromFormat('!Y-m-d', $data['dateOfBirth']);
            if (!$date || $date->format('Y-m-d') !== $data['dateOfBirth']) return ['success' => false, 'message' => 'Invalid date of birth.'];
        }
        try {
            $auth = neonAuthRequest('sign-up/email', ['email' => $data['email'], 'password' => $data['password'], 'name' => $data['firstName'] . ' ' . $data['lastName']], false);
            if (empty($auth['user']['id'])) throw new RuntimeException('Could not create account.');
            $stmt = $this->conn->prepare('INSERT INTO users (auth_id, first_name, last_name, email, phone, date_of_birth) VALUES (?, ?, ?, ?, ?, ?) RETURNING user_id');
            $stmt->execute([$auth['user']['id'], $data['firstName'], $data['lastName'], $auth['user']['email'], $data['phone'], $data['dateOfBirth']]);
            return ['success' => true, 'message' => 'Account created successfully!', 'user_id' => $stmt->fetchColumn()];
        } catch (RuntimeException $e) { return ['success' => false, 'message' => $e->getMessage()]; }
    }
    public function login($email, $password) {
        unset($_SESSION['neon_auth_cookie'], $_SESSION['neon_auth_id'], $_SESSION['user_logged_in'], $_SESSION['user_id']);
        try {
            $auth = neonAuthRequest('sign-in/email', ['email' => $email, 'password' => $password], false);
            if (empty($auth['user']['id'])) return ['success' => false, 'message' => 'Invalid email or password.'];
            // Recover an Auth account if an earlier signup's profile write failed.
            // ON CONFLICT never reactivates or overwrites an existing profile.
            $names = explode(' ', $auth['user']['name'] ?? 'Customer', 2);
            $recover = $this->conn->prepare('INSERT INTO users (auth_id, first_name, last_name, email) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING');
            $recover->execute([$auth['user']['id'], $names[0], $names[1] ?? '', $auth['user']['email']]);
            $stmt = $this->conn->prepare('SELECT user_id, auth_id, first_name, last_name, email FROM users WHERE auth_id = ? AND is_active = 1');
            $stmt->execute([$auth['user']['id']]);
            $user = $stmt->fetch();
            if (!$user) return ['success' => false, 'message' => 'Your account profile is unavailable. Please contact support.'];
            return ['success' => true, 'message' => 'Login successful!', 'user' => $user];
        } catch (RuntimeException $e) {
            error_log('Customer sign-in failed: ' . $e->getMessage());
            unset($_SESSION['neon_auth_cookie'], $_SESSION['neon_auth_id'], $_SESSION['user_logged_in'], $_SESSION['user_id']);
            return ['success' => false, 'message' => 'Sign-in failed. Please check your credentials and try again.'];
        }
    }
    public function emailExists($email) {
        $stmt = $this->conn->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool)$stmt->fetchColumn();
    }
    public function getUserById($id) {
        $stmt = $this->conn->prepare('SELECT user_id, first_name, last_name, email, phone, date_of_birth, created_at FROM users WHERE user_id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function updateProfile($data) {
        $stmt = $this->conn->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
        $stmt->execute([$data['first_name'], $data['last_name'], $data['phone'], $data['date_of_birth'] ?: null, $data['user_id']]);
        return ['success' => true, 'message' => 'Profile updated successfully!'];
    }
}
