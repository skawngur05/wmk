<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Import Leads from CSV - Wrap My Kitchen';
include 'includes/header.php';
?>

<div class="container-fluid">
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-file-csv me-2"></i>Import Leads from CSV</h1>
            <a href="leads.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Leads
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Step 1: Upload CSV -->
        <div class="card mb-4" id="upload-section">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-upload"></i> Step 1: Upload CSV File
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>CSV Format Required:</strong><br>
                    Columns: date_created, lead_origin, name, Phone Number, Email, next_followup_date, Remarks, assigned_to, Notes, additional_notes<br>
                    <strong>Required:</strong> date_created and lead_origin must be filled<br>
                    <strong>Note:</strong> Rows without name, email, or phone number will be skipped<br>
                    <a href="sample_import_template.csv" download class="btn btn-sm btn-outline-info mt-2">
                        <i class="fas fa-download"></i> Download Sample Template
                    </a>
                </div>
                
                <form id="csv-upload-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Only CSV files are accepted</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Preview Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Step 2: Preview Data -->
        <div class="card mb-4" id="preview-section" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-eye"></i> Step 2: Preview & Validate Data
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Review the data below. Rows highlighted in red have issues that need to be resolved.
                </div>
                
                <div id="preview-content">
                    <!-- Preview table will be loaded here -->
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-secondary" onclick="resetUpload()">
                        <i class="fas fa-arrow-left"></i> Upload Different File
                    </button>
                    <button type="button" class="btn btn-success" id="import-btn" onclick="importData()">
                        <i class="fas fa-download"></i> Import Valid Rows
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Import Results -->
        <div class="card" id="results-section" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle"></i> Step 3: Import Results
                </h5>
            </div>
            <div class="card-body">
                <div id="import-results">
                    <!-- Import results will be shown here -->
                </div>
                
                <div class="text-center mt-3">
                    <a href="leads.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View All Leads
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let previewData = [];

document.getElementById('csv-upload-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const fileInput = document.getElementById('csv_file');
    formData.append('csv_file', fileInput.files[0]);
    formData.append('action', 'preview');
    
    // Show loading
    document.getElementById('preview-section').style.display = 'block';
    document.getElementById('preview-content').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Processing CSV file...</div>';
    
    fetch('handlers/import_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            previewData = data.data;
            displayPreview(data.data, data.validation);
        } else {
            alert('Error: ' + data.message);
            document.getElementById('preview-section').style.display = 'none';
        }
    })
    .catch(error => {
        alert('Error processing file: ' + error);
        document.getElementById('preview-section').style.display = 'none';
    });
});

function displayPreview(data, validation) {
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
    
    // Header
    html += '<thead class="table-dark"><tr>';
    html += '<th>#</th><th>Date</th><th>Origin</th><th>Name</th><th>Phone</th><th>Email</th>';
    html += '<th>Follow-up</th><th>Remarks</th><th>Assigned</th><th>Notes</th><th>Additional</th>';
    html += '<th>Status</th></tr></thead><tbody>';
    
    // Data rows
    data.forEach((row, index) => {
        const isValid = validation[index].valid;
        const rowClass = isValid ? '' : 'table-danger';
        
        html += `<tr class="${rowClass}">`;
        html += `<td>${index + 1}</td>`;
        html += `<td>${row.date_created || ''}</td>`;
        html += `<td>${row.lead_origin || ''}</td>`;
        html += `<td>${row.name || ''}</td>`;
        html += `<td>${row.phone_number || ''}</td>`;
        html += `<td>${row.email || ''}</td>`;
        html += `<td>${row.next_followup_date || ''}</td>`;
        html += `<td>${row.remarks || ''}</td>`;
        html += `<td>${row.assigned_to || ''}</td>`;
        html += `<td>${(row.notes || '').substring(0, 30)}${row.notes && row.notes.length > 30 ? '...' : ''}</td>`;
        html += `<td>${(row.additional_notes || '').substring(0, 30)}${row.additional_notes && row.additional_notes.length > 30 ? '...' : ''}</td>`;
        
        if (isValid) {
            html += '<td><span class="badge bg-success">Valid</span></td>';
        } else {
            html += `<td><span class="badge bg-danger">Invalid</span><br><small class="text-danger">${validation[index].errors.join(', ')}</small></td>`;
        }
        
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    // Summary
    const validCount = validation.filter(v => v.valid).length;
    const invalidCount = data.length - validCount;
    
    html += `<div class="alert alert-info mt-3">
        <strong>Summary:</strong> ${data.length} total rows, 
        <span class="text-success">${validCount} valid</span>, 
        <span class="text-danger">${invalidCount} invalid</span>
    </div>`;
    
    document.getElementById('preview-content').innerHTML = html;
    
    // Enable/disable import button
    document.getElementById('import-btn').disabled = validCount === 0;
}

function importData() {
    document.getElementById('results-section').style.display = 'block';
    document.getElementById('import-results').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Importing data...</div>';
    
    fetch('handlers/import_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'import',
            data: previewData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = `<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Import Completed!</strong><br>
                Successfully imported ${data.imported} leads.<br>
                ${data.duplicates} duplicates were skipped.<br>
                ${data.invalid} invalid rows were skipped.
            </div>`;
            
            if (data.errors.length > 0) {
                html += '<div class="alert alert-warning"><strong>Warnings:</strong><ul>';
                data.errors.forEach(error => {
                    html += `<li>${error}</li>`;
                });
                html += '</ul></div>';
            }
            
            document.getElementById('import-results').innerHTML = html;
        } else {
            document.getElementById('import-results').innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('import-results').innerHTML = `<div class="alert alert-danger">Error: ${error}</div>`;
    });
}

function resetUpload() {
    document.getElementById('csv-upload-form').reset();
    document.getElementById('preview-section').style.display = 'none';
    document.getElementById('results-section').style.display = 'none';
    previewData = [];
}
</script>

</div> <!-- End container-fluid -->

<?php include 'includes/footer.php'; ?>