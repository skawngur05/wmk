<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Edit Lead - Wrap My Kitchen';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: leads.php');
    exit();
}

$lead_id = (int)$_GET['id'];

// Fetch lead data
$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Location: leads.php');
    exit();
}

$success = false;
$errors = [];

if ($_POST) {
    // Validate required fields
    $required_fields = ['date_created', 'lead_origin', 'name', 'assigned_to'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    // Validate email format if provided
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Validate project amount
    if (!empty($_POST['project_amount']) && !is_numeric($_POST['project_amount'])) {
        $errors[] = 'Project amount must be a valid number.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE leads SET 
                    date_created = ?, lead_origin = ?, name = ?, phone = ?, email = ?, 
                    next_followup_date = ?, remarks = ?, assigned_to = ?, notes = ?, 
                    additional_notes = ?, project_amount = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['date_created'],
                $_POST['lead_origin'],
                trim($_POST['name']),
                trim($_POST['phone']),
                trim($_POST['email']),
                !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null,
                $_POST['remarks'],
                $_POST['assigned_to'],
                trim($_POST['notes']),
                trim($_POST['additional_notes']),
                !empty($_POST['project_amount']) ? floatval($_POST['project_amount']) : 0,
                $lead_id
            ]);
            
            $success = true;
            
            // Refresh lead data
            $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            $lead = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-edit me-2"></i>Edit Lead: <?php echo htmlspecialchars($lead['name']); ?></h1>
            <div>
                <a href="leads.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Leads
                </a>
                <a href="add_lead.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Add New Lead
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>Lead has been updated successfully!
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_created" class="form-label">Date Created *</label>
                            <input type="date" class="form-control" id="date_created" name="date_created" 
                                   value="<?php echo htmlspecialchars($lead['date_created']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lead_origin" class="form-label">Lead Origin *</label>
                            <select class="form-select" id="lead_origin" name="lead_origin" required>
                                <option value="">Select Origin</option>
                                <option value="Facebook" <?php echo $lead['lead_origin'] === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                                <option value="Google Text" <?php echo $lead['lead_origin'] === 'Google Text' ? 'selected' : ''; ?>>Google Text</option>
                                <option value="Instagram" <?php echo $lead['lead_origin'] === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                                <option value="Trade Show" <?php echo $lead['lead_origin'] === 'Trade Show' ? 'selected' : ''; ?>>Trade Show</option>
                                <option value="WhatsApp" <?php echo $lead['lead_origin'] === 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
                                <option value="Commercial" <?php echo $lead['lead_origin'] === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="Referral" <?php echo $lead['lead_origin'] === 'Referral' ? 'selected' : ''; ?>>Referral</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Client Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($lead['name']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="assigned_to" class="form-label">Assigned To *</label>
                            <select class="form-select" id="assigned_to" name="assigned_to" required>
                                <option value="">Select Assignee</option>
                                <option value="Kim" <?php echo $lead['assigned_to'] === 'Kim' ? 'selected' : ''; ?>>Kim</option>
                                <option value="Patrick" <?php echo $lead['assigned_to'] === 'Patrick' ? 'selected' : ''; ?>>Patrick</option>
                                <option value="Lina" <?php echo $lead['assigned_to'] === 'Lina' ? 'selected' : ''; ?>>Lina</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($lead['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($lead['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="next_followup_date" class="form-label">Next Follow-up Date</label>
                            <input type="date" class="form-control" id="next_followup_date" name="next_followup_date" 
                                   value="<?php echo htmlspecialchars($lead['next_followup_date']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="remarks" class="form-label">Status</label>
                            <select class="form-select" id="remarks" name="remarks">
                                <option value="New" <?php echo $lead['remarks'] === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="In Progress" <?php echo $lead['remarks'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Sold" <?php echo $lead['remarks'] === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                                <option value="Not Interested" <?php echo $lead['remarks'] === 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                                <option value="Not Service Area" <?php echo $lead['remarks'] === 'Not Service Area' ? 'selected' : ''; ?>>Not Service Area</option>
                                <option value="Not Compatible" <?php echo $lead['remarks'] === 'Not Compatible' ? 'selected' : ''; ?>>Not Compatible</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="project_amount" class="form-label">Project Amount ($)</label>
                        <input type="number" step="0.01" class="form-control" id="project_amount" name="project_amount" 
                               value="<?php echo htmlspecialchars($lead['project_amount']); ?>" placeholder="0.00">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="General notes about the lead..."><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="additional_notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" 
                                  placeholder="Additional information..."><?php echo htmlspecialchars($lead['additional_notes']); ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="leads.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Lead Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Created:</strong></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($lead['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Last Updated:</strong></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($lead['updated_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Lead ID:</strong></td>
                        <td>#<?php echo $lead['id']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="updateStatus('Sold')">
                        <i class="fas fa-handshake me-1"></i>Mark as Sold
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateStatus('In Progress')">
                        <i class="fas fa-spinner me-1"></i>Mark In Progress
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="setFollowupDate(1)">
                        <i class="fas fa-calendar-plus me-1"></i>Follow-up Tomorrow
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="setFollowupDate(7)">
                        <i class="fas fa-calendar-week me-1"></i>Follow-up Next Week
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(status) {
    document.getElementById('remarks').value = status;
}

function setFollowupDate(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const formattedDate = date.toISOString().split('T')[0];
    document.getElementById('next_followup_date').value = formattedDate;
}
</script>

<?php include 'includes/footer.php'; ?>
