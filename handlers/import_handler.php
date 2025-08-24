<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

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

try {
    if (isset($_POST['action']) && $_POST['action'] === 'preview') {
        handlePreview();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
        handleImport();
    } else {
        // Handle JSON input for import
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action']) && $input['action'] === 'import') {
            handleImportFromJSON($input);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handlePreview() {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $file = $_FILES['csv_file']['tmp_name'];
    $data = [];
    $validation = [];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Read header row
        $row_number = 0;
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_number++;
            
            // Map CSV data to expected fields
            $lead_data = [
                'date_created' => isset($row[0]) ? trim($row[0]) : '',
                'lead_origin' => isset($row[1]) ? trim($row[1]) : '',
                'name' => isset($row[2]) ? trim($row[2]) : '',
                'phone_number' => isset($row[3]) ? trim($row[3]) : '',
                'email' => isset($row[4]) ? trim($row[4]) : '',
                'next_followup_date' => isset($row[5]) ? trim($row[5]) : '',
                'remarks' => isset($row[6]) ? trim($row[6]) : '',
                'assigned_to' => isset($row[7]) ? trim($row[7]) : '',
                'notes' => isset($row[8]) ? trim($row[8]) : '',
                'additional_notes' => isset($row[9]) ? trim($row[9]) : ''
            ];
            
            // Validate row
            $validation_result = validateRow($lead_data, $row_number);
            
            $data[] = $lead_data;
            $validation[] = $validation_result;
        }
        fclose($handle);
    } else {
        throw new Exception('Unable to read CSV file');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'validation' => $validation
    ]);
}

function handleImportFromJSON($input) {
    global $pdo;
    
    if (!isset($input['data']) || !is_array($input['data'])) {
        throw new Exception('No data provided for import');
    }
    
    $data = $input['data'];
    $imported = 0;
    $duplicates = 0;
    $invalid = 0;
    $errors = [];
    
    foreach ($data as $index => $lead_data) {
        $validation = validateRow($lead_data, $index + 1);
        
        if (!$validation['valid']) {
            $invalid++;
            continue;
        }
        
        // Check for duplicates
        if (checkDuplicate($lead_data)) {
            $duplicates++;
            continue;
        }
        
        // Format date
        $date_created = formatDate($lead_data['date_created']);
        $next_followup_date = !empty($lead_data['next_followup_date']) ? formatDate($lead_data['next_followup_date']) : null;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO leads (
                    date_created, lead_origin, name, phone, email, 
                    next_followup_date, remarks, assigned_to, notes, 
                    additional_notes, project_amount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $date_created,
                $lead_data['lead_origin'],
                $lead_data['name'],
                $lead_data['phone_number'],
                $lead_data['email'],
                $next_followup_date,
                $lead_data['remarks'] ?: 'New',
                $lead_data['assigned_to'],
                $lead_data['notes'],
                $lead_data['additional_notes'],
                0 // project_amount default
            ]);
            
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Row " . ($index + 1) . ": Database error - " . $e->getMessage();
            $invalid++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'duplicates' => $duplicates,
        'invalid' => $invalid,
        'errors' => $errors
    ]);
}

function validateRow($lead_data, $row_number) {
    $errors = [];
    $valid = true;
    
    // Required fields: date_created and lead_origin
    if (empty($lead_data['date_created'])) {
        $errors[] = 'Date Created is required';
        $valid = false;
    }
    
    if (empty($lead_data['lead_origin'])) {
        $errors[] = 'Lead Origin is required';
        $valid = false;
    }
    
    // Must have at least name, email, or phone
    if (empty($lead_data['name']) && empty($lead_data['email']) && empty($lead_data['phone_number'])) {
        $errors[] = 'Must have at least Name, Email, or Phone Number';
        $valid = false;
    }
    
    // Validate date format if provided
    if (!empty($lead_data['date_created']) && !isValidDate($lead_data['date_created'])) {
        $errors[] = 'Invalid date format (use YYYY-MM-DD or MM/DD/YYYY)';
        $valid = false;
    }
    
    // Validate email format if provided
    if (!empty($lead_data['email']) && !filter_var($lead_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
        $valid = false;
    }
    
    return [
        'valid' => $valid,
        'errors' => $errors
    ];
}

function checkDuplicate($lead_data) {
    global $pdo;
    
    if (empty($lead_data['email']) && empty($lead_data['phone_number'])) {
        return false; // No way to check for duplicates
    }
    
    $conditions = [];
    $params = [];
    
    if (!empty($lead_data['email'])) {
        $conditions[] = "email = ?";
        $params[] = $lead_data['email'];
    }
    
    if (!empty($lead_data['phone_number'])) {
        $conditions[] = "phone = ?";
        $params[] = $lead_data['phone_number'];
    }
    
    $sql = "SELECT id FROM leads WHERE " . implode(' OR ', $conditions);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false; // If check fails, assume not duplicate
    }
}

function isValidDate($date) {
    // Check YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return true;
    }
    
    // Check MM/DD/YYYY format
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
        return true;
    }
    
    return false;
}

function formatDate($date) {
    if (empty($date)) {
        return date('Y-m-d');
    }
    
    // If already in YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    
    // Convert MM/DD/YYYY to YYYY-MM-DD
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
        $parts = explode('/', $date);
        return sprintf('%04d-%02d-%02d', $parts[2], $parts[0], $parts[1]);
    }
    
    // Default to today if invalid format
    return date('Y-m-d');
}
?>
