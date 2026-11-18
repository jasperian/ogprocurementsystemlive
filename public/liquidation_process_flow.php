<?php
require '../init.php';

include 'header.php';
include 'navigation.php';

$approvalFlows = [
    'Cash Advance' => [
        ['owner' => 'Department PIC', 'action' => 'Creates and submits the Cash Advance.', 'status' => 'Create Cash Advance', 'tone' => 'primary'],
        ['owner' => 'Department Manager', 'action' => 'Reviews and endorses the request.', 'status' => 'For Department Manager Approval', 'tone' => 'info'],
        ['owner' => 'Accounting', 'action' => 'Releases the Cash Advance without GM or SAP processing.', 'status' => 'For Release', 'tone' => 'success'],
        ['owner' => 'System', 'action' => 'Marks the Cash Advance as released.', 'status' => 'Completed', 'tone' => 'success'],
    ],
    'Liquidation' => [
        ['owner' => 'Department PIC', 'action' => 'Creates and submits the Liquidation.', 'status' => 'Create Liquidation', 'tone' => 'primary'],
        ['owner' => 'Department Manager', 'action' => 'Reviews and endorses the request.', 'status' => 'For Department Manager Approval', 'tone' => 'info'],
        ['owner' => 'Accounting Staff', 'action' => 'Encodes the SAP Document Number.', 'status' => 'Recommended', 'tone' => 'secondary'],
        ['owner' => 'General / Branch Manager', 'action' => 'Approves or rejects the Liquidation after SAP encoding.', 'status' => 'For GM Approval', 'tone' => 'warning'],
        ['owner' => 'Accounting Manager', 'action' => 'Gives final accounting approval.', 'status' => 'For Accounting Approval', 'tone' => 'success'],
        ['owner' => 'System', 'action' => 'Closes the Liquidation.', 'status' => 'Completed', 'tone' => 'success'],
    ],
    'Cash Advance to Liquidation' => [
        ['owner' => 'Department PIC', 'action' => 'Creates the Liquidation from a released Cash Advance.', 'status' => 'Create CA Liquidation', 'tone' => 'primary'],
        ['owner' => 'Department Manager', 'action' => 'Reviews and endorses the Liquidation.', 'status' => 'For Department Manager Approval', 'tone' => 'info'],
        ['owner' => 'Accounting Staff', 'action' => 'Encodes the SAP Document Number.', 'status' => 'Recommended', 'tone' => 'secondary'],
        ['owner' => 'General / Branch Manager', 'action' => 'Approves or rejects the Liquidation after SAP encoding.', 'status' => 'For GM Approval', 'tone' => 'warning'],
        ['owner' => 'Accounting Manager', 'action' => 'Gives final accounting approval.', 'status' => 'For Accounting Approval', 'tone' => 'success'],
        ['owner' => 'System', 'action' => 'Closes the Cash Advance Liquidation.', 'status' => 'Completed', 'tone' => 'success'],
    ],
    'RFP up to PHP 10,000' => [
        ['owner' => 'Department PIC', 'action' => 'Creates and submits the Request for Payment.', 'status' => 'Create RFP', 'tone' => 'primary'],
        ['owner' => 'Department Manager', 'action' => 'Reviews and endorses the request.', 'status' => 'For Department Manager Approval', 'tone' => 'info'],
        ['owner' => 'Accounting', 'action' => 'Completes the request without GM or SAP processing.', 'status' => 'For Accounting Completion', 'tone' => 'success'],
        ['owner' => 'System', 'action' => 'Closes the Request for Payment.', 'status' => 'Completed', 'tone' => 'success'],
    ],
    'RFP above PHP 10,000' => [
        ['owner' => 'Department PIC', 'action' => 'Creates and submits the Request for Payment.', 'status' => 'Create RFP', 'tone' => 'primary'],
        ['owner' => 'Department Manager', 'action' => 'Reviews and endorses the request.', 'status' => 'For Department Manager Approval', 'tone' => 'info'],
        ['owner' => 'General / Branch Manager', 'action' => 'Approves or rejects the Request for Payment.', 'status' => 'For GM Approval', 'tone' => 'warning'],
        ['owner' => 'Accountinge', 'action' => 'Completes the request without SAP processing.', 'status' => 'For Accounting Completion', 'tone' => 'success'],
        ['owner' => 'System', 'action' => 'Closes the Request for Payment.', 'status' => 'Completed', 'tone' => 'success'],
    ],
];
?>

<style>
    .liq-flow-wrap {
        --flow-border: #dbe4ef;
        --flow-muted: #6c757d;
    }

    .flow-summary,
    .flow-card {
        border: 1px solid var(--flow-border);
        border-radius: 8px;
        background: #fff;
    }

    .flow-track {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 6px;
    }

    .flow-track.ca-track {
        grid-template-columns: repeat(6, minmax(180px, 1fr));
    }

    .flow-node {
        min-height: 146px;
        border: 1px solid var(--flow-border);
        border-radius: 8px;
        background: #fff;
        padding: 16px;
        position: relative;
    }

    .flow-node:not(:last-child)::after {
        content: "";
        position: absolute;
        top: 36px;
        right: -12px;
        width: 12px;
        border-top: 2px solid var(--flow-border);
    }

    .flow-node-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-bottom: 12px;
    }

    .flow-node h6 {
        min-height: 38px;
        margin-bottom: 8px;
        line-height: 1.25;
    }

    .flow-node p,
    .flow-card p {
        color: var(--flow-muted);
    }

    .flow-node .badge {
        white-space: normal;
        text-align: left;
    }

    .request-type-card {
        height: 100%;
        border: 1px solid var(--flow-border);
        border-radius: 8px;
        padding: 18px;
    }

    .rule-list li {
        margin-bottom: 10px;
    }

    @media (max-width: 991.98px) {
        .flow-track {
            grid-template-columns: repeat(4, minmax(210px, 1fr));
        }

        .flow-track.ca-track {
            grid-template-columns: repeat(6, minmax(210px, 1fr));
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content liq-flow-wrap">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="page-header-title">
                                <h2 class="mb-0">Liquidation / RFP / CA Process Flow</h2>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="flow-summary p-3 p-md-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <span class="badge bg-light-primary mb-2">From Add Liquidation / RFP / CA to Accounting completion</span>
                            <h4 class="mb-2">Role and status at every approval step</h4>
                            <p class="text-muted mb-0">
                                Each flow shows who handles the request and the status shown while it is in that person's queue.
                            </p>
                        </div>
                        <div class="col-lg-4">
                            <div class="border rounded-3 p-3 bg-light">
                          
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted">Request types</span>
                                    <span class="badge bg-light-secondary">LIQ / RFP / CA</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($approvalFlows as $flowName => $steps) { ?>
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                            <h5 class="mb-0"><?= htmlspecialchars($flowName) ?> Approval Flow</h5>
                            <span class="badge bg-light-secondary"><?= count($steps) ?> steps</span>
                        </div>
                        <div class="card-body">
                            <div class="flow-track" style="grid-template-columns: repeat(<?= count($steps) ?>, minmax(190px, 1fr));">
                                <?php foreach ($steps as $index => $step) { ?>
                                    <div class="flow-node">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                            <span class="badge bg-light-secondary">Step <?= $index + 1 ?></span>
                                            <span class="badge bg-light-<?= htmlspecialchars($step['tone']) ?>"><?= htmlspecialchars($step['status']) ?></span>
                                        </div>
                                        <h6 class="mb-2"><?= htmlspecialchars($step['owner']) ?></h6>
                                        <p class="mb-0"><?= htmlspecialchars($step['action']) ?></p>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="col-md-6" hidden>
                <div class="flow-card p-3 h-100">
                    <h5 class="mb-2">Department Manager submissions</h5>
                    <p class="mb-0">When a Department Manager creates a request directly, the Department PIC and Department Manager steps are skipped. The request starts at its next applicable Accounting or final-approver stage.</p>
                </div>
            </div>
            <div class="col-md-6" hidden>
                <div class="flow-card p-3 h-100">
                    <h5 class="mb-2">Rejected requests</h5>
                    <p class="mb-0">The Department Manager or General Manager can reject a pending request. Its status becomes <strong>Rejected</strong>, and the rejection reason is recorded.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
