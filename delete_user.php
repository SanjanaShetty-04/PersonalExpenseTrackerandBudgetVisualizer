<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_users.php");
    exit();
}

$user_id = intval($_GET['id']);

$conn = new mysqli("localhost", "root", "", "budget_tracker");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: view_users.php?msg=User+deleted+successfully");
    exit();
} else {
    $error = "Error deleting user: " . $conn->error;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Delete User</title>
</head>
<body>
  <p><?php echo isset($error) ? htmlspecialchars($error) : "Unknown error."; ?></p>
  <p><a href="view_users.php">Back to Users List</a></p>
</body>
</html>
