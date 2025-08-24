<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Add New Lead - Wrap My Kitchen';

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
                INSERT INTO leads (
                    date_created, lead_origin, name, phone, email, 
                    next_followup_date, remarks, assigned_to, notes, 
                    additional_notes, project_amount, deposit_paid, 
                    balance_paid, installation_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Handle payment fields for sold leads
            $deposit_paid = isset($_POST['deposit_paid']) ? 1 : 0;
            $balance_paid = isset($_POST['balance_paid']) ? 1 : 0;
            $installation_date = !empty($_POST['installation_date']) ? $_POST['installation_date'] : null;
            
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
                $deposit_paid,
                $balance_paid,
                $installation_date
            ]);
            
            $success = true;
            $_POST = []; // Clear form
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
            <h1><i class="fas fa-plus me-2"></i>Add New Lead</h1>
            <a href="leads.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Leads
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>Lead has been added successfully!
                        <div class="mt-2">
                            <a href="add_lead.php" class="btn btn-sm btn-outline-success me-2">Add Another Lead</a>
                            <a href="leads.php" class="btn btn-sm btn-success">View All Leads</a>
                        </div>
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
                            <label for="date_created" class="form-label"><i class="fas fa-calendar me-2"></i>Date Created *</label>
                            <input type="date" class="form-control" id="date_created" name="date_created" 
                                   value="<?php echo htmlspecialchars($_POST['date_created'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lead_origin" class="form-label"><i class="fas fa-map-marker-alt me-2"></i>Lead Origin *</label>
                            <select class="form-select" id="lead_origin" name="lead_origin" required>
                                <option value="">Select Origin</option>
                                <option value="Facebook" <?php echo ($_POST['lead_origin'] ?? '') === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                                <option value="Google Text" <?php echo ($_POST['lead_origin'] ?? '') === 'Google Text' ? 'selected' : ''; ?>>Google Text</option>
                                <option value="Instagram" <?php echo ($_POST['lead_origin'] ?? '') === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                                <option value="Trade Show" <?php echo ($_POST['lead_origin'] ?? '') === 'Trade Show' ? 'selected' : ''; ?>>Trade Show</option>
                                <option value="WhatsApp" <?php echo ($_POST['lead_origin'] ?? '') === 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
                                <option value="Website" <?php echo ($_POST['lead_origin'] ?? '') === 'Website' ? 'selected' : ''; ?>>Website</option>
                                <option value="Commercial" <?php echo ($_POST['lead_origin'] ?? '') === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="Referral" <?php echo ($_POST['lead_origin'] ?? '') === 'Referral' ? 'selected' : ''; ?>>Referral</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label"><i class="fas fa-user me-2"></i>Client Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="assigned_to" class="form-label"><i class="fas fa-user-tie me-2"></i>Assigned To *</label>
                            <select class="form-select" id="assigned_to" name="assigned_to" required>
                                <option value="">Select Assignee</option>
                                <option value="Kim" <?php echo ($_POST['assigned_to'] ?? '') === 'Kim' ? 'selected' : ''; ?>>Kim</option>
                                <option value="Patrick" <?php echo ($_POST['assigned_to'] ?? '') === 'Patrick' ? 'selected' : ''; ?>>Patrick</option>
                                <option value="Lina" <?php echo ($_POST['assigned_to'] ?? '') === 'Lina' ? 'selected' : ''; ?>>Lina</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label"><i class="fas fa-phone me-2"></i>Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3 email-field-enhanced">
                            <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="Enter email for auto-enrichment">
                            <div class="form-text">
                                <i class="fas fa-magic text-info me-1"></i>
                                <small>We'll automatically lookup contact information when you enter an email</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="next_followup_date" class="form-label"><i class="fas fa-calendar-check me-2"></i>Next Follow-up Date</label>
                            <input type="date" class="form-control" id="next_followup_date" name="next_followup_date" 
                                   value="<?php echo htmlspecialchars($_POST['next_followup_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="remarks" class="form-label"><i class="fas fa-flag me-2"></i>Status</label>
                            <select class="form-select" id="remarks" name="remarks" onchange="togglePaymentTracking(this.value)">
                                <option value="New" <?php echo ($_POST['remarks'] ?? 'New') === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="In Progress" <?php echo ($_POST['remarks'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Sold" <?php echo ($_POST['remarks'] ?? '') === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                                <option value="Not Interested" <?php echo ($_POST['remarks'] ?? '') === 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                                <option value="Not Service Area" <?php echo ($_POST['remarks'] ?? '') === 'Not Service Area' ? 'selected' : ''; ?>>Not Service Area</option>
                                <option value="Not Compatible" <?php echo ($_POST['remarks'] ?? '') === 'Not Compatible' ? 'selected' : ''; ?>>Not Compatible</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="project_amount" class="form-label"><i class="fas fa-dollar-sign me-2"></i>Project Amount ($)</label>
                        <input type="number" step="0.01" class="form-control" id="project_amount" name="project_amount" 
                               value="<?php echo htmlspecialchars($_POST['project_amount'] ?? ''); ?>" placeholder="0.00">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><i class="fas fa-sticky-note me-2"></i>Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="General notes about the lead..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="additional_notes" class="form-label"><i class="fas fa-comment me-2"></i>Additional Notes</label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" 
                                  placeholder="Additional information..."><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Payment Tracking Section - Only visible for Sold leads -->
                    <div id="paymentTrackingSection" style="<?php echo ($_POST['remarks'] ?? '') == 'Sold' ? '' : 'display: none;'; ?>">
                        <div class="card bg-light border-success mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-dollar-sign me-2"></i>Payment Tracking
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="deposit_paid" 
                                                   name="deposit_paid" value="1" <?php echo (isset($_POST['deposit_paid']) && $_POST['deposit_paid']) ? 'checked' : ''; ?>
                                                   onchange="toggleInstallationDate(this.checked)">
                                            <label class="form-check-label" for="deposit_paid">
                                                <strong>Deposit Received</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="balance_paid" 
                                                   name="balance_paid" value="1" <?php echo (isset($_POST['balance_paid']) && $_POST['balance_paid']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="balance_paid">
                                                <strong>Remaining Balance Received</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Installation Date Field -->
                                <div id="installationDateSection" style="<?php echo (isset($_POST['deposit_paid']) && $_POST['deposit_paid']) ? '' : 'display: none;'; ?>">
                                    <div class="alert alert-info">
                                        <i class="fas fa-tools me-2"></i>
                                        <strong>Installation Scheduling</strong> - Deposit received, please set installation date
                                    </div>
                                    <label for="installation_date" class="form-label">
                                        <i class="fas fa-calendar-plus me-2"></i>Installation Date
                                    </label>
                                    <input type="date" class="form-control" id="installation_date" 
                                           name="installation_date" value="<?php echo htmlspecialchars($_POST['installation_date'] ?? ''); ?>">
                                    <div class="form-text">Set the scheduled installation date for this project</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="leads.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Required fields are marked with *</small>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Set a follow-up date to appear in dashboard</small>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Use notes for important details</small>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Update status as lead progresses</small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Existing Lead Modal -->
<div class="modal fade" id="existingLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Existing Lead Found
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    We found an existing lead with the same contact information.
                </div>
                
                <div id="existingLeadInfo" class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Existing Lead Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <span id="existing-name"></span></p>
                                <p><strong>Phone:</strong> <span id="existing-phone"></span></p>
                                <p><strong>Email:</strong> <span id="existing-email"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <span id="existing-date"></span></p>
                                <p><strong>Status:</strong> <span id="existing-status"></span></p>
                                <p><strong>Assigned To:</strong> <span id="existing-assigned"></span></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <p><strong>Notes:</strong> <span id="existing-notes"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h6>What would you like to do?</h6>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" onclick="useExistingLead()">
                        <i class="fas fa-copy me-2"></i>
                        Use Existing Information (New Project)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="continueWithNew()">
                        <i class="fas fa-plus me-2"></i>
                        Continue Adding New Lead Anyway
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="clearForm()">
                        <i class="fas fa-times me-2"></i>
                        Clear Form and Start Over
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let existingLeadData = null;

// Function to use existing lead information
function useExistingLead() {
    if (!existingLeadData) return;
    
    document.getElementById('name').value = existingLeadData.name || '';
    document.getElementById('phone').value = existingLeadData.phone || '';
    document.getElementById('email').value = existingLeadData.email || '';
    document.getElementById('assigned_to').value = existingLeadData.assigned_to || '';
    
    // Add a note indicating this is a new project for existing client
    const notesField = document.getElementById('notes');
    const currentNotes = notesField.value;
    const newProjectNote = 'New project for existing client (Previous: ' + (existingLeadData.notes || 'No previous notes') + ')';
    notesField.value = currentNotes ? currentNotes + '\n\n' + newProjectNote : newProjectNote;
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('existingLeadModal'));
    modal.hide();
    
    // Focus on lead origin
    document.getElementById('lead_origin').focus();
}

// Function to continue with new lead
function continueWithNew() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('existingLeadModal'));
    modal.hide();
}

// Function to clear form
function clearForm() {
    document.querySelector('form').reset();
    document.getElementById('date_created').value = '<?php echo date('Y-m-d'); ?>';
    const modal = bootstrap.Modal.getInstance(document.getElementById('existingLeadModal'));
    modal.hide();
}

// Unified email enrichment and existing lead check
let enrichmentTimeout = null;

function enrichEmail() {
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    if (!email || !isValidEmail(email)) {
        return;
    }
    
    // Clear previous timeout
    if (enrichmentTimeout) {
        clearTimeout(enrichmentTimeout);
    }
    
    // Set a new timeout to avoid too many requests
    enrichmentTimeout = setTimeout(() => {
        // Show loading state
        const nameField = document.getElementById('name');
        const originalPlaceholder = nameField.placeholder;
        nameField.placeholder = 'Looking up email...';
        nameField.style.backgroundColor = '#f8f9fa';
        
        fetch('email_enrichment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                phone: phone // Also send phone for comprehensive check
            })
        })
        .then(response => response.json())
        .then(data => {
            // Reset loading state
            nameField.placeholder = originalPlaceholder;
            nameField.style.backgroundColor = '';
            
            if (data.found && data.data) {
                handleEnrichmentData(data);
            }
        })
        .catch(error => {
            console.error('Error enriching email:', error);
            nameField.placeholder = originalPlaceholder;
            nameField.style.backgroundColor = '';
        });
    }, 800); // Wait 800ms after user stops typing
}

function handleEnrichmentData(enrichmentData) {
    const { source, data } = enrichmentData;
    
    switch (source) {
        case 'internal_database':
            // This is an existing lead - show the comprehensive existing lead modal
            showExistingLeadModal(data);
            break;
            
        case 'domain_analysis':
            if (data.potential_company) {
                showCompanyEnrichmentToast(data.potential_company);
            }
            break;
            
        case 'email_pattern':
            if (data.suggested_name && !document.getElementById('name').value.trim()) {
                showNameSuggestion(data.suggested_name, data.confidence);
            }
            break;
            
        case 'clearbit':
        case 'hunter':
            fillEnrichedData(data);
            break;
    }
}

function showExistingLeadModal(data) {
    // Use the existing lead modal with enriched data structure
    existingLeadData = {
        name: data.name,
        phone: data.phone,
        email: data.email || document.getElementById('email').value,
        date_created: data.date_created || 'Unknown',
        remarks: data.previous_status || 'Unknown',
        assigned_to: data.assigned_to,
        notes: data.previous_notes
    };
    
    document.getElementById('existing-name').textContent = existingLeadData.name || 'N/A';
    document.getElementById('existing-phone').textContent = existingLeadData.phone || 'N/A';
    document.getElementById('existing-email').textContent = existingLeadData.email || 'N/A';
    document.getElementById('existing-date').textContent = existingLeadData.date_created || 'N/A';
    document.getElementById('existing-status').textContent = existingLeadData.remarks || 'N/A';
    document.getElementById('existing-assigned').textContent = existingLeadData.assigned_to || 'N/A';
    document.getElementById('existing-notes').textContent = existingLeadData.notes || 'No notes';
    
    const modal = new bootstrap.Modal(document.getElementById('existingLeadModal'));
    modal.show();
}

function showNameSuggestion(suggestedName, confidence) {
    const nameField = document.getElementById('name');
    if (nameField.value.trim()) return; // Don't override existing name
    
    // Create suggestion tooltip
    const suggestion = document.createElement('div');
    suggestion.className = 'alert alert-info alert-dismissible fade show mt-2';
    suggestion.innerHTML = `
        <i class="fas fa-lightbulb me-2"></i>
        Suggested name: <strong>${suggestedName}</strong>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="acceptNameSuggestion('${suggestedName}')">
            Use This
        </button>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    nameField.parentNode.appendChild(suggestion);
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (suggestion.parentNode) {
            suggestion.remove();
        }
    }, 10000);
}

function acceptNameSuggestion(name) {
    document.getElementById('name').value = name;
    // Remove suggestion alerts
    const alerts = document.querySelectorAll('.alert-info');
    alerts.forEach(alert => {
        if (alert.textContent.includes('Suggested name')) {
            alert.remove();
        }
    });
    showToast('Name suggestion applied!', 'success');
}

function showCompanyEnrichmentToast(company) {
    showToast(`Business email detected. Potential company: ${company}`, 'info');
}

function fillEnrichedData(data) {
    if (data.name && !document.getElementById('name').value.trim()) {
        document.getElementById('name').value = data.name;
    }
    if (data.phone && !document.getElementById('phone').value.trim()) {
        document.getElementById('phone').value = data.phone;
    }
    if (data.company) {
        const notesField = document.getElementById('notes');
        const companyNote = `Company: ${data.company}`;
        if (data.title) {
            companyNote += ` | Title: ${data.title}`;
        }
        notesField.value = notesField.value ? notesField.value + '\n' + companyNote : companyNote;
    }
    
    showToast('Contact information enriched from external source!', 'success');
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1050';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    const emailField = document.getElementById('email');
    
    // Unified email enrichment (replaces separate existing lead check)
    emailField.addEventListener('input', enrichEmail);
    
    // Add visual feedback for email field
    emailField.addEventListener('input', function() {
        const email = this.value.trim();
        if (email && isValidEmail(email)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else if (email) {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-valid', 'is-invalid');
        }
    });
});

// Toggle payment tracking section based on lead status
function togglePaymentTracking(status) {
    const paymentSection = document.getElementById('paymentTrackingSection');
    if (paymentSection) {
        if (status === 'Sold') {
            paymentSection.style.display = 'block';
        } else {
            paymentSection.style.display = 'none';
            // Reset payment checkboxes when not sold
            const depositCheckbox = document.getElementById('deposit_paid');
            const balanceCheckbox = document.getElementById('balance_paid');
            const installationSection = document.getElementById('installationDateSection');
            const installationInput = document.getElementById('installation_date');
            
            if (depositCheckbox) depositCheckbox.checked = false;
            if (balanceCheckbox) balanceCheckbox.checked = false;
            if (installationSection) installationSection.style.display = 'none';
            if (installationInput) installationInput.value = '';
        }
    }
}

// Toggle installation date section when deposit is paid
function toggleInstallationDate(isDepositPaid) {
    const installationSection = document.getElementById('installationDateSection');
    const installationInput = document.getElementById('installation_date');
    
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
</script>

<?php include 'includes/footer.php'; ?>
