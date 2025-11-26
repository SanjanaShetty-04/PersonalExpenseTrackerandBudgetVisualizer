<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

// Connect to the database
$conn = new mysqli("localhost", "root", "", "budget_tracker");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$username = $_SESSION['username'];
$userQuery = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userQuery->bind_param("s", $username);
$userQuery->execute();
$userQuery->bind_result($user_id);
$userQuery->fetch();
$userQuery->close();

// Fetch income data
$stmt = $conn->prepare("SELECT id, amount, source, date FROM income WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Income</title>
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
    }
    th, td {
      padding: 0.75rem;
      border: 1px solid #ccc;
      text-align: left;
    }
    th {
      background-color: #21867a;
      color: white;
    }
    a {
      display: inline-block;
      margin-top: 1.5rem;
      color: #21867a;
      text-decoration: none;
      font-weight: bold;
    }
    .actions a {
      margin-right: 10px;
      color: #21867a;
    }
  </style>
</head>
<body>

<h2>Your Income Records</h2>

<?php if ($result->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Amount</th>
        <th>Source</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['amount']); ?></td>
        <td><?php echo htmlspecialchars($row['source']); ?></td>
        <td><?php echo htmlspecialchars($row['date']); ?></td>
        <td class="actions">
          <a href="edit_income.php?id=<?php echo $row['id']; ?>">Edit</a> |
          <a href="delete_income.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this income?');">Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No income records found.</p>
<?php endif; ?>

<a href="dashboard.php">← Back to Dashboard</a>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
