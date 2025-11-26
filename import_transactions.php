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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file_ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');

            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header row
                $imported_count = 0;
                $errors = [];

                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) >= 5) {
                        $type = strtolower(trim($data[1]));
                        $category = trim($data[2]);
                        $amount = floatval(str_replace(['$', ','], '', $data[3]));
                        $date = trim($data[4]);

                        // Validate data
                        if (in_array($type, ['income', 'expense']) && $amount > 0 && !empty($category) && !empty($date)) {
                            // Convert date format if needed (assuming DD/MM/YYYY)
                            if (strpos($date, '/') !== false) {
                                $date_parts = explode('/', $date);
                                if (count($date_parts) === 3) {
                                    $date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                                }
                            }

                            // Insert into appropriate table
                            if ($type === 'income') {
                                $sql = "INSERT INTO income (user_id, source, amount, date) VALUES (?, ?, ?, ?)";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("isds", $user_id, $category, $amount, $date);
                            } else {
                                $sql = "INSERT INTO expenses (user_id, category, amount, date) VALUES (?, ?, ?, ?)";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("isds", $user_id, $category, $amount, $date);
                            }

                            if ($stmt->execute()) {
                                // Also insert into transactions table
                                $trans_sql = "INSERT INTO transactions (user_id, type, category, amount, date) VALUES (?, ?, ?, ?, ?)";
                                $trans_stmt = $conn->prepare($trans_sql);
                                $trans_stmt->bind_param("issds", $user_id, $type, $category, $amount, $date);
                                $trans_stmt->execute();
                                $trans_stmt->close();

                                $imported_count++;
                            } else {
                                $errors[] = "Failed to import row: " . implode(', ', $data);
                            }
                            $stmt->close();
                        } else {
                            $errors[] = "Invalid data in row: " . implode(', ', $data);
                        }
                    }
                }

                fclose($handle);

                if ($imported_count > 0) {
                    $message = "Successfully imported $imported_count transactions.";
                    $message_type = 'success';
                }

                if (!empty($errors)) {
                    $message .= " Errors: " . implode('; ', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $message .= " (and " . (count($errors) - 5) . " more errors)";
                    }
                    $message_type = 'warning';
                }
            } else {
                $message = "Error reading CSV file.";
                $message_type = 'error';
            }
        } else {
            $message = "Please upload a valid CSV file.";
            $message_type = 'error';
        }
    } else {
        $message = "Error uploading file.";
        $message_type = 'error';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Import Transactions</title>
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
            max-width: 800px;
            margin: 2rem auto 4rem;
            padding: 0 1.25rem;
        }

        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            margin-bottom: 2.75rem;
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

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .file-input {
            padding: 1rem;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input:hover {
            border-color: #2980b9;
            background: #e3f2fd;
        }

        .file-input input[type="file"] {
            display: none;
        }

        .file-input label {
            cursor: pointer;
            font-weight: 600;
            color: #2980b9;
        }

        .upload-btn {
            padding: 1rem 2rem;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .upload-btn:hover {
            background: #219150;
        }

        .instructions {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #2980b9;
        }

        .instructions h3 {
            margin-top: 0;
            color: #1976d2;
        }

        .instructions ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .instructions li {
            margin-bottom: 0.5rem;
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
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">$<span>Finance</span>Tracker</div>
    <div class="nav-links">
        <a href="dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a>
        <a href="../dbms/transaction.php"><i class="ri-exchange-line"></i> Transactions</a>
        <a href="logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
    </div>
</nav>

<main>
    <div class="section">
        <h2>Import Transactions</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h3>CSV File Format Instructions</h3>
            <ul>
                <li>The CSV file should have a header row with columns: ID, Type, Category, Amount, Date</li>
                <li>Type should be either "income" or "expense" (case insensitive)</li>
                <li>Amount should be a number (with or without $ symbol)</li>
                <li>Date should be in DD/MM/YYYY or YYYY-MM-DD format</li>
                <li>Example: <code>1,income,Salary,$2500.00,15/05/2025</code></li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="file-input">
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                <label for="csv_file">
                    <i class="ri-file-upload-line"></i>
                    Choose CSV file to import
                </label>
            </div>
            <button type="submit" class="upload-btn">
                <i class="ri-upload-cloud-line"></i> Import Transactions
            </button>
        </form>
    </div>
</main>

<script>
document.getElementById('csv_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const label = e.target.parentElement.querySelector('label');
    if (file) {
        label.innerHTML = '<i class="ri-file-text-line"></i> ' + file.name;
    } else {
        label.innerHTML = '<i class="ri-file-upload-line"></i> Choose CSV file to import';
    }
});
</script>

</body>
</html>