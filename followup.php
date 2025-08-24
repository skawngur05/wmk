<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Follow-Ups - Wrap My Kitchen';

// Function to get lead origin CSS class
function getLeadOriginClass($origin) {
    $origin_classes = [
        'facebook' => 'lead-origin-facebook',
        'google' => 'lead-origin-google',
        'google text' => 'lead-origin-googletext',
        'googletext' => 'lead-origin-googletext',
        'referral' => 'lead-origin-referral',
        'website' => 'lead-origin-website',
        'instagram' => 'lead-origin-instagram',
        'tiktok' => 'lead-origin-tiktok',
        'youtube' => 'lead-origin-youtube',
        'linkedin' => 'lead-origin-linkedin',
        'twitter' => 'lead-origin-twitter',
        'email' => 'lead-origin-email',
        'phone' => 'lead-origin-phone',
        'walk-in' => 'lead-origin-walkin',
        'walkin' => 'lead-origin-walkin',
        'trade show' => 'lead-origin-tradeshow',
        'tradeshow' => 'lead-origin-tradeshow',
        'whatsapp' => 'lead-origin-whatsapp'
    ];
    
    $origin_lower = strtolower(trim($origin));
    return $origin_classes[$origin_lower] ?? 'lead-origin-other';
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

// Function to ensure installers table exists
function ensureInstallersTable($pdo) {
    static $installers_checked = false;
    if (!$installers_checked) {
        try {
            // Check if installers table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'installers'");
            if ($stmt->rowCount() == 0) {
                // Create installers table
                $create_table_sql = "
                    CREATE TABLE installers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL UNIQUE,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        phone VARCHAR(20) NULL,
                        email VARCHAR(100) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $pdo->exec($create_table_sql);
                
                // Insert default installers
                $insert_installers_sql = "
                    INSERT INTO installers (name, status, created_at) VALUES 
                    ('Angel', 'active', NOW()),
                    ('Brian', 'active', NOW()),
                    ('Luis', 'active', NOW())
                ";
                $pdo->exec($insert_installers_sql);
                
                // Create indexes
                $pdo->exec("CREATE INDEX idx_installer_name ON installers(name)");
                $pdo->exec("CREATE INDEX idx_installer_status ON installers(status)");
                
                error_log("Installers table created successfully with Angel, Brian, and Luis");
            } else {
                // Check if we need to add the default installers
                $count_installers = $pdo->query("SELECT COUNT(*) as count FROM installers")->fetch()['count'];
                if ($count_installers == 0) {
                    $insert_installers_sql = "
                        INSERT INTO installers (name, status, created_at) VALUES 
                        ('Angel', 'active', NOW()),
                        ('Brian', 'active', NOW()),
                        ('Luis', 'active', NOW())
                    ";
                    $pdo->exec($insert_installers_sql);
                    error_log("Added default installers: Angel, Brian, Luis");
                }
            }
            $installers_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring installers table: " . $e->getMessage());
        }
    }
}

// Function to format project amount
function formatProjectAmount($amount) {
    if (!empty($amount) && $amount > 0) {
        return '<strong>$' . number_format($amount, 2) . '</strong>';
    }
    return '<span class="text-muted">-</span>';
}

// Function to get payment status badges
function getPaymentStatusBadges($lead) {
    if ($lead['remarks'] !== 'Sold') {
        return '';
    }
    
    $html = '<div class="mt-1">';
    $html .= isset($lead['deposit_paid']) && $lead['deposit_paid'] 
        ? '<small class="badge bg-success">Deposit ✓</small>'
        : '<small class="badge bg-warning">Deposit Pending</small>';
    $html .= ' ';
    $html .= isset($lead['balance_paid']) && $lead['balance_paid'] 
        ? '<small class="badge bg-success">Balance ✓</small>'
        : '<small class="badge bg-warning">Balance Pending</small>';
    
    // Add installation date if deposit is paid and date is set
    if (isset($lead['deposit_paid']) && $lead['deposit_paid'] && !empty($lead['installation_date'])) {
        $installation_date = date('M j, Y', strtotime($lead['installation_date']));
        $html .= '<br><small class="badge bg-info mt-1"><i class="fas fa-tools me-1"></i>Install: ' . $installation_date . '</small>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// Function to render table rows
function renderLeadTableRow($lead, $is_priority_table = false) {
    $today = date('Y-m-d');
    $is_overdue = $lead['next_followup_date'] < $today;
    $is_due_today = $lead['next_followup_date'] == $today;
    $row_class = '';
    
    if ($is_priority_table) {
        $row_class = $is_overdue ? 'table-danger' : ($is_due_today ? 'table-warning' : '');
    }
    
    $badges = '';
    if ($is_priority_table) {
        if ($is_overdue) {
            $badges = '<span class="badge bg-danger ms-2">OVERDUE</span>';
        } elseif ($is_due_today) {
            $badges = '<span class="badge bg-warning">DUE TODAY</span>';
        }
    }
    
    $overdue_info = '';
    if ($is_priority_table && $is_overdue) {
        $days_overdue = abs((strtotime($today) - strtotime($lead['next_followup_date'])) / 86400);
        $overdue_info = '<small class="text-danger">(' . $days_overdue . ' days overdue)</small>';
    }
    
    $status_badge = '';
    if ($lead['remarks'] == 'Sold') {
        $status_badge = '<span class="badge bg-success">Sold</span>' . getPaymentStatusBadges($lead);
    } elseif ($lead['remarks']) {
        $status_badge = '<span class="badge bg-secondary">' . htmlspecialchars($lead['remarks']) . '</span>';
    } else {
        $status_badge = '<span class="badge bg-light text-dark">New</span>';
    }
    
    $action_buttons = '';
    if ($lead['remarks'] == 'Sold') {
        $action_buttons = '
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateFollowupModal' . $lead['id'] . '" title="Payment Tracking">
                <i class="fas fa-dollar-sign"></i>
            </button>';
    } else {
        $action_buttons = '
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateFollowupModal' . $lead['id'] . '" title="Update Follow-up">
                <i class="fas fa-calendar-alt"></i>
            </button>';
    }
    
    if (!$is_priority_table) {
        $action_buttons .= '
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editLeadModal' . $lead['id'] . '" title="Edit Lead">
                <i class="fas fa-edit"></i>
            </button>';
    } else {
        $action_buttons .= '
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editLeadModal' . $lead['id'] . '">
                <i class="fas fa-edit"></i>
            </button>';
    }
    
    echo '<tr class="' . $row_class . '">
        <td>
            <strong>' . htmlspecialchars($lead['name']) . '</strong>
            ' . $badges . '
        </td>
        <td>
            <div>' . htmlspecialchars($lead['phone']) . '</div>
            <small class="text-muted">' . htmlspecialchars($lead['email']) . '</small>
        </td>
        <td>
            <span class="badge ' . getLeadOriginClass($lead['lead_origin']) . '">' . htmlspecialchars($lead['lead_origin']) . '</span>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <span class="me-2">' . date('M j, Y', strtotime($lead['next_followup_date'])) . '</span>
                ' . $overdue_info . '
            </div>
        </td>
        <td>' . $status_badge . '</td>
        <td>' . formatProjectAmount($lead['project_amount']) . '</td>
        <td>' . htmlspecialchars($lead['assigned_to']) . '</td>
        <td>
            <div class="btn-group">
                ' . $action_buttons . '
            </div>
        </td>
    </tr>';
}

// Handle follow-up date updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_followup'])) {
    $lead_id = (int)$_POST['lead_id'];
    $new_followup_date = $_POST['next_followup_date'];
    $assigned_to = $_POST['assigned_to'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE leads SET next_followup_date = ?, assigned_to = ?, notes = ? WHERE id = ?");
        $stmt->execute([$new_followup_date, $assigned_to, $notes, $lead_id]);
        $success_message = "Follow-up date updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating follow-up date: " . $e->getMessage();
    }
}

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment_status'])) {
    $lead_id = (int)$_POST['lead_id'];
    $deposit_paid = isset($_POST['deposit_paid']) ? 1 : 0;
    $balance_paid = isset($_POST['balance_paid']) ? 1 : 0;
    $installation_date = !empty($_POST['installation_date']) ? $_POST['installation_date'] : null;
    $assigned_installer = !empty($_POST['assigned_installer']) ? $_POST['assigned_installer'] : null;
    $new_followup_date = $_POST['next_followup_date'] ?? null;
    $assigned_to = $_POST['assigned_to'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        ensurePaymentColumns($pdo);
        ensureInstallersTable($pdo);
        $stmt = $pdo->prepare("UPDATE leads SET deposit_paid = ?, balance_paid = ?, installation_date = ?, assigned_installer = ?, next_followup_date = ?, assigned_to = ?, notes = ? WHERE id = ?");
        $stmt->execute([$deposit_paid, $balance_paid, $installation_date, $assigned_installer, $new_followup_date, $assigned_to, $notes, $lead_id]);
        $success_message = "Payment status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating payment status: " . $e->getMessage();
    }
}

// Handle lead updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_lead'])) {
    $lead_id = (int)$_POST['lead_id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address'] ?? '');
    $remarks = $_POST['remarks'];
    $assigned_to = $_POST['assigned_to'];
    $notes = trim($_POST['notes']);
    $project_amount = !empty($_POST['project_amount']) ? floatval($_POST['project_amount']) : 0;
    $next_followup_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
    $lead_origin = trim($_POST['lead_origin'] ?? '');
    
    // Handle payment status for sold leads
    $deposit_paid = isset($_POST['deposit_paid']) ? 1 : 0;
    $balance_paid = isset($_POST['balance_paid']) ? 1 : 0;
    $installation_date = !empty($_POST['installation_date']) ? $_POST['installation_date'] : null;
    $assigned_installer = !empty($_POST['assigned_installer']) ? $_POST['assigned_installer'] : null;
    
    try {
        ensurePaymentColumns($pdo);
        ensureInstallersTable($pdo);
        $stmt = $pdo->prepare("
            UPDATE leads SET 
                name = ?, phone = ?, email = ?, address = ?, remarks = ?, 
                assigned_to = ?, notes = ?, project_amount = ?,
                next_followup_date = ?, lead_origin = ?,
                deposit_paid = ?, balance_paid = ?, installation_date = ?, assigned_installer = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $phone, $email, $address, $remarks, $assigned_to, $notes, $project_amount, $next_followup_date, $lead_origin, $deposit_paid, $balance_paid, $installation_date, $assigned_installer, $lead_id]);
        $success_message = "Lead updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating lead: " . $e->getMessage();
    }
}

// Get leads and users data
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

// Base query conditions
$base_conditions = "
    remarks NOT IN ('Not Service Area', 'Not Interested', 'Not Compatible')
    AND NOT (remarks = 'Sold' AND deposit_paid = 1 AND balance_paid = 1)
";

try {
    // Ensure database columns exist first
    ensurePaymentColumns($pdo);
    ensureInstallersTable($pdo);
    
    // Get overdue and due today leads
    $followup_sql = "
        SELECT *, DATE(created_at) as created_at FROM leads 
        WHERE next_followup_date <= ? AND {$base_conditions}
        ORDER BY next_followup_date ASC, created_at DESC
    ";
    $stmt = $pdo->prepare($followup_sql);
    $stmt->execute([$today]);
    $followup_leads = $stmt->fetchAll();

    // Get upcoming leads (next 7 days)
    $upcoming_sql = "
        SELECT *, DATE(created_at) as created_at FROM leads 
        WHERE next_followup_date > ? AND next_followup_date <= ? AND {$base_conditions}
        ORDER BY next_followup_date ASC
    ";
    $stmt = $pdo->prepare($upcoming_sql);
    $stmt->execute([$today, $next_week]);
    $upcoming_leads = $stmt->fetchAll();

    // Get all users for dropdowns
    $stmt = $pdo->prepare("SELECT id, full_name, username FROM users ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Get all installers for dropdowns
    $stmt = $pdo->prepare("SELECT id, name FROM installers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $installers = $stmt->fetchAll();
    
    // Calculate statistics
    $overdue_count = count(array_filter($followup_leads, function($lead) use ($today) { 
        return $lead['next_followup_date'] < $today; 
    }));
    $due_today_count = count(array_filter($followup_leads, function($lead) use ($today) { 
        return $lead['next_followup_date'] == $today; 
    }));
    $upcoming_count = count($upcoming_leads);
    
    // Get installations count (sold leads with installation dates)
    $installations_sql = "
        SELECT COUNT(*) as count FROM leads 
        WHERE remarks = 'Sold' 
        AND installation_date IS NOT NULL 
        AND installation_date >= ?
    ";
    $stmt = $pdo->prepare($installations_sql);
    $stmt->execute([$today]);
    $installations_count = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    $followup_leads = $upcoming_leads = $users = $installers = [];
    $overdue_count = $due_today_count = $upcoming_count = $installations_count = 0;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-calendar-check text-primary me-2"></i>
        Follow-Up Management
    </h1>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100" style="transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='scale(1.02)'" 
             onmouseout="this.style.transform='scale(1)'"
             onclick="scrollToSection('priority-followups')">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Overdue Follow-Ups</h5>
                        <h2 class="mb-0"><?php echo $overdue_count; ?></h2>
                    </div>
                    <div class="fs-1 opacity-75">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">
                        <i class="fas fa-arrow-down me-1"></i>Click to view
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100" style="transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='scale(1.02)'" 
             onmouseout="this.style.transform='scale(1)'"
             onclick="scrollToSection('priority-followups')">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Due Today</h5>
                        <h2 class="mb-0"><?php echo $due_today_count; ?></h2>
                    </div>
                    <div class="fs-1 opacity-75">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">
                        <i class="fas fa-arrow-down me-1"></i>Click to view
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100" style="transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='scale(1.02)'" 
             onmouseout="this.style.transform='scale(1)'"
             onclick="scrollToSection('upcoming-followups')">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Next 7 Days</h5>
                        <h2 class="mb-0"><?php echo $upcoming_count; ?></h2>
                    </div>
                    <div class="fs-1 opacity-75">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="opacity-75">
                        <i class="fas fa-arrow-down me-1"></i>Click to view
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <a href="installations.php" class="text-decoration-none">
            <div class="card bg-success text-white h-100" style="transition: transform 0.2s; cursor: pointer;" 
                 onmouseover="this.style.transform='scale(1.02)'" 
                 onmouseout="this.style.transform='scale(1)'">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Scheduled Installations</h5>
                            <h2 class="mb-0"><?php echo $installations_count; ?></h2>
                        </div>
                        <div class="fs-1 opacity-75">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="opacity-75">
                            <i class="fas fa-external-link-alt me-1"></i>Click to manage
                        </small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Priority Follow-Ups -->
<?php if (!empty($followup_leads)): ?>
<div class="card mb-4" id="priority-followups">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-fire text-danger me-2"></i>
            Priority Follow-Ups (Overdue & Due Today)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Lead Origin</th>
                        <th>Follow-Up Date</th>
                        <th>Status</th>
                        <th>Project Amount</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($followup_leads as $lead): ?>
                        <?php renderLeadTableRow($lead, true); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Upcoming Follow-Ups -->
<?php if (!empty($upcoming_leads)): ?>
<div class="card" id="upcoming-followups">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-calendar-week text-info me-2"></i>
            Upcoming Follow-Ups (Next 7 Days)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Lead Origin</th>
                        <th>Follow-Up Date</th>
                        <th>Status</th>
                        <th>Project Amount</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_leads as $lead): ?>
                        <?php renderLeadTableRow($lead, false); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Update Follow-up Modals -->
<?php foreach (array_merge($followup_leads, $upcoming_leads) as $lead): ?>
<div class="modal fade" id="updateFollowupModal<?php echo $lead['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php if ($lead['remarks'] == 'Sold'): ?>
                        <i class="fas fa-dollar-sign me-2"></i>Update Payment Status
                    <?php else: ?>
                        <i class="fas fa-calendar-alt me-2"></i>Update Follow-up Date
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <?php if ($lead['remarks'] == 'Sold'): ?>
            <!-- Payment Status Form for Sold Leads -->
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-trophy me-2"></i>
                        <strong>Sold Lead - Payment Tracking</strong>
                    </div>
                    
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($lead['name']); ?></p>
                    <p><strong>Project Amount:</strong> $<?php echo number_format($lead['project_amount'], 2); ?></p>
                    <p><strong>Current Follow-up Date:</strong> <?php echo date('M j, Y', strtotime($lead['next_followup_date'])); ?></p>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="deposit_paid<?php echo $lead['id']; ?>" 
                                       name="deposit_paid" value="1" <?php echo (isset($lead['deposit_paid']) && $lead['deposit_paid']) ? 'checked' : ''; ?>
                                       onchange="toggleInstallationDate<?php echo $lead['id']; ?>(this.checked)">
                                <label class="form-check-label" for="deposit_paid<?php echo $lead['id']; ?>">
                                    <strong>Deposit Received</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="balance_paid<?php echo $lead['id']; ?>" 
                                       name="balance_paid" value="1" <?php echo (isset($lead['balance_paid']) && $lead['balance_paid']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="balance_paid<?php echo $lead['id']; ?>">
                                    <strong>Remaining Balance Received</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Installation Date Field (shown when deposit is paid) -->
                    <div class="mb-3" id="installationDateSection<?php echo $lead['id']; ?>" style="<?php echo (isset($lead['deposit_paid']) && $lead['deposit_paid']) ? '' : 'display: none;'; ?>">
                        <div class="alert alert-info">
                            <i class="fas fa-tools me-2"></i>
                            <strong>Installation Scheduling</strong> - Deposit received, please set installation date and assign installer
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="installation_date<?php echo $lead['id']; ?>" class="form-label">
                                    <i class="fas fa-calendar-plus me-2"></i>Installation Date
                                </label>
                                <input type="date" class="form-control" id="installation_date<?php echo $lead['id']; ?>" 
                                       name="installation_date" value="<?php echo isset($lead['installation_date']) ? $lead['installation_date'] : ''; ?>">
                                <div class="form-text">Set the scheduled installation date</div>
                            </div>
                            <div class="col-md-6">
                                <label for="assigned_installer<?php echo $lead['id']; ?>" class="form-label">
                                    <i class="fas fa-user-hard-hat me-2"></i>Assigned Installer
                                </label>
                                <select class="form-select" id="assigned_installer<?php echo $lead['id']; ?>" name="assigned_installer">
                                    <option value="">-- Select Installer --</option>
                                    <?php foreach ($installers as $installer): ?>
                                        <option value="<?php echo htmlspecialchars($installer['name']); ?>" 
                                                <?php echo (isset($lead['assigned_installer']) && $lead['assigned_installer'] == $installer['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($installer['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Assign installer for this project</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="next_followup_date<?php echo $lead['id']; ?>" class="form-label">Next Follow-up Date</label>
                        <input type="date" class="form-control" id="next_followup_date<?php echo $lead['id']; ?>" name="next_followup_date" 
                               value="<?php echo $lead['next_followup_date']; ?>">
                        <div class="form-text">Leave empty if no further follow-up needed</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assigned_to_payment<?php echo $lead['id']; ?>" class="form-label">Assigned To</label>
                        <select class="form-select" name="assigned_to" id="assigned_to_payment<?php echo $lead['id']; ?>">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                        <?php echo $lead['assigned_to'] == $user['full_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Assign payment follow-up to a team member</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes_payment<?php echo $lead['id']; ?>" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_payment<?php echo $lead['id']; ?>" rows="3" placeholder="Add notes about payment status or follow-up details..."><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                        <div class="form-text">Update notes for this payment follow-up</div>
                    </div>
                    
                    <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                    <input type="hidden" name="update_payment_status" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Update Payment Status
                    </button>
                </div>
            </form>
            <?php else: ?>
            <!-- Regular Follow-up Form for Non-Sold Leads -->
            <form method="POST">
                <div class="modal-body">
                    <p><strong>Lead:</strong> <?php echo htmlspecialchars($lead['name']); ?></p>
                    <p><strong>Current Follow-up Date:</strong> <?php echo date('M j, Y', strtotime($lead['next_followup_date'])); ?></p>
                    
                    <div class="mb-3">
                        <label for="next_followup_date<?php echo $lead['id']; ?>" class="form-label">New Follow-up Date</label>
                        <input type="date" class="form-control" id="next_followup_date<?php echo $lead['id']; ?>" name="next_followup_date" 
                               value="<?php echo $lead['next_followup_date']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assigned_to_followup<?php echo $lead['id']; ?>" class="form-label">Assigned To</label>
                        <select class="form-select" name="assigned_to" id="assigned_to_followup<?php echo $lead['id']; ?>">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                        <?php echo $lead['assigned_to'] == $user['full_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Assign follow-up to a team member</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes_followup<?php echo $lead['id']; ?>" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_followup<?php echo $lead['id']; ?>" rows="3" placeholder="Add follow-up notes or reminders..."><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                        <div class="form-text">Update notes for this follow-up</div>
                    </div>
                    
                    <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                    <input type="hidden" name="update_followup" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Follow-up Date</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Edit Lead Modals -->
<?php foreach (array_merge($followup_leads, $upcoming_leads) as $lead): ?>
<div class="modal fade" id="editLeadModal<?php echo $lead['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_lead" value="1">
                    <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name<?php echo $lead['id']; ?>" class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" id="name<?php echo $lead['id']; ?>" value="<?php echo htmlspecialchars($lead['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone<?php echo $lead['id']; ?>" class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" id="phone<?php echo $lead['id']; ?>" value="<?php echo htmlspecialchars($lead['phone']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email<?php echo $lead['id']; ?>" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email<?php echo $lead['id']; ?>" value="<?php echo htmlspecialchars($lead['email']); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="address<?php echo $lead['id']; ?>" class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="address<?php echo $lead['id']; ?>" 
                                   value="<?php echo htmlspecialchars($lead['address'] ?? ''); ?>" placeholder="Property address for installation">
                            <div class="form-text">Full address where service will be provided</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="remarks<?php echo $lead['id']; ?>" class="form-label">Status *</label>
                            <select class="form-select" name="remarks" id="remarks<?php echo $lead['id']; ?>" required onchange="togglePaymentTracking<?php echo $lead['id']; ?>(this.value)">
                                <option value="New" <?php echo $lead['remarks'] == 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="Sold" <?php echo $lead['remarks'] == 'Sold' ? 'selected' : ''; ?>>Sold</option>
                                <option value="In Progress" <?php echo $lead['remarks'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Not Service Area" <?php echo $lead['remarks'] == 'Not Service Area' ? 'selected' : ''; ?>>Not Service Area</option>
                                <option value="Not Interested" <?php echo $lead['remarks'] == 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                                <option value="Not Compatible" <?php echo $lead['remarks'] == 'Not Compatible' ? 'selected' : ''; ?>>Not Compatible</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assigned_to<?php echo $lead['id']; ?>" class="form-label">Assigned To</label>
                            <select class="form-select" name="assigned_to" id="assigned_to<?php echo $lead['id']; ?>">
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                            <?php echo $lead['assigned_to'] == $user['full_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="project_amount<?php echo $lead['id']; ?>" class="form-label">Project Amount</label>
                            <input type="number" class="form-control" name="project_amount" id="project_amount<?php echo $lead['id']; ?>" value="<?php echo $lead['project_amount']; ?>" step="0.01" min="0">
                        </div>
                        
                        <!-- Payment Tracking Section - Only visible for Sold leads -->
                        <div class="col-12 mb-3" id="paymentTracking<?php echo $lead['id']; ?>" style="<?php echo $lead['remarks'] == 'Sold' ? '' : 'display: none;'; ?>">
                            <div class="card bg-light border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-dollar-sign me-2"></i>Payment Tracking
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_deposit_paid<?php echo $lead['id']; ?>" 
                                                       name="deposit_paid" value="1" <?php echo (isset($lead['deposit_paid']) && $lead['deposit_paid']) ? 'checked' : ''; ?>
                                                       onchange="toggleEditInstallationDate<?php echo $lead['id']; ?>(this.checked)">
                                                <label class="form-check-label" for="edit_deposit_paid<?php echo $lead['id']; ?>">
                                                    <strong>Deposit Received</strong>
                                                    <?php if (isset($lead['deposit_paid']) && $lead['deposit_paid']): ?>
                                                        <span class="badge bg-success ms-2">✓ Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning ms-2">Pending</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_balance_paid<?php echo $lead['id']; ?>" 
                                                       name="balance_paid" value="1" <?php echo (isset($lead['balance_paid']) && $lead['balance_paid']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="edit_balance_paid<?php echo $lead['id']; ?>">
                                                    <strong>Remaining Balance Received</strong>
                                                    <?php if (isset($lead['balance_paid']) && $lead['balance_paid']): ?>
                                                        <span class="badge bg-success ms-2">✓ Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning ms-2">Pending</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Installation Date for Edit Modal -->
                                    <div class="mt-3" id="editInstallationDateSection<?php echo $lead['id']; ?>" style="<?php echo (isset($lead['deposit_paid']) && $lead['deposit_paid']) ? '' : 'display: none;'; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="edit_installation_date<?php echo $lead['id']; ?>" class="form-label">
                                                    <i class="fas fa-calendar-plus me-2"></i>Installation Date
                                                </label>
                                                <input type="date" class="form-control" id="edit_installation_date<?php echo $lead['id']; ?>" 
                                                       name="installation_date" value="<?php echo isset($lead['installation_date']) ? $lead['installation_date'] : ''; ?>">
                                                <div class="form-text">Scheduled installation date</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="edit_assigned_installer<?php echo $lead['id']; ?>" class="form-label">
                                                    <i class="fas fa-user-hard-hat me-2"></i>Assigned Installer
                                                </label>
                                                <select class="form-select" id="edit_assigned_installer<?php echo $lead['id']; ?>" name="assigned_installer">
                                                    <option value="">-- Select Installer --</option>
                                                    <?php foreach ($installers as $installer): ?>
                                                        <option value="<?php echo htmlspecialchars($installer['name']); ?>" 
                                                                <?php echo (isset($lead['assigned_installer']) && $lead['assigned_installer'] == $installer['name']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($installer['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text">Assign installer for this project</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Payment tracking is only available for sold leads. Toggle the switches to mark payments as received.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="next_followup_date_edit<?php echo $lead['id']; ?>" class="form-label">
                                <i class="fas fa-calendar-alt me-2"></i>Next Follow-up Date
                            </label>
                            <input type="date" class="form-control" name="next_followup_date" id="next_followup_date_edit<?php echo $lead['id']; ?>" 
                                   value="<?php echo $lead['next_followup_date']; ?>">
                            <div class="form-text">Set next follow-up date (leave empty if no follow-up needed)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lead_origin<?php echo $lead['id']; ?>" class="form-label">Lead Origin</label>
                            <input type="text" class="form-control" name="lead_origin" id="lead_origin<?php echo $lead['id']; ?>" 
                                   value="<?php echo htmlspecialchars($lead['lead_origin']); ?>" placeholder="e.g., Facebook, Google, Referral">
                            <div class="form-text">Source where this lead originated from</div>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="notes<?php echo $lead['id']; ?>" class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="notes<?php echo $lead['id']; ?>" rows="3"><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<style>
/* Smooth animations and transitions */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn {
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transition: background-color 0.2s ease;
}

.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: none;
}

/* Loading animation */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

.loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #007bff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Smooth form transitions */
.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

/* Badge animations */
.badge {
    transition: transform 0.2s ease;
}

.badge:hover {
    transform: scale(1.05);
}

/* Quick action stats hover effects */
.text-center:hover .display-4 {
    color: #007bff !important;
    transition: color 0.2s ease;
}

/* Success message animation */
.alert {
    animation: slideInDown 0.3s ease;
}

@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Table row animation on load */
tbody tr {
    animation: fadeInUp 0.3s ease forwards;
    opacity: 0;
    transform: translateY(20px);
}

tbody tr:nth-child(1) { animation-delay: 0.1s; }
tbody tr:nth-child(2) { animation-delay: 0.2s; }
tbody tr:nth-child(3) { animation-delay: 0.3s; }
tbody tr:nth-child(4) { animation-delay: 0.4s; }
tbody tr:nth-child(5) { animation-delay: 0.5s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Form submission loading state */
form.submitting {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

form.submitting::after {
    content: "Saving...";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.9);
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: bold;
    z-index: 1000;
}
</style>

<script>
// Add form submission loading states
document.addEventListener('DOMContentLoaded', function() {
    // Add loading state to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            this.classList.add('submitting');
        });
    });
    
    // Add smooth scroll behavior for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let hasErrors = false;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    field.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                    hasErrors = true;
                    
                    // Remove error styling when user types
                    field.addEventListener('input', function() {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    });
                } else {
                    field.style.borderColor = '';
                    field.style.boxShadow = '';
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                // Show error message
                const existingError = this.querySelector('.validation-error');
                if (!existingError) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger validation-error mt-2';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please fill in all required fields.';
                    this.querySelector('.modal-body').appendChild(errorDiv);
                    
                    setTimeout(() => errorDiv.remove(), 3000);
                }
                return false;
            }
        });
    });
});

// Smooth scroll to section
function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
        
        // Add a subtle highlight effect
        element.style.transition = 'box-shadow 0.3s ease';
        element.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.3)';
        setTimeout(() => {
            element.style.boxShadow = '';
        }, 2000);
    }
}

// Toggle payment tracking section based on lead status
function togglePaymentTracking(leadId, status) {
    const paymentSection = document.getElementById('paymentTracking' + leadId);
    if (paymentSection) {
        if (status === 'Sold') {
            paymentSection.style.display = 'block';
        } else {
            paymentSection.style.display = 'none';
            // Reset payment checkboxes when not sold
            const depositCheckbox = document.getElementById('edit_deposit_paid' + leadId);
            const balanceCheckbox = document.getElementById('edit_balance_paid' + leadId);
            const installationSection = document.getElementById('editInstallationDateSection' + leadId);
            const installationInput = document.getElementById('edit_installation_date' + leadId);
            
            if (depositCheckbox) depositCheckbox.checked = false;
            if (balanceCheckbox) balanceCheckbox.checked = false;
            if (installationSection) installationSection.style.display = 'none';
            if (installationInput) installationInput.value = '';
        }
    }
}

// Toggle installation date section in payment status modal
function toggleInstallationDate(leadId, isDepositPaid) {
    const installationSection = document.getElementById('installationDateSection' + leadId);
    const installationInput = document.getElementById('installation_date' + leadId);
    
    if (installationSection) {
        if (isDepositPaid) {
            installationSection.style.display = 'block';
            // Set default date to next week if empty
            if (installationInput && !installationInput.value) {
                const nextWeek = new Date();
                nextWeek.setDate(nextWeek.getDate() + 7);
                installationInput.value = nextWeek.toISOString().split('T')[0];
            }
        } else {
            installationSection.style.display = 'none';
            if (installationInput) installationInput.value = '';
        }
    }
}

// Toggle installation date section in edit modal
function toggleEditInstallationDate(leadId, isDepositPaid) {
    const installationSection = document.getElementById('editInstallationDateSection' + leadId);
    const installationInput = document.getElementById('edit_installation_date' + leadId);
    
    if (installationSection) {
        if (isDepositPaid) {
            installationSection.style.display = 'block';
            // Set default date to next week if empty
            if (installationInput && !installationInput.value) {
                const nextWeek = new Date();
                nextWeek.setDate(nextWeek.getDate() + 7);
                installationInput.value = nextWeek.toISOString().split('T')[0];
            }
        } else {
            installationSection.style.display = 'none';
            if (installationInput) installationInput.value = '';
        }
    }
}

// Initialize payment tracking toggles for all leads
<?php foreach (array_merge($followup_leads, $upcoming_leads) as $lead): ?>
window['togglePaymentTracking<?php echo $lead['id']; ?>'] = function(status) {
    togglePaymentTracking(<?php echo $lead['id']; ?>, status);
};

window['toggleInstallationDate<?php echo $lead['id']; ?>'] = function(isChecked) {
    toggleInstallationDate(<?php echo $lead['id']; ?>, isChecked);
};

window['toggleEditInstallationDate<?php echo $lead['id']; ?>'] = function(isChecked) {
    toggleEditInstallationDate(<?php echo $lead['id']; ?>, isChecked);
};
<?php endforeach; ?>
</script>

<?php if (empty($followup_leads) && empty($upcoming_leads)): ?>
<div class="text-center py-5">
    <i class="fas fa-calendar-check display-1 text-muted mb-3"></i>
    <h3>All Caught Up!</h3>
    <p class="text-muted">No follow-ups needed at this time. Great job staying on top of your leads!</p>
    <a href="leads.php" class="btn btn-primary">View All Leads</a>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>