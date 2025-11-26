<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header("Location: view_expense.php");
    exit();
}

// Fetch current expense
$stmt = $conn->prepare("SELECT amount, category, category_id, date FROM expenses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: view_expense.php");
    exit();
}
$current = $result->fetch_assoc();
$stmt->close();

// Get category and subcategory names
$category_name = $current['category'];
$subcategory_name = '';
if ($current['category_id']) {
    $stmt = $conn->prepare("SELECT name, parent_id FROM categories WHERE id = ?");
    $stmt->bind_param("i", $current['category_id']);
    $stmt->execute();
    $cat_result = $stmt->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        if ($cat_row['parent_id']) {
            // It's a subcategory
            $subcategory_name = $cat_row['name'];
            // Get parent name
            $stmt2 = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt2->bind_param("i", $cat_row['parent_id']);
            $stmt2->execute();
            $parent_result = $stmt2->get_result();
            if ($parent_row = $parent_result->fetch_assoc()) {
                $category_name = $parent_row['name'];
            }
            $stmt2->close();
        } else {
            $category_name = $cat_row['name'];
        }
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category']);
    $subcategory_name = trim($_POST['subcategory']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];

    if ($category_name && $subcategory_name && $amount > 0 && $date) {
        // Find or create parent category
        $stmt = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND parent_id IS NULL AND type = 'expense'");
        $stmt->bind_param("is", $user_id, $category_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $parent_id = $row['id'];
        } else {
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
            $stmt2 = $conn->prepare("INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, 'expense', ?)");
            $stmt2->bind_param("isi", $user_id, $subcategory_name, $parent_id);
            $stmt2->execute();
            $category_id = $stmt2->insert_id;
            $stmt2->close();
        }
        $stmt->close();

        // Update expense
        $stmt = $conn->prepare("UPDATE expenses SET category = ?, category_id = ?, amount = ?, date = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sidssi", $category_name, $category_id, $amount, $date, $id, $user_id);
        if ($stmt->execute()) {
            // Update transactions
            $stmt2 = $conn->prepare("UPDATE transactions SET category = ?, amount = ?, date = ? WHERE user_id = ? AND type = 'expense' AND category = ? AND amount = ? AND date = ?");
            $stmt2->bind_param("sdsisds", $category_name, $amount, $date, $user_id, $current['category'], $current['amount'], $current['date']);
            $stmt2->execute();
            $stmt2->close();

            $conn->close();
            header("Location: ../dbms/expense.php");
            exit();
        } else {
            $error_msg = 'Error updating expense: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_msg = 'Please fill all fields correctly.';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Expense - FinWise</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
  <style>
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
  <div class="logo">$ <span>Finance</span>Tracker</div>
  <div class="nav-links">
    <a href="dashboard.php"><i class="ri-dashboard-line"></i>Dashboard</a>
    <a href="profile.php"><i class="ri-user-line"></i>Profile</a>
    <a href="logout.php"><i class="ri-logout-box-r-line"></i>Logout</a>
  </div>
</nav>

<div class="container">
  <h2>Edit Expense</h2>
  <?php if (!empty($error_msg)): ?>
    <div class="error"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>
  <form action="edit_expense.php?id=<?php echo $id; ?>" method="post">
    <label for="category">Category</label>
    <input type="text" id="category" name="category" placeholder="e.g., Grocery" required value="<?= htmlspecialchars($category_name) ?>" />

    <label for="subcategory">Subcategory</label>
    <input type="text" id="subcategory" name="subcategory" placeholder="e.g., Vegetable" required value="<?= htmlspecialchars($subcategory_name) ?>" />

    <label for="amount">Amount</label>
    <input type="number" step="0.01" id="amount" name="amount" placeholder="Enter amount" required value="<?= htmlspecialchars($current['amount']) ?>" />

    <label for="date">Date</label>
    <input type="date" id="date" name="date" required value="<?= htmlspecialchars($current['date']) ?>" />

    <button type="submit">Update Expense</button>
  </form>

  <a href="view_expense.php" class="back-link">&larr; Back to Expenses</a>
</div>

</body>
</html>