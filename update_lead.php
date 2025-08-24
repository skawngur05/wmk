<?php
// Prevent any HTML output and catch errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to prevent HTML output

require_once 'config/database.php';
require_once 'includes/auth.php';

// Set JSON header first
header('Content-Type: application/json');

// Check authentication for AJAX requests
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Function to ensure payment columns exist
function ensurePaymentColumns($pdo) {
    static $columns_checked = false;
    if (!$columns_checked) {
        try {
            $check_columns = $pdo->query("SHOW COLUMNS FROM leads LIKE 'deposit_paid'");
            if ($check_columns->rowCount() == 0) {
                $pdo->exec("ALTER TABLE leads ADD COLUMN deposit_paid TINYINT(1) DEFAULT 0");
                $pdo->exec("ALTER TABLE leads ADD COLUMN balance_paid TINYINT(1) DEFAULT 0");
            }
            
            // Check and add installation_date column
            $check_installation = $pdo->query("SHOW COLUMNS FROM leads LIKE 'installation_date'");
            if ($check_installation->rowCount() == 0) {
                $pdo->exec("ALTER TABLE leads ADD COLUMN installation_date DATE NULL");
            }
            
            $columns_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring payment columns: " . $e->getMessage());
        }
    }
}

// Handle full form submission from modal
if ($_POST && isset($_POST['lead_id'], $_POST['name'], $_POST['phone'])) {
        $lead_id = (int)$_POST['lead_id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']) ?: null;
    $remarks = $_POST['remarks'] ?? null;
    $next_followup_date = $_POST['next_followup_date'] ?: null;
    $assigned_to = $_POST['assigned_to'] ?? null;
    $assigned_installer = $_POST['assigned_installer'] ?? null;
    $project_amount = !empty($_POST['project_amount']) ? (float)$_POST['project_amount'] : null;
    $notes = trim($_POST['notes']) ?: null;
    
    // Handle payment tracking fields
    $deposit_paid = isset($_POST['deposit_paid']) ? 1 : 0;
    $balance_paid = isset($_POST['balance_paid']) ? 1 : 0;
    $installation_date = !empty($_POST['installation_date']) ? $_POST['installation_date'] : null;
    
    // Log the assignment for debugging
    error_log("Updating lead $lead_id - Assigned to: " . ($assigned_to ?: 'NULL'));
    error_log("Updating lead $lead_id - Assigned installer: " . ($assigned_installer ?: 'NULL'));
    error_log("Updating lead $lead_id - Remarks: " . ($remarks ?: 'NULL'));
    error_log("Updating lead $lead_id - Installation date: " . ($installation_date ?: 'NULL'));
    
    // Log all POST data for debugging
    error_log("POST data: " . print_r($_POST, true));
    error_log("Updating lead $lead_id - Installation date: " . ($installation_date ?: 'NULL'));
    error_log("Updating lead $lead_id - Preserve remarks: " . ($preserve_remarks ? 'YES' : 'NO'));
    
    // Validate required fields
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
        exit();
    }
    
    try {
        // Ensure payment columns exist
        ensurePaymentColumns($pdo);
        
        $sql = "UPDATE leads SET 
                name = ?, 
                phone = ?, 
                email = ?, 
                remarks = ?, 
                next_followup_date = ?, 
                assigned_to = ?, 
                assigned_installer = ?,
                project_amount = ?,
                notes = ?,
                deposit_paid = ?,
                balance_paid = ?,
                installation_date = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, 
            $phone, 
            $email, 
            $remarks, 
            $next_followup_date, 
            $assigned_to, 
            $assigned_installer,
            $project_amount,
            $notes,
            $deposit_paid,
            $balance_paid,
            $installation_date,
            $lead_id
        ]);
        
        // Log successful update
        error_log("Lead $lead_id updated successfully. Rows affected: " . $stmt->rowCount());
        
        // Verify the update by reading back the data
        $verify_stmt = $pdo->prepare("SELECT assigned_to, assigned_installer, remarks FROM leads WHERE id = ?");
        $verify_stmt->execute([$lead_id]);
        $updated_data = $verify_stmt->fetch();
        error_log("Verification - Lead $lead_id now has assigned_to: '" . ($updated_data['assigned_to'] ?? 'NULL') . "', assigned_installer: '" . ($updated_data['assigned_installer'] ?? 'NULL') . "', remarks: '" . ($updated_data['remarks'] ?? 'NULL') . "'");
        
        echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
    } catch (PDOException $e) {
        error_log("Update lead error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
// Handle single field updates (for backward compatibility)
elseif ($_POST && isset($_POST['lead_id'], $_POST['field'], $_POST['value'])) {
    $lead_id = (int)$_POST['lead_id'];
    $field = $_POST['field'];
    $value = $_POST['value'];
    
    // Whitelist allowed fields for security
    $allowed_fields = [
        'remarks', 'next_followup_date', 'assigned_to', 'assigned_installer', 'notes', 
        'additional_notes', 'project_amount', 'deposit_paid', 
        'balance_paid', 'installation_date'
    ];
    
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field']);
        exit();
    }
    
    try {
        $sql = "UPDATE leads SET $field = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value, $lead_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Update lead error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
}
?>
