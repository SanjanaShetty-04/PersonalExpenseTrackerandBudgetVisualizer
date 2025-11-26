<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

// Fetch data for analytics
$income_rows = fetch_all($conn, "SELECT * FROM income WHERE user_id = ?", $user_id);
$expense_rows = fetch_all($conn, "SELECT * FROM expenses WHERE user_id = ?", $user_id);
$budget_rows = fetch_all($conn, "SELECT * FROM budget WHERE user_id = ? ORDER BY month_year DESC", $user_id);
$transaction_rows = fetch_all($conn, "SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC", $user_id);

// Helper function
function fetch_all($conn, $sql, $user_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function format_money($amount) {
    return "₹" . number_format($amount, 2);
}

// Calculate totals
$total_expenses = array_sum(array_column($expense_rows, 'amount'));
$income_table_sum = array_sum(array_column($income_rows, 'amount'));
$total_income = $income_table_sum;

// Expense categories breakdown
$expense_categories = [];
foreach ($expense_rows as $expense) {
    $category = $expense['category'];
    if (!isset($expense_categories[$category])) {
        $expense_categories[$category] = 0;
    }
    $expense_categories[$category] += $expense['amount'];
}

// Monthly trends (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));

    $income_sum = 0;
    foreach ($income_rows as $row) {
        if (date('Y-m', strtotime($row['date'])) === $month) {
            $income_sum += $row['amount'];
        }
    }

    $expense_sum = 0;
    foreach ($expense_rows as $row) {
        if (date('Y-m', strtotime($row['date'])) === $month) {
            $expense_sum += $row['amount'];
        }
    }

    $monthly_data[] = [
        'month' => $month_name,
        'income' => $income_sum,
        'expenses' => $expense_sum
    ];
}

// Budget utilization data
$current_month = date('Y-m');
$budget_data = [];
$actual_data = [];

foreach ($budget_rows as $budget) {
    if ($budget['month_year'] === $current_month) {
        $category = $budget['category'];
        $budget_data[$category] = $budget['limit_amount'];

        $actual = 0;
        foreach ($expense_rows as $expense) {
            if ($expense['category'] === $category &&
                date('Y-m', strtotime($expense['date'])) === $current_month) {
                $actual += $expense['amount'];
            }
        }
        $actual_data[$category] = $actual;
    }
}

$utilization_labels = array_keys($budget_data);
$utilization_data = [];
$utilization_colors = [];
foreach ($budget_data as $category => $budget) {
    $actual = $actual_data[$category] ?? 0;
    $utilization = $budget > 0 ? ($actual / $budget) * 100 : 0;
    $utilization_data[] = $utilization;
    $utilization_colors[] = $utilization > 100 ? '#e74c3c' : ($utilization > 80 ? '#f39c12' : '#27ae60');
}

// Savings data
$savings_data = [];
$savings_colors = [];
foreach ($monthly_data as $data) {
    $savings = $data['income'] - $data['expenses'];
    $savings_data[] = $savings;
    $savings_colors[] = $savings >= 0 ? '#27ae60' : '#e74c3c';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Analytics & Trends</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f9fbfc;
      margin: 0;
      padding: 0;
      color: #222;
      line-height: 1.6;
    }

    nav {
      position: sticky;
      top: 0;
      background: #1e2a38;
      color: #f0f4f8;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 999;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    nav .logo {
      font-weight: 700;
      font-size: 1.6rem;
      letter-spacing: 1.5px;
    }
    nav .logo span {
      color: #9b59b6;
    }
    nav .nav-links a {
      color: #f0f4f8;
      margin-left: 2rem;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: color 0.3s ease;
    }
    nav .nav-links a:hover {
      color: #9b59b6;
      text-decoration: underline;
    }

    main {
      max-width: 1400px;
      margin: 2rem auto 4rem;
      padding: 0 1.25rem;
      display: flex;
      gap: 2rem;
    }

    .sidebar {
      width: 300px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      padding: 0;
      height: fit-content;
      position: sticky;
      top: 100px;
      overflow: hidden;
    }

    .sidebar-nav {
      background: #f8f9fa;
      padding: 1rem;
      border-bottom: 1px solid #e9ecef;
    }

    .sidebar-nav h3 {
      margin: 0;
      color: #333;
      font-size: 1.2rem;
      font-weight: 600;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .sidebar-menu li {
      border-bottom: 1px solid #f1f3f4;
    }

    .sidebar-menu li:last-child {
      border-bottom: none;
    }

    .sidebar-menu a {
      display: block;
      padding: 1rem 1.5rem;
      color: #495057;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      cursor: pointer;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
      background: #e3f2fd;
      color: #1976d2;
      padding-left: 2rem;
    }

    .sidebar-menu a i {
      margin-right: 0.5rem;
      font-size: 1.1rem;
    }

    .sidebar-content {
      padding: 2rem;
      max-height: 70vh;
      overflow-y: auto;
    }

    .sidebar-content h4 {
      margin-bottom: 1rem;
      color: #333;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .sidebar .metric {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid #f1f3f4;
    }

    .sidebar .metric:last-child {
      border-bottom: none;
    }

    .sidebar .metric .label {
      font-weight: 500;
      color: #666;
    }

    .sidebar .metric .value {
      font-weight: 700;
      font-size: 1.1rem;
      color: #27ae60;
    }

    .content {
      flex: 1;
    }

    .section-container {
      display: none;
    }

    .section-container.active {
      display: block;
    }

    .sidebar-content {
      display: none;
    }

    .sidebar-content.active {
      display: block;
    }

    .charts-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
      padding: 2rem;
      transition: box-shadow 0.3s ease;
    }

    .charts-section:hover {
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.10);
    }

    h2 {
      font-size: 1.8rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: #222;
      letter-spacing: 0.02em;
    }

    .charts-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 2rem;
    }

    .chart-card {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 1.5rem;
    }

    .chart-card h3 {
      margin-bottom: 1rem;
      color: #333;
      font-size: 1.2rem;
    }

    .chart-card canvas {
      max-height: 400px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
      text-align: center;
    }

    .stat-card h3 {
      font-size: 1.1rem;
      color: #666;
      margin-bottom: 0.5rem;
    }

    .stat-card .value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #27ae60;
      margin: 0.5rem 0;
    }

    .stat-card .change {
      font-size: 0.9rem;
      color: #666;
    }

    @media (max-width: 768px) {
      main {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        position: static;
      }
      .charts-container {
        grid-template-columns: 1fr;
      }
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<nav>
  <div class="logo">$<span>Fin</span>Wise</div>
  <div class="nav-links">
    <a href="../dbms_backend/dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
    <a href="../dbms_backend/profile.php"><i class="ri-user-line"></i> Profile</a>
    <a href="../dbms_backend/logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
  </div>
</nav>

<main>
  <div class="sidebar">
    <div class="sidebar-nav">
      <h3>Analytics Menu</h3>
    </div>
    <ul class="sidebar-menu">
      <li><a href="analytics.php?section=overview" class="active"><i class="ri-dashboard-line"></i> Overview</a></li>
      <li><a href="income_analytics.php"><i class="ri-money-dollar-circle-line"></i> Income Analysis</a></li>
      <li><a href="expense_analytics.php"><i class="ri-shopping-bag-3-line"></i> Expense Analysis</a></li>
      <li><a href="budget_analytics.php"><i class="ri-calendar-line"></i> Budget Analysis</a></li>
      <li><a href="trends_analytics.php"><i class="ri-trending-up-line"></i> Trends</a></li>
    </ul>

    <div id="overview" class="sidebar-content">
      <h4>Key Metrics</h4>
      <div class="metric">
        <span class="label">Total Income</span>
        <span class="value"><?= format_money($total_income); ?></span>
      </div>
      <div class="metric">
        <span class="label">Total Expenses</span>
        <span class="value" style="color: #e74c3c;"><?= format_money($total_expenses); ?></span>
      </div>
      <div class="metric">
        <span class="label">Net Balance</span>
        <span class="value" style="color: <?= ($total_income - $total_expenses) >= 0 ? '#27ae60' : '#e74c3c' ?>;"><?= format_money($total_income - $total_expenses); ?></span>
      </div>
      <div class="metric">
        <span class="label">Total Transactions</span>
        <span class="value" style="color: #9b59b6;"><?= count($transaction_rows); ?></span>
      </div>
      <div class="metric">
        <span class="label">Active Budgets</span>
        <span class="value" style="color: #2980b9;"><?= count($budget_rows); ?></span>
      </div>
    </div>

    <div id="income" class="sidebar-content" style="display: none;">
      <h4>Income Insights</h4>
      <div class="metric">
        <span class="label">Avg Monthly Income</span>
        <span class="value"><?= format_money($total_income / max(1, count(array_unique(array_column($income_rows, 'date'))))); ?></span>
      </div>
      <div class="metric">
        <span class="label">Income Sources</span>
        <span class="value" style="color: #27ae60;"><?= count(array_unique(array_column($income_rows, 'source'))); ?></span>
      </div>
      <div class="metric">
        <span class="label">Total Entries</span>
        <span class="value" style="color: #f39c12;"><?= count($income_rows); ?></span>
      </div>
    </div>

    <div id="expense" class="sidebar-content" style="display: none;">
      <h4>Expense Insights</h4>
      <div class="metric">
        <span class="label">Avg Monthly Expenses</span>
        <span class="value" style="color: #e74c3c;"><?= format_money($total_expenses / max(1, count(array_unique(array_column($expense_rows, 'date'))))); ?></span>
      </div>
      <div class="metric">
        <span class="label">Expense Categories</span>
        <span class="value" style="color: #e67e22;"><?= count(array_unique(array_column($expense_rows, 'category'))); ?></span>
      </div>
      <div class="metric">
        <span class="label">Total Entries</span>
        <span class="value" style="color: #9b59b6;"><?= count($expense_rows); ?></span>
      </div>
    </div>

    <div id="budget" class="sidebar-content" style="display: none;">
      <h4>Budget Insights</h4>
      <div class="metric">
        <span class="label">Total Budget Limit</span>
        <span class="value" style="color: #2980b9;"><?= format_money(array_sum(array_column($budget_rows, 'limit_amount'))); ?></span>
      </div>
      <div class="metric">
        <span class="label">Budget Utilization</span>
        <span class="value" style="color: #16a085;"><?= array_sum(array_column($budget_rows, 'limit_amount')) > 0 ? round(($total_expenses / array_sum(array_column($budget_rows, 'limit_amount'))) * 100, 1) : 0; ?>%</span>
      </div>
      <div class="metric">
        <span class="label">Active Budgets</span>
        <span class="value" style="color: #f39c12;"><?= count($budget_rows); ?></span>
      </div>
    </div>

    <div id="trends" class="sidebar-content" style="display: none;">
      <h4>Trend Analysis</h4>
      <div class="metric">
        <span class="label">Savings Rate</span>
        <span class="value" style="color: #27ae60;"><?= $total_income > 0 ? round((($total_income - $total_expenses) / $total_income) * 100, 1) : 0; ?>%</span>
      </div>
      <div class="metric">
        <span class="label">Expense Growth</span>
        <span class="value" style="color: #e74c3c;">-</span>
      </div>
      <div class="metric">
        <span class="label">Income Growth</span>
        <span class="value" style="color: #27ae60;">-</span>
      </div>
    </div>
  </div>

  <div class="content">
    <!-- Overview Section -->
    <div id="overview-section" class="section-container active">
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Average Monthly Income</h3>
          <div class="value"><?= format_money($total_income / max(1, count(array_unique(array_column($income_rows, 'date'))))); ?></div>
          <div class="change">Based on <?= count($income_rows); ?> entries</div>
        </div>
        <div class="stat-card">
          <h3>Average Monthly Expenses</h3>
          <div class="value" style="color: #e74c3c;"><?= format_money($total_expenses / max(1, count(array_unique(array_column($expense_rows, 'date'))))); ?></div>
          <div class="change">Based on <?= count($expense_rows); ?> entries</div>
        </div>
        <div class="stat-card">
          <h3>Savings Rate</h3>
          <div class="value" style="color: #f39c12;"><?= $total_income > 0 ? round((($total_income - $total_expenses) / $total_income) * 100, 1) : 0; ?>%</div>
          <div class="change">Of total income</div>
        </div>
      </div>

      <div class="charts-section">
        <h2>Financial Overview</h2>
        <div class="charts-container">
          <div class="chart-card">
            <h3>Monthly Income vs Expenses Trend</h3>
            <canvas id="monthlyTrendChart"></canvas>
          </div>
          <div class="chart-card">
            <h3>Income Sources Breakdown</h3>
            <canvas id="incomeSourcesChartOverview"></canvas>
          </div>
          <div class="chart-card">
            <h3>Expense Categories Breakdown</h3>
            <canvas id="expenseCategoriesChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Income Analysis Section -->
    <div id="income-section" class="section-container">
      <div class="charts-section">
        <h2>Income Analysis</h2>
        <div class="charts-container">
          <div class="chart-card">
            <h3>Income Sources Distribution</h3>
            <canvas id="incomeSourcesChart"></canvas>
          </div>
          <div class="chart-card">
            <h3>Monthly Income Trend</h3>
            <canvas id="incomeTrendChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Expense Analysis Section -->
    <div id="expense-section" class="section-container">
      <div class="charts-section">
        <h2>Expense Analysis</h2>
        <div class="charts-container">
          <div class="chart-card">
            <h3>Expense Categories Breakdown</h3>
            <canvas id="expenseCategoriesChart2"></canvas>
          </div>
          <div class="chart-card">
            <h3>Monthly Expense Trend</h3>
            <canvas id="expenseTrendChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Budget Analysis Section -->
    <div id="budget-section" class="section-container">
      <div class="charts-section">
        <h2>Budget Analysis</h2>
        <div class="charts-container">
          <div class="chart-card">
            <h3>Budget vs Actual Expenses</h3>
            <canvas id="budgetVsActualChart"></canvas>
          </div>
          <div class="chart-card">
            <h3>Budget Utilization by Category</h3>
            <canvas id="budgetUtilizationChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Trends Section -->
    <div id="trends-section" class="section-container">
      <div class="charts-section">
        <h2>Financial Trends</h2>
        <div class="charts-container">
          <div class="chart-card">
            <h3>Income vs Expenses Over Time</h3>
            <canvas id="trendsChart"></canvas>
          </div>
          <div class="chart-card">
            <h3>Savings Trend</h3>
            <canvas id="savingsTrendChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Navigation functionality
function showSection(sectionName) {
    console.log('Switching to section:', sectionName);

    // Hide all sections
    const sections = document.querySelectorAll('.section-container');
    sections.forEach(section => {
        section.classList.remove('active');
        section.style.display = 'none';
    });

    // Hide all sidebar contents
    const sidebarContents = document.querySelectorAll('.sidebar-content');
    sidebarContents.forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });

    // Remove active class from menu items
    const menuItems = document.querySelectorAll('.sidebar-menu a');
    menuItems.forEach(item => item.classList.remove('active'));

    // Show selected section
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.add('active');
        targetSection.style.display = 'block';
        console.log('Section activated:', sectionName + '-section');
    } else {
        console.log('Section not found:', sectionName + '-section');
    }

    // Show corresponding sidebar content
    const targetSidebar = document.getElementById(sectionName);
    if (targetSidebar) {
        targetSidebar.classList.add('active');
        targetSidebar.style.display = 'block';
        console.log('Sidebar activated:', sectionName);
    } else {
        console.log('Sidebar not found:', sectionName);
    }

    // Add active class to corresponding menu item
    const activeLink = document.querySelector(`.sidebar-menu a[href*="section=${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check URL for section parameter
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'overview';
    showSection(section);

    // Add click listeners to sidebar menu links
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            const sectionParam = this.href.split('section=')[1];
            if (sectionParam) {
                e.preventDefault();
                showSection(sectionParam);
                // Update URL without reload
                history.pushState(null, '', `?section=${sectionParam}`);
            }
            // If no section param, allow default navigation
        });
    });

    // Overview Charts (always init since it's default)
    initOverviewCharts();

    // Initialize other sections (they will be created when sections are shown)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const target = mutation.target;
                if (target.classList.contains('active')) {
                    const sectionId = target.id;
                    if (sectionId === 'income-section') {
                        initIncomeCharts();
                    } else if (sectionId === 'expense-section') {
                        initExpenseCharts();
                    } else if (sectionId === 'budget-section') {
                        initBudgetCharts();
                    } else if (sectionId === 'trends-section') {
                        initTrendsCharts();
                    }
                }
            }
        });
    });

    // Observe all section containers
    document.querySelectorAll('.section-container').forEach(section => {
        observer.observe(section, { attributes: true });
    });
});

function initOverviewCharts() {
    // Monthly Trend Chart
    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Income',
                    data: <?= json_encode(array_column($monthly_data, 'income')) ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Expenses',
                    data: <?= json_encode(array_column($monthly_data, 'expenses')) ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '₹' + value.toLocaleString() }
                    }
                }
            }
        });
    }

    // Income Sources Chart for Overview
    <?php
    $income_sources_overview = [];
    foreach ($income_rows as $income) {
        $source = $income['source'];
        if (!isset($income_sources_overview[$source])) {
            $income_sources_overview[$source] = 0;
        }
        $income_sources_overview[$source] += $income['amount'];
    }
    ?>
    const incomeSourcesOverviewCtx = document.getElementById('incomeSourcesChartOverview');
    if (incomeSourcesOverviewCtx) {
        new Chart(incomeSourcesOverviewCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($income_sources_overview)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($income_sources_overview)) ?>,
                    backgroundColor: ['#27ae60', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // Expense Categories Pie Chart
    const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart');
    if (expenseCategoriesCtx) {
        new Chart(expenseCategoriesCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($expense_categories)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($expense_categories)) ?>,
                    backgroundColor: ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }
}

function initIncomeCharts() {
    // Income Sources Chart
    <?php
    $income_sources = [];
    foreach ($income_rows as $income) {
        $source = $income['source'];
        if (!isset($income_sources[$source])) {
            $income_sources[$source] = 0;
        }
        $income_sources[$source] += $income['amount'];
    }
    ?>

    const incomeSourcesCtx = document.getElementById('incomeSourcesChart');
    if (incomeSourcesCtx) {
        new Chart(incomeSourcesCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($income_sources)) ?>,
                datasets: [{
                    label: 'Amount ($)',
                    data: <?= json_encode(array_values($income_sources)) ?>,
                    backgroundColor: '#27ae60',
                    borderColor: '#229954',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '$' + value.toLocaleString() }
                    }
                }
            }
        });
    }

    // Income Trend Chart (simplified version)
    const incomeTrendCtx = document.getElementById('incomeTrendChart');
    if (incomeTrendCtx) {
        new Chart(incomeTrendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Monthly Income',
                    data: <?= json_encode(array_column($monthly_data, 'income')) ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '₹' + value.toLocaleString() }
                    }
                }
            }
        });
    }
}

function initExpenseCharts() {
    // Expense Categories Chart
    const expenseCategoriesCtx2 = document.getElementById('expenseCategoriesChart2');
    if (expenseCategoriesCtx2) {
        new Chart(expenseCategoriesCtx2.getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($expense_categories)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($expense_categories)) ?>,
                    backgroundColor: ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22']
                }]
            },
            options: { responsive: true }
        });
    }

    // Expense Trend Chart
    const expenseTrendCtx = document.getElementById('expenseTrendChart');
    if (expenseTrendCtx) {
        new Chart(expenseTrendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Monthly Expenses',
                    data: <?= json_encode(array_column($monthly_data, 'expenses')) ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '₹' + value.toLocaleString() }
                    }
                }
            }
        });
    }
}

function initBudgetCharts() {
    // Budget vs Actual Chart
    const budgetVsActualCtx = document.getElementById('budgetVsActualChart');
    if (budgetVsActualCtx) {
        new Chart(budgetVsActualCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($budget_data)) ?>,
                datasets: [{
                    label: 'Budget',
                    data: <?= json_encode(array_values($budget_data)) ?>,
                    backgroundColor: '#2980b9',
                    borderColor: '#21618c',
                    borderWidth: 1
                }, {
                    label: 'Actual Expenses',
                    data: <?= json_encode(array_values($actual_data)) ?>,
                    backgroundColor: '#e74c3c',
                    borderColor: '#c0392b',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '₹' + value.toLocaleString() }
                    }
                }
            }
        });
    }

    // Budget Utilization Chart
    const budgetUtilizationCtx = document.getElementById('budgetUtilizationChart');
    if (budgetUtilizationCtx) {
        new Chart(budgetUtilizationCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($utilization_labels) ?>,
                datasets: [{
                    label: 'Budget Utilization (%)',
                    data: <?= json_encode($utilization_data) ?>,
                    backgroundColor: <?= json_encode($utilization_colors) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: value => value + '%' }
                    }
                }
            }
        });
    }
}

function initTrendsCharts() {
    // Combined Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        new Chart(trendsCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Income',
                    data: <?= json_encode(array_column($monthly_data, 'income')) ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Expenses',
                    data: <?= json_encode(array_column($monthly_data, 'expenses')) ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '$' + value.toLocaleString() }
                    }
                }
            }
        });
    }

    // Savings Trend Chart
    const savingsTrendCtx = document.getElementById('savingsTrendChart');
    if (savingsTrendCtx) {
        new Chart(savingsTrendCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Monthly Savings',
                    data: <?= json_encode($savings_data) ?>,
                    backgroundColor: <?= json_encode($savings_colors) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        ticks: { callback: value => '$' + value.toLocaleString() }
                    }
                }
            }
        });
    }
}
</script>

</body>
</html>