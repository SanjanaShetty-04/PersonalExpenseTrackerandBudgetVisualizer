<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header("Location: view_budget.php");
    exit();
}

// Fetch current budget
$sql = "SELECT category, limit_amount, month_year FROM budget WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: view_budget.php");
    exit();
}
$current = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $limit_amount = floatval($_POST['limit_amount']);
    $month_year = $_POST['month_year'];

    if ($category && $limit_amount > 0 && preg_match('/^\d{4}-\d{2}$/', $month_year)) {
        // Check if category changed
        if ($category !== $current['category']) {
            // Find or create category_id
            $stmt = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND parent_id IS NULL AND type = 'expense'");
            $stmt->bind_param("is", $user_id, $category);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $category_id = $row['id'];
            } else {
                $stmt2 = $conn->prepare("INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, 'expense', NULL)");
                $stmt2->bind_param("is", $user_id, $category);
                $stmt2->execute();
                $category_id = $stmt2->insert_id;
                $stmt2->close();
            }
            $stmt->close();
        } else {
            // Get existing category_id
            $stmt = $conn->prepare("SELECT category_id FROM budget WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category_id = $result->fetch_assoc()['category_id'];
            $stmt->close();
        }

        $sql = "UPDATE budget SET category = ?, category_id = ?, limit_amount = ?, month_year = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidssi", $category, $category_id, $limit_amount, $month_year, $id, $user_id);
        if ($stmt->execute()) {
            header("Location: ../dbms/budget.php");
            exit();
        } else {
            $error = "Failed to update budget. Please try again.";
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
<title>Edit Budget - FinWise</title>
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
  <div class="logo">$ <span>Finance</span>Tracker</div>
  <div class="nav-links">
    <a href="dashboard.php"><i class="ri-dashboard-line"></i>Dashboard</a>
    <a href="profile.php"><i class="ri-user-line"></i>Profile</a>
    <a href="budget.php"><i class="ri-wallet-line"></i>Budget</a>
    <a href="logout.php"><i class="ri-logout-box-r-line"></i>Logout</a>
  </div>
</nav>

<main>
  <h2>Edit Budget</h2>
  <?php if (!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <form method="POST" action="edit_budget.php?id=<?php echo $id; ?>">
    <label for="category">Category</label>
    <input type="text" id="category" name="category" required placeholder="e.g., Groceries" value="<?php echo htmlspecialchars($current['category']); ?>" />

    <label for="limit_amount">Limit Amount ($)</label>
    <input type="number" id="limit_amount" name="limit_amount" min="0.01" step="0.01" required placeholder="e.g., 500" value="<?php echo htmlspecialchars($current['limit_amount']); ?>" />

    <label for="month_year">Month and Year</label>
    <input type="month" id="month_year" name="month_year" required value="<?php echo htmlspecialchars($current['month_year']); ?>" />

    <button type="submit" class="submit-btn">Update Budget</button>
  </form>

  <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
</main>

</body>
</html>