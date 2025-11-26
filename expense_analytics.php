<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

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

// Expense categories breakdown
$expense_categories = [];
foreach ($expense_rows as $expense) {
    $category = $expense['category'];
    if (!isset($expense_categories[$category])) {
        $expense_categories[$category] = 0;
    }
    $expense_categories[$category] += $expense['amount'];
}

// Monthly expense trend (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));

    $filtered_expenses = array_filter($expense_rows, function($expense) use ($month) {
        return date('Y-m', strtotime($expense['date'])) === $month;
    });
    $expense_sum = array_sum(array_column($filtered_expenses, 'amount'));

    $monthly_data[] = [
        'month' => $month_name,
        'expenses' => $expense_sum
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Expense Analytics</title>
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
      color: #e74c3c;
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
      color: #e74c3c;
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
      color: #e74c3c;
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
      color: #e74c3c;
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
    <h1>Expense Analytics</h1>
    <p>Detailed analysis of your expenses and spending patterns</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <h3>Total Expenses</h3>
      <div class="value"><?= format_money(array_sum(array_column($expense_rows, 'amount'))); ?></div>
      <div>All time</div>
    </div>
    <div class="stat-card">
      <h3>Average Monthly Expenses</h3>
      <div class="value"><?= format_money(array_sum(array_column($expense_rows, 'amount')) / max(1, count(array_unique(array_column($expense_rows, 'date'))))); ?></div>
      <div>Based on <?= count($expense_rows); ?> entries</div>
    </div>
    <div class="stat-card">
      <h3>Expense Categories</h3>
      <div class="value" style="color: #f39c12;"><?= count(array_unique(array_column($expense_rows, 'category'))); ?></div>
      <div>Different categories</div>
    </div>
    <div class="stat-card">
      <h3>Largest Category</h3>
      <div class="value" style="color: #9b59b6;"><?= !empty($expense_categories) ? array_keys($expense_categories, max($expense_categories))[0] : 'N/A'; ?></div>
      <div>By amount</div>
    </div>
  </div>

  <div class="charts-section">
    <h2>Expense Visualization</h2>
    <div class="charts-container">
      <div class="chart-card">
        <h3>Expense Categories Breakdown</h3>
        <canvas id="expenseCategoriesChart"></canvas>
      </div>
      <div class="chart-card">
        <h3>Monthly Expense Trend</h3>
        <canvas id="expenseTrendChart"></canvas>
      </div>
    </div>
  </div>

  <div style="text-align: center;">
    <a href="analytics.php" class="back-btn"><i class="ri-arrow-left-line"></i> Back to Analytics</a>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Expense Categories Pie Chart
const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart');
if (expenseCategoriesCtx) {
    new Chart(expenseCategoriesCtx.getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($expense_categories)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($expense_categories)) ?>,
                backgroundColor: ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
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
</script>

</body>
</html>