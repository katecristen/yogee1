<?php
/**
 * Reservation Management Class for Yogee System
 */

require_once __DIR__ . '/../config/database.php';

class Reservation {
    private $conn;
    private $table_name = "reservations";

    public function __construct() {
        $this->conn = getDB();
    }

    // Create new reservation
    public function createReservation($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, customer_name, contact_number, email, event_datetime, 
                   package_id, additional_notes, total_amount, status, payment_status) 
                  VALUES (:user_id, :customer_name, :contact_number, :email, :event_datetime, 
                          :package_id, :additional_notes, :total_amount, 'pending', 'unpaid') RETURNING reservation_id";

        $stmt = $this->conn->prepare($query);

        // Get package price
        $package_price = $this->getPackagePrice($data['package_id']);
        $total_amount = $package_price + 2000; // Include reservation fee

        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':customer_name', $data['customer_name']);
        $stmt->bindParam(':contact_number', $data['contact_number']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':event_datetime', $data['event_datetime']);
        $stmt->bindParam(':package_id', $data['package_id']);
        $stmt->bindParam(':additional_notes', $data['additional_notes']);
        $stmt->bindParam(':total_amount', $total_amount);

        try {
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Reservation created successfully!',
                    'reservation_id' => $stmt->fetchColumn(),
                    'total_amount' => $total_amount
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Failed to create reservation: ' . $e->getMessage()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create reservation!'
        ];
    }

    // Get package price
    private function getPackagePrice($package_id) {
        $query = "SELECT price FROM packages WHERE package_id = :package_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':package_id', $package_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch();
            return $result['price'];
        }
        throw new InvalidArgumentException('Package not found');
    }

    // Get all packages
    public function getPackages() {
        $query = "SELECT package_id, package_name, package_number, price, description,
                         classic_sauces, premium_sauces, classic_toppings, premium_toppings, 
                         fruit_choices, setup_hours
                  FROM packages 
                  WHERE is_active = 1 
                  ORDER BY package_number";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Get reservation by ID
    public function getReservationById($reservation_id) {
        $query = "SELECT r.*, p.package_name, p.price as package_price
                  FROM " . $this->table_name . " r
                  JOIN packages p ON r.package_id = p.package_id
                  WHERE r.reservation_id = :reservation_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reservation_id', $reservation_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch();
        }
        return false;
    }

    // Get user reservations
    public function getUserReservations($user_id) {
        $query = "SELECT r.*, p.package_name, p.price as package_price
                  FROM " . $this->table_name . " r
                  JOIN packages p ON r.package_id = p.package_id
                  WHERE r.user_id = :user_id
                  ORDER BY r.event_datetime DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Update reservation status
    public function updateReservationStatus($reservation_id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = CURRENT_TIMESTAMP
                  WHERE reservation_id = :reservation_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':reservation_id', $reservation_id);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Reservation status updated successfully!'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update reservation status!'
        ];
    }

    // Cancel reservation
    public function cancelReservation($reservation_id, $user_id = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
                  WHERE reservation_id = :reservation_id";
        
        if ($user_id) {
            $query .= " AND user_id = :user_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reservation_id', $reservation_id);
        
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Reservation cancelled successfully!'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to cancel reservation!'
        ];
    }

    // Get available time slots for a date
    public function getAvailableSlots($date) {
        $query = "SELECT event_datetime::time as booked_time 
                  FROM " . $this->table_name . " 
                  WHERE DATE(event_datetime) = :date 
                  AND status NOT IN ('cancelled')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->execute();

        $bookedTimes = [];
        while ($row = $stmt->fetch()) {
            $bookedTimes[] = $row['booked_time'];
        }

        return $bookedTimes;
    }

    // Get toppings
    public function getToppings($type = null) {
        $query = "SELECT * FROM toppings WHERE is_available = 1";
        if ($type) {
            $query .= " AND topping_type = :type";
        }
        $query .= " ORDER BY topping_type, topping_name";

        $stmt = $this->conn->prepare($query);
        if ($type) {
            $stmt->bindParam(':type', $type);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Get syrups
    public function getSyrups($type = null) {
        $query = "SELECT * FROM syrups WHERE is_available = 1";
        if ($type) {
            $query .= " AND syrup_type = :type";
        }
        $query .= " ORDER BY syrup_type, syrup_name";

        $stmt = $this->conn->prepare($query);
        if ($type) {
            $stmt->bindParam(':type', $type);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Get fruits
    public function getFruits() {
        $query = "SELECT * FROM fruits WHERE is_available = 1 ORDER BY fruit_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
?>
