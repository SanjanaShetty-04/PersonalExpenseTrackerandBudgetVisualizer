<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
$user_id = $_SESSION['user_id'];

// Get unread notifications count
$unread_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $unread_count = $row['count'];
}
$stmt->close();

// Helper function
function format_money($amount) {
    return "₹" . number_format($amount, 2);
}

// Fetch data
function fetch_all($conn, $sql, $user_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$income_rows = fetch_all($conn, "SELECT * FROM income WHERE user_id = ?", $user_id);
$expense_rows = fetch_all($conn, "SELECT * FROM expenses WHERE user_id = ?", $user_id);
$budget_rows = fetch_all($conn, "SELECT * FROM budget WHERE user_id = ? ORDER BY month_year DESC", $user_id);
$transaction_rows = fetch_all($conn, "SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC", $user_id);

$total_expenses = array_sum(array_column($expense_rows, 'amount'));
$income_table_sum = array_sum(array_column($income_rows, 'amount'));
$total_income = $income_table_sum;

$current_month_year = date('Y-m');
$current_budget_stmt = $conn->prepare("SELECT SUM(limit_amount) AS total_budget FROM budget WHERE user_id = ? AND month_year = ?");
$current_budget_stmt->bind_param("is", $user_id, $current_month_year);
$current_budget_stmt->execute();
$current_budget_result = $current_budget_stmt->get_result()->fetch_assoc();
$current_budget_stmt->close();

$total_budget = $current_budget_result['total_budget'] ?? 0;
$remaining_budget = $total_budget - $total_expenses;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard - FinWise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
  <style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        margin: 0;
        padding: 0;
        color: #2d3748;
        line-height: 1.6;
        min-height: 100vh;
    }

    nav {
        position: sticky;
        top: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #f0f4f8;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 999;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    nav .logo {
      font-weight: 700;
      font-size: 1.6rem;
      letter-spacing: 1.5px;
    }
    nav .logo span {
        color: #ffffff;
        font-weight: 700;
    }
    nav .nav-links a {
      color: #f0f4f8;
      margin-left: 2rem;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: color 0.3s ease;
      position: relative;
    }
    nav .nav-links a:hover {
      color: #27ae60;
      text-decoration: underline;
    }

    .notification-badge {
      background: #e74c3c;
      color: white;
      border-radius: 50%;
      padding: 0.2rem 0.5rem;
      font-size: 0.8rem;
      position: absolute;
      top: -5px;
      right: -10px;
    }

    main {
      max-width: 1200px;
      margin: 2rem auto 4rem;
      padding: 0 1.25rem;
    }

    .summary {
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(250px,1fr));
      gap: 2rem;
      margin-bottom: 3rem;
    }

    .card {
      background: white;
      border-radius: 20px;
      padding: 2.5rem 2rem;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(15px);
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    }

    .card:hover {
      transform: translateY(-12px) scale(1.02);
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    }

    .card.income {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #1a202c;
    }

    .card.expenses {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #1a202c;
    }

    .card.budget {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: #1a202c;
    }

    .card.remaining {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
      color: #1a202c;
    }


    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(8px);
    }

    .modal-content {
      background-color: #fff;
      margin: 5% auto;
      padding: 0;
      border-radius: 16px;
      width: 90%;
      max-width: 800px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }


    .close {
      color: white;
      float: right;
      font-size: 2rem;
      font-weight: bold;
      margin-top: -0.5rem;
      cursor: pointer;
      transition: color 0.3s ease;
    }

    .close:hover {
      color: #e74c3c;
    }


    .card h3 {
      font-weight: 600;
      font-size: 1.1rem;
      margin-bottom: 0.35rem;
      color: #555;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
    }

    .card h2 {
      font-size: 2.3rem;
      font-weight: 700;
      color: #111;
      margin: 0;
      letter-spacing: 0.02em;
    }

    .card.income {
      border-left: 6px solid #27ae60;
      background: #e8f6ef;
    }

    .card.expenses {
      border-left: 6px solid #e74c3c;
      background: #fdecea;
    }

    .card.budget {
      border-left: 6px solid #2980b9;
      background: #e6f0fa;
    }

    .card.remaining {
      border-left: 6px solid #16a085;
      background: #eafaf6;
    }

    .section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2.75rem;
      padding: 1.5rem 2rem;
      transition: box-shadow 0.3s ease;
    }

    .section:hover {
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.10);
    }

    h3 {
      font-size: 1.4rem;
      margin-bottom: 1.25rem;
      font-weight: 700;
      color: #222;
      letter-spacing: 0.02em;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }

    th, td {
      padding: 0.85rem 1rem;
      border-bottom: 1px solid #e0e3e6;
      text-align: left;
    }

    th {
      background: #f5f7fa;
      font-weight: 700;
      color: #555;
      user-select: none;
    }

    tbody tr:hover {
      background: #f1f7fb;
    }

    td.actions {
      width: 85px;
      text-align: center;
    }

    td.actions button {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.2rem;
      margin-right: 0.5rem;
      color: #2980b9;
      transition: color 0.25s ease;
    }

    td.actions button:last-child {
      margin-right: 0;
    }

    td.actions button:hover {
      color: #e74c3c;
    }

    .add-btn {
      margin-top: 1.25rem;
      padding: 0.9rem 1rem;
      width: 100%;
      background: #27ae60;
      border: none;
      border-radius: 0 0 12px 12px;
      color: white;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
      letter-spacing: 0.02em;
    }

    .add-btn:hover {
      background: #219150;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    .dashboard-grid {
      display: flex;
      justify-content: space-between;
      gap: 1.5rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
    }

    .dashboard-card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
      flex: 1;
      min-width: 250px;
      max-width: 300px;
    }

    .dashboard-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .card-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.8;
    }

    .card-content h3 {
      font-size: 1.4rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
      color: #222;
    }

    .card-amount {
      font-size: 2rem;
      font-weight: 700;
      margin: 0.5rem 0;
      color: #111;
    }

    .card-count {
      font-size: 0.9rem;
      color: #666;
      margin: 0;
    }

    .income-card .card-icon { color: #27ae60; }
    .expense-card .card-icon { color: #e74c3c; }
    .budget-card .card-icon { color: #2980b9; }
    .transaction-card .card-icon { color: #9b59b6; }

    .charts-section {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
    }

    .analytics-section {
      text-align: center;
      margin-top: 2rem;
    }

    .analytics-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .analytics-btn, .forecast-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 1rem 2rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }

    .analytics-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .analytics-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
      background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    .forecast-btn {
      background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
    }

    .forecast-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(6, 182, 212, 0.4);
      background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
    }

    .analytics-btn i, .forecast-btn i {
      font-size: 1.2rem;
    }

    @media (max-width: 600px) {
      nav {
        flex-direction: column;
        gap: 0.75rem;
        padding: 1rem;
      }
      nav .nav-links {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 1rem;
      }
      main {
        margin: 1rem auto 3rem;
        padding: 0 0.75rem;
      }
      .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      .charts-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<nav>
  <div class="logo"><span>Fin</span>Wise</div>
  <div class="nav-links">
    <a href="dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
    <a href="notifications.php"><i class="ri-notification-line"></i> Notifications<?php if ($unread_count > 0) echo " <span class='notification-badge'>$unread_count</span>"; ?></a>
    <a href="profile.php"><i class="ri-user-line"></i> Profile</a>
    <a href="logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
  </div>
</nav>

<main>

  <!-- Summary Cards -->
  <div class="summary">
    <div class="card income">
      <h3><i class="ri-money-dollar-circle-line"></i> Total Income</h3>
      <h2><?= format_money($total_income); ?></h2>
    </div>
    <div class="card expenses">
      <h3><i class="ri-shopping-bag-3-line"></i> Total Expenses</h3>
      <h2><?= format_money($total_expenses); ?></h2>
    </div>
    <div class="card budget">
      <h3><i class="ri-calendar-line"></i> Total Budget</h3>
      <h2><?= format_money($total_budget); ?></h2>
    </div>
    <div class="card remaining">
      <h3><i class="ri-pie-chart-2-line"></i> Remaining Budget</h3>
      <h2><?= format_money($remaining_budget); ?></h2>
    </div>

  </div>

  <!-- Dashboard Boxes -->
  <div class="dashboard-grid">
    <div class="dashboard-card income-card" onclick="location.href='../dbms/income.php'">
      <div class="card-icon">
        <i class="ri-money-dollar-circle-line"></i>
      </div>
      <div class="card-content">
        <h3>Income</h3>
        <p class="card-amount"><?= format_money($total_income); ?></p>
        <p class="card-count"><?= count($income_rows); ?> entries</p>
      </div>
    </div>

    <div class="dashboard-card expense-card" onclick="location.href='../dbms/expense.php'">
      <div class="card-icon">
        <i class="ri-shopping-bag-3-line"></i>
      </div>
      <div class="card-content">
        <h3>Expenses</h3>
        <p class="card-amount"><?= format_money($total_expenses); ?></p>
        <p class="card-count"><?= count($expense_rows); ?> entries</p>
      </div>
    </div>

    <div class="dashboard-card budget-card" onclick="location.href='../dbms/budget.php'">
      <div class="card-icon">
        <i class="ri-calendar-line"></i>
      </div>
      <div class="card-content">
        <h3>Budget</h3>
        <p class="card-amount"><?= format_money($total_budget); ?></p>
        <p class="card-count"><?= count($budget_rows); ?> entries</p>
      </div>
    </div>

    <div class="dashboard-card transaction-card" onclick="location.href='../dbms/transaction.php'">
      <div class="card-icon">
        <i class="ri-exchange-line"></i>
      </div>
      <div class="card-content">
        <h3>Transactions</h3>
        <p class="card-amount"><?= format_money($total_income - $total_expenses); ?></p>
        <p class="card-count"><?= count($transaction_rows); ?> entries</p>
      </div>
    </div>
  </div>

  <!-- Quick Analytics Links -->
  <div class="analytics-section">
    <div class="analytics-buttons">
      <a href="../dbms/analytics.php" class="analytics-btn">
        <i class="ri-bar-chart-line"></i>
        View Detailed Analytics & Trends
      </a>
    </div>
  </div>

</main>

</body>
</html>