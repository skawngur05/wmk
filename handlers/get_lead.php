<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

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
            
            // Check and add assigned_installer column
            $check_assigned = $pdo->query("SHOW COLUMNS FROM leads LIKE 'assigned_installer'");
            if ($check_assigned->rowCount() == 0) {
                $pdo->exec("ALTER TABLE leads ADD COLUMN assigned_installer VARCHAR(100) NULL");
            }
            
            $columns_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring payment columns: " . $e->getMessage());
        }
    }
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead ID']);
    exit;
}

$leadId = (int)$_GET['id'];

try {
    // Ensure payment columns exist
    ensurePaymentColumns($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'lead' => $lead]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
