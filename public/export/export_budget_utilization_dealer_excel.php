<?php
require_once __DIR__ . '/../../includes/db.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('The PHP ZIP extension is required to create Excel exports.');
}

function valid_export_date(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function xml_value($value): string
{
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', (string)$value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excel_column_name(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function unique_sheet_name(string $name, array &$usedNames): string
{
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $name = $name === '' ? 'Department' : mb_substr($name, 0, 31);
    $base = $name;
    $suffix = 2;

    while (isset($usedNames[strtolower($name)])) {
        $suffixText = ' ' . $suffix++;
        $name = mb_substr($base, 0, 31 - mb_strlen($suffixText)) . $suffixText;
    }

    $usedNames[strtolower($name)] = true;
    return $name;
}

function worksheet_xml(
    string $title,
    array $metadata,
    array $headers,
    array $rows,
    array $numericColumns,
    array $percentColumns,
    array $columnWidths
): string {
    $lastColumn = excel_column_name(count($headers));
    $headerRow = count($metadata) + 3;
    $lastRow = $headerRow + count($rows);
    $sheetRows = [];

    $sheetRows[] = '<row r="1" ht="24" customHeight="1">'
        . '<c r="A1" s="1" t="inlineStr"><is><t>' . xml_value($title) . '</t></is></c>'
        . '</row>';

    $rowNumber = 2;
    foreach ($metadata as $label => $value) {
        $sheetRows[] = '<row r="' . $rowNumber . '">'
            . '<c r="A' . $rowNumber . '" s="2" t="inlineStr"><is><t>' . xml_value($label) . '</t></is></c>'
            . '<c r="B' . $rowNumber . '" t="inlineStr"><is><t>' . xml_value($value) . '</t></is></c>'
            . '</row>';
        $rowNumber++;
    }

    $sheetRows[] = '<row r="' . $headerRow . '" ht="22" customHeight="1">';
    foreach ($headers as $index => $header) {
        $column = excel_column_name($index + 1);
        $sheetRows[count($sheetRows) - 1] .= '<c r="' . $column . $headerRow . '" s="2" t="inlineStr"><is><t>'
            . xml_value($header) . '</t></is></c>';
    }
    $sheetRows[count($sheetRows) - 1] .= '</row>';

    foreach ($rows as $rowIndex => $row) {
        $currentRow = $headerRow + $rowIndex + 1;
        $rowXml = '<row r="' . $currentRow . '">';

        foreach (array_values($row) as $columnIndex => $value) {
            $columnNumber = $columnIndex + 1;
            $reference = excel_column_name($columnNumber) . $currentRow;

            if (in_array($columnNumber, $percentColumns, true)) {
                $rowXml .= '<c r="' . $reference . '" s="4"><v>' . ((float)$value / 100) . '</v></c>';
            } elseif (in_array($columnNumber, $numericColumns, true)) {
                $rowXml .= '<c r="' . $reference . '" s="3"><v>' . (float)$value . '</v></c>';
            } else {
                $rowXml .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . xml_value($value) . '</t></is></c>';
            }
        }

        $rowXml .= '</row>';
        $sheetRows[] = $rowXml;
    }

    $columnsXml = '';
    foreach ($columnWidths as $index => $width) {
        $columnNumber = $index + 1;
        $columnsXml .= '<col min="' . $columnNumber . '" max="' . $columnNumber
            . '" width="' . (float)$width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastColumn . max(1, $lastRow) . '"/>'
        . '<sheetViews><sheetView tabSelected="0" showGridLines="0" workbookViewId="0">'
        . '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>' . $columnsXml . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<autoFilter ref="A' . $headerRow . ':' . $lastColumn . max($headerRow, $lastRow) . '"/>'
        . '</worksheet>';
}

function fetch_budget_rows(PDO $pdo, string $grouping, array $filters): array
{
    $selectGrouping = $grouping === 'dealer'
        ? 'b.budget_dealer AS BudgetDealer'
        : 'b.budget_dealer AS BudgetDealer, b.budget_department AS BudgetDepartment';
    $groupBy = $grouping === 'dealer'
        ? 'b.budget_dealer'
        : 'b.budget_dealer, b.budget_department';

    $sql = "
        SELECT
            $selectGrouping,
            SUM(b.approved_budget) AS TotalApprovedBudget,
            SUM(IFNULL(e.total_expense, 0)) AS TotalActualExpense,
            SUM(IFNULL(lr.total_liquidation, 0)) AS TotalLiquidationExpense,
            SUM(IFNULL(lr.total_rfp, 0)) AS TotalRFP,
            SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0)) AS TotalCashAdvance,
            (
                SUM(IFNULL(e.total_expense, 0))
                + SUM(IFNULL(lr.total_liquidation, 0))
                + SUM(IFNULL(lr.total_rfp, 0))
                + SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
            ) AS TotalCommittedAmount,
            (
                SUM(b.approved_budget)
                - SUM(IFNULL(e.total_expense, 0))
                - SUM(IFNULL(lr.total_liquidation, 0))
                - SUM(IFNULL(lr.total_rfp, 0))
                - SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
            ) AS TotalRemainingBudget,
            ROUND(
                (
                    (
                        SUM(IFNULL(e.total_expense, 0))
                        + SUM(IFNULL(lr.total_liquidation, 0))
                        + SUM(IFNULL(lr.total_rfp, 0))
                        + SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
                    ) / NULLIF(SUM(b.approved_budget), 0)
                ) * 100,
                2
            ) AS BudgetUtilization
        FROM budgets b
        LEFT JOIN (
            SELECT
                budget_lookup.budget_id,
                SUM(po.amount_purchased) AS total_expense
            FROM purchase_order po
            INNER JOIN canvass_sheet_batches csb ON csb.pr_number = po.pr_number
            INNER JOIN budgets budget_lookup
                ON CAST(budget_lookup.budget_id AS CHAR) = TRIM(csb.budget_code)
                OR budget_lookup.budget_code = TRIM(csb.budget_code)
            WHERE csb.budget_code IS NOT NULL
              AND TRIM(csb.budget_code) <> ''
              {$filters['purchaseOrderSql']}
            GROUP BY budget_lookup.budget_id
        ) e ON b.budget_id = e.budget_id
        LEFT JOIN (
            SELECT budget_code, pr_dealer, pr_department, SUM(ca_amount) AS total_cash_advance
            FROM cash_advance
            WHERE COALESCE(pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
              {$filters['cashSql']}
            GROUP BY budget_code, pr_dealer, pr_department
        ) ca ON b.budget_code = ca.budget_code
            AND b.budget_dealer = ca.pr_dealer
            AND UPPER(TRIM(b.budget_department)) = UPPER(TRIM(ca.pr_department))
        LEFT JOIN (
            SELECT
                budget_lookup.budget_id,
                SUM(CASE WHEN l.request_type = 'Liquidation' THEN l.liquidation_amount ELSE 0 END) AS total_liquidation,
                SUM(CASE WHEN l.request_type = 'Request for Payment' THEN l.liquidation_amount ELSE 0 END) AS total_rfp,
                SUM(CASE WHEN l.request_type = 'Cash Advance' THEN l.liquidation_amount ELSE 0 END) AS total_cash_advance
            FROM liquidations l
            INNER JOIN budgets budget_lookup
                ON budget_lookup.budget_id = l.budget_id
                OR budget_lookup.budget_code = l.budget_code
            WHERE COALESCE(l.status, '') <> 'Rejected'
              {$filters['liquidationSql']}
            GROUP BY budget_lookup.budget_id
        ) lr ON b.budget_id = lr.budget_id
        WHERE b.budget_dealer IS NOT NULL
          AND b.budget_dealer <> ''
          {$filters['dealerSql']}
        GROUP BY $groupBy
        ORDER BY $groupBy
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($filters['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_budget_details(PDO $pdo, array $filters): array
{
    $sql = "
        SELECT
            b.budget_dealer AS BudgetDealer,
            b.budget_department AS BudgetDepartment,
            b.budget_code AS BudgetCode,
            b.budget_activity AS BudgetActivity,
            b.approved_budget AS ApprovedBudget,
            IFNULL(e.total_expense, 0) AS ActualExpense,
            IFNULL(lr.total_liquidation, 0) AS LiquidationExpense,
            IFNULL(lr.total_rfp, 0) AS RFP,
            (IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0)) AS CashAdvance,
            (
                IFNULL(e.total_expense, 0)
                + IFNULL(lr.total_liquidation, 0)
                + IFNULL(lr.total_rfp, 0)
                + IFNULL(ca.total_cash_advance, 0)
                + IFNULL(lr.total_cash_advance, 0)
            ) AS CommittedAmount,
            (
                b.approved_budget
                - IFNULL(e.total_expense, 0)
                - IFNULL(lr.total_liquidation, 0)
                - IFNULL(lr.total_rfp, 0)
                - IFNULL(ca.total_cash_advance, 0)
                - IFNULL(lr.total_cash_advance, 0)
            ) AS RemainingBudget,
            ROUND(
                (
                    (
                        IFNULL(e.total_expense, 0)
                        + IFNULL(lr.total_liquidation, 0)
                        + IFNULL(lr.total_rfp, 0)
                        + IFNULL(ca.total_cash_advance, 0)
                        + IFNULL(lr.total_cash_advance, 0)
                    ) / NULLIF(b.approved_budget, 0)
                ) * 100,
                2
            ) AS BudgetUtilization
        FROM budgets b
        LEFT JOIN (
            SELECT
                budget_lookup.budget_id,
                SUM(po.amount_purchased) AS total_expense
            FROM purchase_order po
            INNER JOIN canvass_sheet_batches csb ON csb.pr_number = po.pr_number
            INNER JOIN budgets budget_lookup
                ON CAST(budget_lookup.budget_id AS CHAR) = TRIM(csb.budget_code)
                OR budget_lookup.budget_code = TRIM(csb.budget_code)
            WHERE csb.budget_code IS NOT NULL
              AND TRIM(csb.budget_code) <> ''
              {$filters['purchaseOrderSql']}
            GROUP BY budget_lookup.budget_id
        ) e ON b.budget_id = e.budget_id
        LEFT JOIN (
            SELECT budget_code, pr_dealer, pr_department, SUM(ca_amount) AS total_cash_advance
            FROM cash_advance
            WHERE COALESCE(pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
              {$filters['cashSql']}
            GROUP BY budget_code, pr_dealer, pr_department
        ) ca ON b.budget_code = ca.budget_code
            AND b.budget_dealer = ca.pr_dealer
            AND UPPER(TRIM(b.budget_department)) = UPPER(TRIM(ca.pr_department))
        LEFT JOIN (
            SELECT
                budget_lookup.budget_id,
                SUM(CASE WHEN l.request_type = 'Liquidation' THEN l.liquidation_amount ELSE 0 END) AS total_liquidation,
                SUM(CASE WHEN l.request_type = 'Request for Payment' THEN l.liquidation_amount ELSE 0 END) AS total_rfp,
                SUM(CASE WHEN l.request_type = 'Cash Advance' THEN l.liquidation_amount ELSE 0 END) AS total_cash_advance
            FROM liquidations l
            INNER JOIN budgets budget_lookup
                ON budget_lookup.budget_id = l.budget_id
                OR budget_lookup.budget_code = l.budget_code
            WHERE COALESCE(l.status, '') <> 'Rejected'
              {$filters['liquidationSql']}
            GROUP BY budget_lookup.budget_id
        ) lr ON b.budget_id = lr.budget_id
        WHERE b.budget_dealer IS NOT NULL
          AND b.budget_dealer <> ''
          {$filters['dealerSql']}
        ORDER BY b.budget_dealer, b.budget_department, b.budget_code
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($filters['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$start = valid_export_date(trim($_GET['start'] ?? ''));
$end = valid_export_date(trim($_GET['end'] ?? ''));
$dealer = trim($_GET['dealer'] ?? '');

if ($start !== '' && $end !== '' && $start > $end) {
    [$start, $end] = [$end, $start];
}

$filters = [
    'purchaseOrderSql' => '',
    'cashSql' => '',
    'liquidationSql' => '',
    'dealerSql' => '',
    'params' => [],
];

if ($start !== '' && $end !== '') {
    $filters['purchaseOrderSql'] = ' AND DATE(po.po_date) BETWEEN :expense_start AND :expense_end';
    $filters['cashSql'] = ' AND COALESCE(ca_date, DATE(pr_date)) BETWEEN :cash_start AND :cash_end';
    $filters['liquidationSql'] = ' AND liquidation_date BETWEEN :liquidation_start AND :liquidation_end';
    $filters['params'] += [
        ':expense_start' => $start,
        ':expense_end' => $end,
        ':cash_start' => $start,
        ':cash_end' => $end,
        ':liquidation_start' => $start,
        ':liquidation_end' => $end,
    ];
} elseif ($start !== '') {
    $filters['purchaseOrderSql'] = ' AND DATE(po.po_date) >= :expense_start';
    $filters['cashSql'] = ' AND COALESCE(ca_date, DATE(pr_date)) >= :cash_start';
    $filters['liquidationSql'] = ' AND liquidation_date >= :liquidation_start';
    $filters['params'] += [
        ':expense_start' => $start,
        ':cash_start' => $start,
        ':liquidation_start' => $start,
    ];
} elseif ($end !== '') {
    $filters['purchaseOrderSql'] = ' AND DATE(po.po_date) <= :expense_end';
    $filters['cashSql'] = ' AND COALESCE(ca_date, DATE(pr_date)) <= :cash_end';
    $filters['liquidationSql'] = ' AND liquidation_date <= :liquidation_end';
    $filters['params'] += [
        ':expense_end' => $end,
        ':cash_end' => $end,
        ':liquidation_end' => $end,
    ];
}

if ($dealer !== '') {
    $filters['dealerSql'] = ' AND b.budget_dealer = :dealer';
    $filters['params'][':dealer'] = $dealer;
}

$dealerRows = fetch_budget_rows($pdo, 'dealer', $filters);
$departmentRows = fetch_budget_rows($pdo, 'department', $filters);
$detailRows = fetch_budget_details($pdo, $filters);

$metadata = [
    'Dealer Filter' => $dealer === '' ? 'All Dealers' : $dealer,
    'Start Date' => $start === '' ? 'All' : $start,
    'End Date' => $end === '' ? 'All' : $end,
];

$summaryHeaders = [
    'Dealer', 'Approved Budget', 'Actual Expenses', 'Liquidation Expenses', 'RFP',
    'Cash Advances', 'Pending Transactions', 'Remaining Budget', 'Utilization (%)'
];
$summaryData = array_map(static function (array $row): array {
    return [
        $row['BudgetDealer'], $row['TotalApprovedBudget'], $row['TotalActualExpense'],
        $row['TotalLiquidationExpense'], $row['TotalRFP'], $row['TotalCashAdvance'],
        $row['TotalCommittedAmount'], $row['TotalRemainingBudget'], $row['BudgetUtilization'],
    ];
}, $dealerRows);

$departmentHeaders = array_merge(['Dealer', 'Department'], array_slice($summaryHeaders, 1));
$departmentData = array_map(static function (array $row): array {
    return [
        $row['BudgetDealer'], $row['BudgetDepartment'], $row['TotalApprovedBudget'],
        $row['TotalActualExpense'], $row['TotalLiquidationExpense'], $row['TotalRFP'],
        $row['TotalCashAdvance'], $row['TotalCommittedAmount'], $row['TotalRemainingBudget'],
        $row['BudgetUtilization'],
    ];
}, $departmentRows);

$sheets = [
    [
        'name' => 'Dealer Summary',
        'xml' => worksheet_xml(
            'Budget Utilization by Dealer',
            $metadata,
            $summaryHeaders,
            $summaryData,
            [2, 3, 4, 5, 6, 7, 8],
            [9],
            [16, 18, 18, 20, 14, 18, 19, 19, 16]
        ),
    ],
    [
        'name' => 'Department Summary',
        'xml' => worksheet_xml(
            'Budget Utilization by Department',
            $metadata,
            $departmentHeaders,
            $departmentData,
            [3, 4, 5, 6, 7, 8, 9],
            [10],
            [14, 20, 18, 18, 20, 14, 18, 19, 19, 16]
        ),
    ],
];

$detailsByDepartment = [];
foreach ($detailRows as $row) {
    $key = $row['BudgetDealer'] . "\0" . $row['BudgetDepartment'];
    $detailsByDepartment[$key][] = $row;
}

$usedNames = ['dealer summary' => true, 'department summary' => true];
$detailHeaders = [
    'Budget Code', 'Activity', 'Approved Budget', 'Actual Expenses', 'Liquidation Expenses',
    'RFP', 'Cash Advances', 'Pending Transactions', 'Remaining Budget', 'Utilization (%)'
];

foreach ($detailsByDepartment as $rows) {
    $first = $rows[0];
    $sheetLabel = $dealer === ''
        ? $first['BudgetDealer'] . ' - ' . $first['BudgetDepartment']
        : $first['BudgetDepartment'];
    $sheetName = unique_sheet_name($sheetLabel, $usedNames);
    $sheetMetadata = $metadata + [
        'Dealer' => $first['BudgetDealer'],
        'Department' => $first['BudgetDepartment'],
    ];
    $sheetData = array_map(static function (array $row): array {
        return [
            $row['BudgetCode'], $row['BudgetActivity'], $row['ApprovedBudget'], $row['ActualExpense'],
            $row['LiquidationExpense'], $row['RFP'], $row['CashAdvance'], $row['CommittedAmount'],
            $row['RemainingBudget'], $row['BudgetUtilization'],
        ];
    }, $rows);

    $sheets[] = [
        'name' => $sheetName,
        'xml' => worksheet_xml(
            $first['BudgetDealer'] . ' - ' . $first['BudgetDepartment'] . ' Budget Details',
            $sheetMetadata,
            $detailHeaders,
            $sheetData,
            [3, 4, 5, 6, 7, 8, 9],
            [10],
            [17, 34, 18, 18, 20, 14, 18, 19, 19, 16]
        ),
    ];
}

$workbookSheets = '';
$workbookRelationships = '';
$contentOverrides = '';
foreach ($sheets as $index => $sheet) {
    $sheetId = $index + 1;
    $workbookSheets .= '<sheet name="' . xml_value($sheet['name']) . '" sheetId="' . $sheetId
        . '" r:id="rId' . $sheetId . '"/>';
    $workbookRelationships .= '<Relationship Id="rId' . $sheetId
        . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
        . ' Target="worksheets/sheet' . $sheetId . '.xml"/>';
    $contentOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetId
        . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$stylesRelationshipId = count($sheets) + 1;
$workbookRelationships .= '<Relationship Id="rId' . $stylesRelationshipId
    . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

$tempFile = tempnam(sys_get_temp_dir(), 'budget_xlsx_');
$zip = new ZipArchive();
if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Unable to create Excel export.');
}

$zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . $contentOverrides
    . '</Types>'
);
$zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>'
);
$zip->addFromString('docProps/core.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
    . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:creator>OG Purchasing System</dc:creator><dc:title>Budget Utilization by Dealer and Department</dc:title>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
    . '</cp:coreProperties>'
);
$sheetNamesXml = implode('', array_map(static function (array $sheet): string {
    return '<vt:lpstr>' . xml_value($sheet['name']) . '</vt:lpstr>';
}, $sheets));
$zip->addFromString('docProps/app.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
    . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>OG Purchasing System</Application><TitlesOfParts><vt:vector size="' . count($sheets)
    . '" baseType="lpstr">' . $sheetNamesXml . '</vt:vector></TitlesOfParts></Properties>'
);
$zip->addFromString('xl/workbook.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>' . $workbookSheets . '</sheets>'
    . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>'
);
$zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . $workbookRelationships . '</Relationships>'
);
$zip->addFromString('xl/styles.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;PHP&quot; #,##0.00"/>'
    . '<numFmt numFmtId="165" formatCode="0.00%"/></numFmts>'
    . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
    . '<fills count="4"><fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF0D6EFD"/><bgColor indexed="64"/></patternFill></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF495057"/><bgColor indexed="64"/></patternFill></fill></fills>'
    . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
    . '<border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right>'
    . '<top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
    . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
    . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
    . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>'
);

foreach ($sheets as $index => $sheet) {
    $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $sheet['xml']);
}
$zip->close();

$dealerForFile = $dealer === ''
    ? 'AllDealers'
    : trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dealer), '_');
$filename = sprintf(
    'budget_utilization_%s_%s_to_%s.xlsx',
    $dealerForFile ?: 'Dealer',
    $start === '' ? 'start' : $start,
    $end === '' ? 'end' : $end
);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tempFile);
unlink($tempFile);
exit;
