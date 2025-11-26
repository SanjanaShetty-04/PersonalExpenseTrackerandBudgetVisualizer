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
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

// Fetch transactions
$sql = "SELECT id, type, amount, category, date FROM transactions WHERE user_id = ? ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Transactions</title>
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
  </style>
</head>
<body>

<h2>Your Transactions</h2>

<?php if ($result->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Type</th>
        <th>Amount</th>
        <th>Category</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars(ucfirst($row['type'])); ?></td>
        <td><?php echo htmlspecialchars($row['amount']); ?></td>
        <td><?php echo htmlspecialchars($row['category']); ?></td>
        <td><?php echo htmlspecialchars($row['date']); ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No transactions found.</p>
<?php endif; ?>

<a href="dashboard.php">← Back to Dashboard</a>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
