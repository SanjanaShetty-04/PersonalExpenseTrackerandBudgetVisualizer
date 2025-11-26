<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

$income_rows = fetch_all($conn, "SELECT * FROM income WHERE user_id = ?", $user_id);
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

// Monthly trends (last 12 months for better analysis)
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));

    $filtered_incomes = array_filter($income_rows, function($income) use ($month) {
        return date('Y-m', strtotime($income['date'])) === $month;
    });
    $income_sum = array_sum(array_column($filtered_incomes, 'amount'));

    $filtered_expenses = array_filter($expense_rows, function($expense) use ($month) {
        return date('Y-m', strtotime($expense['date'])) === $month;
    });
    $expense_sum = array_sum(array_column($filtered_expenses, 'amount'));

    $monthly_data[] = [
        'month' => $month_name,
        'income' => $income_sum,
        'expenses' => $expense_sum,
        'savings' => $income_sum - $expense_sum
    ];
}

// Calculate growth rates
$savings_trend = array_column($monthly_data, 'savings');
$income_trend = array_column($monthly_data, 'income');
$expense_trend = array_column($monthly_data, 'expenses');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Financial Trends</title>
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
    }

    .header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .header h1 {
      color: #9b59b6;
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
      margin: 0.5rem 0;
    }

    .stat-card .change {
      font-size: 0.9rem;
      color: #666;
      margin-top: 0.5rem;
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
      grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
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

    .insights-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
      padding: 2rem;
    }

    .insights-section h2 {
      font-size: 1.8rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: #222;
      text-align: center;
    }

    .insights-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
    }

    .insight-card {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 1.5rem;
      border-left: 4px solid #9b59b6;
    }

    .insight-card h4 {
      margin-bottom: 0.5rem;
      color: #333;
      font-size: 1.1rem;
    }

    .insight-card p {
      margin: 0;
      color: #666;
      line-height: 1.5;
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
      .insights-grid {
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
    <h1>Financial Trends</h1>
    <p>Long-term analysis of your financial patterns and trends</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <h3>Average Monthly Savings</h3>
      <div class="value" style="color: #27ae60;">
        <?= format_money(array_sum(array_column($monthly_data, 'savings')) / max(1, count(array_filter($monthly_data, function($m) { return $m['income'] > 0 || $m['expenses'] > 0; })))); ?>
      </div>
      <div class="change">Last 12 months</div>
    </div>
    <div class="stat-card">
      <h3>Savings Rate</h3>
      <div class="value" style="color: #f39c12;">
        <?php
        $total_income = array_sum(array_column($monthly_data, 'income'));
        $total_expenses = array_sum(array_column($monthly_data, 'expenses'));
        echo $total_income > 0 ? round((($total_income - $total_expenses) / $total_income) * 100, 1) : 0;
        ?>%
      </div>
      <div class="change">Overall average</div>
    </div>
    <div class="stat-card">
      <h3>Best Month</h3>
      <div class="value" style="color: #9b59b6;">
        <?php
        $best_savings = max(array_column($monthly_data, 'savings'));
        $best_month = '';
        foreach ($monthly_data as $month) {
          if ($month['savings'] == $best_savings) {
            $best_month = $month['month'];
            break;
          }
        }
        echo $best_month ?: 'N/A';
        ?>
      </div>
      <div class="change">Highest savings</div>
    </div>
    <div class="stat-card">
      <h3>Consistency Score</h3>
      <div class="value" style="color: #16a085;">
        <?php
        $positive_months = count(array_filter($monthly_data, function($m) { return $m['savings'] > 0; }));
        echo round(($positive_months / max(1, count($monthly_data))) * 100, 1);
        ?>%
      </div>
      <div class="change">Positive savings months</div>
    </div>
  </div>

  <div class="charts-section">
    <h2>Trend Analysis</h2>
    <div class="charts-container">
      <div class="chart-card">
        <h3>Income vs Expenses Over Time</h3>
        <canvas id="trendsChart"></canvas>
      </div>
      <div class="chart-card">
        <h3>Monthly Savings Trend</h3>
        <canvas id="savingsTrendChart"></canvas>
      </div>
    </div>
  </div>

  <div class="insights-section">
    <h2>Financial Insights</h2>
    <div class="insights-grid">
      <div class="insight-card">
        <h4><i class="ri-trending-up-line"></i> Income Stability</h4>
        <p>Your income has been <?= calculate_income_stability($income_trend); ?> over the past year. <?= get_income_insight($income_trend); ?></p>
      </div>
      <div class="insight-card">
        <h4><i class="ri-trending-down-line"></i> Spending Patterns</h4>
        <p>Your expenses show a <?= calculate_expense_trend($expense_trend); ?> trend. <?= get_expense_insight($expense_trend); ?></p>
      </div>
      <div class="insight-card">
        <h4><i class="ri-pie-chart-line"></i> Savings Performance</h4>
        <p>You've maintained positive savings in <?= count(array_filter($monthly_data, function($m) { return $m['savings'] > 0; })); ?> out of <?= count($monthly_data); ?> months. <?= get_savings_insight($savings_trend); ?></p>
      </div>
      <div class="insight-card">
        <h4><i class="ri-lightbulb-line"></i> Recommendations</h4>
        <p>Consider <?= get_recommendation($monthly_data); ?> to optimize your financial health.</p>
      </div>
    </div>
  </div>

  <div style="text-align: center;">
    <a href="analytics.php" class="back-btn"><i class="ri-arrow-left-line"></i> Back to Analytics</a>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
function calculate_income_stability($trend) {
    if (empty($trend)) return 'unknown';
    $avg = array_sum($trend) / count($trend);
    $variance = 0;
    foreach ($trend as $val) {
        $variance += pow($val - $avg, 2);
    }
    $variance = $variance / count($trend);
    $std_dev = sqrt($variance);
    $cv = $avg > 0 ? ($std_dev / $avg) : 0;
    return $cv < 0.2 ? 'very stable' : ($cv < 0.5 ? 'moderately stable' : 'variable');
}

function get_income_insight($trend) {
    if (empty($trend)) return 'Add more income entries to see insights.';
    $recent = array_slice($trend, -3);
    $older = array_slice($trend, 0, -3);
    if (empty($older)) return 'Continue tracking to see trends.';
    $recent_avg = array_sum($recent) / count($recent);
    $older_avg = array_sum($older) / count($older);
    $change = (($recent_avg - $older_avg) / max(1, $older_avg)) * 100;
    if ($change > 10) return 'Your income is trending upward - great job!';
    if ($change < -10) return 'Your income has decreased recently. Consider additional income sources.';
    return 'Your income remains relatively stable.';
}

function calculate_expense_trend($trend) {
    if (count($trend) < 2) return 'insufficient data';
    $recent = array_slice($trend, -3);
    $older = array_slice($trend, 0, -3);
    if (empty($older)) return 'stable';
    $recent_avg = array_sum($recent) / count($recent);
    $older_avg = array_sum($older) / count($older);
    $change = (($recent_avg - $older_avg) / max(1, $older_avg)) * 100;
    return abs($change) < 5 ? 'stable' : ($change > 0 ? 'increasing' : 'decreasing');
}

function get_expense_insight($trend) {
    if (count($trend) < 2) return 'Add more expense entries to see spending patterns.';
    $avg = array_sum($trend) / count($trend);
    $max = max($trend);
    $min = min($trend);
    $variation = (($max - $min) / max(1, $avg)) * 100;
    if ($variation > 50) return 'Your spending varies significantly month to month.';
    return 'Your spending is relatively consistent.';
}

function get_savings_insight($trend) {
    if (empty($trend)) return 'Start tracking both income and expenses to see savings insights.';
    $positive_months = count(array_filter($trend, function($s) { return $s > 0; }));
    $total_months = count($trend);
    $rate = $positive_months / $total_months;
    if ($rate >= 0.8) return 'Excellent savings discipline!';
    if ($rate >= 0.6) return 'Good savings habits. Keep it up!';
    return 'Consider reviewing your budget to increase savings.';
}

function get_recommendation($data) {
    $avg_savings = array_sum(array_column($data, 'savings')) / count($data);
    $avg_income = array_sum(array_column($data, 'income')) / count($data);
    $avg_expenses = array_sum(array_column($data, 'expenses')) / count($data);

    if ($avg_savings < 0) return 'reducing expenses or increasing income';
    if ($avg_savings / max(1, $avg_income) < 0.1) return 'setting specific savings goals';
    return 'building an emergency fund or investing surplus savings';
}
?>

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
    const savingsData = [];
    <?php foreach ($monthly_data as $month): ?>
        savingsData.push(<?= $month['income'] - $month['expenses'] ?>);
    <?php endforeach; ?>

    new Chart(savingsTrendCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
            datasets: [{
                label: 'Monthly Savings',
                data: savingsData,
                backgroundColor: savingsData.map(val => val >= 0 ? '#27ae60' : '#e74c3c'),
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
</script>

</body>
</html>