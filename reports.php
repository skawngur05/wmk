<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Reports - Wrap My Kitchen';

// Get current year and month
$current_year = date('Y');
$current_month = date('n');

// Filter parameters
$year = $_GET['year'] ?? $current_year;
$month = $_GET['month'] ?? '';

// Monthly leads by origin
$monthly_origins_sql = "
    SELECT 
        lead_origin,
        MONTH(date_created) as month,
        COUNT(*) as total_leads,
        COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) as sold_leads,
        SUM(CASE WHEN remarks = 'Sold' THEN project_amount ELSE 0 END) as sold_amount
    FROM leads 
    WHERE YEAR(date_created) = ?
";

if (!empty($month)) {
    $monthly_origins_sql .= " AND MONTH(date_created) = ?";
    $params = [$year, $month];
} else {
    $params = [$year];
}

$monthly_origins_sql .= " GROUP BY lead_origin, MONTH(date_created) ORDER BY month, lead_origin";

$stmt = $pdo->prepare($monthly_origins_sql);
$stmt->execute($params);
$monthly_data = $stmt->fetchAll();

// Yearly summary by origin
$yearly_summary_sql = "
    SELECT 
        lead_origin,
        COUNT(*) as total_leads,
        COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) as sold_leads,
        SUM(CASE WHEN remarks = 'Sold' THEN project_amount ELSE 0 END) as sold_amount,
        ROUND(COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) * 100.0 / COUNT(*), 2) as conversion_rate
    FROM leads 
    WHERE YEAR(date_created) = ?
    GROUP BY lead_origin 
    ORDER BY total_leads DESC
";

$stmt = $pdo->prepare($yearly_summary_sql);
$stmt->execute([$year]);
$yearly_summary = $stmt->fetchAll();

// Monthly totals
$monthly_totals_sql = "
    SELECT 
        MONTH(date_created) as month,
        MONTHNAME(date_created) as month_name,
        COUNT(*) as total_leads,
        COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) as sold_leads,
        SUM(CASE WHEN remarks = 'Sold' THEN project_amount ELSE 0 END) as sold_amount
    FROM leads 
    WHERE YEAR(date_created) = ?
    GROUP BY MONTH(date_created), MONTHNAME(date_created)
    ORDER BY MONTH(date_created)
";

$stmt = $pdo->prepare($monthly_totals_sql);
$stmt->execute([$year]);
$monthly_totals = $stmt->fetchAll();

// Team performance
$team_performance_sql = "
    SELECT 
        assigned_to,
        COUNT(*) as total_leads,
        COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) as sold_leads,
        SUM(CASE WHEN remarks = 'Sold' THEN project_amount ELSE 0 END) as sold_amount,
        ROUND(COUNT(CASE WHEN remarks = 'Sold' THEN 1 END) * 100.0 / COUNT(*), 2) as conversion_rate
    FROM leads 
    WHERE YEAR(date_created) = ?
";

if (!empty($month)) {
    $team_performance_sql .= " AND MONTH(date_created) = ?";
    $team_params = [$year, $month];
} else {
    $team_params = [$year];
}

$team_performance_sql .= " GROUP BY assigned_to ORDER BY sold_amount DESC";

$stmt = $pdo->prepare($team_performance_sql);
$stmt->execute($team_params);
$team_performance = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php for ($y = $current_year; $y >= $current_year - 3; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">Month (Optional)</label>
                <select class="form-select" id="month" name="month">
                    <option value="">All Months</option>
                    <?php 
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    foreach ($months as $num => $name): 
                    ?>
                        <option value="<?php echo $num; ?>" <?php echo $month == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <?php
    $total_leads = array_sum(array_column($yearly_summary, 'total_leads'));
    $total_sold = array_sum(array_column($yearly_summary, 'sold_leads'));
    $total_revenue = array_sum(array_column($yearly_summary, 'sold_amount'));
    $overall_conversion = $total_leads > 0 ? round(($total_sold / $total_leads) * 100, 2) : 0;
    ?>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $total_leads; ?></h4>
                        <p class="mb-0">Total Leads</p>
                        <small><?php echo !empty($month) ? $months[$month] : ''; ?> <?php echo $year; ?></small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $total_sold; ?></h4>
                        <p class="mb-0">Sold</p>
                        <small><?php echo $overall_conversion; ?>% conversion</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-handshake fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>$<?php echo number_format($total_revenue, 0); ?></h4>
                        <p class="mb-0">Revenue</p>
                        <small>From sold leads</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>$<?php echo $total_sold > 0 ? number_format($total_revenue / $total_sold, 0) : 0; ?></h4>
                        <p class="mb-0">Avg Deal Size</p>
                        <small>Per sold lead</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Lead Origin Performance -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Performance by Lead Origin</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Lead Origin</th>
                                <th>Total Leads</th>
                                <th>Sold</th>
                                <th>Conversion Rate</th>
                                <th>Revenue</th>
                                <th>Avg Deal Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yearly_summary as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['lead_origin']); ?></strong>
                                </td>
                                <td><?php echo $row['total_leads']; ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $row['sold_leads']; ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $row['conversion_rate']; ?>%" 
                                             aria-valuenow="<?php echo $row['conversion_rate']; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $row['conversion_rate']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($row['sold_amount'], 0); ?></strong>
                                </td>
                                <td>
                                    <?php if ($row['sold_leads'] > 0): ?>
                                        $<?php echo number_format($row['sold_amount'] / $row['sold_leads'], 0); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Performance -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Team Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Team Member</th>
                                <th>Leads</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_performance as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['assigned_to']); ?></strong>
                                    <br><small class="text-muted"><?php echo $member['conversion_rate']; ?>% conv.</small>
                                </td>
                                <td><?php echo $member['total_leads']; ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $member['sold_leads']; ?></span>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($member['sold_amount'], 0); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($month)): ?>
<!-- Monthly Breakdown -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Monthly Breakdown - <?php echo $year; ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th>Total Leads</th>
                                <th>Sold</th>
                                <th>Conversion Rate</th>
                                <th>Revenue</th>
                                <th>Avg Deal Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_totals as $month_data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($month_data['month_name']); ?></strong>
                                </td>
                                <td><?php echo $month_data['total_leads']; ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $month_data['sold_leads']; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $monthly_conversion = $month_data['total_leads'] > 0 ? 
                                        round(($month_data['sold_leads'] / $month_data['total_leads']) * 100, 2) : 0; 
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $monthly_conversion; ?>%" 
                                             aria-valuenow="<?php echo $monthly_conversion; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $monthly_conversion; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($month_data['sold_amount'], 0); ?></strong>
                                </td>
                                <td>
                                    <?php if ($month_data['sold_leads'] > 0): ?>
                                        $<?php echo number_format($month_data['sold_amount'] / $month_data['sold_leads'], 0); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
