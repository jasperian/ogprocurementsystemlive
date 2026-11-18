<?php
require '../init.php';
include 'header.php';
include 'navigation.php';

$dealers = $pdo->query("SELECT DISTINCT TRIM(pr_dealer) FROM purchase_requisition WHERE TRIM(COALESCE(pr_dealer, '')) <> '' ORDER BY TRIM(pr_dealer)")->fetchAll(PDO::FETCH_COLUMN);
$tmStmt = $pdo->query("SELECT user_id, full_name, dealer FROM users WHERE is_active = 1 AND UPPER(TRIM(department)) = 'PURCHASING' AND position = 'Purchasing Staff' ORDER BY full_name");
$teamMembers = $tmStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    .productivity-hero { background: linear-gradient(135deg, #0d6efd, #174ea6); color: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 8px 24px rgba(13,110,253,.18); }
    .productivity-hero h3 { color: #fff; margin: 0 0 4px; }
    .filter-label { color: rgba(255,255,255,.8); font-size: .75rem; font-weight: 600; margin-bottom: 4px; }
    .metric-card { border: 0; border-radius: 12px; box-shadow: 0 5px 18px rgba(0,0,0,.06); }
    .metric-card .metric-label { color: #6c757d; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
    .metric-card .metric-value { color: #174ea6; font-size: 1.65rem; font-weight: 800; }
    .report-card { border: 0; border-radius: 12px; box-shadow: 0 5px 18px rgba(0,0,0,.06); }
    .empty-state { padding: 42px 16px; text-align: center; color: #6c757d; }
    @media (max-width: 767px) { .productivity-actions .btn { width: 100%; } }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="productivity-hero mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div><h3>Purchasing Productivity</h3><div class="text-white-50">Review and download performance by dealer or team member (TM).</div></div>
                <button id="downloadBtn" class="btn btn-light"><i class="ti ti-download me-1"></i> Download CSV</button>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-2"><div class="filter-label">Report view</div><select id="groupBy" class="form-select"><option value="dealer">Per Dealer</option><option value="tm">Per TM</option></select></div>
                <div class="col-6 col-md-2"><div class="filter-label">From</div><input id="startDate" type="date" class="form-control"></div>
                <div class="col-6 col-md-2"><div class="filter-label">To</div><input id="endDate" type="date" class="form-control"></div>
                <div class="col-12 col-md-2"><div class="filter-label">Dealer</div><select id="dealer" class="form-select"><option value="">All dealers</option><?php foreach ($dealers as $dealer): ?><option value="<?= htmlspecialchars($dealer, ENT_QUOTES) ?>"><?= htmlspecialchars($dealer) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-3"><div class="filter-label">TM</div><select id="tm" class="form-select"><option value="">All TMs</option><?php foreach ($teamMembers as $tm): ?><option value="<?= (int)$tm['user_id'] ?>" data-dealer="<?= htmlspecialchars($tm['dealer'] ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars($tm['full_name']) ?><?= $tm['dealer'] ? ' (' . htmlspecialchars($tm['dealer']) . ')' : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-1 productivity-actions"><button id="clearBtn" class="btn btn-outline-light w-100" title="Clear filters"><i class="ti ti-filter-off"></i></button></div>
            </div>
        </div>

        <div id="alert" class="alert alert-danger d-none" role="alert"></div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="metric-label">Total PRs</div><div id="totalPrs" class="metric-value">0</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="metric-label">Completion</div><div id="completion" class="metric-value">0%</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="metric-label">Total POs</div><div id="totalPos" class="metric-value">0</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="metric-label">Purchase Amount</div><div id="totalAmount" class="metric-value">PHP 0</div></div></div></div>
        </div>

        <div class="card report-card"><div class="card-header d-flex justify-content-between align-items-center"><h5 id="tableTitle" class="mb-0">Productivity by Dealer</h5><span id="rowCount" class="badge bg-light-primary">0 rows</span></div>
            <div class="card-body"><div class="table-responsive"><table id="productivityTable" class="table table-hover align-middle w-100"><thead><tr><th id="primaryHeading">Dealer</th><th id="secondaryHeading">Scope</th><th>PRs</th><th>Completed</th><th>Rate</th><th>POs</th><th>Suppliers</th><th>Cross-completed</th><th>Amount</th><th>Avg. Days</th></tr></thead><tbody id="reportRows"></tbody></table></div><div id="emptyState" class="empty-state d-none"><i class="ti ti-file-off f-30 d-block mb-2"></i>No productivity records match the selected filters.</div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ids = ['groupBy', 'startDate', 'endDate', 'dealer', 'tm'];
    const el = Object.fromEntries(ids.map(id => [id, document.getElementById(id)]));
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
    const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
    let timer;
    let productivityTable = null;

    function query() {
        const params = new URLSearchParams();
        ids.forEach(id => { if (el[id].value) params.set(id === 'groupBy' ? 'group_by' : id.replace(/[A-Z]/g, m => '_' + m.toLowerCase()), el[id].value); });
        return params;
    }
    function render(data) {
        if (productivityTable) {
            productivityTable.destroy();
            productivityTable = null;
        }
        const totals = data.totals || {};
        document.getElementById('totalPrs').textContent = Number(totals.total_prs || 0).toLocaleString();
        document.getElementById('completion').textContent = Number(totals.completion_rate || 0).toFixed(1) + '%';
        document.getElementById('totalPos').textContent = Number(totals.total_pos || 0).toLocaleString();
        document.getElementById('totalAmount').textContent = money.format(Number(totals.total_amount || 0));
        const byTm = el.groupBy.value === 'tm';
        document.getElementById('tableTitle').textContent = 'Productivity by ' + (byTm ? 'TM' : 'Dealer');
        document.getElementById('primaryHeading').textContent = byTm ? 'TM' : 'Dealer';
        document.getElementById('secondaryHeading').textContent = byTm ? 'Dealer' : 'Scope';
        document.getElementById('rowCount').textContent = data.rows.length + (data.rows.length === 1 ? ' row' : ' rows');
        document.getElementById('reportRows').innerHTML = data.rows.map(row => `<tr><td><strong>${escapeHtml(row.group_label)}</strong></td><td>${escapeHtml(row.secondary_label)}</td><td data-order="${Number(row.total_prs)}">${Number(row.total_prs).toLocaleString()}</td><td data-order="${Number(row.completed_prs)}">${Number(row.completed_prs).toLocaleString()}</td><td data-order="${Number(row.completion_rate || 0)}"><span class="badge ${Number(row.completion_rate) >= 80 ? 'bg-light-success' : 'bg-light-warning'}">${Number(row.completion_rate || 0).toFixed(1)}%</span></td><td data-order="${Number(row.total_pos)}">${Number(row.total_pos).toLocaleString()}</td><td data-order="${Number(row.suppliers)}">${Number(row.suppliers).toLocaleString()}</td><td data-order="${Number(row.cross_completed || 0)}">${Number(row.cross_completed || 0).toLocaleString()}</td><td data-order="${Number(row.total_amount || 0)}">${money.format(Number(row.total_amount || 0))}</td><td data-order="${row.avg_lead_days == null ? -1 : Number(row.avg_lead_days)}">${row.avg_lead_days == null ? '—' : Number(row.avg_lead_days).toFixed(1)}</td></tr>`).join('');
        document.getElementById('emptyState').classList.toggle('d-none', data.rows.length > 0);
        document.getElementById('productivityTable').classList.toggle('d-none', data.rows.length === 0);
        if (data.rows.length > 0) {
            productivityTable = $('#productivityTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50],
                order: [[2, 'desc']],
                columnDefs: [{ targets: [2, 3, 4, 5, 6, 7, 8, 9], type: 'num' }],
                language: { search: 'Search productivity:' }
            });
        }
    }
    async function load() {
        const alert = document.getElementById('alert'); alert.classList.add('d-none');
        try { const response = await fetch('reports/get_purchasing_productivity.php?' + query()); const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load report.'); render(data); }
        catch (error) { alert.textContent = error.message; alert.classList.remove('d-none'); }
    }
    ids.forEach(id => el[id].addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(load, 150); }));
    document.getElementById('clearBtn').addEventListener('click', () => { ids.forEach(id => el[id].value = id === 'groupBy' ? 'dealer' : ''); load(); });
    document.getElementById('downloadBtn').addEventListener('click', () => { window.location.href = 'export/export_purchasing_productivity_csv.php?' + query(); });
    load();
});
</script>
<?php include 'footer.php'; ?>
