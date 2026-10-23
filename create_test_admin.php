<?php
/**
 * Create Test Admin User
 * Username: test
 * Password: 12345678
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    // Admin user details
    $username = 'test';
    $password = '12345678';
    $email = 'test@yogee.com';
    $full_name = 'Test Admin';
    $role = 'admin';
    
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h2>Creating Test Admin User</h2>\n";
    echo "<p>Username: <strong>$username</strong></p>\n";
    echo "<p>Password: <strong>$password</strong></p>\n";
    echo "<p>Email: <strong>$email</strong></p>\n";
    echo "<p>Role: <strong>$role</strong></p>\n";
    echo "<hr>\n";
    
    // Check if user already exists
    $checkQuery = "SELECT admin_id FROM admin_users WHERE username = :username";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':username', $username);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ User '$username' already exists. Updating password...</p>\n";
        
        // Update existing user
        $updateQuery = "UPDATE admin_users SET 
                        password_hash = :password_hash,
                        email = :email,
                        full_name = :full_name,
                        role = :role,
                        is_active = 1,
                        updated_at = NOW()
                        WHERE username = :username";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':password_hash', $password_hash);
        $updateStmt->bindParam(':email', $email);
        $updateStmt->bindParam(':full_name', $full_name);
        $updateStmt->bindParam(':role', $role);
        $updateStmt->bindParam(':username', $username);
        
        if ($updateStmt->execute()) {
            echo "<p style='color: green;'>✓ Test admin user updated successfully!</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to update test admin user</p>\n";
        }
    } else {
        // Insert new user
        $insertQuery = "INSERT INTO admin_users (username, password_hash, email, full_name, role, is_active, created_at) 
                        VALUES (:username, :password_hash, :email, :full_name, :role, 1, NOW())";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':password_hash', $password_hash);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':full_name', $full_name);
        $insertStmt->bindParam(':role', $role);
        
        if ($insertStmt->execute()) {
            echo "<p style='color: green;'>✓ Test admin user created successfully!</p>\n";
            echo "<p>Admin ID: " . $db->lastInsertId() . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to create test admin user</p>\n";
        }
    }
    
    // Verify the user was created/updated
    $verifyQuery = "SELECT admin_id, username, email, full_name, role, is_active, created_at FROM admin_users WHERE username = :username";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindParam(':username', $username);
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() > 0) {
        $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        echo "<h3>✓ Verification Successful</h3>\n";
        echo "<ul>\n";
        echo "<li>Admin ID: " . $admin['admin_id'] . "</li>\n";
        echo "<li>Username: " . $admin['username'] . "</li>\n";
        echo "<li>Email: " . $admin['email'] . "</li>\n";
        echo "<li>Full Name: " . $admin['full_name'] . "</li>\n";
        echo "<li>Role: " . $admin['role'] . "</li>\n";
        echo "<li>Active: " . ($admin['is_active'] ? 'YES' : 'NO') . "</li>\n";
        echo "<li>Created: " . $admin['created_at'] . "</li>\n";
        echo "</ul>\n";
        
        // Test password verification
        if (password_verify($password, $password_hash)) {
            echo "<p style='color: green;'>✓ Password verification: WORKING</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Password verification: FAILED</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ Verification failed - user not found</p>\n";
    }
    
    echo "<hr>\n";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>🎉 Test Admin Created Successfully!</h3>\n";
    echo "<p><strong>Login Credentials:</strong></p>\n";
    echo "<div style='background: white; padding: 10px; border-radius: 3px;'>\n";
    echo "Username: <strong>test</strong><br>\n";
    echo "Password: <strong>12345678</strong>\n";
    echo "</div>\n";
    echo "</div>\n";
    
    echo "<p><a href='Login.html' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login Now</a></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
}
ul {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
}
</style>
