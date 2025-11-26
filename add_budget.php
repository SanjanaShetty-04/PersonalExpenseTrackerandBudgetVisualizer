
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $limit_amount = floatval($_POST['limit_amount']);
    $month_year = $_POST['month_year']; // Expect format YYYY-MM

    if ($category && $limit_amount > 0 && preg_match('/^\d{4}-\d{2}$/', $month_year)) {
        // Find or create category_id for the typed category
        $stmt = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND parent_id IS NULL AND type = 'expense'");
        $stmt->bind_param("is", $user_id, $category);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $category_id = $row['id'];
        } else {
            // Create the parent category
            $stmt2 = $conn->prepare("INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, 'expense', NULL)");
            $stmt2->bind_param("is", $user_id, $category);
            $stmt2->execute();
            $category_id = $stmt2->insert_id;
            $stmt2->close();
        }
        $stmt->close();

        $sql = "INSERT INTO budget (user_id, category, category_id, limit_amount, month_year) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isids", $user_id, $category, $category_id, $limit_amount, $month_year);
        if ($stmt->execute()) {
            // Add notification for new budget
            $budget_message = "New budget created for '$category' with limit of $" . number_format($limit_amount, 2) . " for $month_year.";
            $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'budget_created', ?)");
            $stmt3->bind_param("is", $user_id, $budget_message);
            $stmt3->execute();
            $stmt3->close();

            header("Location: ../dbms/budget.php");
            exit();
        } else {
            $error = "Failed to add budget. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Add Budget - FinWise</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
<style>
  body {
    font-family: Arial, sans-serif;
    background: #f5f7fa;
    margin: 0;
    padding: 0;
  }
  nav {
    background: #2c3e50;
    color: #fff;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  nav .logo {
    font-size: 1.5rem;
    font-weight: bold;
  }
  nav .nav-links a {
    color: #fff;
    margin-left: 1.5rem;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
  }
  nav .nav-links a:hover {
    text-decoration: underline;
  }
  main {
    max-width: 500px;
    margin: 3rem auto;
    background: #fff;
    padding: 2rem 2.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
  }
  h2 {
    margin-bottom: 1.5rem;
    color: #333;
    font-weight: 600;
    text-align: center;
  }
  form label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 600;
    color: #555;
  }
  form input[type="text"],
  form input[type="number"],
  form input[type="month"] {
    width: 100%;
    padding: 0.5rem 0.75rem;
    margin-bottom: 1.2rem;
    border: 1.8px solid #ccc;
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
  }
  form input[type="text"]:focus,
  form input[type="number"]:focus,
  form input[type="month"]:focus {
    border-color: #2980b9;
    outline: none;
  }
  button.submit-btn {
    background: #27ae60;
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: 600;
    width: 100%;
    transition: background-color 0.3s ease;
  }
  button.submit-btn:hover {
    background: #1f6391;
  }
  .error-msg {
    color: #e74c3c;
    margin-bottom: 1rem;
    font-weight: 600;
    text-align: center;
  }
  .back-link {
    margin-top: 1rem;
    display: block;
    text-align: center;
    font-size: 0.95rem;
    color:rgb(15, 113, 30);
    text-decoration: none;
    font-weight: 600;
  }
  .back-link:hover {
    text-decoration: underline;
  }
</style>
</head>
<body>

<nav>
  <div class="logo">$ <span>Fin</span>Wise</div>
  <div class="nav-links">
    <a href="dashboard.php"><i class="ri-dashboard-line"></i>Dashboard</a>
    <a href="profile.php"><i class="ri-user-line"></i>Profile</a>
    <a href="budget.php"><i class="ri-wallet-line"></i>Budget</a>
    <a href="logout.php"><i class="ri-logout-box-r-line"></i>Logout</a>
  </div>
</nav>

<main>
  <h2>Add New Budget</h2>
  <?php if (!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <form method="POST" action="add_budget.php">
    <label for="category">Category</label>
    <input type="text" id="category" name="category" required placeholder="e.g., Groceries" />

    <label for="limit_amount">Limit Amount ($)</label>
    <input type="number" id="limit_amount" name="limit_amount" min="0.01" step="0.01" required placeholder="e.g., 500" />

    <label for="month_year">Month and Year</label>
    <input type="month" id="month_year" name="month_year" required />

    <button type="submit" class="submit-btn">Add Budget</button>
  </form>

  <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
</main>

</body>
</html>
