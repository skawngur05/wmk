<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Sample Booklets Management - Wrap My Kitchen';

// Function to ensure sample_booklets table exists
function ensureSampleBookletsTable($pdo) {
    static $table_checked = false;
    if (!$table_checked) {
        try {
            // Check if sample_booklets table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'sample_booklets'");
            $table_exists = $stmt->rowCount() > 0;
            error_log("Table existence check: sample_booklets " . ($table_exists ? "EXISTS" : "DOES NOT EXIST"));
            
            if (!$table_exists) {
                error_log("Creating sample_booklets table...");
                // Create sample_booklets table
                $create_table_sql = "
                    CREATE TABLE sample_booklets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_number VARCHAR(100) NOT NULL UNIQUE,
                        customer_name VARCHAR(255) NOT NULL,
                        address TEXT NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        phone VARCHAR(20) NOT NULL,
                        product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL,
                        tracking_number VARCHAR(100) NULL,
                        status ENUM('Pending', 'Shipped', 'Delivered') DEFAULT 'Pending',
                        date_ordered DATE NOT NULL,
                        date_shipped DATE NULL,
                        notes TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ";
                $pdo->exec($create_table_sql);
                
                // Create indexes
                $pdo->exec("CREATE INDEX idx_order_number ON sample_booklets(order_number)");
                $pdo->exec("CREATE INDEX idx_status ON sample_booklets(status)");
                $pdo->exec("CREATE INDEX idx_date_ordered ON sample_booklets(date_ordered)");
                
                error_log("Sample booklets table created successfully");
            } else {
                error_log("Sample booklets table already exists, checking product_type column...");
                
                // Check if we need to update the product_type ENUM values
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM sample_booklets LIKE 'product_type'");
                    $column = $stmt->fetch();
                    
                    if ($column && !strpos($column['Type'], 'Sample Booklet Only')) {
                        error_log("Updating product_type ENUM values...");
                        // Update existing records first
                        $pdo->exec("UPDATE sample_booklets SET product_type = 'Sample Booklet Only' WHERE product_type = 'Sample Booklet'");
                        $pdo->exec("UPDATE sample_booklets SET product_type = 'Demo Kit Only' WHERE product_type = 'Demo Kit'");
                        
                        // Then alter the column
                        $pdo->exec("ALTER TABLE sample_booklets MODIFY COLUMN product_type ENUM('Demo Kit & Sample Booklet', 'Sample Booklet Only', 'Trial Kit', 'Demo Kit Only') NOT NULL");
                        error_log("Product type ENUM updated successfully");
                    }
                } catch (PDOException $e) {
                    error_log("Error updating product_type column: " . $e->getMessage());
                }
            }
            
            $table_checked = true;
        } catch (PDOException $e) {
            error_log("Error ensuring sample_booklets table: " . $e->getMessage());
        }
    }
}

// Helper function to get status badge
function getStatusBadge($status) {
    $badges = [
        'Pending' => 'bg-warning',
        'Shipped' => 'bg-info',
        'Delivered' => 'bg-success'
    ];
    
    $badgeClass = $badges[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($status) . '</span>';
}

// Helper function to get product badge
function getProductBadge($product) {
    $badges = [
        'Demo Kit & Sample Booklet' => 'bg-success',
        'Sample Booklet Only' => 'bg-primary',
        'Trial Kit' => 'bg-info',
        'Demo Kit Only' => 'bg-warning'
    ];
    
    $badgeClass = $badges[$product] ?? 'bg-secondary';
    return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($product) . '</span>';
}

// Initialize database
ensureSampleBookletsTable($pdo);

// Auto-check delivery status for shipped orders (run automatically in background)
// This will check for deliveries every time the page loads if it's been more than 1 hour since last check
$auto_check_enabled = true; // Set to false to disable auto-checking
if ($auto_check_enabled) {
    try {
        // Check when we last ran the auto-delivery check
        $last_check_file = 'temp/last_delivery_check.txt';
        $last_check_time = 0;
        
        if (file_exists($last_check_file)) {
            $last_check_time = (int)file_get_contents($last_check_file);
        }
        
        $current_time = time();
        $check_interval = 3600; // 1 hour = 3600 seconds
        
        // Only run auto-check if it's been more than 1 hour since last check
        if (($current_time - $last_check_time) > $check_interval) {
            // Create temp directory if it doesn't exist
            if (!is_dir('temp')) {
                mkdir('temp', 0755, true);
            }
            
            // Update last check time
            file_put_contents($last_check_file, $current_time);
            
            // Run the delivery check in the background (non-blocking)
            // This will automatically update any delivered orders
            $check_url = 'handlers/delivery_check.php?cron=1';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 1, // Quick timeout to avoid page delays
                    'ignore_errors' => true
                ]
            ]);
            
            // Fire and forget - don't wait for response
            @file_get_contents($check_url, false, $context);
            
            error_log("Auto-delivery check triggered in background");
        }
    } catch (Exception $e) {
        error_log("Auto-delivery check failed: " . $e->getMessage());
    }
}

try {
    // Get all sample booklets first - main data query
    $stmt = $pdo->query("SELECT * FROM sample_booklets ORDER BY id DESC");
    $sample_booklets = $stmt->fetchAll();
    
    // Calculate statistics from the fetched data
    $stats = [
        'total' => count($sample_booklets),
        'pending' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'recent' => 0
    ];
    
    $one_week_ago = date('Y-m-d', strtotime('-7 days'));
    
    foreach ($sample_booklets as $order) {
        // Count by status
        switch ($order['status']) {
            case 'Pending':
                $stats['pending']++;
                break;
            case 'Shipped':
                $stats['shipped']++;
                break;
            case 'Delivered':
                $stats['delivered']++;
                break;
        }
        
        // Count recent orders
        if ($order['date_ordered'] >= $one_week_ago) {
            $stats['recent']++;
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in sample booklets page: " . $e->getMessage());
    $stats = ['total' => 0, 'pending' => 0, 'shipped' => 0, 'delivered' => 0, 'recent' => 0];
    $sample_booklets = [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-book me-2"></i>Sample Booklets Management</h1>
            <div>
                <button type="button" class="btn btn-outline-info me-2" onclick="checkDeliveryStatus()">
                    <i class="fas fa-search me-2"></i>Check Delivery Status
                </button>
                <button type="button" class="btn btn-outline-secondary me-2" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus me-2"></i>Add New Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2 col-sm-4 col-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total Orders</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-boxes fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-4 col-6">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['pending']; ?></h4>
                        <p class="mb-0">Pending</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-4 col-6">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['shipped']; ?></h4>
                        <p class="mb-0">Shipped</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-shipping-fast fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-4 col-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['delivered']; ?></h4>
                        <p class="mb-0">Delivered</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 col-sm-4 col-6">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['recent']; ?></h4>
                        <p class="mb-0">This Week</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sample Booklets Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Sample Booklet Orders</h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm active" onclick="filterOrders('all')">All</button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterOrders('pending')">Pending</button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="filterOrders('shipped')">Shipped</button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="filterOrders('delivered')">Delivered</button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sample_booklets)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No sample booklet orders found.</p>
                        <p><small>Click "Add New Order" to get started!</small></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="ordersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Date Ordered</th>
                                    <th>Tracking</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample_booklets as $order): ?>
                                <tr data-status="<?php echo strtolower($order['status']); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                            <br><small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                            </small>
                                            <br><small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($order['email']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo getProductBadge($order['product_type']); ?>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($order['status']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($order['date_ordered'])); ?>
                                        <?php if ($order['date_shipped']): ?>
                                            <br><small class="text-muted">
                                                Shipped: <?php echo date('M j, Y', strtotime($order['date_shipped'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['tracking_number'])): ?>
                                            <a href="https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=2&text28777=&tLabels=<?php echo urlencode($order['tracking_number']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-external-link-alt me-1"></i><?php echo htmlspecialchars($order['tracking_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                                    data-address="<?php echo htmlspecialchars($order['address']); ?>"
                                                    data-email="<?php echo htmlspecialchars($order['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($order['phone']); ?>"
                                                    data-product-type="<?php echo $order['product_type']; ?>"
                                                    data-status="<?php echo $order['status']; ?>"
                                                    data-tracking-number="<?php echo $order['tracking_number']; ?>"
                                                    data-date-ordered="<?php echo $order['date_ordered']; ?>"
                                                    data-notes="<?php echo htmlspecialchars($order['notes'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($order['status'] === 'Pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="openShippingModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars(addslashes($order['customer_name'])); ?>', '<?php echo htmlspecialchars(addslashes($order['email'])); ?>')">
                                                    <i class="fas fa-shipping-fast"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>', '<?php echo htmlspecialchars(addslashes($order['customer_name'])); ?>')">
                                                <i class="fas fa-trash"></i>
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

<!-- Add/Edit Order Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">
                    <i class="fas fa-plus me-2"></i>Add New Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="orderForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="orderNumber" class="form-label">Order Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="orderNumber" name="order_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customerName" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="customerName" name="customer_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="3" required placeholder="Enter complete shipping address..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productType" class="form-label">Product Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="productType" name="product_type" required>
                                    <option value="">Select Product</option>
                                    <option value="Demo Kit & Sample Booklet">Demo Kit & Sample Booklet</option>
                                    <option value="Sample Booklet Only">Sample Booklet Only</option>
                                    <option value="Trial Kit">Trial Kit</option>
                                    <option value="Demo Kit Only">Demo Kit Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dateOrdered" class="form-label">Date Ordered <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateOrdered" name="date_ordered" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="orderStatus" class="form-label">Status</label>
                                <select class="form-select" id="orderStatus" name="status">
                                    <option value="Pending">Pending</option>
                                    <option value="Shipped">Shipped</option>
                                    <option value="Delivered">Delivered</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="trackingNumberEdit" class="form-label">Tracking Number</label>
                                <input type="text" class="form-control" id="trackingNumberEdit" name="tracking_number" placeholder="Enter USPS tracking number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Add any additional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="orderId" name="order_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Shipping Modal -->
<div class="modal fade" id="shippingModal" tabindex="-1" aria-labelledby="shippingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shippingModalLabel">
                    <i class="fas fa-shipping-fast me-2"></i>Ship Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shippingForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Ship to:</strong> <span id="shippingCustomerName"></span>
                        <br><strong>Email:</strong> <span id="shippingCustomerEmail"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="trackingNumber" class="form-label">USPS Tracking Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="trackingNumber" name="tracking_number" required 
                               placeholder="Enter USPS tracking number">
                        <div class="form-text">Customer will receive an email notification with tracking information.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dateShipped" class="form-label">Ship Date</label>
                        <input type="date" class="form-control" id="dateShipped" name="date_shipped" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <input type="hidden" id="shippingOrderId" name="order_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>Ship & Notify Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/sample_booklets.js"></script>

<?php include 'includes/footer.php'; ?>
