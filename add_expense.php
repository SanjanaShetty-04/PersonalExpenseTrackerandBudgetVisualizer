<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

include 'db.php'; // your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category'] ?? '');
    $subcategory_name = trim($_POST['subcategory'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? '';

    if ($category_name && $subcategory_name && $amount > 0 && $date) {
        $user_id = $_SESSION['user_id'];

        // Find or create parent category
        $stmt = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND parent_id IS NULL AND type = 'expense'");
        $stmt->bind_param("is", $user_id, $category_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $parent_id = $row['id'];
        } else {
            // Create parent category
            $stmt2 = $conn->prepare("INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, 'expense', NULL)");
            $stmt2->bind_param("is", $user_id, $category_name);
            if ($stmt2->execute()) {
                $parent_id = $stmt2->insert_id;
            } else {
                $error_msg = 'Error creating category: ' . $stmt2->error;
            }
            $stmt2->close();
        }
        $stmt->close();

        // Find or create subcategory
        $stmt = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND parent_id = ? AND type = 'expense'");
        $stmt->bind_param("isi", $user_id, $subcategory_name, $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $category_id = $row['id'];
        } else {
            // Create subcategory
            $stmt2 = $conn->prepare("INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, 'expense', ?)");
            $stmt2->bind_param("isi", $user_id, $subcategory_name, $parent_id);
            $stmt2->execute();
            $category_id = $stmt2->insert_id;
            $stmt2->close();
        }
        $stmt->close();

        // Check budget
        $month_year = date('Y-m', strtotime($date));
        $stmt = $conn->prepare("SELECT limit_amount FROM budget WHERE user_id = ? AND category = ? AND month_year = ?");
        $stmt->bind_param("iss", $user_id, $category_name, $month_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $budget_limit = null;
        if ($row = $result->fetch_assoc()) {
            $budget_limit = $row['limit_amount'];
        }
        $stmt->close();

        // Always insert the expense
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, category_id, amount, date) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        $stmt->bind_param("isids", $user_id, $category_name, $category_id, $amount, $date);

        if ($stmt->execute()) {
            $stmt->close();

            // Check if budget exceeded after adding
            if ($budget_limit !== null) {
                // Calculate new total expenses for this category
                $stmt4 = $conn->prepare("
                    SELECT SUM(e.amount) as total
                    FROM expenses e
                    JOIN categories c ON e.category_id = c.id
                    WHERE e.user_id = ? AND (c.id = ? OR c.parent_id = (SELECT parent_id FROM categories WHERE id = ?)) AND DATE_FORMAT(e.date, '%Y-%m') = ?
                ");
                $stmt4->bind_param("iiis", $user_id, $category_id, $category_id, $month_year);
                $stmt4->execute();
                $result4 = $stmt4->get_result();
                $new_total = 0;
                if ($row4 = $result4->fetch_assoc()) {
                    $new_total = $row4['total'] ?? 0;
                }
                $stmt4->close();

                if ($new_total > $budget_limit) {
                    // Insert notification
                    $message = "Budget exceeded for category '$category_name' in $month_year. Spent: $" . number_format($new_total, 2) . ", Limit: $" . number_format($budget_limit, 2) . ".";
                    $stmt5 = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'budget_exceeded', ?)");
                    $stmt5->bind_param("is", $user_id, $message);
                    $stmt5->execute();
                    $stmt5->close();
                }
            }

            $conn->close();
            header("Location: ../dbms/expense.php");
            exit();
        } else {
            $error_msg = 'Error adding expense: ' . $stmt->error;
        }
    } else {
        $error_msg = 'Please fill all fields correctly.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Expense - FinWise</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
  <style>
    /* your CSS here */
    body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
    nav { background: #2c3e50; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: fixed; width: 100%; top: 0; left: 0; z-index: 1000; }
    nav .logo { font-size: 1.5rem; font-weight: bold; }
    nav .nav-links a { color: #fff; margin-left: 1.5rem; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.3rem; }
    nav .nav-links a:hover { text-decoration: underline; }
    .container { max-width: 450px; background: white; margin: 100px auto 2rem; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    h2 { text-align: center; margin-bottom: 1.5rem; color: #333; }
    form { display: flex; flex-direction: column; gap: 1rem; }
    label { font-weight: 600; color: #555; }
    input[type="text"], input[type="number"], input[type="date"] { padding: 0.6rem 0.8rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s ease; }
    input[type="text"]:focus, input[type="number"]:focus, input[type="date"]:focus { border-color: #27ae60; outline: none; box-shadow: 0 0 5px rgba(39, 174, 96, 0.3); }
    button { background: #27ae60; color: white; font-weight: 600; border: none; padding: 0.75rem; border-radius: 4px; cursor: pointer; font-size: 1.1rem; transition: background-color 0.3s ease; }
    button:hover { background: #219150; }
    .back-link { display: block; margin-top: 1rem; text-align: center; color: #2980b9; text-decoration: none; font-weight: 600; }
    .back-link:hover { text-decoration: underline; }
    .error { color: red; text-align: center; margin-bottom: 1rem; }
  </style>
</head>
<body>

<nav>
  <div class="logo">$ <span>Fin</span>Wise</div>
  <div class="nav-links">
    <a href="dashboard.php"><i class="ri-dashboard-line"></i>Dashboard</a>
    <a href="profile.php"><i class="ri-user-line"></i>Profile</a>
    <a href="logout.php"><i class="ri-logout-box-r-line"></i>Logout</a>
  </div>
</nav>

<div class="container">
  <h2>Add New Expense</h2>
  <?php if (!empty($error_msg)): ?>
    <div class="error"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>
  <form action="add_expense.php" method="post">
    <label for="category">Category</label>
    <input type="text" id="category" name="category" placeholder="e.g., Grocery" required value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" />

    <label for="subcategory">Subcategory</label>
    <input type="text" id="subcategory" name="subcategory" placeholder="e.g., Vegetable" required value="<?= htmlspecialchars($_POST['subcategory'] ?? '') ?>" />

    <label for="amount">Amount</label>
    <input type="number" step="0.01" id="amount" name="amount" placeholder="Enter amount" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" />

    <label for="date">Date</label>
    <input type="date" id="date" name="date" required value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" />

    <button type="submit">Add Expense</button>
  </form>

  <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
</div>

</body>
</html>
