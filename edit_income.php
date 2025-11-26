<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please log in first.'); window.location.href='login.html';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header("Location: view_income.php");
    exit();
}

// Fetch current income
$stmt = $conn->prepare("SELECT amount, source, date FROM income WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo "<script>alert('Income not found.'); window.location.href='view_income.php';</script>";
    exit();
}
$current = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $source = trim($_POST['source']);
    $date = $_POST['date'];

    if ($amount <= 0 || !$source || !$date) {
        $error = "Please fill all fields correctly.";
    } else {
        // Update income
        $stmt = $conn->prepare("UPDATE income SET amount = ?, source = ?, date = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("dssii", $amount, $source, $date, $id, $user_id);
        if ($stmt->execute()) {
            // Update transactions
            $stmt2 = $conn->prepare("UPDATE transactions SET category = ?, amount = ?, date = ? WHERE user_id = ? AND type = 'income' AND category = ? AND amount = ? AND date = ?");
            $stmt2->bind_param("sdsisds", $source, $amount, $date, $user_id, $current['source'], $current['amount'], $current['date']);
            $stmt2->execute();
            $stmt2->close();

            $conn->close();
            echo "<script>alert('Income updated successfully!'); window.location.href='../dbms/income.php';</script>";
            exit();
        } else {
            $error = "Error updating income: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Income</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 2rem; }
        .container { max-width: 500px; margin: auto; background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        form { display: flex; flex-direction: column; gap: 1rem; }
        label { font-weight: bold; }
        input { padding: 0.5rem; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #27ae60; color: white; border: none; padding: 0.75rem; border-radius: 5px; cursor: pointer; }
        button:hover { background: #219150; }
        .error { color: red; text-align: center; }
        .back-link { text-align: center; margin-top: 1rem; }
        .back-link a { color: #2980b9; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Income</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="amount">Amount</label>
            <input type="number" id="amount" name="amount" step="0.01" value="<?php echo htmlspecialchars($current['amount']); ?>" required>

            <label for="source">Source</label>
            <input type="text" id="source" name="source" value="<?php echo htmlspecialchars($current['source']); ?>" required>

            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($current['date']); ?>" required>

            <button type="submit">Update Income</button>
        </form>
        <div class="back-link">
            <a href="view_income.php">&larr; Back to Income</a>
        </div>
    </div>
</body>
</html>