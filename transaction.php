<?php
// Move PHP session and database code to the top, before any HTML output
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../dbms_backend/login.php");
    exit();
}

include '../dbms_backend/db.php';
$user_id = $_SESSION['user_id'];

$sql = "SELECT id, type, category, amount, date FROM transactions WHERE user_id = ? ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
$categories = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
        if (!in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Transaction History</title>
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

    .transaction-type {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .income-type {
      background: #d4edda;
      color: #155724;
    }

    .expense-type {
      background: #f8d7da;
      color: #721c24;
    }

    .filters {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 250px;
    }

    .search-box input {
      width: 100%;
      padding: 0.75rem 2.5rem 0.75rem 1rem;
      border: 2px solid #e0e3e6;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .search-box input:focus {
      outline: none;
      border-color: #2980b9;
    }

    .search-box i {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
      font-size: 1.1rem;
    }

    .filter-controls {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .filter-controls select,
    .filter-controls input[type="date"] {
      padding: 0.75rem;
      border: 2px solid #e0e3e6;
      border-radius: 8px;
      font-size: 1rem;
      min-width: 120px;
      transition: border-color 0.3s ease;
    }

    .filter-controls select:focus,
    .filter-controls input[type="date"]:focus {
      outline: none;
      border-color: #2980b9;
    }

    .clear-btn {
      padding: 0.75rem 1.5rem;
      background: #95a5a6;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .clear-btn:hover {
      background: #7f8c8d;
    }

    .export-controls {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      justify-content: flex-end;
    }

    .export-btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      font-weight: 600;
    }

    .csv-btn {
      background: #27ae60;
      color: white;
    }

    .csv-btn:hover {
      background: #219150;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }

    .pdf-btn {
      background: #e74c3c;
      color: white;
    }

    .pdf-btn:hover {
      background: #c0392b;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
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
      .filters {
        flex-direction: column;
        align-items: stretch;
      }
      .filter-controls {
        justify-content: center;
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
    <a href="../dbms_backend/manage_categories.php"><i class="ri-folder-line"></i> Categories</a>
    <a href="../dbms_backend/recurring_transactions.php"><i class="ri-repeat-line"></i> Recurring</a>
    <a href="../dbms_backend/notifications.php"><i class="ri-notification-line"></i> Alerts</a>
    <a href="../dbms_backend/import_transactions.php"><i class="ri-file-upload-line"></i> Import</a>
    <a href="../dbms_backend/logout.php"><i class="ri-logout-box-r-line"></i> Logout</a>
  </div>
</nav>

<main>
  <div class="section">
    <h2>Transaction History</h2>

    <!-- Export Controls -->
    <div class="export-controls">
      <button id="exportCSV" class="export-btn csv-btn">
        <i class="ri-file-excel-line"></i> Export CSV
      </button>
      <button id="exportPDF" class="export-btn pdf-btn">
        <i class="ri-file-pdf-line"></i> Export PDF
      </button>
    </div>

    <!-- Search and Filter Controls -->
    <div class="filters">
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search transactions..." />
        <i class="ri-search-line"></i>
      </div>
      <div class="filter-controls">
        <select id="typeFilter">
          <option value="">All Types</option>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
        <select id="categoryFilter">
          <option value="">All Categories</option>
          <!-- Categories will be populated dynamically -->
        </select>
        <input type="date" id="startDate" placeholder="Start Date" />
        <input type="date" id="endDate" placeholder="End Date" />
        <button id="clearFilters" class="clear-btn">Clear Filters</button>
      </div>
    </div>

    <table id="transactionTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Category</th>
          <th>Amount</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="transactionBody">
        <!-- Transactions will be populated by JavaScript -->
      </tbody>
    </table>
  </div>
</main>

<script>
// Pass PHP data to JavaScript
const transactionsData = <?php echo json_encode($transactions); ?>;
const categoriesData = <?php echo json_encode($categories); ?>;

// Function to render transactions
function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionBody');
    tbody.innerHTML = '';

    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">No transactions found.</td></tr>';
        return;
    }

    transactions.forEach(row => {
        const typeClass = row.type === 'income' ? 'income-type' : 'expense-type';
        const tr = document.createElement('tr');
        tr.setAttribute('data-type', row.type);
        tr.setAttribute('data-category', row.category);
        tr.setAttribute('data-date', row.date);
        tr.setAttribute('data-amount', row.amount);

        tr.innerHTML = `
            <td>${row.id}</td>
            <td><span class='transaction-type ${typeClass}'>${row.type.charAt(0).toUpperCase() + row.type.slice(1)}</span></td>
            <td>${row.category}</td>
            <td>$${parseFloat(row.amount).toFixed(2)}</td>
            <td>${new Date(row.date).toLocaleDateString('en-GB')}</td>
        `;

        tbody.appendChild(tr);
    });
}

// Initialize table with data
document.addEventListener('DOMContentLoaded', function() {
    renderTransactions(transactionsData);

    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const clearFilters = document.getElementById('clearFilters');
    const table = document.getElementById('transactionTable');
    const rows = table.querySelectorAll('tbody tr');

    // Populate categories
    const categories = new Set();
    transactionsData.forEach(transaction => {
        if (transaction.category) categories.add(transaction.category);
    });
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedType = typeFilter.value;
        const selectedCategory = categoryFilter.value;
        const start = startDate.value ? new Date(startDate.value) : null;
        const end = endDate.value ? new Date(endDate.value) : null;

        const filteredTransactions = transactionsData.filter(transaction => {
            const matchesSearch = !searchTerm ||
                transaction.id.toString().includes(searchTerm) ||
                transaction.type.toLowerCase().includes(searchTerm) ||
                transaction.category.toLowerCase().includes(searchTerm) ||
                transaction.amount.toString().includes(searchTerm);

            const matchesType = !selectedType || transaction.type === selectedType;
            const matchesCategory = !selectedCategory || transaction.category === selectedCategory;
            const transactionDate = new Date(transaction.date);
            const matchesDate = (!start || transactionDate >= start) && (!end || transactionDate <= end);

            return matchesSearch && matchesType && matchesCategory && matchesDate;
        });

        renderTransactions(filteredTransactions);
    }

    // Event listeners
    searchInput.addEventListener('input', filterTable);
    typeFilter.addEventListener('change', filterTable);
    categoryFilter.addEventListener('change', filterTable);
    startDate.addEventListener('change', filterTable);
    endDate.addEventListener('change', filterTable);

    clearFilters.addEventListener('click', function() {
        searchInput.value = '';
        typeFilter.value = '';
        categoryFilter.value = '';
        startDate.value = '';
        endDate.value = '';
        renderTransactions(transactionsData);
    });

    // Export functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        const visibleRows = Array.from(document.querySelectorAll('#transactionBody tr'));
        if (visibleRows.length === 0) {
            alert('No data to export');
            return;
        }

        let csv = 'ID,Type,Category,Amount,Date\n';
        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const rowData = Array.from(cells).map(cell => {
                const text = cell.textContent.trim();
                return text.replace('$', '').replace(/,/g, '');
            });
            csv += rowData.join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'transactions_' + new Date().toISOString().split('T')[0] + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    });

    document.getElementById('exportPDF').addEventListener('click', function() {
        const visibleRows = Array.from(document.querySelectorAll('#transactionBody tr'));
        if (visibleRows.length === 0) {
            alert('No data to export');
            return;
        }

        let html = `
            <html>
            <head>
                <style>
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                    th { background-color: #f5f5f5; }
                    .income-type { background-color: #d4edda; color: #155724; }
                    .expense-type { background-color: #f8d7da; color: #721c24; }
                </style>
            </head>
            <body>
                <h2>Transaction History</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>`;

        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            html += '<tr>';
            cells.forEach(cell => {
                const className = cell.querySelector('.transaction-type') ? cell.querySelector('.transaction-type').className : '';
                html += `<td${className ? ` class="${className}"` : ''}>${cell.textContent.trim()}</td>`;
            });
            html += '</tr>';
        });

        html += `
                    </tbody>
                </table>
            </body>
            </html>`;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.print();
    });
});
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchInput');
  const typeFilter = document.getElementById('typeFilter');
  const categoryFilter = document.getElementById('categoryFilter');
  const startDate = document.getElementById('startDate');
  const endDate = document.getElementById('endDate');
  const clearFilters = document.getElementById('clearFilters');
  const table = document.getElementById('transactionTable');
  const rows = table.querySelectorAll('tbody tr');

  // Populate categories
  const categories = new Set();
  rows.forEach(row => {
      const category = row.getAttribute('data-category');
      if (category) categories.add(category);
  });
  categories.forEach(category => {
      const option = document.createElement('option');
      option.value = category;
      option.textContent = category;
      categoryFilter.appendChild(option);
  });

  function filterTable() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedType = typeFilter.value;
      const selectedCategory = categoryFilter.value;
      const start = startDate.value ? new Date(startDate.value) : null;
      const end = endDate.value ? new Date(endDate.value) : null;

      rows.forEach(row => {
          const type = row.getAttribute('data-type');
          const category = row.getAttribute('data-category');
          const date = new Date(row.getAttribute('data-date'));
          const amount = parseFloat(row.getAttribute('data-amount'));
          const text = row.textContent.toLowerCase();

          const matchesSearch = text.includes(searchTerm);
          const matchesType = !selectedType || type === selectedType;
          const matchesCategory = !selectedCategory || category === selectedCategory;
          const matchesDate = (!start || date >= start) && (!end || date <= end);

          if (matchesSearch && matchesType && matchesCategory && matchesDate) {
              row.style.display = '';
          } else {
              row.style.display = 'none';
          }
      });
  }

  // Event listeners
  searchInput.addEventListener('input', filterTable);
  typeFilter.addEventListener('change', filterTable);
  categoryFilter.addEventListener('change', filterTable);
  startDate.addEventListener('change', filterTable);
  endDate.addEventListener('change', filterTable);

  clearFilters.addEventListener('click', function() {
      searchInput.value = '';
      typeFilter.value = '';
      categoryFilter.value = '';
      startDate.value = '';
      endDate.value = '';
      rows.forEach(row => row.style.display = '');
  });

  // Export functionality
  document.getElementById('exportCSV').addEventListener('click', function() {
      exportToCSV();
  });

  document.getElementById('exportPDF').addEventListener('click', function() {
      exportToPDF();
  });

  function exportToCSV() {
      const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
      if (visibleRows.length === 0) {
          alert('No data to export');
          return;
      }

      let csv = 'ID,Type,Category,Amount,Date\n';
      visibleRows.forEach(row => {
          const cells = row.querySelectorAll('td');
          const rowData = Array.from(cells).map(cell => {
              const text = cell.textContent.trim();
              // Remove $ and format numbers properly
              return text.replace('$', '').replace(/,/g, '');
          });
          csv += rowData.join(',') + '\n';
      });

      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'transactions_' + new Date().toISOString().split('T')[0] + '.csv';
      a.click();
      window.URL.revokeObjectURL(url);
  }

  function exportToPDF() {
      const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
      if (visibleRows.length === 0) {
          alert('No data to export');
          return;
      }

      // Create a simple HTML table for PDF generation
      let html = `
          <html>
          <head>
              <style>
                  table { width: 100%; border-collapse: collapse; }
                  th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                  th { background-color: #f5f5f5; }
                  .income-type { background-color: #d4edda; color: #155724; }
                  .expense-type { background-color: #f8d7da; color: #721c24; }
              </style>
          </head>
          <body>
              <h2>Transaction History</h2>
              <table>
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Type</th>
                          <th>Category</th>
                          <th>Amount</th>
                          <th>Date</th>
                      </tr>
                  </thead>
                  <tbody>`;

      visibleRows.forEach(row => {
          const cells = row.querySelectorAll('td');
          html += '<tr>';
          cells.forEach(cell => {
              const className = cell.querySelector('.transaction-type') ? cell.querySelector('.transaction-type').className : '';
              html += `<td${className ? ` class="${className}"` : ''}>${cell.textContent.trim()}</td>`;
          });
          html += '</tr>';
      });

      html += `
                  </tbody>
              </table>
          </body>
          </html>`;

      // Open in new window for printing/saving as PDF
      const printWindow = window.open('', '_blank');
      printWindow.document.write(html);
      printWindow.document.close();
      printWindow.print();
  }
});
</script>

</body>
</html>