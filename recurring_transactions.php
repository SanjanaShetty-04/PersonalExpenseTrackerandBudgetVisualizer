<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type = $_POST['type'];
        $description = $_POST['description'];
        $amount = floatval($_POST['amount']);
        $frequency = $_POST['frequency'];
        $start_date = $_POST['start_date'];
        $category = $_POST['category'] ?? '';

        if ($amount > 0 && !empty($description) && !empty($start_date)) {
            // Insert recurring transaction
            $sql = "INSERT INTO recurring_transactions (user_id, type, description, amount, frequency, start_date, category, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsss", $user_id, $type, $description, $amount, $frequency, $start_date, $category);

            if ($stmt->execute()) {
                $message = "Recurring transaction added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding recurring transaction.";
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Please fill in all required fields with valid data.";
            $message_type = 'error';
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id']);
        $is_active = intval($_POST['is_active']);

        $sql = "UPDATE recurring_transactions SET is_active = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $is_active, $id, $user_id);

        if ($stmt->execute()) {
            $message = "Recurring transaction " . ($is_active ? "activated" : "deactivated") . " successfully!";
            $message_type = 'success';
        } else {
            $message = "Error updating recurring transaction.";
            $message_type = 'error';
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        $sql = "DELETE FROM recurring_transactions WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);

        if ($stmt->execute()) {
            $message = "Recurring transaction deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting recurring transaction.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Fetch recurring transactions
$sql = "SELECT * FROM recurring_transactions WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recurring_transactions = [];
while ($row = $result->fetch_assoc()) {
    $recurring_transactions[] = $row;
}
$stmt->close();

// Check for due transactions and auto-add them
$current_date = date('Y-m-d');
foreach ($recurring_transactions as $recurring) {
    if ($recurring['is_active']) {
        $last_added = $recurring['last_added_date'] ?? $recurring['start_date'];
        $next_date = getNextDate($last_added, $recurring['frequency']);

        if ($next_date <= $current_date) {
            // Auto-add the transaction
            if ($recurring['type'] === 'income') {
                $sql = "INSERT INTO income (user_id, source, amount, date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isds", $user_id, $recurring['description'], $recurring['amount'], $next_date);
            } else {
                $sql = "INSERT INTO expenses (user_id, category, amount, date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isds", $user_id, $recurring['category'], $recurring['amount'], $next_date);
            }

            if ($stmt->execute()) {
                // Update last_added_date
                $update_sql = "UPDATE recurring_transactions SET last_added_date = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $next_date, $recurring['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
}

function getNextDate($last_date, $frequency) {
    $date = new DateTime($last_date);

    switch ($frequency) {
        case 'daily':
            $date->modify('+1 day');
            break;
        case 'weekly':
            $date->modify('+1 week');
            break;
        case 'bi-weekly':
            $date->modify('+2 weeks');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'yearly':
            $date->modify('+1 year');
            break;
    }

    return $date->format('Y-m-d');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Recurring Transactions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" />
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9fbfc;
            margin: 0;
            padding: 0;
            color: #222;
            line-height: 1.6;
        }

        nav {
            position: sticky;
            top: 0;
            background: #1e2a38;
            color: #f0f4f8;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        nav .logo {
            font-weight: 700;
            font-size: 1.6rem;
            letter-spacing: 1.5px;
        }
        nav .logo span {
            color: #9b59b6;
        }
        nav .nav-links a {
            color: #f0f4f8;
            margin-left: 2rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: color 0.3s ease;
        }
        nav .nav-links a:hover {
            color: #9b59b6;
            text-decoration: underline;
        }

        main {
            max-width: 1200px;
            margin: 2rem auto 4rem;
            padding: 0 1.25rem;
        }

        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
            padding: 2rem;
            transition: box-shadow 0.3s ease;
        }

        .section:hover {
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.10);
        }

        h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            color: #222;
            letter-spacing: 0.02em;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e0e3e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2980b9;
        }

        .add-btn {
            padding: 0.9rem 2rem;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .add-btn:hover {
            background: #219150;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th, td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e0e3e6;
            text-align: left;
        }

        th {
            background: #f5f7fa;
            font-weight: 700;
            color: #555;
        }

        tbody tr:hover {
            background: #f1f7fb;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .active { background: #d4edda; color: #155724; }
        .inactive { background: #f8d7da; color: #721c24; }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-btn.active { background: #27ae60; color: white; }
        .toggle-btn.inactive { background: #e74c3c; color: white; }
        .delete-btn { background: #e74c3c; color: white; }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        @media (max-width: 600px) {
            nav {
                flex-direction: column;
                gap: 0.75rem;
                padding: 1rem;
            }
            nav .nav-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            main {
                margin: 1rem auto 3rem;
                padding: 0 0.75rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">$<span>Fin</span>Wise</div>
    <div class="nav-links">
        <a href="dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
        <a href="../dbms/transaction.php"><i class="ri-exchange-line"></i> Transactions</a>
        <a href="manage_categories.php"><i class="ri-folder-line"></i> Categories</a>
        <a href="notifications.php"><i class="ri-notification-line"></i> Alerts</a>
        <a href="logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
    </div>
</nav>

<main>
    <div class="section">
        <h2><i class="ri-repeat-line"></i> Add Recurring Transaction</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="recurring-form">
            <input type="hidden" name="action" value="add" />

            <div class="form-grid">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select name="type" id="type" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" required placeholder="e.g., Monthly Salary" />
                </div>

                <div class="form-group">
                    <label for="amount">Amount ($)</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01" required placeholder="0.00" />
                </div>

                <div class="form-group">
                    <label for="frequency">Frequency</label>
                    <select name="frequency" id="frequency" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="bi-weekly">Bi-weekly</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required value="<?php echo date('Y-m-d'); ?>" />
                </div>

                <div class="form-group" id="category_group" style="display: none;">
                    <label for="category">Category</label>
                    <input type="text" name="category" id="category" placeholder="e.g., Utilities" />
                </div>
            </div>

            <button type="submit" class="add-btn">
                <i class="ri-add-line"></i> Add Recurring Transaction
            </button>
        </form>
    </div>

    <div class="section">
        <h2><i class="ri-list-check"></i> Your Recurring Transactions</h2>

        <?php if (empty($recurring_transactions)): ?>
            <p style="text-align: center; padding: 2rem; color: #666;">
                <i class="ri-calendar-line" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                No recurring transactions set up yet.
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Frequency</th>
                        <th>Next Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recurring_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            <td><?php echo ucfirst($transaction['type']); ?></td>
                            <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                            <td><?php echo ucfirst(str_replace('-', ' ', $transaction['frequency'])); ?></td>
                            <td><?php echo date("M j, Y", strtotime(getNextDate($transaction['last_added_date'] ?? $transaction['start_date'], $transaction['frequency']))); ?></td>
                            <td><span class="status-badge <?php echo $transaction['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $transaction['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td class="actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle" />
                                    <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>" />
                                    <input type="hidden" name="is_active" value="<?php echo $transaction['is_active'] ? 0 : 1; ?>" />
                                    <button type="submit" class="action-btn toggle-btn <?php echo $transaction['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="ri-<?php echo $transaction['is_active'] ? 'pause' : 'play'; ?>-line"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this recurring transaction?')">
                                    <input type="hidden" name="action" value="delete" />
                                    <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>" />
                                    <button type="submit" class="action-btn delete-btn">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<script>
document.getElementById('type').addEventListener('change', function() {
    const categoryGroup = document.getElementById('category_group');
    if (this.value === 'expense') {
        categoryGroup.style.display = 'block';
        document.getElementById('category').required = true;
    } else {
        categoryGroup.style.display = 'none';
        document.getElementById('category').required = false;
    }
});
</script>

</body>
</html>