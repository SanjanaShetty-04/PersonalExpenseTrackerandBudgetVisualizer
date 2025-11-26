<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

$sql = "SELECT b.id, COALESCE(c.name, b.category) as category, b.limit_amount, b.month_year FROM budget b LEFT JOIN categories c ON b.category_id = c.id WHERE b.user_id = ? ORDER BY b.month_year DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$budget_rows = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $budget_rows[] = $row;
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Budget Management</title>
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
      color: #2980b9;
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
      color: #2980b9;
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
      margin-bottom: 2.75rem;
      padding: 1.5rem 2rem;
      transition: box-shadow 0.3s ease;
    }

    .section:hover {
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.10);
    }

    h2 {
      font-size: 1.8rem;
      margin-bottom: 1.25rem;
      font-weight: 700;
      color: #222;
      letter-spacing: 0.02em;
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
      user-select: none;
    }

    tbody tr:hover {
      background: #f1f7fb;
    }

    td.actions {
      width: 120px;
      text-align: center;
      display: flex;
      justify-content: center;
    }

    td.actions button {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.2rem;
      margin-right: 0.5rem;
      color: #2980b9;
      transition: color 0.25s ease;
    }

    td.actions button:last-child {
      margin-right: 0;
    }

    td.actions button:hover {
      color: #e74c3c;
    }

    .add-btn {
      margin-top: 1.25rem;
      padding: 0.9rem 1rem;
      width: 100%;
      background: #2980b9;
      border: none;
      border-radius: 0 0 12px 12px;
      color: white;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
      letter-spacing: 0.02em;
    }

    .add-btn:hover {
      background: #21618c;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
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
      table, th, td {
        font-size: 0.9rem;
      }
      .add-btn {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

<nav>
  <div class="logo">$<span>Fin</span>Wise</div>
  <div class="nav-links">
    <a href="../dbms_backend/dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
    <a href="../dbms_backend/profile.php"><i class="ri-user-line"></i> Profile</a>
    <a href="../dbms_backend/logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
  </div>
</nav>

<main>
  <div class="section">
    <h2>Budget Management</h2>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Category</th>
          <th>Limit Amount</th>
          <th>Month/Year</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if (!empty($budget_rows)) {
            foreach ($budget_rows as $row) {
                echo "<tr>
                        <td>" . htmlspecialchars($row['id']) . "</td>
                        <td>" . htmlspecialchars($row['category']) . "</td>
                        <td>$" . htmlspecialchars(number_format($row['limit_amount'], 2)) . "</td>
                        <td>" . htmlspecialchars($row['month_year']) . "</td>
                        <td class='actions'>
                          <button title='Edit' onclick=\"location.href='../dbms_backend/edit_budget.php?id={$row['id']}'\"><i class='ri-edit-line'></i></button>
                          <button title='Delete' onclick=\"if(confirm('Delete this budget entry?')) location.href='../dbms_backend/delete_budget.php?id={$row['id']}'\"><i class='ri-delete-bin-6-line'></i></button>
                        </td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='5' style='text-align: center; padding: 2rem;'>No budget entries found.</td></tr>";
        }
        ?>
      </tbody>
    </table>
    <button class="add-btn" onclick="location.href='../dbms_backend/add_budget.php'"><i class="ri-add-line"></i> Add Budget</button>
  </div>
</main>

</body>
</html>