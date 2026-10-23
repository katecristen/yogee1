<?php
/**
 * Debug Login Issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Login Debug Test</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    $db = getDB();
    echo "<p style='color:green;'>✅ Database connection successful</p>";
    
    // Check if admin_users table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'admin_users'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "<p style='color:green;'>✅ admin_users table exists</p>";
        
        // Check admin users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM admin_users");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<p>Admin users count: <strong>" . $result['count'] . "</strong></p>";
        
        // Show admin users
        $stmt = $db->prepare("SELECT username, email, full_name, is_active FROM admin_users");
        $stmt->execute();
        echo "<table border='1' style='border-collapse:collapse;'>";
        echo "<tr><th>Username</th><th>Email</th><th>Full Name</th><th>Active</th></tr>";
        while ($admin = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['full_name']) . "</td>";
            echo "<td>" . ($admin['is_active'] ? 'YES' : 'NO') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ admin_users table missing</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 2: Session Manager
echo "<h2>2. Session Manager Test</h2>";
try {
    require_once __DIR__ . '/includes/session.php';
    echo "<p style='color:green;'>✅ SessionManager loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ SessionManager error: " . $e->getMessage() . "</p>";
}

// Test 3: Test admin login directly
echo "<h2>3. Direct Admin Login Test</h2>";
if (isset($_POST['test_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $query = "SELECT admin_id, username, email, password_hash, full_name, role, is_active 
                  FROM admin_users 
                  WHERE username = :username AND is_active = 1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch();
            echo "<p>Found admin: " . htmlspecialchars($admin['username']) . "</p>";
            
            if (password_verify($password, $admin['password_hash'])) {
                echo "<p style='color:green;'>✅ Password verification successful!</p>";
                echo "<pre>" . print_r($admin, true) . "</pre>";
            } else {
                echo "<p style='color:red;'>❌ Password verification failed</p>";
                echo "<p>Hash in DB: " . $admin['password_hash'] . "</p>";
                echo "<p>Password entered: " . $password . "</p>";
            }
        } else {
            echo "<p style='color:red;'>❌ Admin user not found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Login test error: " . $e->getMessage() . "</p>";
    }
}
?>

<form method="POST">
    <h3>Test Admin Login</h3>
    <label>Username: <input type="text" name="username" value="test" required></label><br><br>
    <label>Password: <input type="password" name="password" value="12345678" required></label><br><br>
    <button type="submit" name="test_login">Test Login</button>
</form>

<h2>4. Test API Endpoints</h2>
<p><a href="api/admin_login.php" target="_blank">Test admin_login.php</a> (should show method not allowed)</p>
<p><a href="api/login.php" target="_blank">Test login.php</a> (should show method not allowed)</p>
