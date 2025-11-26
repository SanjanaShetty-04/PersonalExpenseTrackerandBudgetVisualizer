<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "budget_tracker");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$userQuery = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userQuery->bind_param("s", $username);
$userQuery->execute();
$userQuery->bind_result($user_id);
$userQuery->fetch();
$userQuery->close();

$stmt = $conn->prepare("SELECT id, amount, category, date FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Your Expenses</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background-color: #f4f4f4;
    }
    h2 {
      color: #333;
      margin-bottom: 1rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    th, td {
      padding: 0.75rem;
      border: 1px solid #ccc;
      text-align: left;
    }
    th {
      background-color: #b22222;
      color: white;
    }
    a {
      color: #b22222;
      text-decoration: none;
      font-weight: bold;
    }
    .actions a {
      margin-right: 10px;
    }
    .nav-links {
      margin-top: 20px;
    }
    .nav-links a {
      margin-right: 15px;
    }
  </style>
</head>
<body>

<h2>Your Expense Records</h2>

<?php if ($result->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Amount</th>
        <th>Category</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['amount']); ?></td>
        <td><?php echo htmlspecialchars($row['category']); ?></td>
        <td><?php echo htmlspecialchars($row['date']); ?></td>
        <td class="actions">
          <a href="edit_expense.php?id=<?php echo $row['id']; ?>">Edit</a>
          <a href="delete_expense.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this expense?');">Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No expense records found.</p>
<?php endif; ?>

<div class="nav-links">
  <a href="add_expense.php">➕ Add New Expense</a> |
  <a href="dashboard.php">← Back to Dashboard</a>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
