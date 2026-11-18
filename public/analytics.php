<?php
// analytics.php
// Make sure path to db include is correct for your project
require '../init.php';
// include '../includes/db.php';

include 'header.php';
include 'navigation.php';
// Fetch dealers for dropdown (default "All Dealers")
// If your dealer column is in a different table, change this query accordingly.
$dealerStmt = $pdo->prepare("SELECT DISTINCT dealer FROM users WHERE dealer IS NOT NULL AND dealer <> '' AND dealer <> 'OG' AND dealer <> 'ADMIN' ORDER BY dealer");
$dealerStmt->execute();
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
?>


<!-- Bootstrap (CDNJS) -->
<!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"> -->

<!-- DataTables CSS -->
<!-- <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet"> -->

<!-- Chart.js (UNPKG) -->
<script src="https://unpkg.com/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://unpkg.com/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<!--<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>-->
<script src="https://unpkg.com/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>




<style>
    /* corporate blue/gray theme — matches your earlier dashboard */
    body {
        background: #f4f6f9;
        font-family: 'Inter', 'Segoe UI', sans-serif;
        color: #212529;
    }

    .dashboard-header {
        background: linear-gradient(90deg, #0d6efd, #007bff);
        color: #fff;
        padding: 18px 22px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(13, 110, 253, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dashboard-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-stat {
        border: none;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    }

    .card-stat h5 {
        color: #6c757d;
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 6px;
    }

    .card-stat h2 {
        color: #0d6efd;
        font-weight: 800;
        font-size: 1.8rem;
        margin: 0;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        border-left: 4px solid #0d6efd;
        padding-left: 10px;
        margin-bottom: 14px;
    }

    canvas {
        max-height: 340px;
    }

    .filter-inline {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .filter-inline label {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.95);
        margin-bottom: 0;
        display: block;
    }

    .filter-inline input,
    .filter-inline select {
        min-width: 170px;
    }

    .table-card {
        border-radius: 12px;
        padding: 12px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
        margin-top: 20px;
    }
</style>
<div class="pc-container">
    <div class="pc-content">

        <!-- Header with date filters & dealer dropdown -->
        <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 OGPS Analytics</h3>
                <small class="text-white-50">Updated: <?= date('F j, Y') ?></small>
            </div>

            <div class="filter-inline">
                <div>
                    <label for="startDate">From</label>
                    <input id="startDate" type="date" class="form-control form-control-sm" />
                </div>
                <div>
                    <label for="endDate">To</label>
                    <input id="endDate" type="date" class="form-control form-control-sm" />
                </div>
                <div>
                    <label for="dealerSelect">Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealers</option>
                        <?php foreach ($dealers as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="color: rgba(255,255,255,0.85); font-size:0.85rem;">(auto updates)</div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4" id="kpis">
            <div class="col-6 col-md-3">
                <div class="card card-stat p-3 text-center">
                    <h5>Total PRs</h5>
                    <h2 id="totalPRs">0</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-stat p-3 text-center">
                    <h5>Total POs</h5>
                    <h2 id="totalPOs">0</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-stat p-3 text-center">
                    <h5>Total Amount</h5>
                    <h2 id="totalAmount">₱0</h2>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-stat p-3 text-center">
                    <h5>Unique Suppliers</h5>
                    <h2 id="totalSuppliers">0</h2>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card p-3">
                    <h6 class="section-title">📈 Purchases Amount (monthly)</h6>
                    <canvas id="purchaseTrend"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-3">
                    <h6 class="section-title">🏆 Top Suppliers (by amount)</h6>
                    <canvas id="topSuppliers"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card p-3">
                    <h6 class="section-title">🏢 Purchases by Dealer (Amount)</h6>
                    <canvas id="byDealer"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-3">
                    <h6 class="section-title">🧾 Purchases by Purchasing Officer (Amount)</h6>
                    <canvas id="byApprover"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h6 class="section-title">🏢 Purchases by Dealer (PR)</h6>
                        <canvas id="prByDealerUnique"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h6 class="section-title">🧾 Purchases by Purchasing Officer (PR)</h6>
                        <canvas id="prByApproverUnique"></canvas>
                    </div>
                </div>
            </div>
        </div>



        <!-- DataTable -->
        <div class="table-card">
            <h6 class="section-title">📋 Detailed Purchase Records</h6>
            <div class="table-responsive">
                <table id="detailsTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>PR Number</th>
                            <th>PR Date</th>
                            <th>PO Number</th>
                            <th>PO Date</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Approver</th>
                            <th>Dealer</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // API endpoint (adjust path if your PHP file is elsewhere)
    const API_URL = 'reports/get_analytics.php';

    // Chart instances
    let charts = {
        purchaseTrend: null,
        topSuppliers: null,
        byDealer: null,
        byApprover: null
    };


    // DataTable instance
    let detailsTable = null;

    // debounce helper (ms)
    function debounce(fn, wait) {
        let t;
        return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // Format currency (PHP peso)
    function formatPeso(n) {
        if (isNaN(n) || n === null) return '₱0.00';
        return '₱' + Number(n).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Fetch analytics data (cards + charts + table)
    async function fetchAnalytics(startDate = '', endDate = '', dealer = '') {
        const url = new URL(API_URL, window.location.href);
        if (startDate) url.searchParams.append('start_date', startDate);
        if (endDate) url.searchParams.append('end_date', endDate);
        if (dealer) url.searchParams.append('dealer', dealer);

        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('Network response was not ok');
            const payload = await res.json();
            return payload;
        } catch (err) {
            console.error('Fetch error:', err);
            return {
                data: []
            };
        }
    }

    // Dealer color mapping
    const dealerColors = {
        'TNE': '#0d6efd', // blue
        'TMR': '#28a745', // green
        'TSJ': '#ffc107', // yellow
        'TBK': '#dc3545', // red
        'TOT': '#6c757d', // gray
        'Unknown': '#adb5bd' // default gray for others
    };


    // Build charts and cards with fetched rows
    function renderDashboard(rows) {
        // KPIs
        const totalPRs = new Set(rows.map(r => r.pr_number)).size;
        const totalPOs = new Set(rows.map(r => r.po_number)).size;
        const totalAmount = rows.reduce((s, r) => s + parseFloat(r.amount_purchased || 0), 0);
        const totalSuppliers = new Set(rows.map(r => r.supplier_name)).size;

        document.getElementById('totalPRs').textContent = totalPRs;
        document.getElementById('totalPOs').textContent = totalPOs;
        document.getElementById('totalAmount').textContent = formatPeso(totalAmount);
        document.getElementById('totalSuppliers').textContent = totalSuppliers;

        // Destroy existing charts if any
        Object.values(charts).forEach(c => {
            if (c) {
                c.destroy();
            }
        });

        // Purchases Over Time (group by month-year)
        const monthTotals = {};
        rows.forEach(r => {
            const date = r.pr_date ? new Date(r.pr_date) : null;
            const m = date ? date.toLocaleString('default', {
                month: 'short',
                year: 'numeric'
            }) : 'Unknown';
            monthTotals[m] = (monthTotals[m] || 0) + parseFloat(r.amount_purchased || 0);
        });
        // sort by parsed month-year
        const months = Object.keys(monthTotals).sort((a, b) => {
            const parseDate = s => {
                if (s === 'Unknown') return 0;
                const [mon, yr] = s.split(' ');
                return new Date(`${mon} 1, ${yr}`).getTime();
            };
            return parseDate(a) - parseDate(b);
        });
        const monthValues = months.map(m => monthTotals[m]);

        charts.purchaseTrend = new Chart(document.getElementById('purchaseTrend'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Total Purchase (₱)',
                    data: monthValues,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: '#000', // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                maintainAspectRatio: false
            },
            plugins: [ChartDataLabels] 
        });

        // Top Suppliers
        // Top Suppliers
        const supplierTotals = {};
        rows.forEach(r => {
            supplierTotals[r.supplier_name] = (supplierTotals[r.supplier_name] || 0) + parseFloat(r.amount_purchased || 0);
        });
        const topSuppliersArr = Object.entries(supplierTotals).sort((a, b) => b[1] - a[1]).slice(0, 10);

        charts.topSuppliers = new Chart(document.getElementById('topSuppliers'), {
            type: 'bar',
            data: {
                labels: topSuppliersArr.map(t => t[0]),
                datasets: [{
                    data: topSuppliersArr.map(t => t[1]),
                    backgroundColor: '#D6b4fc',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                       color: '#000',  // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                },
                maintainAspectRatio: false
            },
            plugins: [ChartDataLabels] // ✅ activate the DataLabels plugin
        });



        // Purchases by Dealer (pie)
        const dealerTotals = {};
        rows.forEach(r => {
            const dealer = r.dealer || 'Unknown';
            dealerTotals[dealer] = (dealerTotals[dealer] || 0) + parseFloat(r.amount_purchased || 0);
        });
        const dealerLabels = Object.keys(dealerTotals);
        const dealerValues = dealerLabels.map(k => dealerTotals[k]);
        charts.byDealer = new Chart(document.getElementById('byDealer'), {
            type: 'pie',
            data: {
                labels: dealerLabels,
                datasets: [{
                    data: dealerValues,
                    backgroundColor: dealerLabels.map(d => dealerColors[d] || dealerColors['Unknown'])
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: true
                    },
                    datalabels: {
                        color: '#000',  // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                maintainAspectRatio: false
            },
             plugins: [ChartDataLabels]
        });

        // Purchases by Approver
        const approverTotals = {};
        rows.forEach(r => {
            const approver = r.full_name || 'Unknown';
            approverTotals[approver] = (approverTotals[approver] || 0) + parseFloat(r.amount_purchased || 0);
        });
        const approverLabels = Object.keys(approverTotals);
        const approverValues = approverLabels.map(k => approverTotals[k]);
        charts.byApprover = new Chart(document.getElementById('byApprover'), {
            type: 'bar',
            data: {
                labels: approverLabels,
                datasets: [{
                    data: approverValues,
                    backgroundColor: '#D6b4fc',
                    borderRadius: 6
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: '#000',  // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                maintainAspectRatio: false
            },
            plugins: [ChartDataLabels]
        });

        // ✅ Unique PR Count by Dealer
        const prByDealerUnique = {};
        rows.forEach(r => {
            const dealer = r.dealer || 'Unknown';
            const prNumber = r.pr_number || r.PRNumber || r.PR || ''; // adjust based on your actual field
            if (!prByDealerUnique[dealer]) prByDealerUnique[dealer] = new Set();
            prByDealerUnique[dealer].add(prNumber);
        });

        const dealerLabelsUnique = Object.keys(prByDealerUnique);
        const dealerCountsUnique = dealerLabelsUnique.map(k => prByDealerUnique[k].size);

        charts.prByDealerUnique = new Chart(document.getElementById('prByDealerUnique'), {
            type: 'bar',
            data: {
                labels: dealerLabelsUnique,
                datasets: [{
                    data: dealerCountsUnique,
                    backgroundColor: dealerLabels.map(d => dealerColors[d] || dealerColors['Unknown']),
                    borderRadius: 6
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: '#000',  // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,

                    }
                },
                maintainAspectRatio: false
            },
            plugins: [ChartDataLabels]
        });

        // ✅ Unique PR Count by Approver
        const prByApproverUnique = {};
        rows.forEach(r => {
            const approver = r.full_name || 'Unknown';
            const prNumber = r.pr_number || r.PRNumber || r.PR || '';
            if (!prByApproverUnique[approver]) prByApproverUnique[approver] = new Set();
            prByApproverUnique[approver].add(prNumber);
        });

        const approverLabelsUnique = Object.keys(prByApproverUnique);
        const approverCountsUnique = approverLabelsUnique.map(k => prByApproverUnique[k].size);

        charts.prByApproverUnique = new Chart(document.getElementById('prByApproverUnique'), {
            type: 'bar',
            data: {
                labels: approverLabelsUnique,
                datasets: [{
                    data: approverCountsUnique,
                    backgroundColor: '#D6b4fc',
                    borderRadius: 6
                }]
            },
            options: {
               plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                       color: '#000',  // white text inside bars
                        anchor: 'center', // center vertically
                        align: 'center', // center horizontally
                        clamp: true,
                        formatter: value => value.toLocaleString(), // add commas
                        font: {
                            size: 12
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,

                    }
                },
                maintainAspectRatio: false
            },
            plugins: [ChartDataLabels]
        });


        // Populate DataTable
        if (!detailsTable) {
            // initialize
            detailsTable = $('#detailsTable').DataTable({
                data: rows,
                columns: [{
                        data: 'pr_number'
                    },
                    {
                        data: 'pr_date',
                        render: d => d ? new Date(d).toLocaleDateString() : ''
                    },
                    {
                        data: 'po_number'
                    },
                    {
                        data: 'po_date',
                        render: d => d ? new Date(d).toLocaleDateString() : ''
                    },
                    {
                        data: 'supplier_name'
                    },

                    {
                        data: 'amount_purchased',
                        render: d => formatPeso(d)
                    },
                    {
                        data: 'full_name'
                    },
                    {
                        data: 'dealer'
                    }
                ],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [
                    [1, 'desc']
                ],
                responsive: true
            });
        } else {
            // reload with new data
            detailsTable.clear();
            detailsTable.rows.add(rows);
            detailsTable.draw();
        }
    }

    // Main reload function (debounced)
    const reload = debounce(async () => {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        const dealer = document.getElementById('dealerSelect').value;
        const payload = await fetchAnalytics(start, end, dealer);
        const rows = payload.data || [];
        renderDashboard(rows);
    }, 450); // 450ms debounce

    // Wire auto-update on change
    document.addEventListener('DOMContentLoaded', () => {
        // initial load (no filter)
        reload();

        // auto update when any filter changes
        document.getElementById('startDate').addEventListener('change', reload);
        document.getElementById('endDate').addEventListener('change', reload);
        document.getElementById('dealerSelect').addEventListener('change', reload);

        // allow clearing via double-click: clears both dates
        document.getElementById('startDate').addEventListener('dblclick', () => {
            document.getElementById('startDate').value = '';
            reload();
        });
        document.getElementById('endDate').addEventListener('dblclick', () => {
            document.getElementById('endDate').value = '';
            reload();
        });
    });
</script>
<?php
include 'footer.php';
?>