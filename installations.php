<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Installation Management - Wrap My Kitchen';

// Function to ensure payment and installation columns
function ensureInstallationColumns($pdo) {
    static $columns_checked = false;
    if (!$columns_checked) {
        try {
            // Check and add payment columns
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
                error_log("Added assigned_installer column to leads table");
            }
            
            $columns_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring installation columns: " . $e->getMessage());
        }
    }
}

// Function to ensure installers table exists
function ensureInstallersTable($pdo) {
    static $table_checked = false;
    if (!$table_checked) {
        try {
            // Check if installers table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'installers'");
            if ($stmt->rowCount() == 0) {
                // Create installers table
                $create_table_sql = "
                    CREATE TABLE installers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        phone VARCHAR(20) NULL,
                        email VARCHAR(100) NULL,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        hire_date DATE NULL,
                        hourly_rate DECIMAL(10,2) NULL,
                        specialty TEXT NULL,
                        notes TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
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
                    error_log("Added default installers: Angel, Brian, and Luis");
                }
            }
            
            $table_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring installers table: " . $e->getMessage());
        }
    }
}

// Helper function to get payment status badge
function getPaymentStatusBadge($deposit_paid, $balance_paid, $project_amount) {
    if (empty($project_amount) || $project_amount <= 0) {
        return '<span class="badge bg-secondary">No Amount Set</span>';
    }
    
    if ($deposit_paid && $balance_paid) {
        return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Fully Paid</span>';
    } elseif ($deposit_paid && !$balance_paid) {
        return '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Balance Due</span>';
    } else {
        return '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i>Payment Pending</span>';
    }
}

// Helper function to format installation date
function formatInstallationDate($date) {
    if (empty($date)) {
        return '<span class="text-muted">Not Scheduled</span>';
    }
    
    // Set timezone to EST
    $est_timezone = new DateTimeZone('America/New_York');
    $install_date = new DateTime($date, $est_timezone);
    $today = new DateTime('now', $est_timezone);
    $diff = $today->diff($install_date);
    
    if ($install_date->format('Y-m-d') === $today->format('Y-m-d')) {
        return '<span class="text-primary font-weight-bold"><i class="fas fa-calendar-day me-1"></i>Today</span>';
    } elseif ($install_date < $today) {
        return '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>' . $install_date->format('M j, Y') . ' (Overdue)</span>';
    } else {
        $days = $diff->days;
        if ($days <= 7) {
            return '<span class="text-warning"><i class="fas fa-calendar-alt me-1"></i>' . $install_date->format('M j, Y') . ' (' . $days . ' days)</span>';
        } else {
            return '<span class="text-info"><i class="fas fa-calendar me-1"></i>' . $install_date->format('M j, Y') . '</span>';
        }
    }
}

// Get date ranges (using EST timezone)
$est_timezone = new DateTimeZone('America/New_York');
$today_est = new DateTime('now', $est_timezone);
$today = $today_est->format('Y-m-d');
$tomorrow = $today_est->modify('+1 day')->format('Y-m-d');
$today_est = new DateTime('now', $est_timezone); // Reset for next calculation
$next_7_days = $today_est->modify('+7 days')->format('Y-m-d');
$today_est = new DateTime('now', $est_timezone); // Reset for next calculation
$next_30_days = $today_est->modify('+30 days')->format('Y-m-d');
$today_est = new DateTime('now', $est_timezone); // Reset for next calculation
$next_month_start = $today_est->modify('first day of next month')->format('Y-m-d');
$today_est = new DateTime('now', $est_timezone); // Reset for next calculation
$next_month_end = $today_est->modify('last day of next month')->format('Y-m-d');

try {
    // Ensure database columns exist first
    ensureInstallationColumns($pdo);
    
    // Ensure installers table exists
    ensureInstallersTable($pdo);
    
    // Get installation statistics
    $stats_queries = [
        'today' => "SELECT COUNT(*) as count FROM leads WHERE installation_date = ? AND remarks = 'Sold'",
        'tomorrow' => "SELECT COUNT(*) as count FROM leads WHERE installation_date = ? AND remarks = 'Sold'",
        'next_7_days' => "SELECT COUNT(*) as count FROM leads WHERE installation_date BETWEEN ? AND ? AND remarks = 'Sold'",
        'total_scheduled' => "SELECT COUNT(*) as count FROM leads WHERE installation_date IS NOT NULL AND installation_date >= ? AND remarks = 'Sold'",
        'pending_balance' => "SELECT COUNT(*) as count FROM leads WHERE installation_date IS NOT NULL AND remarks = 'Sold' AND deposit_paid = 1 AND balance_paid = 0",
        'overdue_installations' => "SELECT COUNT(*) as count FROM leads WHERE installation_date < ? AND installation_date IS NOT NULL AND remarks = 'Sold'",
        'next_month' => "SELECT COUNT(*) as count FROM leads WHERE installation_date BETWEEN ? AND ? AND remarks = 'Sold'"
    ];
    
    // Execute statistics queries
    $stmt = $pdo->prepare($stats_queries['today']);
    $stmt->execute([$today]);
    $today_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['tomorrow']);
    $stmt->execute([$tomorrow]);
    $tomorrow_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['next_7_days']);
    $stmt->execute([$today, $next_7_days]);
    $next_7_days_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['total_scheduled']);
    $stmt->execute([$today]);
    $total_scheduled = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['pending_balance']);
    $stmt->execute();
    $pending_balance_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['overdue_installations']);
    $stmt->execute([$today]);
    $overdue_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare($stats_queries['next_month']);
    $stmt->execute([$next_month_start, $next_month_end]);
    $next_month_count = $stmt->fetch()['count'];
    
    // Get current month installations (for current month table)
    $current_month_sql = "
        SELECT id, name, phone, email, project_amount, installation_date, 
               deposit_paid, balance_paid, assigned_installer, notes, remarks
        FROM leads 
        WHERE installation_date IS NOT NULL 
        AND installation_date < ?
        AND remarks = 'Sold'
        ORDER BY installation_date ASC
    ";
    
    $stmt = $pdo->prepare($current_month_sql);
    $stmt->execute([$next_month_start]);
    $current_installations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get next month installations
    $next_month_sql = "
        SELECT id, name, phone, email, project_amount, installation_date, 
               deposit_paid, balance_paid, assigned_installer, notes, remarks
        FROM leads 
        WHERE installation_date BETWEEN ? AND ?
        AND remarks = 'Sold'
        ORDER BY installation_date ASC
    ";
    
    $stmt = $pdo->prepare($next_month_sql);
    $stmt->execute([$next_month_start, $next_month_end]);
    $next_month_installations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the query and results for troubleshooting
    error_log("Installations queries executed successfully");
    error_log("Found " . count($current_installations) . " current installations");
    error_log("Found " . count($next_month_installations) . " next month installations");
    
    // Additional verification - check if installation_date column exists
    $column_check = $pdo->query("SHOW COLUMNS FROM leads LIKE 'installation_date'");
    if ($column_check->rowCount() == 0) {
        error_log("WARNING: installation_date column does not exist in leads table");
    } else {
        error_log("Confirmed: installation_date column exists in leads table");
    }
    
    if (!empty($current_installations)) {
        error_log("Sample current installation data - ID: " . $current_installations[0]['id'] . 
                 ", Name: " . $current_installations[0]['name'] . 
                 ", Installation Date: " . ($current_installations[0]['installation_date'] ?? 'NULL') . 
                 ", Remarks: " . $current_installations[0]['remarks']);
    }
    
    if (!empty($next_month_installations)) {
        error_log("Sample next month installation data - ID: " . $next_month_installations[0]['id'] . 
                 ", Name: " . $next_month_installations[0]['name'] . 
                 ", Installation Date: " . ($next_month_installations[0]['installation_date'] ?? 'NULL'));
    }
    
    // Get installers for assignment dropdown
    $stmt = $pdo->query("SELECT id, name, status FROM installers WHERE status = 'active' ORDER BY name");
    $installers = $stmt->fetchAll();
    
    // Get users for backward compatibility (if needed)
    $stmt = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name");
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Installation page error: " . $e->getMessage());
    $today_count = $tomorrow_count = $next_7_days_count = $total_scheduled = $pending_balance_count = $overdue_count = $next_month_count = 0;
    $current_installations = $next_month_installations = $users = $installers = [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tools me-2"></i>Installation Management</h1>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i><?php 
                // Display current date/time in EST
                $est_timezone = new DateTimeZone('America/New_York');
                $now_est = new DateTime('now', $est_timezone);
                echo $now_est->format('l, M j, Y g:i A T'); 
                ?>
                <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                    <br><small class="text-info">Debug mode: ON</small>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <div class="alert alert-info">
            <h6><i class="fas fa-bug me-2"></i>Debug Information</h6>
            <?php
            try {
                // Show column existence
                $column_exists = $pdo->query("SHOW COLUMNS FROM leads LIKE 'installation_date'")->rowCount() > 0;
                echo "<p><strong>installation_date column exists:</strong> " . ($column_exists ? "✅ Yes" : "❌ No") . "</p>";
                
                $assigned_column_exists = $pdo->query("SHOW COLUMNS FROM leads LIKE 'assigned_installer'")->rowCount() > 0;
                echo "<p><strong>assigned_installer column exists:</strong> " . ($assigned_column_exists ? "✅ Yes" : "❌ No") . "</p>";
                
                // Show installers table
                $installers_table_exists = $pdo->query("SHOW TABLES LIKE 'installers'")->rowCount() > 0;
                echo "<p><strong>installers table exists:</strong> " . ($installers_table_exists ? "✅ Yes" : "❌ No") . "</p>";
                
                if ($installers_table_exists) {
                    $installers_list = $pdo->query("SELECT id, name, status FROM installers ORDER BY name")->fetchAll();
                    echo "<p><strong>Available installers:</strong> ";
                    foreach ($installers_list as $installer) {
                        $status_icon = $installer['status'] === 'active' ? '✅' : '❌';
                        echo $status_icon . " " . $installer['name'] . " ";
                    }
                    echo "</p>";
                    echo "<p><strong>Total installers:</strong> " . count($installers_list) . "</p>";
                }
                
                // Show total sold leads
                $total_sold = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE remarks = 'Sold'")->fetch()['count'];
                echo "<p><strong>Total sold leads:</strong> " . $total_sold . "</p>";
                
                // Show sold leads with installation dates
                $with_dates = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE remarks = 'Sold' AND installation_date IS NOT NULL AND installation_date != '' AND installation_date != '0000-00-00'")->fetch()['count'];
                echo "<p><strong>Sold leads with installation dates:</strong> " . $with_dates . "</p>";
                
                // Show sample data
                $sample = $pdo->query("SELECT id, name, installation_date, remarks FROM leads WHERE remarks = 'Sold' LIMIT 3")->fetchAll();
                if (!empty($sample)) {
                    echo "<p><strong>Sample data:</strong></p><ul>";
                    foreach ($sample as $row) {
                        echo "<li>ID {$row['id']}: {$row['name']} - Date: " . ($row['installation_date'] ?? 'NULL') . " - Status: {$row['remarks']}</li>";
                    }
                    echo "</ul>";
                }
            } catch (PDOException $e) {
                echo "<p class='text-danger'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
            <small class="text-muted">Remove ?debug=1 from URL to hide this information</small>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Installation Statistics -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $today_count; ?></h4>
                        <p class="mb-0">Today</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $tomorrow_count; ?></h4>
                        <p class="mb-0">Tomorrow</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-plus fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $next_7_days_count; ?></h4>
                        <p class="mb-0">Next 7 Days</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card bg-purple text-white" style="background-color: #6f42c1 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $next_month_count; ?></h4>
                        <p class="mb-0">Next Month</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $pending_balance_count; ?></h4>
                        <p class="mb-0">Balance Due</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $overdue_count; ?></h4>
                        <p class="mb-0">Overdue</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current & Upcoming Installations Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Current & Upcoming Installations</h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterCurrentInstallations('all')">All</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterCurrentInstallations('today')">Today</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterCurrentInstallations('week')">This Week</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterCurrentInstallations('overdue')">Overdue</button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($current_installations)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <p>No current installations scheduled.</p>
                        
                        <?php
                        // Check if there are sold leads without installation dates
                        try {
                            $sold_without_date = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE remarks = 'Sold' AND (installation_date IS NULL OR installation_date = '')")->fetch()['count'];
                            if ($sold_without_date > 0) {
                                echo '<div class="alert alert-info mt-3">';
                                echo '<i class="fas fa-info-circle me-2"></i>';
                                echo 'There are <strong>' . $sold_without_date . '</strong> sold leads without installation dates. ';
                                echo '<a href="followup.php" class="alert-link">Update them in Follow-ups</a>';
                                echo '</div>';
                            }
                        } catch (PDOException $e) {
                            error_log("Error checking sold leads: " . $e->getMessage());
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="currentInstallationsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Installation Date</th>
                                    <th>Customer</th>
                                    <th>Contact Info</th>
                                    <th>Project Amount</th>
                                    <th>Payment Status</th>
                                    <th>Assigned Installer</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_installations as $installation): ?>
                                <tr data-date="<?php echo $installation['installation_date'] ?? ''; ?>">
                                    <td>
                                        <?php 
                                        // Detailed installation date display with debugging
                                        if (empty($installation['installation_date']) || $installation['installation_date'] === '0000-00-00') {
                                            echo '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>No Date Set</span>';
                                            echo '<br><small class="text-muted">Raw value: ' . ($installation['installation_date'] ?? 'NULL') . '</small>';
                                        } else {
                                            echo formatInstallationDate($installation['installation_date']); 
                                            echo '<br><small class="text-muted">Raw: ' . $installation['installation_date'] . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($installation['name']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($installation['phone']); ?>
                                        </div>
                                        <?php if (!empty($installation['email'])): ?>
                                        <div class="text-muted small">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($installation['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($installation['project_amount']) && $installation['project_amount'] > 0): ?>
                                            <strong>$<?php echo number_format($installation['project_amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo getPaymentStatusBadge($installation['deposit_paid'], $installation['balance_paid'], $installation['project_amount']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $assigned_installer = htmlspecialchars($installation['assigned_installer'] ?? '');
                                        
                                        if (empty($assigned_installer) || $assigned_installer === 'Not Assigned') {
                                            echo '<span class="badge bg-light text-dark">Not Assigned</span>';
                                        } else {
                                            // Check if it's one of our installers
                                            $installer_names = ['Angel', 'Brian', 'Luis'];
                                            if (in_array($assigned_installer, $installer_names)) {
                                                $badge_class = match($assigned_installer) {
                                                    'Angel' => 'bg-primary',
                                                    'Brian' => 'bg-success', 
                                                    'Luis' => 'bg-info',
                                                    default => 'bg-secondary'
                                                };
                                                echo '<span class="badge ' . $badge_class . '"><i class="fas fa-tools me-1"></i>' . $assigned_installer . '</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">' . $assigned_installer . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="openEditModal(<?php echo $installation['id']; ?>, '<?php echo htmlspecialchars(addslashes($installation['name'])); ?>', '<?php echo htmlspecialchars(addslashes($installation['phone'])); ?>', '<?php echo htmlspecialchars(addslashes($installation['email'])); ?>', '<?php echo $installation['installation_date']; ?>', <?php echo $installation['deposit_paid']; ?>, <?php echo $installation['balance_paid']; ?>, '<?php echo $installation['project_amount']; ?>', '<?php echo htmlspecialchars(addslashes($installation['assigned_installer'] ?? 'Not Assigned')); ?>', '<?php echo htmlspecialchars(addslashes($installation['notes'] ?? '')); ?>')">>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="markAsCompleted(<?php echo $installation['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Next Month Installations Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2 text-purple"></i>
                        Next Month Installations
                        <span class="badge bg-purple ms-2"><?php echo date('F Y', strtotime($next_month_start)); ?></span>
                    </h5>
                    <small class="text-muted">
                        <?php echo $next_month_count; ?> installation<?php echo $next_month_count != 1 ? 's' : ''; ?> scheduled
                    </small>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($next_month_installations)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-check fa-3x mb-3"></i>
                        <p>No installations scheduled for <?php echo date('F Y', strtotime($next_month_start)); ?> yet.</p>
                        <p><small>Schedule installations through the <a href="followup.php" class="text-decoration-none">Follow-ups page</a> when deposits are received.</small></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="nextMonthInstallationsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Installation Date</th>
                                    <th>Customer</th>
                                    <th>Contact Info</th>
                                    <th>Project Amount</th>
                                    <th>Payment Status</th>
                                    <th>Assigned Installer</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($next_month_installations as $installation): ?>
                                <tr data-date="<?php echo $installation['installation_date'] ?? ''; ?>">
                                    <td>
                                        <?php 
                                        if (empty($installation['installation_date']) || $installation['installation_date'] === '0000-00-00') {
                                            echo '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>No Date Set</span>';
                                        } else {
                                            echo formatInstallationDate($installation['installation_date']); 
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($installation['name']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($installation['phone']); ?>
                                        </div>
                                        <?php if (!empty($installation['email'])): ?>
                                        <div class="text-muted small">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($installation['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($installation['project_amount']) && $installation['project_amount'] > 0): ?>
                                            <strong>$<?php echo number_format($installation['project_amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo getPaymentStatusBadge($installation['deposit_paid'], $installation['balance_paid'], $installation['project_amount']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $assigned_installer = htmlspecialchars($installation['assigned_installer'] ?? '');
                                        
                                        if (empty($assigned_installer) || $assigned_installer === 'Not Assigned') {
                                            echo '<span class="badge bg-light text-dark">Not Assigned</span>';
                                        } else {
                                            // Check if it's one of our installers
                                            $installer_names = ['Angel', 'Brian', 'Luis'];
                                            if (in_array($assigned_installer, $installer_names)) {
                                                $badge_class = match($assigned_installer) {
                                                    'Angel' => 'bg-primary',
                                                    'Brian' => 'bg-success', 
                                                    'Luis' => 'bg-info',
                                                    default => 'bg-secondary'
                                                };
                                                echo '<span class="badge ' . $badge_class . '"><i class="fas fa-tools me-1"></i>' . $assigned_installer . '</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">' . $assigned_installer . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="openEditModal(<?php echo $installation['id']; ?>, '<?php echo htmlspecialchars(addslashes($installation['name'])); ?>', '<?php echo htmlspecialchars(addslashes($installation['phone'])); ?>', '<?php echo htmlspecialchars(addslashes($installation['email'])); ?>', '<?php echo $installation['installation_date']; ?>', <?php echo $installation['deposit_paid']; ?>, <?php echo $installation['balance_paid']; ?>, '<?php echo $installation['project_amount']; ?>', '<?php echo htmlspecialchars(addslashes($installation['assigned_installer'] ?? 'Not Assigned')); ?>', '<?php echo htmlspecialchars(addslashes($installation['notes'] ?? '')); ?>')">>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="moveToCurrentMonth(<?php echo $installation['id']; ?>)" 
                                                    title="Move to current month">
                                                <i class="fas fa-arrow-left"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Installation Modal -->
<div class="modal fade" id="editInstallationModal" tabindex="-1" aria-labelledby="editInstallationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editInstallationModalLabel">
                    <i class="fas fa-tools me-2"></i>Edit Installation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editInstallationForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editName" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPhone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="editPhone" name="phone" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editInstallationDate" class="form-label">Installation Date</label>
                                <input type="date" class="form-control" id="editInstallationDate" name="installation_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editProjectAmount" class="form-label">Project Amount</label>
                                <input type="number" class="form-control" id="editProjectAmount" name="project_amount" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editAssignedTo" class="form-label">Assigned Installer</label>
                                <select class="form-select" id="editAssignedTo" name="assigned_installer">
                                    <option value="Not Assigned">Not Assigned</option>
                                    <?php foreach ($installers as $installer): ?>
                                        <option value="<?php echo htmlspecialchars($installer['name']); ?>">
                                            <?php echo htmlspecialchars($installer['name']); ?>
                                            <?php if ($installer['status'] !== 'active'): ?>
                                                (Inactive)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Assign this installation to one of your installers</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editDepositPaid" name="deposit_paid">
                                    <label class="form-check-label" for="editDepositPaid">
                                        Deposit Paid
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editBalancePaid" name="balance_paid">
                                    <label class="form-check-label" for="editBalancePaid">
                                        Balance Paid
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="editNotes" class="form-label">Installation Notes</label>
                                <textarea class="form-control" id="editNotes" name="notes" rows="3" placeholder="Add notes about the installation..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="editInstallationId" name="lead_id">
                    <input type="hidden" name="remarks" value="Sold">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Installation Management JavaScript
const InstallationManager = {
    config: {
        modalId: 'editInstallationModal',
        formId: 'editInstallationForm',
        updateUrl: 'update_lead.php',
        toastDuration: 3000
    },

    init() {
        this.setupFormHandler();
        this.setupModalEvents();
    },

    openEditModal(id, name, phone, email, installationDate, depositPaid, balancePaid, projectAmount, assignedTo, notes) {
        console.log('Opening modal with assigned_to:', assignedTo);
        
        const fields = {
            'editInstallationId': id,
            'editName': name,
            'editPhone': phone,
            'editEmail': email || '',
            'editInstallationDate': installationDate || '',
            'editProjectAmount': projectAmount || '',
            'editAssignedTo': assignedTo || 'Not Assigned',
            'editNotes': notes || ''
        };

        // Populate form fields
        Object.entries(fields).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            if (element) {
                element.value = value;
                console.log(`Set ${fieldId} to:`, value);
            }
        });

        // Set checkboxes
        document.getElementById('editDepositPaid').checked = depositPaid == 1;
        document.getElementById('editBalancePaid').checked = balancePaid == 1;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(this.config.modalId));
        modal.show();
    },

    setupFormHandler() {
        const form = document.getElementById(this.config.formId);
        if (!form) return;

        form.addEventListener('submit', (e) => this.handleSubmit(e));
    },

    setupModalEvents() {
        const modal = document.getElementById(this.config.modalId);
        if (!modal) return;

        modal.addEventListener('hidden.bs.modal', () => {
            document.getElementById(this.config.formId).reset();
        });
    },

    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            
            const response = await fetch(this.config.updateUrl, {
                method: 'POST',
                body: new FormData(form)
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to debug
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Server returned invalid JSON. Check console for details.');
            }
            
            if (data.success) {
                this.showToast('success', 'Installation updated successfully!');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(data.message || data.error || 'Failed to update installation');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showToast('error', error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    },

    showToast(type, message) {
        const isError = type === 'error';
        const bgClass = isError ? 'bg-danger' : 'bg-success';
        const icon = isError ? 'exclamation-circle' : 'check-circle';
        
        const toast = document.createElement('div');
        toast.className = 'toast show position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast-header ${bgClass} text-white">
                <i class="fas fa-${icon} me-2"></i>
                <strong class="me-auto">${type === 'error' ? 'Error' : 'Success'}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), this.config.toastDuration);
    }
};

// Filter functions for current installations
function filterCurrentInstallations(filter) {
    const table = document.getElementById('currentInstallationsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);
    const nextWeekStr = nextWeek.toISOString().split('T')[0];

    rows.forEach(row => {
        const dateCell = row.getAttribute('data-date');
        let show = true;

        switch(filter) {
            case 'today':
                show = dateCell === today;
                break;
            case 'week':
                show = dateCell >= today && dateCell <= nextWeekStr;
                break;
            case 'overdue':
                show = dateCell < today;
                break;
            case 'all':
            default:
                show = true;
                break;
        }

        row.style.display = show ? '' : 'none';
    });

    // Update button states for current installations
    const currentFilterButtons = document.querySelectorAll('.card:first-of-type .btn-group button');
    currentFilterButtons.forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Legacy function for backward compatibility
function filterInstallations(filter) {
    filterCurrentInstallations(filter);
}

// Move installation to current month
async function moveToCurrentMonth(leadId) {
    if (!confirm('Move this installation to current month?')) return;

    try {
        const today = new Date();
        const targetDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 7); // Next week
        const formattedDate = targetDate.toISOString().split('T')[0];

        const formData = new FormData();
        formData.append('lead_id', leadId);
        formData.append('installation_date', formattedDate);

        const response = await fetch('update_lead.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get response text first to debug
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }

        if (data.success) {
            InstallationManager.showToast('success', 'Installation moved to current month!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || data.error || 'Failed to move installation');
        }
    } catch (error) {
        console.error('Error:', error);
        InstallationManager.showToast('error', error.message);
    }
}

// Mark installation as completed
async function markAsCompleted(leadId) {
    if (!confirm('Mark this installation as completed?')) return;

    try {
        const formData = new FormData();
        formData.append('lead_id', leadId);
        formData.append('remarks', 'Installation Complete');
        formData.append('installation_date', '');

        const response = await fetch('update_lead.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get response text first to debug
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }

        if (data.success) {
            InstallationManager.showToast('success', 'Installation marked as completed!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || data.error || 'Failed to update installation');
        }
    } catch (error) {
        console.error('Error:', error);
        InstallationManager.showToast('error', error.message);
    }
}

// Global functions for backward compatibility
function openEditModal(id, name, phone, email, installationDate, depositPaid, balancePaid, projectAmount, assignedTo, notes) {
    InstallationManager.openEditModal(id, name, phone, email, installationDate, depositPaid, balancePaid, projectAmount, assignedTo, notes);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    InstallationManager.init();
    // Set 'All' filter as active by default for current installations
    const firstFilterBtn = document.querySelector('.card:first-of-type .btn-group button');
    if (firstFilterBtn) {
        firstFilterBtn.classList.add('active');
    }
});
</script>

<style>
.bg-purple {
    background-color: #6f42c1 !important;
}
.text-purple {
    color: #6f42c1 !important;
}
.badge.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<?php include 'includes/footer.php'; ?>
