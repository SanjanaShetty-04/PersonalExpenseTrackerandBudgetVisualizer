<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM budget WHERE user_id = ? ORDER BY month_year DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$budgets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Budget - FinWise</title>
  <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
  <style>
    body { font-family: Arial; background: #f5f7fa; padding: 2rem; }
    .container {
      max-width: 800px; margin: auto; background: #fff;
      padding: 2rem; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { margin-bottom: 1rem; color: #2c3e50; }
    .add-btn {
      float: right; margin-bottom: 1rem;
      background: #2980b9; color: white; padding: 0.5rem 1rem;
      border: none; border-radius: 4px; cursor: pointer;
    }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th, td {
      padding: 0.75rem; border-bottom: 1px solid #ddd;
      text-align: left;
    }
    th { background-color: #f0f0f0; }
    td.actions button {
      background: none; border: none; cursor: pointer;
      font-size: 1.2rem; margin-right: 0.5rem; color: #2980b9;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Monthly Budget</h2>
    <button class="add-btn" onclick="location.href='add_budget.php'"><i class="ri-add-line"></i> Add Budget</button>
    <table>
      <thead>
        <tr>
          <th>Month</th>
          <th>Category</th>
          <th>Limit</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($budgets)): ?>
          <?php foreach ($budgets as $b): ?>
            <tr>
              <td><?php echo htmlspecialchars($b['month_year']); ?></td>
              <td><?php echo htmlspecialchars($b['category']); ?></td>
              <td>$<?php echo number_format($b['limit_amount'], 2); ?></td>
              <td class="actions">
                <button onclick="location.href='edit_budget.php?id=<?php echo $b['id']; ?>'"><i class="ri-edit-line"></i></button>
                <button onclick="location.href='delete_budget.php?id=<?php echo $b['id']; ?>'"><i class="ri-delete-bin-line"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">No budget entries found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
