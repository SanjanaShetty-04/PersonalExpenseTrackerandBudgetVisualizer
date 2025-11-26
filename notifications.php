<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
$user_id = $_SESSION['user_id'];

// Get budget alerts
$budget_alerts = [];
$current_month = date('Y-m');
$sql = "SELECT b.category, b.limit_amount, COALESCE(SUM(e.amount), 0) as spent
        FROM budget b
        LEFT JOIN expenses e ON b.category = e.category
            AND e.user_id = b.user_id
            AND DATE_FORMAT(e.date, '%Y-%m') = b.month_year
        WHERE b.user_id = ? AND b.month_year = ?
        GROUP BY b.category, b.limit_amount";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $current_month);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $percentage = $row['limit_amount'] > 0 ? ($row['spent'] / $row['limit_amount']) * 100 : 0;
    if ($percentage >= 80) {
        $budget_alerts[] = [
            'category' => $row['category'],
            'spent' => $row['spent'],
            'limit' => $row['limit_amount'],
            'percentage' => round($percentage, 1),
            'severity' => $percentage >= 100 ? 'danger' : 'warning'
        ];
    }
}
$stmt->close();

// Get notifications
$notifications = [];
$sql = "SELECT id, type, message, created_at FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Mark notifications as read since user has viewed them
if (!empty($notifications)) {
    $notification_ids = array_column($notifications, 'id');
    $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param(str_repeat('i', count($notification_ids)), ...$notification_ids);
    $stmt->execute();
    $stmt->close();
}

// Get upcoming recurring reminders
$recurring_reminders = [];
$sql = "SELECT description, frequency, start_date, last_added_date, amount, type
        FROM recurring_transactions
        WHERE user_id = ? AND is_active = 1
        ORDER BY start_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $last_added = $row['last_added_date'] ?? $row['start_date'];
    $next_date = getNextDate($last_added, $row['frequency']);

    // Only show reminders for next 30 days
    if (strtotime($next_date) <= strtotime('+30 days')) {
        $recurring_reminders[] = [
            'description' => $row['description'],
            'frequency' => $row['frequency'],
            'next_date' => $next_date,
            'amount' => $row['amount'],
            'type' => $row['type']
        ];
    }
}
$stmt->close();

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

// Get recent activity
$recent_activity = [];
$sql = "SELECT 'income' as type, source as description, amount, date
        FROM income WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'expense' as type, category as description, amount, date
        FROM expenses WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY date DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_activity[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Notifications & Alerts</title>
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

        .alert {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .alert.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }

        .alert.danger {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .alert i {
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .alert-message {
            font-size: 0.9rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .income-icon {
            background: #d4edda;
            color: #155724;
        }

        .expense-icon {
            background: #f8d7da;
            color: #721c24;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-details {
            font-size: 0.9rem;
            color: #666;
        }

        .activity-amount {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .income-amount {
            color: #27ae60;
        }

        .expense-amount {
            color: #e74c3c;
        }

        .no-alerts {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .no-alerts i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
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
            .alert {
                flex-direction: column;
                text-align: center;
            }
            .alert i {
                margin-right: 0;
                margin-bottom: 0.5rem;
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
        <a href="recurring_transactions.php"><i class="ri-repeat-line"></i> Recurring</a>
        <a href="logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
    </div>
</nav>

<main>
    <div class="section">
        <h2><i class="ri-notification-line"></i> Budget Alerts</h2>

        <?php if (empty($budget_alerts)): ?>
            <div class="no-alerts">
                <i class="ri-check-circle-line"></i>
                <div>No budget alerts at this time. You're staying within your limits!</div>
            </div>
        <?php else: ?>
            <?php foreach ($budget_alerts as $alert): ?>
                <div class="alert <?php echo $alert['severity']; ?>">
                    <i class="ri-alert-line"></i>
                    <div class="alert-content">
                        <div class="alert-title">Budget Alert: <?php echo htmlspecialchars($alert['category']); ?></div>
                        <div class="alert-message">
                            You've spent $<?php echo number_format($alert['spent'], 2); ?> of your $<?php echo number_format($alert['limit'], 2); ?> budget
                            (<?php echo $alert['percentage']; ?>%)
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><i class="ri-notification-2-line"></i> Notifications</h2>

        <?php if (empty($notifications)): ?>
            <div class="no-alerts">
                <i class="ri-notification-off-line"></i>
                <div>No new notifications.</div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="alert warning">
                    <i class="ri-information-line"></i>
                    <div class="alert-content">
                        <div class="alert-title"><?php echo ucfirst(str_replace('_', ' ', $notif['type'])); ?></div>
                        <div class="alert-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                            <br><small><?php echo date("M j, Y H:i", strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><i class="ri-time-line"></i> Recent Activity</h2>

        <?php if (empty($recent_activity)): ?>
            <div class="no-alerts">
                <i class="ri-calendar-line"></i>
                <div>No recent activity in the last 7 days.</div>
            </div>
        <?php else: ?>
            <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo $activity['type']; ?>-icon">
                        <i class="ri-<?php echo $activity['type'] === 'income' ? 'add' : 'subtract'; ?>-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
                        <div class="activity-details"><?php echo date("M j, Y", strtotime($activity['date'])); ?></div>
                    </div>
                    <div class="activity-amount <?php echo $activity['type']; ?>-amount">
                        <?php echo $activity['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($activity['amount'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><i class="ri-information-line"></i> Upcoming Reminders</h2>

        <?php if (empty($recurring_reminders)): ?>
            <div class="no-alerts">
                <i class="ri-calendar-check-line"></i>
                <div>No upcoming recurring transactions in the next 30 days.</div>
            </div>
        <?php else: ?>
            <?php foreach ($recurring_reminders as $reminder): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo $reminder['type']; ?>-icon">
                        <i class="ri-time-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?php echo htmlspecialchars($reminder['description']); ?> (<?php echo ucfirst($reminder['frequency']); ?>)</div>
                        <div class="activity-details">Next: <?php echo date("M j, Y", strtotime($reminder['next_date'])); ?></div>
                    </div>
                    <div class="activity-amount <?php echo $reminder['type']; ?>-amount">
                        <?php echo $reminder['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($reminder['amount'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>