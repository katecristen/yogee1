<?php
/**
 * Sample Data Creator for Admin Panel Testing
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Creating Sample Data for Admin Panel Testing</h2>";
    
    // Check if packages exist, if not create some
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM packages");
    $stmt->execute();
    $packageCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($packageCount == 0) {
        echo "<p>Creating sample packages...</p>";
        
        $packages = [
            ['Birthday Package', 'Complete birthday celebration package', 2500.00, 8],
            ['Wedding Package', 'Romantic wedding celebration package', 15000.00, 50],
            ['Corporate Package', 'Professional corporate event package', 8000.00, 25],
            ['Anniversary Package', 'Special anniversary celebration package', 5000.00, 15]
        ];
        
        $stmt = $db->prepare("INSERT INTO packages (package_name, description, price, max_guests) VALUES (?, ?, ?, ?)");
        
        foreach ($packages as $package) {
            $stmt->execute($package);
        }
        
        echo "<p>✅ Sample packages created successfully!</p>";
    } else {
        echo "<p>✅ Packages already exist ($packageCount packages)</p>";
    }
    
    // Check if users exist, if not create some
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($userCount == 0) {
        echo "<p>Creating sample users...</p>";
        
        $users = [
            ['John', 'Doe', 'john.doe@example.com', password_hash('password123', PASSWORD_DEFAULT), '09123456789'],
            ['Jane', 'Smith', 'jane.smith@example.com', password_hash('password123', PASSWORD_DEFAULT), '09987654321'],
            ['Mike', 'Johnson', 'mike.johnson@example.com', password_hash('password123', PASSWORD_DEFAULT), '09111222333'],
            ['Sarah', 'Wilson', 'sarah.wilson@example.com', password_hash('password123', PASSWORD_DEFAULT), '09444555666']
        ];
        
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password, phone) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        
        echo "<p>✅ Sample users created successfully!</p>";
    } else {
        echo "<p>✅ Users already exist ($userCount users)</p>";
    }
    
    // Check if reservations exist, if not create some
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations");
    $stmt->execute();
    $reservationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($reservationCount == 0) {
        echo "<p>Creating sample reservations...</p>";
        
        // Get some user and package IDs
        $stmt = $db->prepare("SELECT user_id FROM users LIMIT 4");
        $stmt->execute();
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $db->prepare("SELECT package_id, price FROM packages LIMIT 4");
        $stmt->execute();
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($userIds) > 0 && count($packages) > 0) {
            $reservations = [
                [$userIds[0], $packages[0]['package_id'], '2024-12-15 14:00:00', 10, $packages[0]['price'], 'confirmed'],
                [$userIds[1], $packages[1]['package_id'], '2024-12-20 18:00:00', 25, $packages[1]['price'], 'pending'],
                [$userIds[2], $packages[2]['package_id'], '2024-12-22 16:00:00', 15, $packages[2]['price'], 'confirmed'],
                [$userIds[0], $packages[3]['package_id'], '2024-12-25 12:00:00', 8, $packages[3]['price'], 'completed']
            ];
            
            $stmt = $db->prepare("INSERT INTO reservations (user_id, package_id, event_date, number_of_guests, total_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($reservations as $reservation) {
                $stmt->execute($reservation);
            }
            
            echo "<p>✅ Sample reservations created successfully!</p>";
        } else {
            echo "<p>❌ Cannot create reservations - no users or packages found</p>";
        }
    } else {
        echo "<p>✅ Reservations already exist ($reservationCount reservations)</p>";
    }
    
    echo "<h3>Final Data Summary:</h3>";
    
    // Show final counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM packages");
    $stmt->execute();
    $packageCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations");
    $stmt->execute();
    $reservationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE status = 'confirmed'");
    $stmt->execute();
    $confirmedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
    $stmt->execute();
    $pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<ul>";
    echo "<li>Total Packages: $packageCount</li>";
    echo "<li>Total Users: $userCount</li>";
    echo "<li>Total Reservations: $reservationCount</li>";
    echo "<li>Confirmed Reservations: $confirmedCount</li>";
    echo "<li>Pending Reservations: $pendingCount</li>";
    echo "</ul>";
    
    echo "<p><strong>You can now test the admin panel with sample data!</strong></p>";
    echo "<p><a href='admin.html'>Go to Admin Panel</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
