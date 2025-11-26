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

    if ($action === 'add_category') {
        $name = trim($_POST['category_name']);
        $type = $_POST['category_type'];
        $parent_id = !empty($_POST['parent_category']) ? intval($_POST['parent_category']) : null;

        if (!empty($name)) {
            // Check if category already exists for this user
            $check_sql = "SELECT id FROM categories WHERE user_id = ? AND BINARY name = ? AND type = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iss", $user_id, $name, $type);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                $sql = "INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issi", $user_id, $name, $type, $parent_id);

                if ($stmt->execute()) {
                    $message = "Category added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding category.";
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $message = "Category already exists.";
                $message_type = 'warning';
            }
            $check_stmt->close();
        } else {
            $message = "Category name cannot be empty.";
            $message_type = 'error';
        }
    } elseif ($action === 'edit_category') {
        $id = intval($_POST['category_id']);
        $name = trim($_POST['edit_category_name']);
        $parent_id = !empty($_POST['edit_parent_category']) ? intval($_POST['edit_parent_category']) : null;

        if (!empty($name)) {
            $sql = "UPDATE categories SET name = ?, parent_id = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siii", $name, $parent_id, $id, $user_id);

            if ($stmt->execute()) {
                $message = "Category updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating category.";
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = "Category name cannot be empty.";
            $message_type = 'error';
        }
    } elseif ($action === 'delete_category') {
        $id = intval($_POST['category_id']);

        // Check if category is being used
        $check_usage_sql = "SELECT COALESCE(SUM(cnt), 0) as count FROM (
            SELECT COUNT(*) as cnt FROM expenses WHERE user_id = ? AND category = (SELECT name FROM categories WHERE id = ? AND user_id = ?)
            UNION ALL
            SELECT COUNT(*) as cnt FROM income WHERE user_id = ? AND source = (SELECT name FROM categories WHERE id = ? AND user_id = ?)
        ) as counts";
        $check_usage_stmt = $conn->prepare($check_usage_sql);
        $check_usage_stmt->bind_param("iiiiii", $user_id, $id, $user_id, $user_id, $id, $user_id);
        $check_usage_stmt->execute();
        $usage_result = $check_usage_stmt->get_result();
        $usage_count = $usage_result->fetch_assoc()['count'];
        $check_usage_stmt->close();

        if ($usage_count > 0) {
            $message = "Cannot delete category that is being used in transactions.";
            $message_type = 'error';
        } else {
            $sql = "DELETE FROM categories WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_id);

            if ($stmt->execute()) {
                $message = "Category deleted successfully!";
                $message_type = 'success';
            } else {
                $message = "Error deleting category.";
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Fetch categories
$income_categories = [];
$expense_categories = [];

$sql = "SELECT c1.*, c2.name as parent_name
        FROM categories c1
        LEFT JOIN categories c2 ON c1.parent_id = c2.id AND c1.user_id = c2.user_id
        WHERE c1.user_id = ?
        ORDER BY c1.type, c1.parent_id IS NULL DESC, c1.name";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Calculate usage count
    if ($row['parent_id'] === null) {
        // Parent category: count all expenses/income for this category and its subcategories
        $usage_count = 0;

        // Get all category IDs under this parent (including itself)
        $category_ids = [$row['id']];
        $sub_sql = "SELECT id FROM categories WHERE user_id = ? AND parent_id = ?";
        $sub_stmt = $conn->prepare($sub_sql);
        $sub_stmt->bind_param("ii", $user_id, $row['id']);
        $sub_stmt->execute();
        $sub_result = $sub_stmt->get_result();
        while ($sub_row = $sub_result->fetch_assoc()) {
            $category_ids[] = $sub_row['id'];
        }
        $sub_stmt->close();

        // Count expenses with these category_ids
        $placeholders = str_repeat('?,', count($category_ids) - 1) . '?';
        $exp_sql = "SELECT COUNT(*) as count FROM expenses WHERE user_id = ? AND category_id IN ($placeholders)";
        $exp_stmt = $conn->prepare($exp_sql);
        $exp_stmt->bind_param("i" . str_repeat("i", count($category_ids)), $user_id, ...$category_ids);
        $exp_stmt->execute();
        $exp_count = $exp_stmt->get_result()->fetch_assoc()['count'];
        $exp_stmt->close();

        // Count income with source = name
        $inc_sql = "SELECT COUNT(*) as count FROM income WHERE user_id = ? AND source = ?";
        $inc_stmt = $conn->prepare($inc_sql);
        $inc_stmt->bind_param("is", $user_id, $row['name']);
        $inc_stmt->execute();
        $inc_count = $inc_stmt->get_result()->fetch_assoc()['count'];
        $inc_stmt->close();

        $usage_count = $exp_count + $inc_count;
    } else {
        // Subcategory: count expenses with this category_id and income with source = name
        $exp_sql = "SELECT COUNT(*) as count FROM expenses WHERE user_id = ? AND category_id = ?";
        $exp_stmt = $conn->prepare($exp_sql);
        $exp_stmt->bind_param("ii", $user_id, $row['id']);
        $exp_stmt->execute();
        $exp_count = $exp_stmt->get_result()->fetch_assoc()['count'];
        $exp_stmt->close();

        $inc_sql = "SELECT COUNT(*) as count FROM income WHERE user_id = ? AND source = ?";
        $inc_stmt = $conn->prepare($inc_sql);
        $inc_stmt->bind_param("is", $user_id, $row['name']);
        $inc_stmt->execute();
        $inc_count = $inc_stmt->get_result()->fetch_assoc()['count'];
        $inc_stmt->close();

        $usage_count = $exp_count + $inc_count;
    }

    $row['usage_count'] = $usage_count;

    if ($row['type'] === 'income') {
        $income_categories[] = $row;
    } else {
        $expense_categories[] = $row;
    }
}
$stmt->close();

$conn->close();

// Helper function to build category tree
function buildCategoryTree($categories, $parent_id = null, $level = 0) {
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parent_id) {
            $category['level'] = $level;
            $tree[] = $category;
            $children = buildCategoryTree($categories, $category['id'], $level + 1);
            $tree = array_merge($tree, $children);
        }
    }
    return $tree;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Categories</title>
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
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
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

        .category-tree {
            margin-top: 1.5rem;
        }

        .category-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e0e3e6;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
        }

        .category-info {
            flex: 1;
        }

        .category-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .category-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .category-actions {
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

        .edit-btn { background: #2980b9; color: white; }
        .delete-btn { background: #e74c3c; color: white; }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .subcategory {
            margin-left: 2rem;
            border-left: 2px solid #ddd;
            padding-left: 1rem;
            background: #fff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
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
            .category-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .category-actions {
                align-self: flex-end;
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
        <a href="recurring_transactions.php"><i class="ri-repeat-line"></i> Recurring</a>
        <a href="notifications.php"><i class="ri-notification-line"></i> Alerts</a>
        <a href="logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
    </div>
</nav>

<main>
    <div class="section">
        <h2><i class="ri-folder-add-line"></i> Add New Category</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="category-form">
            <input type="hidden" name="action" value="add_category" />

            <div class="form-grid">
                <div class="form-group">
                    <label for="category_name">Category Name</label>
                    <input type="text" name="category_name" id="category_name" required placeholder="e.g., Food, Transportation" />
                </div>

                <div class="form-group">
                    <label for="category_type">Type</label>
                    <select name="category_type" id="category_type" required>
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="parent_category">Parent Category (Optional)</label>
                    <select name="parent_category" id="parent_category">
                        <option value="">None (Top Level)</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
            </div>

            <button type="submit" class="add-btn">
                <i class="ri-add-line"></i> Add Category
            </button>
        </form>
    </div>

    <div class="section">
        <h2><i class="ri-folder-line"></i> Expense Categories</h2>

        <div class="category-tree">
            <?php
            $expense_tree = buildCategoryTree($expense_categories);
            $current_parent = null;
            foreach ($expense_tree as $category):
                $indent_class = $category['level'] > 0 ? 'subcategory' : '';
            ?>
                <div class="category-item <?php echo $indent_class; ?>">
                    <div class="category-info">
                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        <div class="category-meta">
                            Used in <?php echo $category['usage_count']; ?> transactions
                            <?php if ($category['parent_name']): ?>
                                • Subcategory of <?php echo htmlspecialchars($category['parent_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="category-actions">
                        <button class="action-btn edit-btn" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['parent_id'] ?: 'null'; ?>)">
                            <i class="ri-edit-line"></i> Edit
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category? This action cannot be undone.')">
                            <input type="hidden" name="action" value="delete_category" />
                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>" />
                            <button type="submit" class="action-btn delete-btn">
                                <i class="ri-delete-bin-line"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($expense_categories)): ?>
            <p style="text-align: center; padding: 2rem; color: #666;">
                <i class="ri-folder-line" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                No expense categories created yet.
            </p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><i class="ri-money-dollar-circle-line"></i> Income Categories</h2>

        <div class="category-tree">
            <?php
            $income_tree = buildCategoryTree($income_categories);
            foreach ($income_tree as $category):
                $indent_class = $category['level'] > 0 ? 'subcategory' : '';
            ?>
                <div class="category-item <?php echo $indent_class; ?>">
                    <div class="category-info">
                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                        <div class="category-meta">
                            <?php if ($category['parent_name']): ?>
                                Subcategory of <?php echo htmlspecialchars($category['parent_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="category-actions">
                        <button class="action-btn edit-btn" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['parent_id'] ?: 'null'; ?>)">
                            <i class="ri-edit-line"></i> Edit
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category? This action cannot be undone.')">
                            <input type="hidden" name="action" value="delete_category" />
                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>" />
                            <button type="submit" class="action-btn delete-btn">
                                <i class="ri-delete-bin-line"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($income_categories)): ?>
            <p style="text-align: center; padding: 2rem; color: #666;">
                <i class="ri-money-dollar-circle-line" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                No income categories created yet.
            </p>
        <?php endif; ?>
    </div>
</main>

<!-- Edit Category Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Category</h2>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit_category" />
            <input type="hidden" name="category_id" id="edit_category_id" />

            <div class="form-group">
                <label for="edit_category_name">Category Name</label>
                <input type="text" name="edit_category_name" id="edit_category_name" required />
            </div>

            <div class="form-group">
                <label for="edit_parent_category">Parent Category (Optional)</label>
                <select name="edit_parent_category" id="edit_parent_category">
                    <option value="">None (Top Level)</option>
                    <!-- Options will be populated by JavaScript -->
                </select>
            </div>

            <button type="submit" class="add-btn">Update Category</button>
        </form>
    </div>
</div>

<script>
const expenseCategories = <?php echo json_encode($expense_categories); ?>;
const incomeCategories = <?php echo json_encode($income_categories); ?>;

function populateParentSelect(selectId, currentCategoryId = null) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">None (Top Level)</option>';

    const allCategories = [...expenseCategories, ...incomeCategories];

    allCategories.forEach(category => {
        if (category.id != currentCategoryId && category.parent_id === null) {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        }
    });
}

document.getElementById('category_type').addEventListener('change', function() {
    populateParentSelect('parent_category');
});

document.addEventListener('DOMContentLoaded', function() {
    populateParentSelect('parent_category');
});

function editCategory(id, name, parentId) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    populateParentSelect('edit_parent_category', id);
    if (parentId) {
        document.getElementById('edit_parent_category').value = parentId;
    }
    document.getElementById('editModal').style.display = 'block';
}

document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('editModal').style.display = 'none';
});

window.addEventListener('click', function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

</body>
</html>