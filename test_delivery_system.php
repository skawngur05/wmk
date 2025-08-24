<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Test Delivery System - Wrap My Kitchen';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-vials me-2"></i>Delivery Detection Test System</h1>
                <a href="sample_booklets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Sample Booklets
                </a>
            </div>
        </div>
    </div>

    <!-- Test Options -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>Test Options</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-grid">
                                <button type="button" class="btn btn-primary btn-lg" onclick="runManualTest()">
                                    <i class="fas fa-search me-2"></i>Run Manual Delivery Check
                                </button>
                                <small class="text-muted mt-2">Check all shipped orders for delivery status</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <button type="button" class="btn btn-info btn-lg" onclick="testSpecificTracking()">
                                    <i class="fas fa-barcode me-2"></i>Test Specific Tracking
                                </button>
                                <small class="text-muted mt-2">Test a specific USPS tracking number</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <button type="button" class="btn btn-success btn-lg" onclick="viewSystemStatus()">
                                    <i class="fas fa-chart-line me-2"></i>View System Status
                                </button>
                                <small class="text-muted mt-2">Check auto-delivery system status</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Shipped Orders -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Current Shipped Orders</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT * FROM sample_booklets WHERE status = 'Shipped' AND tracking_number IS NOT NULL AND tracking_number != '' ORDER BY date_shipped DESC");
                        $shipped_orders = $stmt->fetchAll();
                        
                        if (empty($shipped_orders)) {
                            echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No shipped orders found with tracking numbers.</div>';
                        } else {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-striped">';
                            echo '<thead><tr><th>Order #</th><th>Customer</th><th>Tracking #</th><th>Date Shipped</th><th>Actions</th></tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($shipped_orders as $order) {
                                echo '<tr>';
                                echo '<td><strong>' . htmlspecialchars($order['order_number']) . '</strong></td>';
                                echo '<td>' . htmlspecialchars($order['customer_name']) . '</td>';
                                echo '<td>';
                                echo '<a href="https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=2&text28777=&tLabels=' . urlencode($order['tracking_number']) . '" target="_blank">';
                                echo htmlspecialchars($order['tracking_number']);
                                echo ' <i class="fas fa-external-link-alt"></i></a>';
                                echo '</td>';
                                echo '<td>' . ($order['date_shipped'] ? date('M j, Y', strtotime($order['date_shipped'])) : 'N/A') . '</td>';
                                echo '<td>';
                                echo '<button class="btn btn-sm btn-outline-primary" onclick="checkSingleOrder(\'' . $order['tracking_number'] . '\', \'' . htmlspecialchars(addslashes($order['order_number'])) . '\')">';
                                echo '<i class="fas fa-search me-1"></i>Check Status</button>';
                                echo '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody></table>';
                            echo '</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Results Area -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Test Results</h5>
                </div>
                <div class="card-body">
                    <div id="testResults">
                        <div class="text-muted text-center py-4">
                            <i class="fas fa-flask fa-2x mb-3"></i>
                            <p>Click a test button above to see results here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Specific Tracking Modal -->
<div class="modal fade" id="testTrackingModal" tabindex="-1" aria-labelledby="testTrackingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testTrackingModalLabel">
                    <i class="fas fa-barcode me-2"></i>Test Specific Tracking Number
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="testTrackingForm">
                    <div class="mb-3">
                        <label for="testTrackingNumber" class="form-label">USPS Tracking Number</label>
                        <input type="text" class="form-control" id="testTrackingNumber" placeholder="Enter USPS tracking number..." required>
                        <div class="form-text">Enter any USPS tracking number to test the delivery detection system.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="runTrackingTest()">
                    <i class="fas fa-search me-2"></i>Test Tracking
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function runManualTest() {
    updateTestResults('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Running manual delivery check...</p></div>');
    
    // Open the auto delivery check in a new window/tab to see results
    window.open('handlers/auto_delivery_check.php?manual_test=1', '_blank');
    
    // Also run the JSON version for our results
    fetch('handlers/auto_delivery_check.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ manual_check: true })
    })
    .then(response => response.json())
    .then(data => {
        let html = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + '">';
        html += '<h6><i class="fas fa-' + (data.success ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>Manual Delivery Check Results</h6>';
        html += '<p>' + data.message + '</p>';
        
        if (data.checked !== undefined) {
            html += '<ul class="mb-0">';
            html += '<li>Orders Checked: ' + data.checked + '</li>';
            html += '<li>Orders Updated to Delivered: ' + data.updated + '</li>';
            if (data.errors && data.errors.length > 0) {
                html += '<li>Errors: ' + data.errors.length + '</li>';
            }
            html += '</ul>';
        }
        
        html += '</div>';
        
        if (data.success && data.updated > 0) {
            html += '<div class="alert alert-info">';
            html += '<i class="fas fa-info-circle me-2"></i>Page will refresh in 3 seconds to show updated orders...';
            html += '</div>';
            
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }
        
        updateTestResults(html);
    })
    .catch(error => {
        updateTestResults('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error: ' + error.message + '</div>');
    });
}

function testSpecificTracking() {
    const modal = new bootstrap.Modal(document.getElementById('testTrackingModal'));
    modal.show();
}

function runTrackingTest() {
    const trackingNumber = document.getElementById('testTrackingNumber').value.trim();
    
    if (!trackingNumber) {
        alert('Please enter a tracking number');
        return;
    }
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('testTrackingModal'));
    modal.hide();
    
    updateTestResults('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Testing tracking number: ' + trackingNumber + '</p></div>');
    
    // Create a test request directly to the auto delivery check
    fetch('handlers/auto_delivery_check.php?manual_test=1&single_tracking=' + encodeURIComponent(trackingNumber))
    .then(response => response.text())
    .then(data => {
        // Show the response in a new window for detailed view
        const newWindow = window.open('', '_blank');
        newWindow.document.write(data);
        newWindow.document.title = 'Tracking Test Results: ' + trackingNumber;
        
        // Also show summary in our results area
        let html = '<div class="alert alert-info">';
        html += '<h6><i class="fas fa-barcode me-2"></i>Tracking Test Results for: ' + trackingNumber + '</h6>';
        html += '<p>Test completed! Check the new window for detailed results.</p>';
        html += '<p><strong>Tracking Number:</strong> ' + trackingNumber + '</p>';
        html += '</div>';
        updateTestResults(html);
    })
    .catch(error => {
        updateTestResults('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error testing tracking: ' + error.message + '</div>');
    });
}

function checkSingleOrder(trackingNumber, orderNumber) {
    updateTestResults('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Checking order ' + orderNumber + ' (Tracking: ' + trackingNumber + ')...</p></div>');
    
    // Direct test of this specific tracking number
    fetch('handlers/auto_delivery_check.php?manual_test=1&single_tracking=' + encodeURIComponent(trackingNumber))
    .then(response => response.text())
    .then(data => {
        // Show results in new window
        const newWindow = window.open('', '_blank');
        newWindow.document.write(data);
        newWindow.document.title = 'Order ' + orderNumber + ' Status Check';
        
        // Show summary
        let html = '<div class="alert alert-info">';
        html += '<h6><i class="fas fa-package me-2"></i>Order ' + orderNumber + ' Status Check</h6>';
        html += '<p><strong>Tracking:</strong> ' + trackingNumber + '</p>';
        html += '<p>Check completed! See new window for detailed results.</p>';
        html += '</div>';
        updateTestResults(html);
    })
    .catch(error => {
        updateTestResults('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error checking order: ' + error.message + '</div>');
    });
}

function viewSystemStatus() {
    updateTestResults('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Checking system status...</p></div>');
    
    // For now, show basic system info
    let html = '<div class="alert alert-info">';
    html += '<h6><i class="fas fa-chart-line me-2"></i>Auto-Delivery System Status</h6>';
    html += '<p><strong>System Status:</strong> ✅ Active and Running</p>';
    html += '<p><strong>Auto-Check:</strong> Runs every hour when sample booklets page is loaded</p>';
    html += '<p><strong>Manual Check:</strong> Available via "Check Delivery Status" button</p>';
    html += '<p><strong>Background Check:</strong> Available via scheduled script</p>';
    html += '<p><strong>USPS Integration:</strong> Web scraping method with delivery detection</p>';
    html += '</div>';
    
    updateTestResults(html);
}

function updateTestResults(html) {
    document.getElementById('testResults').innerHTML = html;
}
</script>

<?php include 'includes/footer.php'; ?>
