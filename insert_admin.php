<?php
/**
 * Insert New Admin User into Database
 * Username: test
 * Password: 12345678
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    // New admin user details
    $username = 'test';
    $password = '12345678';
    $email = 'test@yogee.com';
    $full_name = 'Test Admin';
    $role = 'admin';
    
    // Hash the password using PHP's password_hash function
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<style>body{font-family:Arial;padding:20px;max-width:800px;margin:0 auto;}</style>";
    echo "<h1>🔧 Insert New Admin User</h1>";
    echo "<hr>";
    
    // Check if username already exists
    $checkQuery = "SELECT admin_id, username FROM admin_users WHERE username = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$username]);
    
    if ($checkStmt->rowCount() > 0) {
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color:orange;'>⚠️ Admin user with username '<strong>$username</strong>' already exists!</p>";
        echo "<p>Existing Admin ID: " . $existingUser['admin_id'] . "</p>";
        echo "<p style='color:blue;'>Updating the password for existing user...</p>";
        
        // Update existing user's password
        $updateQuery = "UPDATE admin_users SET 
                        password_hash = ?, 
                        email = ?,
                        full_name = ?,
                        updated_at = NOW()
                        WHERE username = ?";
        
        $updateStmt = $db->prepare($updateQuery);
        $result = $updateStmt->execute([$password_hash, $email, $full_name, $username]);
        
        if ($result) {
            echo "<p style='color:green;'>✅ Successfully updated admin user '<strong>$username</strong>'</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to update admin user</p>";
            exit;
        }
        
    } else {
        echo "<p style='color:blue;'>Creating new admin user '<strong>$username</strong>'...</p>";
        
        // Insert new admin user
        $insertQuery = "INSERT INTO admin_users (username, password_hash, email, full_name, role, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, 1, NOW())";
        
        $insertStmt = $db->prepare($insertQuery);
        $result = $insertStmt->execute([$username, $password_hash, $email, $full_name, $role]);
        
        if ($result) {
            $newAdminId = $db->lastInsertId();
            echo "<p style='color:green;'>✅ Successfully created new admin user '<strong>$username</strong>'</p>";
            echo "<p>New Admin ID: <strong>$newAdminId</strong></p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to create admin user</p>";
            print_r($insertStmt->errorInfo());
            exit;
        }
    }
    
    // Verify the insertion/update by fetching the user
    echo "<hr>";
    echo "<h2>🔍 Verification</h2>";
    
    $verifyQuery = "SELECT * FROM admin_users WHERE username = ?";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute([$username]);
    
    if ($verifyStmt->rowCount() > 0) {
        $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div style='background:#f0f8ff;padding:15px;border-radius:5px;margin:10px 0;'>";
        echo "<h3>📋 Admin User Details</h3>";
        echo "<table style='width:100%;border-collapse:collapse;'>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Admin ID:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['admin_id'] . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Username:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['username'] . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Email:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['email'] . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Full Name:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['full_name'] . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Role:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['role'] . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Active:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . ($admin['is_active'] ? 'YES' : 'NO') . "</td></tr>";
        echo "<tr><td style='padding:5px;border:1px solid #ddd;'><strong>Created:</strong></td><td style='padding:5px;border:1px solid #ddd;'>" . $admin['created_at'] . "</td></tr>";
        echo "</table>";
        echo "</div>";
        
        // Test password verification
        if (password_verify($password, $admin['password_hash'])) {
            echo "<p style='color:green;'>✅ Password verification: <strong>WORKING</strong></p>";
        } else {
            echo "<p style='color:red;'>❌ Password verification: <strong>FAILED</strong></p>";
        }
        
    } else {
        echo "<p style='color:red;'>❌ Verification failed - User not found in database</p>";
    }
    
    // Show login credentials
    echo "<hr>";
    echo "<div style='background:#d4edda;padding:20px;border-radius:5px;margin:20px 0;border:1px solid #c3e6cb;'>";
    echo "<h2 style='color:#155724;margin-top:0;'>🎉 Admin User Ready!</h2>";
    echo "<div style='background:white;padding:15px;border-radius:5px;border:2px solid #28a745;'>";
    echo "<h3 style='margin-top:0;color:#333;'>Login Credentials:</h3>";
    echo "<p style='font-size:18px;margin:10px 0;'><strong>Username:</strong> <span style='color:#007bff;font-weight:bold;'>test</span></p>";
    echo "<p style='font-size:18px;margin:10px 0;'><strong>Password:</strong> <span style='color:#007bff;font-weight:bold;'>12345678</span></p>";
    echo "</div>";
    echo "</div>";
    
    echo "<div style='text-align:center;margin:30px 0;'>";
    echo "<a href='Login.html' style='background:#007cba;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;margin:10px;font-size:16px;'>🔐 Test Login</a> ";
    echo "<a href='admin_test.html' style='background:#28a745;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;margin:10px;font-size:16px;'>🧪 Test Page</a>";
    echo "</div>";
    
    // Show all admin users
    echo "<hr>";
    echo "<h2>👥 All Admin Users in Database</h2>";
    $allAdminsQuery = "SELECT admin_id, username, email, full_name, role, is_active, created_at FROM admin_users ORDER BY admin_id";
    $allAdminsStmt = $db->prepare($allAdminsQuery);
    $allAdminsStmt->execute();
    
    if ($allAdminsStmt->rowCount() > 0) {
        echo "<table style='width:100%;border-collapse:collapse;margin:10px 0;'>";
        echo "<tr style='background:#f8f9fa;'>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>ID</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Username</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Email</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Full Name</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Role</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Active</th>";
        echo "<th style='padding:10px;border:1px solid #ddd;text-align:left;'>Created</th>";
        echo "</tr>";
        
        while ($adminUser = $allAdminsStmt->fetch(PDO::FETCH_ASSOC)) {
            $rowStyle = $adminUser['username'] === $username ? 'background:#fff3cd;' : '';
            echo "<tr style='$rowStyle'>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . $adminUser['admin_id'] . "</td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'><strong>" . $adminUser['username'] . "</strong></td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . $adminUser['email'] . "</td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . $adminUser['full_name'] . "</td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . $adminUser['role'] . "</td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . ($adminUser['is_active'] ? '✅' : '❌') . "</td>";
            echo "<td style='padding:8px;border:1px solid #ddd;'>" . date('Y-m-d H:i', strtotime($adminUser['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
