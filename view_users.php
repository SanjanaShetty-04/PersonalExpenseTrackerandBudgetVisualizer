<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "budget_tracker");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch users
$sql = "SELECT id, username, email FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Users</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; background-color: #f4f4f4; }
    table { width: 100%; border-collapse: collapse; background: white; }
    th, td { padding: 0.75rem; border: 1px solid #ccc; text-align: left; }
    th { background-color: #21867a; color: white; }
    a { color: #21867a; text-decoration: none; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Registered Users</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td>
              <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a> |
              <a href="delete_user.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>

<?php
$conn->close();
?>
