<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'All Leads - Wrap My Kitchen';

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
        'whatsapp' => 'lead-origin-whatsapp',
        'commercial' => 'lead-origin-commercial'
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

// Function to get status badge class
function getStatusBadgeClass($status) {
    $status_classes = [
        'Sold' => 'success',
        'Not Interested' => 'danger',
        'Not Service Area' => 'warning',
        'Not Compatible' => 'secondary',
        'In Progress' => 'info',
        'New' => 'primary'
    ];
    
    return $status_classes[$status] ?? 'primary';
}

// Function to format project amount
function formatProjectAmount($amount) {
    if (!empty($amount) && $amount > 0) {
        return '<strong>$' . number_format($amount, 2) . '</strong>';
    }
    return '<span class="text-muted">-</span>';
}

// Function to format follow-up date
function formatFollowupDate($date) {
    if (!$date) {
        return '<span class="text-muted">Not set</span>';
    }
    
    $followup_timestamp = strtotime($date);
    $today_timestamp = strtotime(date('Y-m-d'));
    
    $class = '';
    if ($followup_timestamp < $today_timestamp) {
        $class = 'text-danger';
    } elseif ($followup_timestamp == $today_timestamp) {
        $class = 'text-warning';
    }
    
    return '<span class="' . $class . '">' . date('M j, Y', $followup_timestamp) . '</span>';
}

// Predefined filter options
$origin_options = [
    'Facebook', 'Google Text', 'Instagram', 'Trade Show', 'WhatsApp', 
    'Commercial', 'Referral', 'Website', 'Google', 'Phone', 'Email', 
    'Walk-in', 'TikTok', 'YouTube', 'LinkedIn', 'Twitter'
];

$status_options = [
    'New', 'In Progress', 'Sold', 'Not Interested', 
    'Not Service Area', 'Not Compatible'
];

$assignee_options = ['Kim', 'Patrick', 'Lina'];

// Function to render table row
function renderLeadRow($lead) {
    $notes_icon = '';
    if (!empty($lead['notes'])) {
        $notes_icon = '<i class="fas fa-comment-dots text-muted ms-1" title="' . htmlspecialchars($lead['notes']) . '"></i>';
    }
    
    $contact_info = '';
    if (!empty($lead['phone'])) {
        $contact_info .= '<div><i class="fas fa-phone text-muted me-1"></i>' . htmlspecialchars($lead['phone']) . '</div>';
    }
    if (!empty($lead['email'])) {
        $contact_info .= '<div><i class="fas fa-envelope text-muted me-1"></i><small>' . htmlspecialchars($lead['email']) . '</small></div>';
    }
    
    echo '<tr>
        <td><small>' . date('M j, Y', strtotime($lead['date_created'])) . '</small></td>
        <td>
            <strong>' . htmlspecialchars($lead['name']) . '</strong>' . $notes_icon . '
        </td>
        <td>' . $contact_info . '</td>
        <td>
            <span class="badge ' . getLeadOriginClass($lead['lead_origin']) . '">' . htmlspecialchars($lead['lead_origin']) . '</span>
        </td>
        <td>' . formatFollowupDate($lead['next_followup_date']) . '</td>
        <td>
            <span class="badge bg-secondary">' . htmlspecialchars($lead['assigned_to']) . '</span>
        </td>
        <td>
            <span class="badge bg-' . getStatusBadgeClass($lead['remarks']) . '">' . htmlspecialchars($lead['remarks']) . '</span>
        </td>
        <td>' . formatProjectAmount($lead['project_amount']) . '</td>
        <td>
            <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="openEditModal(' . $lead['id'] . ')">
                <i class="fas fa-edit"></i>
            </button>
        </td>
    </tr>';
}

// Handle search and filters with sanitization
$search = trim($_GET['search'] ?? '');
$origin_filter = $_GET['origin'] ?? '';
$status_filter = $_GET['status'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';

// Build optimized query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($origin_filter) && in_array($origin_filter, $origin_options)) {
    $where_conditions[] = "lead_origin = ?";
    $params[] = $origin_filter;
}

if (!empty($status_filter) && in_array($status_filter, $status_options)) {
    $where_conditions[] = "remarks = ?";
    $params[] = $status_filter;
}

if (!empty($assigned_filter) && in_array($assigned_filter, $assignee_options)) {
    $where_conditions[] = "assigned_to = ?";
    $params[] = $assigned_filter;
}

try {
    // Ensure database columns and tables exist
    ensurePaymentColumns($pdo);
    ensureInstallersTable($pdo);
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $sql = "SELECT * FROM leads $where_clause ORDER BY date_created DESC, next_followup_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all installers for dropdowns
    $stmt = $pdo->prepare("SELECT id, name FROM installers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $installers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching leads: " . $e->getMessage());
    $leads = [];
    $installers = [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users me-2"></i>All Leads</h1>
            <a href="add_lead.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Lead
            </a>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Name, phone, email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="origin" class="form-label">Origin</label>
                <select class="form-select" id="origin" name="origin">
                    <option value="">All Origins</option>
                    <?php foreach ($origin_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" 
                                <?php echo $origin_filter === $option ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" 
                                <?php echo $status_filter === $option ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="assigned" class="form-label">Assigned To</label>
                <select class="form-select" id="assigned" name="assigned">
                    <option value="">All Assignees</option>
                    <?php foreach ($assignee_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" 
                                <?php echo $assigned_filter === $option ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="leads.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Leads 
            <span class="badge bg-primary"><?php echo count($leads); ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($leads)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No leads found</h5>
                <p class="text-muted">Try adjusting your search criteria or <a href="add_lead.php">add a new lead</a>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Origin</th>
                            <th>Next Follow-up</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <?php renderLeadRow($lead); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1" aria-labelledby="editLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeadModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Lead
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalLoadingSpinner" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading lead information...</p>
                </div>
                
                <form id="editLeadForm" style="display: none;">
                    <input type="hidden" id="leadId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editName" class="form-label">Client Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editPhone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editLeadOrigin" class="form-label">Lead Origin <span class="text-danger">*</span></label>
                            <select class="form-select" id="editLeadOrigin" name="lead_origin" required>
                                <option value="">Select Origin</option>
                                <?php foreach ($origin_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editNextFollowup" class="form-label">Next Follow-up Date</label>
                            <input type="date" class="form-control" id="editNextFollowup" name="next_followup_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editRemarks" class="form-label">Status</label>
                            <select class="form-select" id="editRemarks" name="remarks">
                                <?php foreach ($status_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editAssignedTo" class="form-label">Assigned To</label>
                            <select class="form-select" id="editAssignedTo" name="assigned_to">
                                <?php foreach ($assignee_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editProjectAmount" class="form-label">Project Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="editProjectAmount" name="project_amount" 
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Tracking Section - Only visible for Sold leads -->
                    <div class="mb-3" id="paymentTrackingSection" style="display: none;">
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
                                            <input class="form-check-input" type="checkbox" id="editDepositPaid" 
                                                   name="deposit_paid" value="1" onchange="toggleInstallationSection(this.checked)">
                                            <label class="form-check-label" for="editDepositPaid">
                                                <strong>Deposit Received</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="editBalancePaid" 
                                                   name="balance_paid" value="1">
                                            <label class="form-check-label" for="editBalancePaid">
                                                <strong>Remaining Balance Received</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Installation Details - shown when deposit is paid -->
                                <div class="mt-3" id="installationDetailsSection" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-tools me-2"></i>
                                        <strong>Installation Scheduling</strong> - Deposit received, please set installation date and assign installer
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="editInstallationDate" class="form-label">
                                                <i class="fas fa-calendar-plus me-2"></i>Installation Date
                                            </label>
                                            <input type="date" class="form-control" id="editInstallationDate" name="installation_date">
                                            <div class="form-text">Scheduled installation date</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="editAssignedInstaller" class="form-label">
                                                <i class="fas fa-user-hard-hat me-2"></i>Assigned Installer
                                            </label>
                                            <select class="form-select" id="editAssignedInstaller" name="assigned_installer">
                                                <option value="">-- Select Installer --</option>
                                                <?php foreach ($installers as $installer): ?>
                                                    <option value="<?php echo htmlspecialchars($installer['name']); ?>">
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
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAdditionalNotes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="editAdditionalNotes" name="additional_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveLeadBtn" onclick="saveLead()" style="display: none;">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let editModal;

document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
    
    // Add event listener for status changes
    const statusSelect = document.getElementById('editRemarks');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            togglePaymentTracking(this.value);
        });
    }
});

function openEditModal(leadId) {
    // Validate leadId
    if (!leadId || isNaN(leadId)) {
        alert('Invalid lead ID');
        return;
    }
    
    // Reset modal state
    showModalLoading();
    editModal.show();
    
    // Fetch lead data with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    fetch('handlers/get_lead.php?id=' + leadId, {
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            populateEditForm(data.lead);
            hideModalLoading();
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error loading lead:', error);
        alert('Error loading lead data: ' + (error.name === 'AbortError' ? 'Request timeout' : error.message));
        editModal.hide();
    });
}

function showModalLoading() {
    document.getElementById('modalLoadingSpinner').style.display = 'block';
    document.getElementById('editLeadForm').style.display = 'none';
    document.getElementById('saveLeadBtn').style.display = 'none';
}

function hideModalLoading() {
    document.getElementById('modalLoadingSpinner').style.display = 'none';
    document.getElementById('editLeadForm').style.display = 'block';
    document.getElementById('saveLeadBtn').style.display = 'inline-block';
}

function populateEditForm(lead) {
    const fields = [
        'leadId', 'editName', 'editPhone', 'editEmail', 'editLeadOrigin',
        'editNextFollowup', 'editRemarks', 'editAssignedTo', 'editProjectAmount',
        'editNotes', 'editAdditionalNotes', 'editDepositPaid', 'editBalancePaid',
        'editInstallationDate', 'editAssignedInstaller'
    ];
    
    const mapping = {
        'leadId': 'id',
        'editName': 'name',
        'editPhone': 'phone',
        'editEmail': 'email',
        'editLeadOrigin': 'lead_origin',
        'editNextFollowup': 'next_followup_date',
        'editRemarks': 'remarks',
        'editAssignedTo': 'assigned_to',
        'editProjectAmount': 'project_amount',
        'editNotes': 'notes',
        'editAdditionalNotes': 'additional_notes',
        'editDepositPaid': 'deposit_paid',
        'editBalancePaid': 'balance_paid',
        'editInstallationDate': 'installation_date',
        'editAssignedInstaller': 'assigned_installer'
    };
    
    fields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        const leadField = mapping[fieldId];
        if (element && leadField in lead) {
            if (element.type === 'checkbox') {
                element.checked = lead[leadField] == 1;
            } else {
                element.value = lead[leadField] || '';
            }
        }
    });
    
    // Handle payment tracking visibility
    togglePaymentTracking(lead.remarks);
    
    // Handle installation section visibility
    if (lead.deposit_paid == 1) {
        toggleInstallationSection(true);
    }
}

// Toggle payment tracking section based on lead status
function togglePaymentTracking(status) {
    const paymentSection = document.getElementById('paymentTrackingSection');
    if (paymentSection) {
        if (status === 'Sold') {
            paymentSection.style.display = 'block';
        } else {
            paymentSection.style.display = 'none';
            // Reset payment checkboxes when not sold
            const depositCheckbox = document.getElementById('editDepositPaid');
            const balanceCheckbox = document.getElementById('editBalancePaid');
            const installationSection = document.getElementById('installationDetailsSection');
            const installationDateInput = document.getElementById('editInstallationDate');
            const installerSelect = document.getElementById('editAssignedInstaller');
            
            if (depositCheckbox) depositCheckbox.checked = false;
            if (balanceCheckbox) balanceCheckbox.checked = false;
            if (installationSection) installationSection.style.display = 'none';
            if (installationDateInput) installationDateInput.value = '';
            if (installerSelect) installerSelect.value = '';
        }
    }
}

// Toggle installation section when deposit is toggled
function toggleInstallationSection(isDepositPaid) {
    const installationSection = document.getElementById('installationDetailsSection');
    const installationDateInput = document.getElementById('editInstallationDate');
    
    if (installationSection) {
        if (isDepositPaid) {
            installationSection.style.display = 'block';
            // Set default date to next week if empty
            if (installationDateInput && !installationDateInput.value) {
                const nextWeek = new Date();
                nextWeek.setDate(nextWeek.getDate() + 7);
                installationDateInput.value = nextWeek.toISOString().split('T')[0];
            }
        } else {
            installationSection.style.display = 'none';
            if (installationDateInput) installationDateInput.value = '';
            const installerSelect = document.getElementById('editAssignedInstaller');
            if (installerSelect) installerSelect.value = '';
        }
    }
}

function saveLead() {
    const form = document.getElementById('editLeadForm');
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveLeadBtn');
    
    // Debug: Log form data
    console.log('Form data being sent:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validate required fields
    const requiredFields = ['editName', 'editLeadOrigin'];
    for (const fieldId of requiredFields) {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
            field.focus();
            alert('Please fill in all required fields');
            return;
        }
    }
    
    // Show saving state
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    
    fetch('handlers/update_lead.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showSuccessMessage('Lead updated successfully!');
            editModal.hide();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error saving lead:', error);
        alert('Error updating lead: ' + error.message);
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
}

function showSuccessMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show';
    alert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const firstRow = document.querySelector('.row');
    if (firstRow && firstRow.parentNode) {
        firstRow.parentNode.insertBefore(alert, firstRow);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
