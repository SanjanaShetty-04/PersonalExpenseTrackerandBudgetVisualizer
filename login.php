<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "<script>alert('Please enter email and password'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];  // store name for dashboard display
            echo "<script>alert('Login successful!'); window.location.href = 'dashboard.php';</script>";
            exit();
        } else {
            echo "<script>alert('Incorrect password'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('User not found'); window.history.back();</script>";
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../dbms/login.html");
    exit();
}
?>
