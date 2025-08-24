<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Wrap My Kitchen - Lead Management'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-32x32.png" sizes="32x32" />
    <link rel="icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-192x192.png" sizes="192x192" />
    <link rel="apple-touch-icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-180x180.png" />
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <div class="app-container">
        <!-- Mobile Toggle Button -->
        <button class="mobile-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-brand">
                    <img src="images/wmk-wh.png" alt="Wrap My Kitchen">
                    <div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: white;">Wrap My Kitchen</div>
                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.7); font-weight: 400;">Lead Management</div>
                    </div>
                </a>
            </div>
            
            <!-- Sidebar Navigation -->
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>" href="leads.php">
                        <i class="fas fa-users"></i>
                        All Leads
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'followup.php' ? 'active' : ''; ?>" href="followup.php">
                        <i class="fas fa-calendar-check"></i>
                        Follow-Ups
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'installations.php' ? 'active' : ''; ?>" href="installations.php">
                        <i class="fas fa-tools"></i>
                        Installations
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sample_booklets.php' ? 'active' : ''; ?>" href="sample_booklets.php">
                        <i class="fas fa-book"></i>
                        Sample Booklets
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_lead.php' ? 'active' : ''; ?>" href="add_lead.php">
                        <i class="fas fa-user-plus"></i>
                        Add Lead
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'import_leads.php' ? 'active' : ''; ?>" href="import_leads.php">
                        <i class="fas fa-file-csv"></i>
                        Import Leads
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
            </div>
            
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <div><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="mt-2">
                        <a href="logout.php" class="text-decoration-none" style="color: rgba(255,255,255,0.8);">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Main Content Area -->
        <main class="main-content" id="mainContent">
            <div class="content-wrapper">
    <?php else: ?>
    <div class="login-page">
    <?php endif; ?>