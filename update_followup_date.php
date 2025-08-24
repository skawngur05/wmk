<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['lead_id']) || !is_numeric($input['lead_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lead ID']);
    exit;
}

$lead_id = (int) $input['lead_id'];
$followup_date = $input['followup_date'] ?? null;

// Validate date format if provided
if (!empty($followup_date)) {
    $date = DateTime::createFromFormat('Y-m-d', $followup_date);
    if (!$date || $date->format('Y-m-d') !== $followup_date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
}

try {
    // Update the follow-up date
    $stmt = $pdo->prepare("UPDATE leads SET next_followup_date = ? WHERE id = ?");
    $result = $stmt->execute([
        !empty($followup_date) ? $followup_date : null,
        $lead_id
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Follow-up date updated successfully',
            'followup_date' => $followup_date
        ]);
    } else {
        // Check if lead exists
        $check_stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
        $check_stmt->execute([$lead_id]);
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Lead not found']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made']);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
