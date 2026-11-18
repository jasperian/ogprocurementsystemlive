<?php include '../includes/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Purchase Chart Dashboard</title>
 <link rel="stylesheet" href="https://unpkg.com/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



</head>
<body class="bg-light">

<div class="container py-4">
  <h2 class="mb-4 text-center">📊 Purchase Analytics Dashboard</h2>

  <!-- 🔹 Filter Section -->
  <div class="card mb-4">
    <div class="card-body">
      <form class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Start Date</label>
          <input type="date" id="start_date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" id="end_date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Dealer</label>
          <select id="dealer" class="form-select">
            <option value="">All</option>
            <option value="TNE">TNE</option>
            <option value="TMR">TMR</option>
            <option value="TSJ">TSJ</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">User</label>
          <select id="user" class="form-select">
            <option value="">All</option>
            <option value="1">User 1</option>
            <option value="2">User 2</option>
          </select>
        </div>
      </form>
    </div>
  </div>

  <!-- 🔹 Summary Section -->
  <div class="row text-center mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6>Total PRs</h6>
          <h3 id="totalPR" class="text-primary mb-0">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6>Total Suppliers</h6>
          <h3 id="totalSuppliers" class="text-success mb-0">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6>Total Amount Purchased</h6>
          <h3 id="totalAmount" class="text-warning mb-0">₱0</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- 🔹 Chart Section -->
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Purchase Statistics</h5>
      <button id="toggleChartType" class="btn btn-sm btn-outline-primary">Show by Dealer</button>
    </div>
    <div class="card-body">
      <canvas id="purchaseChart" height="100"></canvas>
    </div>
  </div>
</div>

<!-- 🔹 Script Section -->
<script>
let chartType = 'date'; // default chart type
let purchaseChart;

async function loadChartData() {
    const params = new URLSearchParams({
        start_date: document.querySelector('#start_date')?.value || '',
        end_date: document.querySelector('#end_date')?.value || '',
        dealer: document.querySelector('#dealer')?.value || '',
        user: document.querySelector('#user')?.value || '',
        chart_type: chartType
    });

    const response = await fetch('reports/get_chart_data.php?' + params);
    const result = await response.json();

    const data = result.chartData || [];
    const summary = result.summary || {};

    const labels = data.map(row => row.label);
    const totals = data.map(row => row.total);

    // Update summary counts
    document.getElementById('totalPR').textContent = summary.total_pr || 0;
    document.getElementById('totalSuppliers').textContent = summary.total_suppliers || 0;
    document.getElementById('totalAmount').textContent = 
        '₱' + (summary.total_amount ? Number(summary.total_amount).toLocaleString() : 0);

    const ctx = document.getElementById('purchaseChart').getContext('2d');
    if (purchaseChart) purchaseChart.destroy();

    purchaseChart = new Chart(ctx, {
        type: chartType === 'dealer' ? 'bar' : 'line',
        data: {
            labels: labels,
            datasets: [{
                label: chartType === 'dealer' 
                    ? 'Total Amount per Dealer' 
                    : 'Total Amount per Date',
                data: totals,
                backgroundColor: chartType === 'dealer'
                    ? 'rgba(54, 162, 235, 0.6)'
                    : 'rgba(75, 192, 192, 0.2)',
                borderColor: chartType === 'dealer'
                    ? 'rgba(54, 162, 235, 1)'
                    : 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: chartType !== 'dealer',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                title: { 
                    display: true, 
                    text: chartType === 'dealer' 
                        ? 'Purchase Summary by Dealer' 
                        : 'Purchase Trend by Date' 
                }
            },
            scales: { 
                y: { beginAtZero: true } 
            }
        }
    });
}

// Load chart initially
loadChartData();

// 🔄 Toggle button event
document.getElementById('toggleChartType').addEventListener('click', () => {
    chartType = chartType === 'date' ? 'dealer' : 'date';
    const btn = document.getElementById('toggleChartType');
    btn.textContent = chartType === 'dealer' ? 'Show by Date' : 'Show by Dealer';
    loadChartData();
});

// 🔁 Reload when filters change
document.querySelectorAll('#start_date, #end_date, #dealer, #user')
    .forEach(el => el.addEventListener('change', loadChartData));
</script>

</body>
</html>
