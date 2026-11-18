CREATE VIEW v_pr_leadtime AS
SELECT 

    pr.pr_number,
    pr.pr_dealer,
    pr.pr_department,
    pr.pr_status,
    lt.received_date,
    lt.for_canvass_date,
    lt.canvass_approved_date,
    lt.po_created_date,

    -- Lead time calculations in HOURS
    TIMESTAMPDIFF(HOUR, lt.received_date, lt.for_canvass_date) AS leadtime_received_to_canvass,
    TIMESTAMPDIFF(HOUR, lt.for_canvass_date, lt.canvass_approved_date) AS leadtime_canvass_to_approval,
    TIMESTAMPDIFF(HOUR, lt.canvass_approved_date, lt.po_created_date) AS leadtime_approved_to_po,

    -- Overall lead time in HOURS
    TIMESTAMPDIFF(HOUR, lt.received_date, lt.po_created_date) AS total_leadtime

FROM purchase_requisition pr
JOIN leadtime_tracking lt ON pr.pr_number = lt.pr_number;
