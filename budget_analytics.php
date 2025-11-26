<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

$budget_rows = fetch_all($conn, "SELECT * FROM budget WHERE user_id = ? ORDER BY month_year DESC", $user_id);
$expense_rows = fetch_all($conn, "SELECT * FROM expenses WHERE user_id = ?", $user_id);

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

// Budget vs Actual analysis
$current_month = date('Y-m');
$budget_data = [];
$actual_data = [];
$budget_utilization = [];

foreach ($budget_rows as $budget) {
    if ($budget['month_year'] === $current_month) {
        $category = $budget['category'];
        $budget_data[$category] = $budget['limit_amount'];

        // Find actual expenses for this category
        $actual = 0;
        foreach ($expense_rows as $expense) {
            if ($expense['category'] === $category &&
                date('Y-m', strtotime($expense['date'])) === $current_month) {
                $actual += $expense['amount'];
            }
        }
        $actual_data[$category] = $actual;
        $budget_utilization[$category] = $budget['limit_amount'] > 0 ? ($actual / $budget['limit_amount']) * 100 : 0;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Budget Analytics</title>
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
      color: #2980b9;
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
      color: #2980b9;
      text-decoration: underline;
    }

    main {
      max-width: 1200px;
      margin: 2rem auto 4rem;
      padding: 0 1.25rem;
    }

    .header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .header h1 {
      color: #2980b9;
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
      font-size: 2rem;
      font-weight: 700;
      color: #2980b9;
      margin: 0.5rem 0;
    }

    .charts-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
      padding: 2rem;
    }

    .charts-section h2 {
      font-size: 1.8rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: #222;
      text-align: center;
    }

    .charts-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
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
      text-align: center;
    }

    .chart-card canvas {
      max-height: 400px;
    }

    .budget-status {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
      padding: 2rem;
    }

    .budget-status h2 {
      font-size: 1.8rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: #222;
      text-align: center;
    }

    .budget-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }

    .budget-table th,
    .budget-table td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e0e3e6;
      text-align: left;
    }

    .budget-table th {
      background: #f5f7fa;
      font-weight: 700;
      color: #555;
    }

    .status-indicator {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-good {
      background: #d4edda;
      color: #155724;
    }

    .status-warning {
      background: #fff3cd;
      color: #856404;
    }

    .status-danger {
      background: #f8d7da;
      color: #721c24;
    }

    .back-btn {
      display: inline-block;
      background: #2980b9;
      color: white;
      padding: 0.75rem 1.5rem;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      margin-top: 2rem;
      transition: background-color 0.3s ease;
    }

    .back-btn:hover {
      background: #21618c;
    }

    @media (max-width: 768px) {
      main {
        margin: 1rem auto 3rem;
        padding: 0 0.75rem;
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
  <div class="logo">$<span>Finance</span>Tracker</div>
  <div class="nav-links">
    <a href="../dbms_backend/dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
    <a href="analytics.php"><i class="ri-bar-chart-line"></i> Analytics</a>
    <a href="../dbms_backend/profile.php"><i class="ri-user-line"></i> Profile</a>
    <a href="../dbms_backend/logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
  </div>
</nav>

<main>
  <div class="header">
    <h1>Budget Analytics</h1>
    <p>Detailed analysis of your budget performance and spending control</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <h3>Total Budget Limit</h3>
      <div class="value"><?= format_money(array_sum(array_column($budget_rows, 'limit_amount'))); ?></div>
      <div>All budgets</div>
    </div>
    <div class="stat-card">
      <h3>Current Month Budget</h3>
      <div class="value"><?= format_money(array_sum($budget_data)); ?></div>
      <div><?= date('F Y'); ?></div>
    </div>
    <div class="stat-card">
      <h3>Active Budgets</h3>
      <div class="value" style="color: #f39c12;"><?= count($budget_rows); ?></div>
      <div>Total categories</div>
    </div>
    <div class="stat-card">
      <h3>Average Utilization</h3>
      <div class="value" style="color: #9b59b6;"><?= !empty($budget_utilization) ? round(array_sum($budget_utilization) / count($budget_utilization), 1) : 0; ?>%</div>
      <div>Across all budgets</div>
    </div>
  </div>

  <div class="budget-status">
    <h2>Budget Performance Status</h2>
    <table class="budget-table">
      <thead>
        <tr>
          <th>Category</th>
          <th>Budget Limit</th>
          <th>Actual Spent</th>
          <th>Remaining</th>
          <th>Utilization</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($budget_data as $category => $budget): ?>
          <?php
          $actual = $actual_data[$category] ?? 0;
          $remaining = $budget - $actual;
          $utilization = $budget > 0 ? ($actual / $budget) * 100 : 0;
          $status_class = $utilization <= 70 ? 'status-good' : ($utilization <= 90 ? 'status-warning' : 'status-danger');
          $status_text = $utilization <= 70 ? 'Good' : ($utilization <= 90 ? 'Warning' : 'Over Budget');
          ?>
          <tr>
            <td><?= htmlspecialchars($category); ?></td>
            <td><?= format_money($budget); ?></td>
            <td><?= format_money($actual); ?></td>
            <td><?= format_money($remaining); ?></td>
            <td><?= round($utilization, 1); ?>%</td>
            <td><span class="status-indicator <?= $status_class; ?>"><?= $status_text; ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="charts-section">
    <h2>Budget Visualization</h2>
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

  <div style="text-align: center;">
    <a href="analytics.php" class="back-btn"><i class="ri-arrow-left-line"></i> Back to Analytics</a>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
    const utilizationData = [];
    const utilizationLabels = [];

    <?php foreach ($budget_data as $category => $budget): ?>
      <?php
      $actual = $actual_data[$category] ?? 0;
      $utilization = $budget > 0 ? ($actual / $budget) * 100 : 0;
      ?>
      utilizationData.push(<?= $utilization ?>);
      utilizationLabels.push('<?= addslashes($category) ?>');
    <?php endforeach; ?>

    new Chart(budgetUtilizationCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: utilizationLabels,
            datasets: [{
                label: 'Budget Utilization (%)',
                data: utilizationData,
                backgroundColor: utilizationData.map(val => val <= 70 ? '#27ae60' : val <= 90 ? '#f39c12' : '#e74c3c'),
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
</script>

</body>
</html>