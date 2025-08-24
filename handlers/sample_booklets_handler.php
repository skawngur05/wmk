<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure sample_booklets table exists
function ensureSampleBookletsTable($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'sample_booklets'");
        if ($stmt->rowCount() == 0) {
            $create_table_sql = "
                CREATE TABLE sample_booklets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_number VARCHAR(100) NOT NULL UNIQUE,
                    customer_name VARCHAR(255) NOT NULL,
                    address TEXT NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL,
                    tracking_number VARCHAR(100) NULL,
                    status ENUM('Pending', 'Shipped', 'Delivered') DEFAULT 'Pending',
                    date_ordered DATE NOT NULL,
                    date_shipped DATE NULL,
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_table_sql);
            
            // Create indexes
            $pdo->exec("CREATE INDEX idx_order_number ON sample_booklets(order_number)");
            $pdo->exec("CREATE INDEX idx_status ON sample_booklets(status)");
            $pdo->exec("CREATE INDEX idx_date_ordered ON sample_booklets(date_ordered)");
        } else {
            // Check if we need to update the product_type ENUM values
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM sample_booklets LIKE 'product_type'");
                $column = $stmt->fetch();
                
                if ($column && !strpos($column['Type'], 'Sample Booklet Only')) {
                    error_log("Handler: Updating product_type ENUM values...");
                    // Update existing records first
                    $pdo->exec("UPDATE sample_booklets SET product_type = 'Sample Booklet Only' WHERE product_type = 'Sample Booklet'");
                    $pdo->exec("UPDATE sample_booklets SET product_type = 'Demo Kit Only' WHERE product_type = 'Demo Kit'");
                    
                    // Then alter the column
                    $pdo->exec("ALTER TABLE sample_booklets MODIFY COLUMN product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL");
                    error_log("Handler: Product type ENUM updated successfully");
                }
            } catch (PDOException $e) {
                error_log("Handler: Error updating product_type column: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log("Error ensuring sample_booklets table: " . $e->getMessage());
        throw $e;
    }
}

try {
    // Ensure table exists
    ensureSampleBookletsTable($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Check if we have JSON input (for delete action) or form data
    $input = json_decode(file_get_contents('php://input'), true);
    $is_json = $input !== null;
    
    // Handle delete action (JSON input)
    if ($is_json && isset($input['action']) && $input['action'] === 'delete') {
        $order_id = $input['order_id'] ?? '';
        
        if (empty($order_id)) {
            throw new Exception('Order ID is required for deletion');
        }
        
        // Get order details before deletion for logging
        $stmt = $pdo->prepare("SELECT order_number, customer_name FROM sample_booklets WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Delete the order
        $stmt = $pdo->prepare("DELETE FROM sample_booklets WHERE id = ?");
        $stmt->execute([$order_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Order not found or already deleted');
        }
        
        error_log("Sample booklet order deleted: Order #{$order['order_number']} for {$order['customer_name']} (ID: $order_id)");
        echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
        exit;
    }
    
    // Handle add/update actions (form data)
    // Get and validate input data
    $order_id = $_POST['order_id'] ?? '';
    $order_number = trim($_POST['order_number'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $product_type = $_POST['product_type'] ?? '';
    $status = $_POST['status'] ?? 'Pending';
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    $date_ordered = $_POST['date_ordered'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($order_number)) {
        $errors[] = 'Order number is required';
    }
    
    if (empty($customer_name)) {
        $errors[] = 'Customer name is required';
    }
    
    if (empty($address)) {
        $errors[] = 'Address is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone is required';
    }
    
    if (empty($product_type) || !in_array($product_type, ['Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only'])) {
        $errors[] = 'Valid product type is required';
    }
    
    if (!empty($status) && !in_array($status, ['Pending', 'Shipped', 'Delivered'])) {
        $errors[] = 'Valid status is required';
    }
    
    if (empty($date_ordered)) {
        $errors[] = 'Order date is required';
    } elseif (!DateTime::createFromFormat('Y-m-d', $date_ordered)) {
        $errors[] = 'Invalid date format';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    if (empty($order_id)) {
        // Insert new order
        
        // Check if order number already exists
        $stmt = $pdo->prepare("SELECT id FROM sample_booklets WHERE order_number = ?");
        $stmt->execute([$order_number]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Order number already exists']);
            exit;
        }
        
        $sql = "INSERT INTO sample_booklets (
                    order_number, customer_name, address, email, phone, 
                    product_type, tracking_number, status, date_ordered, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $order_number, $customer_name, $address, $email, $phone,
            $product_type, $tracking_number, $status, $date_ordered, $notes
        ]);
        
        $new_order_id = $pdo->lastInsertId();
        $message = 'Order added successfully';
        error_log("Sample booklet order added: Order #$order_number for $customer_name (ID: $new_order_id)");
        
    } else {
        // Update existing order
        
        // Check if order number already exists for different order
        $stmt = $pdo->prepare("SELECT id FROM sample_booklets WHERE order_number = ? AND id != ?");
        $stmt->execute([$order_number, $order_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Order number already exists']);
            exit;
        }
        
        $sql = "UPDATE sample_booklets SET 
                    order_number = ?, customer_name = ?, address = ?, email = ?, 
                    phone = ?, product_type = ?, tracking_number = ?, status = ?, 
                    date_ordered = ?, notes = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $order_number, $customer_name, $address, $email, $phone,
            $product_type, $tracking_number, $status, $date_ordered, $notes, $order_id
        ]);
        
        $message = 'Order updated successfully';
        error_log("Sample booklet order updated: Order #$order_number for $customer_name");
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    error_log("Sample booklets handler database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Sample booklets handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
