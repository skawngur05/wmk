<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Dashboard - Wrap My Kitchen';

// Helper Functions
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

function getStatusBadgeClass($status) {
    $status_classes = [
        'Sold' => 'success',
        'Not Interested' => 'danger',
        'Not Service Area' => 'warning',
        'Not Compatible' => 'secondary',
        'In Progress' => 'info'
    ];
    
    return $status_classes[$status] ?? 'primary';
}

function formatProjectAmount($amount) {
    if (!empty($amount) && $amount > 0) {
        return '<strong>$' . number_format($amount, 2) . '</strong>';
    }
    return '<span class="text-muted">-</span>';
}

function renderStatsCard($title, $value, $icon, $bgClass, $link = '#') {
    $clickable = $link !== '#' ? 'clickable-card' : '';
    $onclick = $link !== '#' ? "onclick=\"window.location.href='{$link}'\"" : '';
    
    return "
    <div class=\"col-md-3\">
        <div class=\"card bg-{$bgClass} text-white {$clickable}\" {$onclick} style=\"cursor: " . ($link !== '#' ? 'pointer' : 'default') . ";\">
            <div class=\"card-body\">
                <div class=\"d-flex justify-content-between\">
                    <div>
                        <h4>{$value}</h4>
                        <p class=\"mb-0\">{$title}</p>
                    </div>
                    <div class=\"align-self-center\">
                        <i class=\"fas fa-{$icon} fa-2x opacity-75\"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>";
}

function renderTableRow($lead, $columns) {
    $html = '<tr>';
    
    foreach ($columns as $column => $config) {
        $html .= '<td>';
        
        switch ($column) {
            case 'name':
                $html .= '<strong>' . htmlspecialchars($lead['name']) . '</strong>';
                if (!empty($config['show_origin'])) {
                    $html .= '<br><small class="text-muted">' . htmlspecialchars($lead['lead_origin']) . '</small>';
                }
                if (!empty($config['show_email']) && !empty($lead['email'])) {
                    $html .= '<br><small class="text-muted">' . htmlspecialchars($lead['email']) . '</small>';
                }
                break;
            
            case 'date':
                $html .= date($config['format'], strtotime($lead[$config['field']]));
                break;
                
            case 'phone':
                $html .= htmlspecialchars($lead['phone']);
                break;
                
            case 'email':
                if (!empty($lead['email'])) {
                    $html .= htmlspecialchars($lead['email']);
                } else {
                    $html .= '<span class="text-muted">-</span>';
                }
                break;
                
            case 'project_amount':
                $html .= formatProjectAmount($lead['project_amount']);
                break;
                
            case 'assigned_to':
                $html .= '<span class="badge bg-secondary">' . htmlspecialchars($lead['assigned_to']) . '</span>';
                break;
                
            case 'origin':
                $originClass = getLeadOriginClass($lead['lead_origin']);
                $html .= '<span class="badge ' . $originClass . '">' . htmlspecialchars($lead['lead_origin']) . '</span>';
                break;
                
            case 'status':
                $statusClass = getStatusBadgeClass($lead['remarks']);
                $html .= '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($lead['remarks']) . '</span>';
                break;
                
            case 'due_date':
                $html .= '<span class="text-danger">' . date('M j, Y', strtotime($lead['next_followup_date'])) . '</span>';
                break;
                
            case 'action':
                $lead_params = [
                    $lead['id'],
                    "'" . htmlspecialchars(addslashes($lead['name'])) . "'",
                    "'" . htmlspecialchars(addslashes($lead['phone'])) . "'",
                    "'" . htmlspecialchars(addslashes($lead['email'])) . "'",
                    "'" . htmlspecialchars(addslashes($lead['remarks'])) . "'",
                    "'" . $lead['next_followup_date'] . "'",
                    "'" . htmlspecialchars(addslashes($lead['assigned_to'])) . "'",
                    "'" . $lead['project_amount'] . "'",
                    "'" . htmlspecialchars(addslashes($lead['notes'] ?? '')) . "'"
                ];
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(' . implode(', ', $lead_params) . ')">
                            <i class="fas fa-edit"></i>
                          </button>';
                break;
        }
        
        $html .= '</td>';
    }
    
    $html .= '</tr>';
    return $html;
}

// Optimized data fetching
$today = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-7 days'));

try {
    // Get dashboard data with optimized queries
    $dashboard_queries = [
        'todays_followups' => "SELECT * FROM leads WHERE next_followup_date = ? AND remarks NOT IN ('Sold', 'Not Interested') ORDER BY name",
        'overdue_followups' => "SELECT * FROM leads WHERE next_followup_date < ? AND remarks NOT IN ('Sold', 'Not Interested') ORDER BY next_followup_date",
        'recent_leads' => "SELECT * FROM leads WHERE date_created >= ? ORDER BY date_created DESC LIMIT 10",
        'total_leads' => "SELECT COUNT(*) as total FROM leads",
        'total_sold' => "SELECT COUNT(*) as total FROM leads WHERE remarks = 'Sold'",
        'todays_leads' => "SELECT COUNT(*) as total FROM leads WHERE date_created = ?",
        'users' => "SELECT id, full_name FROM users ORDER BY full_name"
    ];
    
    // Execute queries efficiently
    $stmt = $pdo->prepare($dashboard_queries['todays_followups']);
    $stmt->execute([$today]);
    $todays_followups = $stmt->fetchAll();
    
    $stmt = $pdo->prepare($dashboard_queries['overdue_followups']);
    $stmt->execute([$today]);
    $overdue_followups = $stmt->fetchAll();
    
    $stmt = $pdo->prepare($dashboard_queries['recent_leads']);
    $stmt->execute([$week_ago]);
    $recent_leads = $stmt->fetchAll();
    
    $stmt = $pdo->query($dashboard_queries['total_leads']);
    $total_leads = $stmt->fetch()['total'];
    
    $stmt = $pdo->query($dashboard_queries['total_sold']);
    $total_sold = $stmt->fetch()['total'];
    
    // Get today's leads count using consistent date format
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE date_created = ?");
    $stmt->execute([date('Y-m-d')]);
    $todays_leads = $stmt->fetch()['total'];
    
    // Debug: Log today's date for troubleshooting
    error_log("Dashboard Debug - Today's date: " . date('Y-m-d') . ", Today's leads count: " . $todays_leads);
    
    $stmt = $pdo->query($dashboard_queries['users']);
    $users = $stmt->fetchAll();
    
    // Dashboard statistics with navigation links
    $stats_cards = [
        ['Total Leads', $total_leads, 'users', 'primary', 'leads.php'],
        ['Sold', $total_sold, 'handshake', 'success', 'followup.php?filter=sold'],
        ["Today's Follow-ups", count($todays_followups), 'calendar-day', 'info', 'followup.php'],
        ['New Today', $todays_leads, 'plus', 'warning', 'leads.php?filter=today']
    ];
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    // Set default values on error
    $todays_followups = $overdue_followups = $recent_leads = $users = [];
    $total_leads = $total_sold = $todays_leads = 0;
    $stats_cards = [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
            <div class="text-muted">
                Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <?php foreach ($stats_cards as [$title, $value, $icon, $bgClass, $link]): ?>
        <?php echo renderStatsCard($title, $value, $icon, $bgClass, $link); ?>
    <?php endforeach; ?>
</div>

<div class="row">
    <!-- Combined Follow-ups -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day me-2"></i>Follow-ups
                    </h5>
                    <div class="badge-group">
                        <?php if (!empty($overdue_followups)): ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i><?php echo count($overdue_followups); ?> Overdue
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($todays_followups)): ?>
                            <span class="badge bg-info">
                                <i class="fas fa-calendar-day me-1"></i><?php echo count($todays_followups); ?> Today
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php 
                // Combine and sort follow-ups - overdue first, then today's
                $all_followups = [];
                
                // Add overdue with priority flag
                foreach ($overdue_followups as $lead) {
                    $lead['followup_type'] = 'overdue';
                    $all_followups[] = $lead;
                }
                
                // Add today's with priority flag
                foreach ($todays_followups as $lead) {
                    $lead['followup_type'] = 'today';
                    $all_followups[] = $lead;
                }
                
                $total_followups = count($all_followups);
                $display_followups = array_slice($all_followups, 0, 5); // Show only first 5
                
                if (empty($all_followups)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-check fa-3x mb-3"></i>
                        <p>No follow-ups scheduled for today or overdue!</p>
                        <small class="text-muted">Great job staying on top of your leads!</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Name</th>
                                    <th>Due Date</th>
                                    <th>Phone</th>
                                    <th>Project Amount</th>
                                    <th>Assigned To</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_followups as $lead): ?>
                                <tr class="<?php echo $lead['followup_type'] === 'overdue' ? 'table-danger' : ''; ?>">
                                    <td>
                                        <?php if ($lead['followup_type'] === 'overdue'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-calendar-day me-1"></i>Today
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($lead['lead_origin']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $due_date = new DateTime($lead['next_followup_date']);
                                        $today_date = new DateTime();
                                        $diff = $today_date->diff($due_date)->days;
                                        
                                        if ($lead['followup_type'] === 'overdue'): ?>
                                            <span class="text-danger">
                                                <i class="fas fa-clock me-1"></i><?php echo $due_date->format('M j, Y'); ?>
                                                <br><small><?php echo $diff; ?> day<?php echo $diff > 1 ? 's' : ''; ?> ago</small>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-primary">
                                                <i class="fas fa-calendar me-1"></i><?php echo $due_date->format('M j, Y'); ?>
                                                <br><small>Today</small>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                                    <td>
                                        <?php if (!empty($lead['project_amount']) && $lead['project_amount'] > 0): ?>
                                            <strong>$<?php echo number_format($lead['project_amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($lead['assigned_to']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $lead_params = [
                                            $lead['id'],
                                            "'" . htmlspecialchars(addslashes($lead['name'])) . "'",
                                            "'" . htmlspecialchars(addslashes($lead['phone'])) . "'",
                                            "'" . htmlspecialchars(addslashes($lead['email'])) . "'",
                                            "'" . htmlspecialchars(addslashes($lead['remarks'])) . "'",
                                            "'" . $lead['next_followup_date'] . "'",
                                            "'" . htmlspecialchars(addslashes($lead['assigned_to'])) . "'",
                                            "'" . $lead['project_amount'] . "'",
                                            "'" . htmlspecialchars(addslashes($lead['notes'] ?? '')) . "'"
                                        ];
                                        ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?php echo implode(', ', $lead_params); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_followups > 5): ?>
                        <div class="card-footer bg-light text-center">
                            <p class="mb-2 text-muted">
                                Showing 5 of <?php echo $total_followups; ?> follow-ups
                            </p>
                            <a href="followup.php" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>View All Follow-ups
                                <span class="badge bg-white text-primary ms-2"><?php echo $total_followups; ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Leads -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Leads (Last 7 days)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_leads)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No recent leads found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Origin</th>
                                    <th>Phone</th>
                                    <th>Assigned To</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recent_columns = [
                                    'date' => ['field' => 'date_created', 'format' => 'M j'],
                                    'name' => ['show_email' => true],
                                    'origin' => [],
                                    'phone' => [],
                                    'assigned_to' => [],
                                    'project_amount' => [],
                                    'status' => [],
                                    'action' => []
                                ];
                                foreach ($recent_leads as $lead): 
                                    echo renderTableRow($lead, $recent_columns);
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Edit Modal -->
<div class="modal fade" id="quickEditModal" tabindex="-1" aria-labelledby="quickEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickEditModalLabel"><i class="fas fa-edit me-2"></i>Quick Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickEditForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editName" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPhone" class="form-label">Phone <span class="text-danger">*</span></label>
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
                                <label for="editProjectAmount" class="form-label">Project Amount</label>
                                <input type="number" class="form-control" id="editProjectAmount" name="project_amount" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editRemarks" class="form-label">Status</label>
                                <select class="form-select" id="editRemarks" name="remarks">
                                    <option value="New Lead">New Lead</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Quoted">Quoted</option>
                                    <option value="Sold">Sold</option>
                                    <option value="Not Interested">Not Interested</option>
                                    <option value="Not Service Area">Not Service Area</option>
                                    <option value="Not Compatible">Not Compatible</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editAssignedTo" class="form-label">Assigned To</label>
                                <select class="form-select" id="editAssignedTo" name="assigned_to">
                                    <option value="Not Assigned">Not Assigned</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editNextFollowup" class="form-label">Next Follow-up Date</label>
                                <input type="date" class="form-control" id="editNextFollowup" name="next_followup_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="editNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="editNotes" name="notes" rows="3" placeholder="Add notes about this lead..."></textarea>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="editLeadId" name="lead_id">
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
// Dashboard JavaScript - Optimized
const DashboardModal = {
    // Configuration
    config: {
        modalId: 'quickEditModal',
        formId: 'quickEditForm',
        updateUrl: 'update_lead.php',
        toastDuration: 3000,
        errorToastDuration: 5000,
        refreshDelay: 1000
    },

    // Initialize modal functionality
    init() {
        this.setupFormHandler();
        this.setupModalEvents();
    },

    // Open edit modal with lead data
    open(id, name, phone, email, remarks, nextFollowup, assignedTo, projectAmount, notes) {
        const fields = {
            'editLeadId': id,
            'editName': name,
            'editPhone': phone,
            'editEmail': email || '',
            'editRemarks': remarks,
            'editNextFollowup': nextFollowup || '',
            'editAssignedTo': assignedTo,
            'editProjectAmount': projectAmount || '',
            'editNotes': notes || ''
        };

        // Populate form fields
        Object.entries(fields).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            if (element) element.value = value;
        });

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(this.config.modalId));
        modal.show();
    },

    // Setup form submission handler
    setupFormHandler() {
        const form = document.getElementById(this.config.formId);
        if (!form) return;

        form.addEventListener('submit', (e) => this.handleSubmit(e));
    },

    // Setup modal event handlers
    setupModalEvents() {
        const modal = document.getElementById(this.config.modalId);
        if (!modal) return;

        modal.addEventListener('hidden.bs.modal', () => {
            document.getElementById(this.config.formId).reset();
        });
    },

    // Handle form submission
    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            this.setButtonState(submitBtn, '<i class="fas fa-spinner fa-spin me-2"></i>Saving...', true);
            
            const response = await fetch(this.config.updateUrl, {
                method: 'POST',
                body: new FormData(form)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast('success', 'Lead updated successfully!');
                this.closeModalAndRefresh();
            } else {
                throw new Error(data.message || 'Failed to update lead');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showToast('error', error.message);
        } finally {
            this.setButtonState(submitBtn, originalText, false);
        }
    },

    // Set button loading state
    setButtonState(button, text, disabled) {
        button.innerHTML = text;
        button.disabled = disabled;
    },

    // Show toast notification
    showToast(type, message) {
        const isError = type === 'error';
        const bgClass = isError ? 'bg-danger' : 'bg-success';
        const icon = isError ? 'exclamation-circle' : 'check-circle';
        const title = isError ? 'Error' : 'Success';
        const duration = isError ? this.config.errorToastDuration : this.config.toastDuration;

        const toast = document.createElement('div');
        toast.className = 'toast show position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast-header ${bgClass} text-white">
                <i class="fas fa-${icon} me-2"></i>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;

        document.body.appendChild(toast);
        
        // Auto-hide toast
        setTimeout(() => toast.remove(), duration);
    },

    // Close modal and refresh page
    closeModalAndRefresh() {
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById(this.config.modalId));
        if (modalInstance) modalInstance.hide();
        
        setTimeout(() => window.location.reload(), this.config.refreshDelay);
    }
};

// Global function for backward compatibility
function openEditModal(id, name, phone, email, remarks, nextFollowup, assignedTo, projectAmount, notes) {
    DashboardModal.open(id, name, phone, email, remarks, nextFollowup, assignedTo, projectAmount, notes);
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    DashboardModal.init();
});
</script>

<style>
.clickable-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.clickable-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}

.clickable-card:active {
    transform: translateY(0);
}
</style>

<?php include 'includes/footer.php'; ?>
