<?php
ob_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /Expense_Budget_tracker/dbms/register.html");
    exit();
}

include 'db.php';

$name = htmlspecialchars(trim($_POST["name"]));
$email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
$password = $_POST["password"];
$confirmPassword = $_POST["confirmPassword"];

if ($password !== $confirmPassword) {
    echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;

    // Add welcome notification
    $welcome_message = "Welcome to FinWise! Start by adding your income sources and setting up budgets to track your finances effectively.";
    $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'welcome', ?)");
    $stmt2->bind_param("is", $user_id, $welcome_message);
    $stmt2->execute();
    $stmt2->close();

    header("Location: /Expense_Budget_tracker/dbms/login.html");
    exit();
} else {
    echo "<script>alert('Error: " . $stmt->error . "'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
exit();
?>
