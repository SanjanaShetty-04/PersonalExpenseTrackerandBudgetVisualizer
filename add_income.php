<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please log in first.'); window.location.href='login.html';</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_income.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount']);
$source = trim($_POST['source']);
$date = $_POST['date'];

if ($amount <= 0) {
    echo "<script>alert('Please enter a valid amount.'); window.history.back();</script>";
    exit();
}

$stmt = $conn->prepare("INSERT INTO income (user_id, amount, source, date) VALUES (?, ?, ?, ?)");
$stmt->bind_param("idss", $user_id, $amount, $source, $date);

if ($stmt->execute()) {
    $stmt->close();

    // Add notification for income added
    $income_message = "Income of $" . number_format($amount, 2) . " from $source added successfully.";
    $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'income_added', ?)");
    $stmt2->bind_param("is", $user_id, $income_message);
    $stmt2->execute();
    $stmt2->close();

    $conn->close();
    echo "<script>alert('Income added successfully!'); window.location.href='../dbms/income.php';</script>";
} else {
    echo "<script>alert('Error: " . $stmt->error . "'); window.history.back();</script>";
    $stmt->close();
    $conn->close();
}
?>
