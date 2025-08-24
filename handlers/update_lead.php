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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate required fields
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead ID']);
    exit;
}

if (empty($_POST['name'])) {
    echo json_encode(['success' => false, 'message' => 'Client name is required']);
    exit;
}

if (empty($_POST['lead_origin'])) {
    echo json_encode(['success' => false, 'message' => 'Lead origin is required']);
    exit;
}

$leadId = (int)$_POST['id'];
$name = trim($_POST['name']);
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$leadOrigin = trim($_POST['lead_origin']);
$nextFollowup = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
$remarks = trim($_POST['remarks'] ?? 'New');
$assignedTo = trim($_POST['assigned_to'] ?? '');
$projectAmount = !empty($_POST['project_amount']) ? floatval($_POST['project_amount']) : 0;
$notes = trim($_POST['notes'] ?? '');
$additionalNotes = trim($_POST['additional_notes'] ?? '');

// Handle payment tracking fields
$depositPaid = isset($_POST['deposit_paid']) ? 1 : 0;
$balancePaid = isset($_POST['balance_paid']) ? 1 : 0;
$installationDate = !empty($_POST['installation_date']) ? $_POST['installation_date'] : null;
$assignedInstaller = !empty($_POST['assigned_installer']) ? trim($_POST['assigned_installer']) : null;

// Validate email format if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Ensure payment columns exist
    ensurePaymentColumns($pdo);
    
    // Check if lead exists
    $checkStmt = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
    $checkStmt->execute([$leadId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Lead not found']);
        exit;
    }
    
    // Update lead with payment tracking fields
    $stmt = $pdo->prepare("
        UPDATE leads SET 
            name = ?, 
            phone = ?, 
            email = ?, 
            lead_origin = ?, 
            next_followup_date = ?, 
            remarks = ?, 
            assigned_to = ?, 
            project_amount = ?, 
            notes = ?, 
            additional_notes = ?,
            deposit_paid = ?,
            balance_paid = ?,
            installation_date = ?,
            assigned_installer = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $name,
        $phone,
        $email,
        $leadOrigin,
        $nextFollowup,
        $remarks,
        $assignedTo,
        $projectAmount,
        $notes,
        $additionalNotes,
        $depositPaid,
        $balancePaid,
        $installationDate,
        $assignedInstaller,
        $leadId
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update lead']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
