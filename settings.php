<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Settings - Wrap My Kitchen';

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        try {
            // Get current user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Check if we're updating password
            if (!empty($new_password)) {
                if (empty($current_password) || !password_verify($current_password, $user['password'])) {
                    throw new Exception("Current password is incorrect");
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match");
                }
                if (strlen($new_password) < 6) {
                    throw new Exception("New password must be at least 6 characters long");
                }
                
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $hashed_password, $_SESSION['user_id']]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $_SESSION['user_id']]);
            }
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            
            $success_message = "Profile updated successfully!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
} catch (PDOException $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Get all users (for admin view)
try {
    $stmt = $pdo->query("SELECT id, username, full_name, created_at FROM users ORDER BY full_name");
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_users = [];
}

// Get system statistics
try {
    // Total leads
    $stmt = $pdo->query("SELECT COUNT(*) as total_leads FROM leads");
    $total_leads = $stmt->fetch()['total_leads'];
    
    // Leads this month
    $stmt = $pdo->query("SELECT COUNT(*) as month_leads FROM leads WHERE MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)");
    $month_leads = $stmt->fetch()['month_leads'];
    
    // Total sold
    $stmt = $pdo->query("SELECT COUNT(*) as sold_leads FROM leads WHERE remarks = 'Sold'");
    $sold_leads = $stmt->fetch()['sold_leads'];
    
    // Total revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(project_amount), 0) as total_revenue FROM leads WHERE remarks = 'Sold'");
    $total_revenue = $stmt->fetch()['total_revenue'];
    
} catch (PDOException $e) {
    $total_leads = $month_leads = $sold_leads = $total_revenue = 0;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-cog text-primary me-2"></i>
        Settings
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

<?php if (isset($_SESSION['export_message'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo $_SESSION['export_message']; unset($_SESSION['export_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['export_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['export_error']; unset($_SESSION['export_error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Profile Settings -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Profile Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Change Password <small class="text-muted">(leave blank to keep current)</small></h6>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Account created: <?php echo date('M j, Y', strtotime($current_user['created_at'])); ?></small>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Team Members -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Team Members
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Member Since</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-primary ms-2">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-success">Active</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Active Sessions -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Active Sessions
                    </h5>
                    <div>
                        <span id="sessionCount" class="badge bg-info">Loading...</span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="refreshSessions()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="activeSessionsTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Login Time</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading active sessions...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>
                        <strong>Status Legend:</strong> 
                        <span class="badge bg-success ms-2">Online</span> Active now
                        <span class="badge bg-warning ms-2">Away</span> Inactive 1-5 min
                        <span class="badge bg-secondary ms-2">Inactive</span> No activity 5+ min
                        <br>
                        Session data updates automatically every 10 seconds. Sessions are considered inactive after 5 minutes of no activity.
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Export Data -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-download me-2"></i>
                    Export Data
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Download your leads data in various formats for backup or analysis.</p>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                                <h6>Export All Leads</h6>
                                <p class="text-muted small">Download all leads data as CSV file</p>
                                <button class="btn btn-success btn-sm" onclick="exportLeads('all')">
                                    <i class="fas fa-download me-1"></i>Download CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-trophy fa-3x text-warning mb-3"></i>
                                <h6>Export Sold Leads</h6>
                                <p class="text-muted small">Download only sold leads data</p>
                                <button class="btn btn-warning btn-sm" onclick="exportLeads('sold')">
                                    <i class="fas fa-download me-1"></i>Download CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-3x text-info mb-3"></i>
                                <h6>Export This Month</h6>
                                <p class="text-muted small">Download current month's leads</p>
                                <button class="btn btn-info btn-sm" onclick="exportLeads('month')">
                                    <i class="fas fa-download me-1"></i>Download CSV
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-filter fa-3x text-primary mb-3"></i>
                                <h6>Custom Export</h6>
                                <p class="text-muted small">Choose date range and filters</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#customExportModal">
                                    <i class="fas fa-cog me-1"></i>Configure
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Export includes:</strong> Date Created, Name, Phone, Email, Lead Origin, Status, Assigned To, Project Amount, Notes, and Follow-up Dates
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Statistics -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    System Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="p-3">
                            <h3 class="text-primary mb-1"><?php echo number_format($total_leads); ?></h3>
                            <small class="text-muted">Total Leads</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3">
                            <h3 class="text-success mb-1"><?php echo number_format($month_leads); ?></h3>
                            <small class="text-muted">This Month</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3">
                            <h3 class="text-info mb-1"><?php echo number_format($sold_leads); ?></h3>
                            <small class="text-muted">Sold Leads</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3">
                            <h3 class="text-warning mb-1">$<?php echo number_format($total_revenue, 0); ?></h3>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    System Information
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Company:</strong><br>
                    <span class="text-muted">Wrap My Kitchen</span>
                </div>
                <div class="mb-3">
                    <strong>Designed & Developed By:</strong><br>
                    <span class="text-muted">Koen Studio</span>
                </div>
                <div class="mb-3">
                    <strong>Database:</strong><br>
                    <span class="text-muted">MySQL</span>
                </div>
                <div class="mb-3">
                    <strong>Current User:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
                <div class="mb-0">
                    <strong>Last Login:</strong><br>
                    <span class="text-muted"><?php echo date('M j, Y g:i A'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Export Modal -->
<div class="modal fade" id="customExportModal" tabindex="-1" aria-labelledby="customExportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customExportModalLabel">
                    <i class="fas fa-filter me-2"></i>Custom Export Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="customExportForm">
                    <div class="mb-3">
                        <label for="dateFrom" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFrom" name="date_from">
                    </div>
                    
                    <div class="mb-3">
                        <label for="dateTo" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateTo" name="date_to">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status Filter</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="selectAllStatuses" onchange="toggleAllStatuses()">
                                <label class="form-check-label fw-bold" for="selectAllStatuses">
                                    Select All Statuses
                                </label>
                            </div>
                            <hr class="my-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="New" id="statusNew">
                                        <label class="form-check-label" for="statusNew">
                                            <span class="badge bg-primary me-2">New</span>
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="In Progress" id="statusInProgress">
                                        <label class="form-check-label" for="statusInProgress">
                                            <span class="badge bg-info me-2">In Progress</span>
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="Sold" id="statusSold">
                                        <label class="form-check-label" for="statusSold">
                                            <span class="badge bg-success me-2">Sold</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="Not Interested" id="statusNotInterested">
                                        <label class="form-check-label" for="statusNotInterested">
                                            <span class="badge bg-danger me-2">Not Interested</span>
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="Not Service Area" id="statusNotServiceArea">
                                        <label class="form-check-label" for="statusNotServiceArea">
                                            <span class="badge bg-warning me-2">Not Service Area</span>
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input status-checkbox" type="checkbox" name="status[]" value="Not Compatible" id="statusNotCompatible">
                                        <label class="form-check-label" for="statusNotCompatible">
                                            <span class="badge bg-secondary me-2">Not Compatible</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted">Leave all unchecked to export all statuses</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exportOrigin" class="form-label">Lead Origin Filter</label>
                        <select class="form-select" id="exportOrigin" name="origin">
                            <option value="">All Origins</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Google Text">Google Text</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Trade Show">Trade Show</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Referral">Referral</option>
                            <option value="Website">Website</option>
                            <option value="Google">Google</option>
                            <option value="Phone">Phone</option>
                            <option value="Email">Email</option>
                            <option value="Walk-in">Walk-in</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exportAssigned" class="form-label">Assigned To Filter</label>
                        <select class="form-select" id="exportAssigned" name="assigned">
                            <option value="">All Assignees</option>
                            <option value="Kim">Kim</option>
                            <option value="Patrick">Patrick</option>
                            <option value="Lina">Lina</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="exportCustomLeads()">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Session Tracker Script -->
<script src="js/session_tracker.js"></script>

<script>
function refreshSessions() {
    if (window.sessionTracker) {
        window.sessionTracker.updateSessionDisplay();
    }
}

function exportLeads(type) {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'handlers/export_leads.php';
    form.style.display = 'none';
    
    const typeInput = document.createElement('input');
    typeInput.name = 'export_type';
    typeInput.value = type;
    form.appendChild(typeInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Reset button after a delay
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    }, 2000);
}

function toggleAllStatuses() {
    const selectAll = document.getElementById('selectAllStatuses');
    const statusCheckboxes = document.querySelectorAll('.status-checkbox');
    
    statusCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function exportCustomLeads() {
    const form = document.getElementById('customExportForm');
    const formData = new FormData(form);
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    // Create hidden form for submission
    const exportForm = document.createElement('form');
    exportForm.method = 'POST';
    exportForm.action = 'handlers/export_leads.php';
    exportForm.style.display = 'none';
    
    // Add export type
    const typeInput = document.createElement('input');
    typeInput.name = 'export_type';
    typeInput.value = 'custom';
    exportForm.appendChild(typeInput);
    
    // Add form data
    for (let [key, value] of formData.entries()) {
        if (value) { // Only add non-empty values
            const input = document.createElement('input');
            input.name = key;
            input.value = value;
            exportForm.appendChild(input);
        }
    }
    
    document.body.appendChild(exportForm);
    exportForm.submit();
    document.body.removeChild(exportForm);
    
    // Close modal and reset button
    const modal = bootstrap.Modal.getInstance(document.getElementById('customExportModal'));
    modal.hide();
    
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    }, 2000);
}

// Set default dates for custom export
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('dateFrom').value = firstDay.toISOString().split('T')[0];
    document.getElementById('dateTo').value = today.toISOString().split('T')[0];
    
    // Check "Select All" by default
    document.getElementById('selectAllStatuses').checked = true;
    toggleAllStatuses();
    
    // Add event listeners to individual checkboxes to update "Select All" state
    document.querySelectorAll('.status-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allCheckboxes = document.querySelectorAll('.status-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.status-checkbox:checked');
            const selectAllCheckbox = document.getElementById('selectAllStatuses');
            
            if (checkedCheckboxes.length === allCheckboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>