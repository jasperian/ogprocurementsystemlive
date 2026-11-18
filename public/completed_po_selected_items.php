<?php
require '../init.php';

$selectedSupplierId = max(0, (int)($_GET['supplier_id'] ?? 0));
$selectedSupplierName = '';
if ($selectedSupplierId > 0) {
    $supplierStmt = $pdo->prepare('SELECT supplier_name FROM suppliers WHERE supplier_id = ?');
    $supplierStmt->execute([$selectedSupplierId]);
    $selectedSupplierName = $supplierStmt->fetchColumn() ?: 'Supplier unavailable';
}

include 'header.php';
include 'navigation.php';
?>
<style>
    @media (max-width: 768px) {
        table.dataTable td {
            white-space: normal !important;
        }
    }

    .dataTables_wrapper .dataTables_length {
        float: right;
        margin-right: 10px;
    }

    .dataTables_wrapper .dataTables_filter {
        float: left;
        margin-left: 10px;
    }

    .dataTables_info {
        margin-left: 10px;
    }

    .table-filter {
        max-width: 240px;
    }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="page-header-title">
                                <h2 class="mb-0">Completed PO and Selected Items</h2>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="accredited_suppliers.php" class="btn btn-outline-secondary">Accredited Suppliers</a>
                                <select id="dealerFilter" class="form-select table-filter">
                                    <option value="">All Dealers</option>
                                </select>
                                <select id="supplierFilter" class="form-select table-filter">
                                    <option value="">All Suppliers</option>
                                    <?php if ($selectedSupplierId > 0): ?>
                                        <option value="<?= $selectedSupplierId ?>" selected><?= htmlspecialchars($selectedSupplierName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">
                        <table class="table-stripped display responsive nowrap table-striped p-1" id="completedPoItemsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Dealer</th>
                                    <th>PO Number</th>
                                    <th>PR Number</th>
                                    <th>Supplier</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>PO Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function truncateText(value, limit = 20) {
        const text = String(value || '');

        return text.length > limit ? `${text.substring(0, limit)}...` : text;
    }

    function renderLimitedText(value) {
        const text = String(value || 'N/A');
        const escapedText = escapeHtml(text);
        const displayText = escapeHtml(truncateText(text));

        return `<span title="${escapedText}">${displayText}</span>`;
    }

    function formatDate(value) {
        if (!value || value === '0000-00-00') {
            return 'N/A';
        }

        return new Date(value).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: '2-digit'
        });
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    const completedPoItemsTable = $('#completedPoItemsTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        language: {
            emptyTable: 'No completed PO items found for the selected filters.',
            zeroRecords: 'No completed PO items match your search or filters.'
        },
        ajax: {
            url: 'data/get_completed_po_selected_items.php',
            data: function(d) {
                d.dealer = $('#dealerFilter').val();
                d.supplier_id = $('#supplierFilter').val();
            }
        },
        order: [
            [9, 'desc']
        ],
        columns: [
            {
                data: 'po_dealer',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'po_number',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'pr_number',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'supplier_name',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'item_name',
                render: function(data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }

                    const description = row.description ? `<div class="text-muted small">${renderLimitedText(row.description)}</div>` : '';

                    return `${renderLimitedText(data)}${description}`;
                }
            },
            {
                data: 'qty',
                className: 'text-end',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'unit_measure',
                render: function(data, type) {
                    return type === 'display' ? renderLimitedText(data) : data;
                }
            },
            {
                data: 'effective_unit_price',
                className: 'text-end',
                render: function(data, type) {
                    const formatted = formatNumber(data);

                    return type === 'display' ? renderLimitedText(formatted) : data;
                }
            },
            {
                data: 'line_total',
                className: 'text-end',
                render: function(data, type) {
                    const formatted = formatNumber(data);

                    return type === 'display' ? renderLimitedText(formatted) : data;
                }
            },
            {
                data: 'po_date',
                render: function(data, type) {
                    const formatted = formatDate(data);

                    return type === 'display' ? renderLimitedText(formatted) : data;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    const prNumber = encodeURIComponent(row.pr_number);
                    const poFile = row.po_file ? escapeHtml(row.po_file) : '';
                    const fileLink = poFile ? `
                        <li class="list-inline-item" title="View PO File">
                            <a href="${poFile}" target="_blank" class="avtar avtar-xs btn-link-warning btn-pc-default">
                                <i class="ti ti-file-text f-18"></i>
                            </a>
                        </li>
                    ` : '';

                    return `
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item" title="View PR">
                                <a href="view_purchase_requisition?pr_number=${prNumber}" class="avtar avtar-xs btn-link-success btn-pc-default">
                                    <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                            <li class="list-inline-item" title="View Canvass">
                                <a href="canvass_view?pr_number=${prNumber}" class="avtar avtar-xs btn-link-primary btn-pc-default">
                                    <i class="ti ti-clipboard-list f-18"></i>
                                </a>
                            </li>
                            ${fileLink}
                        </ul>
                    `;
                }
            }
        ]
    });

    $.getJSON('data/get_completed_po_dealers.php', function(dealers) {
        dealers.forEach(function(dealer) {
            $('#dealerFilter').append(`<option value="${escapeHtml(dealer)}">${escapeHtml(dealer)}</option>`);
        });
    });

    $.getJSON('data/get_completed_po_item_suppliers.php', function(suppliers) {
        suppliers.forEach(function(supplier) {
            const select = document.getElementById('supplierFilter');
            if (!Array.from(select.options).some(option => option.value === String(supplier.supplier_id))) {
                select.add(new Option(supplier.supplier_name, supplier.supplier_id));
            }
        });
    });

    $('#dealerFilter, #supplierFilter').on('change', function() {
        const url = new URL(window.location.href);
        const supplierId = $('#supplierFilter').val();
        if (supplierId) url.searchParams.set('supplier_id', supplierId);
        else url.searchParams.delete('supplier_id');
        window.history.replaceState(null, '', url);
        completedPoItemsTable.ajax.reload();
    });
</script>
<?php include 'footer.php'; ?>
