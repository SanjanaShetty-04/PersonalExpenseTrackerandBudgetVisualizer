<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $type = $_POST['type']; // income or expense
    $category = $_POST['category'];
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];

    // Insert into income or expense
    if ($type === 'income') {
        $stmt = $conn->prepare("INSERT INTO income (user_id, source, amount, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $user_id, $category, $amount, $date);
        $stmt->execute();
        $stmt->close();
    } elseif ($type === 'expense') {
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $user_id, $category, $amount, $date);
        $stmt->execute();
        $stmt->close();
    }

    // Insert into transactions
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issds", $user_id, $type, $category, $amount, $date);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header("Location: ../dbms/transaction.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add Transaction</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f7fa;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 500px;
      margin: 3rem auto;
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    h2 {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    label {
      display: block;
      margin-top: 1rem;
      margin-bottom: 0.5rem;
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    button {
      margin-top: 1.5rem;
      width: 100%;
      background: #27ae60;
      color: white;
      padding: 0.75rem;
      font-size: 1rem;
      border: none;
      border-radius: 4px;
      font-weight: bold;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background: #219150;
    }
    a.back {
      display: inline-block;
      margin-top: 1rem;
      text-align: center;
      width: 100%;
      color: #333;
      text-decoration: none;
    }
    a.back:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Add Transaction</h2>
  <form method="POST" action="">
    <label for="type">Type</label>
    <select name="type" id="type" required>
      <option value="">Select Type</option>
      <option value="income">Income</option>
      <option value="expense">Expense</option>
    </select>

    <label for="category">Category / Source</label>
    <input type="text" name="category" id="category" required>

    <label for="amount">Amount</label>
    <input type="number" name="amount" id="amount" step="0.01" required>

    <label for="date">Date</label>
    <input type="date" name="date" id="date" required>

    <button type="submit"><i class="ri-add-line"></i> Add Transaction</button>
  </form>
  <a class="back" href="dashboard.php">&larr; Back to Dashboard</a>
</div>

</body>
</html>
