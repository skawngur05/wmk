<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['export_type'])) {
    header('Location: ../settings.php');
    exit;
}

$export_type = $_POST['export_type'];

try {
    // Build query based on export type
    $where_conditions = [];
    $params = [];
    $filename_suffix = '';
    
    switch ($export_type) {
        case 'all':
            $filename_suffix = 'all_leads';
            break;
            
        case 'sold':
            $where_conditions[] = "remarks = 'Sold'";
            $filename_suffix = 'sold_leads';
            break;
            
        case 'month':
            $where_conditions[] = "MONTH(date_created) = MONTH(CURRENT_DATE) AND YEAR(date_created) = YEAR(CURRENT_DATE)";
            $filename_suffix = 'leads_this_month';
            break;
            
        case 'custom':
            $filename_suffix = 'custom_leads';
            
            // Date filters
            if (!empty($_POST['date_from'])) {
                $where_conditions[] = "date_created >= ?";
                $params[] = $_POST['date_from'];
            }
            if (!empty($_POST['date_to'])) {
                $where_conditions[] = "date_created <= ?";
                $params[] = $_POST['date_to'];
            }
            
            // Status filter
            if (!empty($_POST['status']) && is_array($_POST['status'])) {
                $status_placeholders = str_repeat('?,', count($_POST['status']) - 1) . '?';
                $where_conditions[] = "remarks IN ($status_placeholders)";
                $params = array_merge($params, $_POST['status']);
                $filename_suffix .= '_' . strtolower(str_replace(' ', '_', implode('_', $_POST['status'])));
            }
            
            // Origin filter
            if (!empty($_POST['origin'])) {
                $where_conditions[] = "lead_origin = ?";
                $params[] = $_POST['origin'];
                $filename_suffix .= '_' . strtolower(str_replace(' ', '_', $_POST['origin']));
            }
            
            // Assigned filter
            if (!empty($_POST['assigned'])) {
                $where_conditions[] = "assigned_to = ?";
                $params[] = $_POST['assigned'];
                $filename_suffix .= '_' . strtolower($_POST['assigned']);
            }
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
    // Build final query
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $sql = "SELECT 
                date_created,
                lead_origin,
                name,
                phone,
                email,
                next_followup_date,
                remarks,
                assigned_to,
                notes,
                additional_notes,
                project_amount,
                created_at
            FROM leads 
            $where_clause 
            ORDER BY date_created DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($leads)) {
        // Redirect back with message if no data
        session_start();
        $_SESSION['export_message'] = 'No leads found matching the selected criteria.';
        header('Location: ../settings.php');
        exit;
    }
    
    // Generate filename
    $filename = 'wmk_' . $filename_suffix . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Description: File Transfer');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    $headers = [
        'Date Created',
        'Lead Origin',
        'Client Name',
        'Phone Number',
        'Email',
        'Next Follow-up Date',
        'Status',
        'Assigned To',
        'Project Amount',
        'Notes',
        'Additional Notes',
        'Record Created'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($leads as $lead) {
        $row = [
            $lead['date_created'],
            $lead['lead_origin'],
            $lead['name'],
            $lead['phone'],
            $lead['email'],
            $lead['next_followup_date'],
            $lead['remarks'],
            $lead['assigned_to'],
            $lead['project_amount'],
            $lead['notes'],
            $lead['additional_notes'],
            $lead['created_at']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // Handle errors
    session_start();
    $_SESSION['export_error'] = 'Export failed: ' . $e->getMessage();
    header('Location: ../settings.php');
    exit;
}
?>
