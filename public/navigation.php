	<?php
	
	require_once __DIR__ . '/data/cash_advance_workflow_helper.php';
	$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
	$stmt->execute([$_SESSION['user_id']]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$user) {
		header("Location: login.php");
		exit();
	}
	$user_id = $_SESSION['user_id'];
	$isDepartmentManager = (int)($user['is_depthead'] ?? 0) === 1 || ($_SESSION['position'] ?? '') === 'Manager';
	$isCanvassManager = (int)($user['is_depthead'] ?? 0) === 1 || stripos($_SESSION['position'] ?? '', 'Manager') !== false;
	$isDepartmentPic = (int)($user['is_dept_pic'] ?? 0) === 1;
	$liquidationGmPendingCount = 0;
	$liquidationManagerPendingCount = 0;
	$liquidationAccountingPendingCount = 0;
	$cashAdvanceLiquidationPendingCount = 0;
	$purchasingApprovalPendingCount = 0;
	$cashAdvanceStatusCounts = [
		'CA Request' => 0,
		'CA Release' => 0,
		'For PO' => 0,
		'For Liquidation' => 0,
		'Liquidated' => 0,
		'For SAP Document' => 0,
		'For Accounting Approval' => 0,
		'For GM Approval' => 0,
	];
	$sessionDealer = trim($_SESSION['dealer'] ?? '');

	try {
		$caCountSql = "
			SELECT pr_status, COUNT(*) AS pending_count
			FROM cash_advance
			WHERE pr_status IN ('CA Request', 'CA Release', 'For PO', 'For Liquidation', 'Liquidated', 'For SAP Document', 'For Accounting Approval', 'For GM Approval')
		";
		$caCountParams = [];
		if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
			$caCountSql .= " AND pr_dealer = :dealer";
			$caCountParams[':dealer'] = $sessionDealer;
		}
		$caCountSql .= " GROUP BY pr_status";

		$caCountStmt = $pdo->prepare($caCountSql);
		$caCountStmt->execute($caCountParams);
		foreach ($caCountStmt->fetchAll(PDO::FETCH_ASSOC) as $caCountRow) {
			$status = $caCountRow['pr_status'] ?? '';
			if (array_key_exists($status, $cashAdvanceStatusCounts)) {
				$cashAdvanceStatusCounts[$status] = (int)$caCountRow['pending_count'];
			}
		}
		
		
		$actionableSql = "SELECT ca.*, u.department AS creator_department, u.username AS creator_username FROM cash_advance ca LEFT JOIN users u ON u.user_id = ca.created_by WHERE ca.pr_status NOT IN ('Completed', 'Cancelled')";
		$actionableParams = [];
		if (purchasingWorkflowIsTnescApprover((string)($_SESSION['position'] ?? ''))) {
			$actionableSql .= " AND LOWER(TRIM(u.username)) = :tnesc_creator AND ca.pr_status = 'For GM Approval'";
			$actionableParams[':tnesc_creator'] = PURCHASING_TNESC_SIGNATORY_CREATOR;
		} elseif ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
			$actionableSql .= " AND ca.pr_dealer = :actionable_dealer";
			$actionableParams[':actionable_dealer'] = $sessionDealer;
		}
		$actionableSql .= " ORDER BY ca.pr_date DESC, ca.pr_id DESC LIMIT 1000";
		$actionableStmt = $pdo->prepare($actionableSql);
		$actionableStmt->execute($actionableParams);
		foreach ($actionableStmt->fetchAll(PDO::FETCH_ASSOC) as $actionableRequest) {
			if ((purchasingWorkflowNextStatus($actionableRequest) !== null
				&& purchasingWorkflowActionAllowed(
					$actionableRequest,
					(string)($_SESSION['position'] ?? ''),
					(string)($_SESSION['department'] ?? ''),
					(int)$user_id
				)) || purchasingWorkflowDepartmentLiquidationVisible($actionableRequest, (string)($_SESSION['department'] ?? ''))) {
				$purchasingApprovalPendingCount++;
			}
		}

		if ($isDepartmentManager) {
			$countSql = "SELECT COUNT(*) FROM liquidations WHERE status = 'For Department Manager Approval'";
			$countParams = [];
			if (trim($_SESSION['department'] ?? '') !== '') {
				$countSql .= " AND UPPER(TRIM(department)) = UPPER(TRIM(:department))";
				$countParams[':department'] = trim($_SESSION['department'] ?? '');
			}
			if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
				$countSql .= " AND dealer = :dealer";
				$countParams[':dealer'] = $sessionDealer;
			}
			$countStmt = $pdo->prepare($countSql);
			$countStmt->execute($countParams);
			$liquidationManagerPendingCount = (int)$countStmt->fetchColumn();
		}

		if (($_SESSION['position'] ?? '') === 'General Manager') {
			$countSql = "SELECT COUNT(*) FROM liquidations WHERE status = 'For GM Approval'";
			$countParams = [];
			if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
				$countSql .= " AND dealer = :dealer";
				$countParams[':dealer'] = $sessionDealer;
			}
			$countStmt = $pdo->prepare($countSql);
			$countStmt->execute($countParams);
			$liquidationGmPendingCount = (int)$countStmt->fetchColumn();
		}

		if (in_array(($_SESSION['department'] ?? ''), ['ACCOUNTING', 'FINANCE'], true)) {
			$countSql = "SELECT COUNT(*) FROM liquidations WHERE status IN ('Liquidated', 'For Accounting Approval')";
			$countParams = [];
			if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
				$countSql .= " AND dealer = :dealer";
				$countParams[':dealer'] = $sessionDealer;
			}
			$countStmt = $pdo->prepare($countSql);
			$countStmt->execute($countParams);
			$liquidationAccountingPendingCount = (int)$countStmt->fetchColumn();
		}

		if ($isDepartmentManager || $isDepartmentPic) {
			$countSql = "
				SELECT COUNT(*)
				FROM liquidations
				WHERE status = 'Completed'
				  AND request_type = 'Cash Advance'
				  AND NOT EXISTS (
					SELECT 1
					FROM liquidations child
					WHERE child.source_cash_advance_id = liquidations.liquidation_id
				  )
			";
			$countParams = [];
			if (trim($_SESSION['department'] ?? '') !== '') {
				$countSql .= " AND UPPER(TRIM(department)) = UPPER(TRIM(:department))";
				$countParams[':department'] = trim($_SESSION['department'] ?? '');
			}
			if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG') {
				$countSql .= " AND dealer = :dealer";
				$countParams[':dealer'] = $sessionDealer;
			}
			$countStmt = $pdo->prepare($countSql);
			$countStmt->execute($countParams);
			$cashAdvanceLiquidationPendingCount = (int)$countStmt->fetchColumn();
		}
	} catch (Exception $e) {
		$liquidationGmPendingCount = 0;
		$liquidationManagerPendingCount = 0;
		$liquidationAccountingPendingCount = 0;
		$cashAdvanceLiquidationPendingCount = 0;
		$cashAdvanceStatusCounts = array_fill_keys(array_keys($cashAdvanceStatusCounts), 0);
	}

	$liquidationApprovalPendingCount = ($_SESSION['position'] ?? '') === 'General Manager'
		? $liquidationGmPendingCount
		: $liquidationManagerPendingCount;

	/*
	 * The notification and system update queries below were duplicated later in
	 * this file. Keeping only the later block avoids extra DB work on every page.
	 *
	$sql = "SELECT DISTINCT 
				n.id AS id,
				n.title,
				n.message,
				n.type,
				n.link,
				-- 👇 If there's a row in recipients, use it. Otherwise fallback to notifications.is_read.
				COALESCE(nr.is_read, n.is_read, 0) AS is_read,
				n.created_at
			FROM notifications n
			LEFT JOIN notification_recipients nr 
				ON nr.notification_id = n.id 
				AND nr.user_id = :user_id
			WHERE nr.user_id = :user_id 
			OR n.user_id = :user_id
			ORDER BY n.created_at DESC
			LIMIT 20;";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $user_id]);
	$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$systemUpdates = [];
	$systemUpdateUnreadCount = 0;
	try {
		$systemUpdateVisibilitySql = "
			su.is_active = 1
			AND su.published_at <= NOW()
			AND (su.expires_at IS NULL OR su.expires_at > NOW())
			AND (
				NOT EXISTS (SELECT 1 FROM system_update_departments all_sud WHERE all_sud.update_id = su.update_id)
				OR EXISTS (
					SELECT 1 FROM system_update_departments own_sud
					WHERE own_sud.update_id = su.update_id
					AND UPPER(TRIM(own_sud.department)) = UPPER(TRIM(:system_update_department))
				)
			)";

		$updateStmt = $pdo->prepare("SELECT su.update_id, su.title, su.message, su.severity, su.published_at,
			EXISTS (
				SELECT 1 FROM system_update_reads sur
				WHERE sur.update_id = su.update_id AND sur.user_id = :system_update_user
			) AS is_read
			FROM system_updates su
			WHERE $systemUpdateVisibilitySql
			ORDER BY su.published_at DESC
			LIMIT 10");
		$updateStmt->execute([
			':system_update_user' => $user_id,
			':system_update_department' => $_SESSION['department'] ?? ''
		]);
		$systemUpdates = $updateStmt->fetchAll(PDO::FETCH_ASSOC);

		$unreadStmt = $pdo->prepare("SELECT COUNT(*)
			FROM system_updates su
			WHERE $systemUpdateVisibilitySql
			AND NOT EXISTS (
				SELECT 1 FROM system_update_reads sur
				WHERE sur.update_id = su.update_id AND sur.user_id = :system_update_user
			)");
		$unreadStmt->execute([
			':system_update_user' => $user_id,
			':system_update_department' => $_SESSION['department'] ?? ''
		]);
		$systemUpdateUnreadCount = (int)$unreadStmt->fetchColumn();
	} catch (Throwable $e) {
		$systemUpdates = [];
		$systemUpdateUnreadCount = 0;
	}
	*/
	
	$sql = "SELECT DISTINCT 
				n.id AS id,
				n.title,
				n.message,
				n.type,
				n.link,
				-- 👇 If there's a row in recipients, use it. Otherwise fallback to notifications.is_read.
				COALESCE(nr.is_read, n.is_read, 0) AS is_read,
				n.created_at
			FROM notifications n
			LEFT JOIN notification_recipients nr 
				ON nr.notification_id = n.id 
				AND nr.user_id = :user_id
			WHERE nr.user_id = :user_id 
			OR n.user_id = :user_id
			ORDER BY n.created_at DESC
			LIMIT 20;";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $user_id]);
	$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$notificationUnreadCountSql = "SELECT COUNT(*)
			FROM notifications n
			LEFT JOIN notification_recipients nr
				ON nr.notification_id = n.id
				AND nr.user_id = :user_id
			WHERE (nr.user_id = :user_id OR n.user_id = :user_id)
			AND COALESCE(nr.is_read, n.is_read, 0) = 0";
	$stmt = $pdo->prepare($notificationUnreadCountSql);
	$stmt->execute([':user_id' => $user_id]);
	$notificationUnreadCount = (int)$stmt->fetchColumn();

	$systemUpdates = [];
	$systemUpdateUnreadCount = 0;
	try {
		$systemUpdateVisibilitySql = "
			su.is_active = 1
			AND su.published_at <= NOW()
			AND (su.expires_at IS NULL OR su.expires_at > NOW())
			AND (
				NOT EXISTS (SELECT 1 FROM system_update_departments all_sud WHERE all_sud.update_id = su.update_id)
				OR EXISTS (
					SELECT 1 FROM system_update_departments own_sud
					WHERE own_sud.update_id = su.update_id
					AND UPPER(TRIM(own_sud.department)) = UPPER(TRIM(:system_update_department))
				)
			)";

		$updateStmt = $pdo->prepare("SELECT su.update_id, su.title, su.message, su.severity, su.published_at,
			EXISTS (
				SELECT 1 FROM system_update_reads sur
				WHERE sur.update_id = su.update_id AND sur.user_id = :system_update_user
			) AS is_read
			FROM system_updates su
			WHERE $systemUpdateVisibilitySql
			ORDER BY su.published_at DESC
			LIMIT 10");
		$updateStmt->execute([
			':system_update_user' => $user_id,
			':system_update_department' => $_SESSION['department'] ?? ''
		]);
		$systemUpdates = $updateStmt->fetchAll(PDO::FETCH_ASSOC);

		$unreadStmt = $pdo->prepare("SELECT COUNT(*)
			FROM system_updates su
			WHERE $systemUpdateVisibilitySql
			AND NOT EXISTS (
				SELECT 1 FROM system_update_reads sur
				WHERE sur.update_id = su.update_id AND sur.user_id = :system_update_user
			)");
		$unreadStmt->execute([
			':system_update_user' => $user_id,
			':system_update_department' => $_SESSION['department'] ?? ''
		]);
		$systemUpdateUnreadCount = (int)$unreadStmt->fetchColumn();
	} catch (Throwable $e) {
		$systemUpdates = [];
		$systemUpdateUnreadCount = 0;
	}
	
	


	?>
	
	<!-- [Body] Start -->

	<style>
		#results {
			border: 1px solid #ccc;
			max-height: 300px;
			overflow-y: auto;
			margin-top: 10px;
			padding: 10px;
		}

		#results ul {
			list-style: none;
			padding: 0;
		}

		#results li {
			margin-bottom: 10px;
		}

		#globalSearchResults ul {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		#globalSearchResults li {
			padding: 8px 0;
			border-bottom: 1px dashed #ccc;
			display: flex;
			flex-direction: column;
		}

		#globalSearchResults a {
			font-weight: bold;
			color: #0d6efd;
			text-decoration: none;
		}

		#globalSearchResults small {
			color: #666;
		}

		#globalSearchLoading {
			padding: 10px 0;
		}

		.notification-card {
			cursor: pointer;
			transition: background-color 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
		}

		.notification-card:not(.opacity-50) {
			border-left: 3px solid var(--bs-primary);
		}

		.notification-card:hover {
			background: rgba(var(--bs-primary-rgb), 0.04);
		}

		.notification-empty-state {
			padding: 28px 16px;
		}

		.dropdown-notification .header-notification-scroll {
			max-height: min(520px, calc(100vh - 215px));
			overflow-y: auto;
			overflow-x: hidden;
			overscroll-behavior: contain;
			padding-right: 6px;
		}

		.dropdown-notification .header-notification-scroll::-webkit-scrollbar {
			width: 6px;
		}

		.dropdown-notification .header-notification-scroll::-webkit-scrollbar-track {
			background: transparent;
		}

		.dropdown-notification .header-notification-scroll::-webkit-scrollbar-thumb {
			background: rgba(108, 117, 125, 0.35);
			border-radius: 999px;
		}

		.dropdown-notification .header-notification-scroll::-webkit-scrollbar-thumb:hover {
			background: rgba(108, 117, 125, 0.55);
		}

		.pc-sidebar {
			background: #ffffff;
			border-right: 1px solid #e6e9ef;
			box-shadow: 8px 0 28px rgba(16, 24, 40, 0.04);
		}

		.pc-sidebar .navbar-wrapper {
			background: #ffffff;
		}

		.pc-sidebar .m-header {
			border-bottom: 1px solid #eef1f5;
			min-height: 78px;
			padding: 18px 20px;
		}

		.pc-sidebar .b-brand {
			align-items: center !important;
			display: flex !important;
			gap: 10px;
		}

		.pc-sidebar .b-brand img {
			max-width: 155px;
			width: 155px !important;
		}

		.pc-sidebar .b-brand .badge {
			background: #eef6ff !important;
			border: 1px solid #cfe5ff;
			color: #1677ff;
			font-weight: 700;
			margin-bottom: 0 !important;
			padding: 5px 8px;
		}

		.pc-sidebar .navbar-content {
			padding: 16px 14px 24px;
		}

		.pc-sidebar .card.pc-user-card {
			background: #f8fafc;
			border: 1px solid #e6e9ef;
			border-radius: 8px;
			box-shadow: none;
			margin: 0 0 16px;
		}

		.pc-sidebar .card.pc-user-card .card-body {
			padding: 14px;
		}

		.pc-sidebar .card.pc-user-card .user-avtar {
			border: 2px solid #ffffff;
			box-shadow: 0 4px 14px rgba(16, 24, 40, 0.12);
		}

		.pc-sidebar .card.pc-user-card h6 {
			color: #1f2937;
			font-weight: 700;
			line-height: 1.25;
		}

		.pc-sidebar .card.pc-user-card small {
			color: #667085;
		}

		.pc-sidebar .pc-user-links a {
			border-radius: 8px;
			font-weight: 600;
			padding: 9px 10px;
		}

		.pc-sidebar .pc-navbar {
			margin: 0;
			padding: 0;
		}

		.pc-sidebar .pc-caption {
			align-items: center;
			color: #667085;
			display: flex;
			font-size: 11px;
			font-weight: 600;
			letter-spacing: 0.06em;
			margin: 18px 6px 8px;
			padding: 0;
			text-transform: uppercase;
		}

		.pc-sidebar .pc-caption label {
			color: inherit;
			font-size: inherit;
			font-weight: inherit;
			letter-spacing: inherit;
		}

		.pc-sidebar .pc-caption svg,
		.pc-sidebar .pc-caption i {
			display: none;
		}

		.pc-sidebar .pc-link {
			align-items: center;
			border-radius: 8px;
			color: #475467;
			display: flex;
			font-weight: 500;
			gap: 10px;
			margin: 2px 0;
			min-height: 42px;
			padding: 10px 12px;
			position: relative;
			transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
		}

		.pc-sidebar .pc-link::after {
			display: none !important;
		}

		.pc-sidebar .pc-link:hover,
		.pc-sidebar .pc-link:focus {
			background: #f3f8ff;
			color: #1677ff;
		}

		.pc-sidebar .pc-item.active > .pc-link,
		.pc-sidebar .pc-link.active {
			background: #eef6ff;
			box-shadow: inset 3px 0 0 #1677ff;
			color: #1677ff;
		}

		.pc-sidebar .pc-micon {
			align-items: center;
			background: #f8fafc;
			border: 1px solid #eef1f5;
			border-radius: 8px;
			color: inherit;
			display: inline-flex;
			flex: 0 0 30px;
			height: 30px;
			justify-content: center;
			margin: 0;
			width: 30px;
		}

		.pc-sidebar .pc-link:hover .pc-micon,
		.pc-sidebar .pc-item.active > .pc-link .pc-micon,
		.pc-sidebar .pc-link.active .pc-micon {
			background: #ffffff;
			border-color: #cfe5ff;
		}

		.pc-sidebar .pc-micon i,
		.pc-sidebar .pc-micon svg {
			font-size: 16px;
			height: 18px;
			width: 18px;
		}

		.pc-sidebar .pc-mtext {
			line-height: 1.25;
			white-space: normal;
		}

		.pc-sidebar .pc-badge {
			background: #dc2626;
			border-radius: 999px;
			color: #ffffff;
			font-size: 11px;
			font-weight: 800;
			margin-left: auto;
			min-width: 22px;
			padding: 3px 7px;
		}

		.pc-header {
			background: rgba(255, 255, 255, 0.88);
			border-bottom: 1px solid #e6e9ef;
			box-shadow: 0 8px 28px rgba(16, 24, 40, 0.05);
			backdrop-filter: blur(10px);
		}

		.pc-header .header-wrapper {
			min-height: 70px;
			padding: 0 24px;
		}

		.pc-header .pc-head-link {
			align-items: center;
			background: #f8fafc;
			border: 1px solid #e6e9ef;
			border-radius: 8px;
			color: #475467;
			display: inline-flex;
			height: 40px;
			justify-content: center;
			overflow: visible;
			width: 40px;
		}

		.pc-header .pc-head-link:hover,
		.pc-header .pc-head-link:focus {
			background: #eef6ff;
			border-color: #cfe5ff;
			color: #1677ff;
		}

		.pc-header .pc-head-link::before {
			display: none;
		}

		.pc-header .pc-head-link .pc-h-badge {
			align-items: center;
			border: 2px solid #ffffff;
			border-radius: 999px;
			box-shadow: 0 2px 6px rgba(16, 24, 40, 0.18);
			display: inline-flex;
			font-size: 10px;
			font-weight: 700;
			height: 18px;
			justify-content: center;
			line-height: 1;
			min-width: 18px;
			padding: 0 5px;
			right: -7px;
			top: -7px;
			z-index: 20;
		}

		.pc-header .drp-search,
		.pc-header .dropdown-notification,
		.pc-header .dropdown-user-profile {
			border: 1px solid #e6e9ef;
			border-radius: 8px;
			box-shadow: 0 18px 45px rgba(16, 24, 40, 0.14);
			overflow: hidden;
		}

		.pc-header .dropdown-header {
			border-bottom: 1px solid #eef1f5;
		}

		.notification-card {
			border: 1px solid #eef1f5;
			border-radius: 8px;
			box-shadow: none;
			cursor: pointer;
		}

		.notification-card:hover {
			border-color: #cfe5ff;
			box-shadow: 0 8px 18px rgba(16, 24, 40, 0.06);
		}

		[data-pc-theme="dark"] .pc-sidebar,
		[data-pc-theme="dark"] .pc-sidebar .navbar-wrapper,
		[data-pc-theme="dark"] .pc-header,
		[data-pc-theme="dark"] .pc-header .dropdown-header,
		[data-pc-theme="dark"] .pc-header .dropdown-notification,
		[data-pc-theme="dark"] .pc-header .dropdown-user-profile,
		[data-pc-theme="dark"] .pc-header .drp-search {
			background: #151a23;
			border-color: #283241;
			color: #d8dee9;
		}

		[data-pc-theme="dark"] .pc-sidebar .m-header,
		[data-pc-theme="dark"] .pc-header,
		[data-pc-theme="dark"] .pc-header .dropdown-header {
			border-color: #283241;
		}

		[data-pc-theme="dark"] .pc-sidebar .card.pc-user-card,
		[data-pc-theme="dark"] .pc-sidebar .pc-micon,
		[data-pc-theme="dark"] .pc-header .pc-head-link,
		[data-pc-theme="dark"] .notification-card,
		[data-pc-theme="dark"] .system-update-notification-card {
			background: #1b2230;
			border-color: #2f3a4b;
			color: #d8dee9;
		}

		[data-pc-theme="dark"] .pc-header .pc-head-link .pc-h-badge {
			border-color: #1f2937;
		}

		[data-pc-theme="dark"] .pc-sidebar .card.pc-user-card h6,
		[data-pc-theme="dark"] .pc-sidebar .pc-link,
		[data-pc-theme="dark"] .pc-header h5,
		[data-pc-theme="dark"] .pc-header h6 {
			color: #e7ecf3;
		}

		[data-pc-theme="dark"] .pc-sidebar .card.pc-user-card small,
		[data-pc-theme="dark"] .pc-sidebar .pc-caption,
		[data-pc-theme="dark"] .pc-header .text-muted,
		[data-pc-theme="dark"] #globalSearchResults small {
			color: #a8b3c4 !important;
		}

		[data-pc-theme="dark"] .pc-sidebar .pc-link:hover,
		[data-pc-theme="dark"] .pc-sidebar .pc-link:focus,
		[data-pc-theme="dark"] .pc-sidebar .pc-item.active > .pc-link,
		[data-pc-theme="dark"] .pc-sidebar .pc-link.active,
		[data-pc-theme="dark"] .pc-header .pc-head-link:hover,
		[data-pc-theme="dark"] .pc-header .pc-head-link:focus {
			background: #22324a;
			border-color: #355377;
			color: #8ec5ff;
		}

		[data-pc-theme="dark"] .pc-sidebar .pc-link:hover .pc-micon,
		[data-pc-theme="dark"] .pc-sidebar .pc-item.active > .pc-link .pc-micon,
		[data-pc-theme="dark"] .pc-sidebar .pc-link.active .pc-micon {
			background: #18202c;
			border-color: #355377;
		}

		[data-pc-theme="dark"] .form-control,
		[data-pc-theme="dark"] .form-select,
		[data-pc-theme="dark"] .input-group-text {
			background-color: #151a23;
			border-color: #2f3a4b;
			color: #e7ecf3;
		}

		[data-pc-theme="dark"] .form-control::placeholder {
			color: #7f8a9d;
		}

		@media (max-width: 1024px) {
			.pc-header .header-wrapper {
				padding: 0 14px;
			}
		}
	</style>

	<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">

		<!-- [ Pre-loader ] start -->
		<div class="loader-bg no-print">
			<div class="loader-track">
				<div class="loader-fill"></div>
			</div>
		</div>
		<!-- [ Pre-loader ] End -->
		<!-- [ Sidebar Menu ] start -->
		<nav class="pc-sidebar no-print">
			<div class="navbar-wrapper no-print">
				<div class="m-header no-print">
					<a href="dashboard" class="b-brand text-primary d-inline-flex align-items-end">
						<img src="assets/images/logo.png" class="img-fluid logo-md" alt="logo" style="width: 170px;" />
						<span class="badge bg-light-success rounded-pill ms-2" style="margin-bottom: 25px;">
							v2.0.0
						</span>
					</a>
				</div>
				<div class="navbar-content">
					<div class="card pc-user-card">
						<div class="card-body">
							<div class="d-flex align-items-center">
								<div class="flex-shrink-0">
									<img src="<?php echo $user['user_pfp']; ?>" alt="user-image" class="user-avtar wid-45 rounded-circle" />
								</div>
								<div class="flex-grow-1 ms-3 me-2">
									<h6 class="mb-0"><?php echo $_SESSION['full_name']; ?> </h6>
									<small><?php echo $_SESSION['position']; ?> </small>
								</div>
								<a class="btn btn-icon btn-link-secondary avtar" data-bs-toggle="collapse" href="#pc_sidebar_userlink">
									<svg class="pc-icon">
										<use xlink:href="#custom-sort-outline"></use>
									</svg>
								</a>
							</div>
							<div class="collapse pc-user-links" id="pc_sidebar_userlink">
								<div class="pt-3">
									<a href="#!">
										<i class="ti ti-user"></i>
										<span>My Account</span>
									</a>
									<a href="user_profile?tab=settings">
										<i class="ti ti-settings"></i>
										<span>Settings</span>
									</a>
									<a href="#!">
										<i class="ti ti-lock"></i>
										<span>Lock Screen</span>
									</a>
									<a href="logout">
										<i class="ti ti-power"></i>
										<span>Logout</span>
									</a>
								</div>
							</div>
						</div>
					</div>
					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['position'] === 'Administrator') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="admin_dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>
							<li class="pc-item">
								<a href="manage_department_pics" class="pc-link">
									<span class="pc-micon"><i class="ti ti-speakerphone"></i></span>
									<span class="pc-mtext">Department PIC</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="admin_system_updates" class="pc-link">
									<span class="pc-micon"><i class="ti ti-speakerphone"></i></span>
									<span class="pc-mtext">System Updates</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="admin_delete_canvass" class="pc-link">
									<span class="pc-micon"><i class="ti ti-trash"></i></span>
									<span class="pc-mtext">Delete Canvass</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Approval</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="admin_item_category" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Item Category</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="admin_supplier_category" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier Category</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>ACCREDITATION</label>
							</li>
					
							<li class="pc-item">
								<a href="suppliers" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-folder-open"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier List</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_approvals" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-file-signature"></i>
									</span>
									<span class="pc-mtext">Supplier Accreditation</span>
									<span class="pc-badge">0</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_rejected" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-folder-open"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier Rejected</span>
								</a>
							</li>
						<li class="pc-item pc-caption">
								<label>Reports</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
							    <a href="analytics" class="pc-link">
									<span class="pc-micon">
										<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-chart-mixed"></i>
									</span>
									</span>
									<span class="pc-mtext">Analytics</span>
								</a>
							
							</li>
							<li class="pc-item">
							    	<a href="generate_reports" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">PR to PO</span>
								</a>
							    
							</li>
							
						</ul>
					<?php }; ?>
					<?php
// If the user is Accounting, show the accounting section
if ($_SESSION['position'] === 'Accounting') { ?>
    <?php
        $aff_users = [97, 98, 99, 100, 101];
        $is_aff_user = in_array((int)$user_id, $aff_users);
    ?>

    <ul class="pc-navbar">
        <li class="pc-item pc-caption">
            <label>Navigation</label>
        </li>
        
        <li class="pc-item">
			<a href="accounting_cash_advance_monitoring" class="pc-link">
				<span class="pc-micon"><i class="ti ti-chart-bar"></i></span>
				<span class="pc-mtext">Monitoring</span>
			</a>
		</li>
        
        <li class="pc-item">
            <a href="manage_budget_access" class="pc-link">
                <span class="pc-micon"><i class="ti ti-key"></i></span>
                <span class="pc-mtext">Budget Access</span>
            </a>
        </li>

        <?php if ($is_aff_user) { ?>
            <li class="pc-item">
                <a href="add_budget_code_aff" class="pc-link">
                    <span class="pc-micon">
                        <svg class="pc-icon">
                            <use xlink:href="#custom-layer"></use>
                        </svg>
                    </span>
                    <span class="pc-mtext">Add Budget Code AFF</span>
                </a>
            </li>

            <li class="pc-item">
                <a href="budget_department_aff" class="pc-link">
                    <span class="pc-micon">
                        <svg class="pc-icon">
                            <use xlink:href="#custom-layer"></use>
                        </svg>
                    </span>
                    <span class="pc-mtext">Budget Code</span>
                </a>
            </li>
        <?php } else { ?>
        <li class="pc-item pc-caption">
							<label>FROM PURCHASING</label>
							<svg class="pc-icon">
								<use xlink:href="#custom-box-1"></use>
							</svg>
						</li>
	
						<li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">For SAP Doc. Number</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
							
					<?php if ($user_id === 126) { ?>
                        <li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">For SAP Doc. Number</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
                    <?php } ?>
						<li class="pc-item pc-caption">
							<label>OTHER DEPARTMENTS</label>
							<svg class="pc-icon">
								<use xlink:href="#custom-box-1"></use>
							</svg>
						</li>
	
						<li class="pc-item">
							<a href="liquidation_completion" class="pc-link">
								<span class="pc-micon">
									<i class="fa-solid fa-list"></i>
								</span>
								<span class="pc-mtext">For SAP Doc. Number</span>
								<span class="pc-badge js-liquidation-accounting-pending <?= $liquidationAccountingPendingCount > 0 ? '' : 'd-none' ?>">
									<?= $liquidationAccountingPendingCount ?>
								</span>
							</a>
						</li>
				
						<li class="pc-item pc-caption">
							<label>BUDGET CODE</label>
							<svg class="pc-icon">
								<use xlink:href="#custom-box-1"></use>
							</svg>
						</li>
                        <li class="pc-item">
                            <a href="add_budget_code" class="pc-link">
                                <span class="pc-micon">
                                    <svg class="pc-icon">
                                        <use xlink:href="#custom-layer"></use>
                                    </svg>
                                </span>
                                <span class="pc-mtext">Add Budget Code</span>
                            </a>
                        </li>
            
                        <li class="pc-item">
                            <a href="budget_utilization_department_code" class="pc-link">
                                <span class="pc-micon">
                                    <svg class="pc-icon">
                                        <use xlink:href="#custom-layer"></use>
                                    </svg>
                                </span>
                                <span class="pc-mtext">Budget Code</span>
                            </a>
                        </li>
            
                        <li class="pc-item">
                            <a href="budget_utilization_department" class="pc-link">
                                <span class="pc-micon">
                                    <svg class="pc-icon">
                                        <use xlink:href="#custom-layer"></use>
                                    </svg>
                                </span>
                                <span class="pc-mtext">Budget Department</span>
                            </a>
                        </li>
            
            
        <?php } ?>

    </ul>

<?php } ?>
<?php
					if ($_SESSION['position'] === '' || $_SESSION['position'] === NULL) { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="add_liquidation" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Add Cash Advance</span>

								</a>
							</li>
							<li class="pc-item">
								<a href="cash_advance_liquidation" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">For Liquidation</span>
									<span class="pc-badge <?= $cashAdvanceLiquidationPendingCount > 0 ? '' : 'd-none' ?>">
										<?= $cashAdvanceLiquidationPendingCount ?>
									</span>

								</a>
							</li>
							<li class="pc-item">
								<a href="my_liquidations" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">My Disbursements</span>

								</a>
							</li>
							<li class="pc-item">
								<a href="liquidation_process_flow" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Process Flow</span>

								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>BUDGET</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="budget_department" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Budget Utilization</span>
								</a>
							</li>

						
							
						</ul>
					<?php }; ?>
					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['position'] === 'Viewer') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>

						
							
						</ul>
					<?php }; ?>
					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['position'] === 'Vice Chairman') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
											
										</svg>
									</span>
							
									<span class="pc-mtext">Canvass Approval</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_completed" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Completed</span>
								</a>
							</li>
						</ul>
					<?php }; ?>
					<?php
					// If the user is an admin, show the admin section
					if (purchasingWorkflowIsTnescApprover((string)($_SESSION['position'] ?? ''))) { ?>
						<ul class="pc-navbar">
								<li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">Purchasing Approvals</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Approval</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_completed" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Completed</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>DISBURSEMENT</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="liquidation_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Liquidation Approval</span>
									<span class="pc-badge js-liquidation-gm-pending <?= $liquidationApprovalPendingCount > 0 ? '' : 'd-none' ?>">
										<?= $liquidationApprovalPendingCount ?>
									</span>
								</a>
							</li>
						</ul>
					<?php }; ?>
					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['position'] === 'Manager' && $_SESSION['department'] !== 'PURCHASING') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Approval</span>
                                        <span id="canvassCountUser" class="pc-badge">0</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_completed" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Completed</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>BUDGET</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="budget_department" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Budget Utilization</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>DISBURSEMENT</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							
							<li class="pc-item">
								<a href="liquidation_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Liquidation Approval</span>
									<span class="pc-badge js-liquidation-gm-pending <?= $liquidationApprovalPendingCount > 0 ? '' : 'd-none' ?>">
										<?= $liquidationApprovalPendingCount ?>
									</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="my_liquidations" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">My Disbursements</span>
									<span class="pc-badge js-liquidation-gm-pending <?= $liquidationApprovalPendingCount > 0 ? '' : 'd-none' ?>">
										<?= $liquidationApprovalPendingCount ?>
									</span>
								</a>
							</li>
							<?php if ($_SESSION['department'] == 'ACCOUNTING') { ?>
							
							<li class="pc-item">
    							<a href="accounting_cash_advance_monitoring" class="pc-link">
    								<span class="pc-micon"><i class="ti ti-chart-bar"></i></span>
    								<span class="pc-mtext">Monitoring</span>
    							</a>
    						</li>
							
								<li class="pc-item">
									<a href="add_budget_code" class="pc-link">
										<span class="pc-micon">
											<svg class="pc-icon">
												<use xlink:href="#custom-layer"></use>
											</svg>
										</span>
										<span class="pc-mtext">Add Budget Code</span>
									</a>
								</li>

								<li class="pc-item">
									<a href="budget_utilization_department_code" class="pc-link">
										<span class="pc-micon">
											<svg class="pc-icon">
												<use xlink:href="#custom-layer"></use>
											</svg>
										</span>
										<span class="pc-mtext">Budget Code</span>
									</a>
								</li>
								<li class="pc-item">
									<a href="budget_utilization_department" class="pc-link">
										<span class="pc-micon">
											<svg class="pc-icon">
												<use xlink:href="#custom-layer"></use>
											</svg>
										</span>
										<span class="pc-mtext">Budget Department</span>
									</a>
								</li>
								
								
								<li class="pc-item">
                                    <a href="manage_budget_access" class="pc-link">
                                        <span class="pc-micon"><i class="ti ti-key"></i></span>
                                        <span class="pc-mtext">Budget Access</span>
                                    </a>
                                </li>
                                <li class="pc-item pc-caption">
    								<label>FROM OTHER DEPARTMENTS</label>
    								<svg class="pc-icon">
    									<use xlink:href="#custom-box-1"></use>
    								</svg>
    							</li>
    							<li class="pc-item">
    								<a href="liquidation_completion" class="pc-link">
    									<span class="pc-micon">
    										<svg class="pc-icon">
    											<use xlink:href="#custom-layer"></use>
    										</svg>
    									</span>
    									<span class="pc-mtext">Liquidation Completion</span>
    									<span class="pc-badge js-liquidation-gm-pending <?= $liquidationApprovalPendingCount > 0 ? '' : 'd-none' ?>">
    										<?= $liquidationApprovalPendingCount ?>
    									</span>
    								</a>
    							</li>
								<li class="pc-item pc-caption">
									<label>FROM PURCHASING DEPARTMENT</label>
									<svg class="pc-icon">
										<use xlink:href="#custom-box-1"></use>
									</svg>
								</li>
				
								<li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">Purchasing Approvals</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
								
								
							<?php } ?>
						</ul>
					<?php }; ?>

					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['dealer'] === 'OG' || $_SESSION['position'] === 'General Manager') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>

							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Approval</span>
									<?php if ($_SESSION['dealer'] === 'OG') { ?>
                                        <span id="canvassCount" class="pc-badge">0</span>
                                    <?php } elseif ($_SESSION['position'] === 'General Manager') { ?>
                                        <span id="canvassCountUser" class="pc-badge">0</span>
                                    <?php } ?>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_completed" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Completed</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
							<label>CASH ADVANCE</label>
							<svg class="pc-icon">
								<use xlink:href="#custom-box-1"></use>
							</svg>
						</li>
							<li class="pc-item">
							<a href="purchasing_transactions" class="pc-link">
								<span class="pc-micon">
									<i class="fa-solid fa-user-check"></i>
								</span>
								<span class="pc-mtext">For GM Approval</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span>
							</a>
						</li>
						<li class="pc-item pc-caption">
								<label>DISBURSEMENT</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="liquidation_approval" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Liquidation Approval</span>
									<span class="pc-badge js-liquidation-gm-pending <?= $liquidationApprovalPendingCount > 0 ? '' : 'd-none' ?>">
										<?= $liquidationApprovalPendingCount ?>
									</span>
								</a>
							</li>
							<?php if($_SESSION['position'] !== 'Chairman') { ?>
							<li class="pc-item pc-caption">
								<label>ACCREDITATION</label>
							</li>
							<li class="pc-item">
								<a href="supplier_approvals" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-document-upload"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier Approvals</span>
									<span id="approvalCount" class="pc-badge">0</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="suppliers" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-folder-open"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier List</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_rejected" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-folder-open"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier Rejected</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>ITEM</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							<li class="pc-item">
								<a href="items" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-basket-shopping"></i>
									</span>
									<span class="pc-mtext">Item List</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>Budget Utilization</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="budget_utilization_dealer" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Budget Code (Dealer)</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="budget_code" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Budget Code</span>
								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>Reports</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="generate_reports" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">PR to PO</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="analytics" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Analytics</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="accredited_suppliers" class="pc-link">
									<span class="pc-micon">
										<i class="fa-solid fa-file-invoice"></i>
									</span>
									<span class="pc-mtext">Accredited Suppliers</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="completed_po_selected_items" class="pc-link">
									<span class="pc-micon">
										<i class="fa-solid fa-file-invoice"></i>
									</span>
									<span class="pc-mtext">Completed PO & Selected Items</span>
								</a>
							</li>
						</ul>
						<?php } ?>
					<?php }; ?>

					<?php
					// If the user is an admin, show the admin section
					if ($_SESSION['department'] === 'PURCHASING') { ?>
						<ul class="pc-navbar">
							<li class="pc-item pc-caption">
								<label>Navigation</label>
							</li>
							<li class="pc-item">
								<a href="dashboard" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Dashboard</span>

								</a>
							</li>
							<li class="pc-item">
								<a href="cash_advance_monitoring" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-status-up"></use>
										</svg>
									</span>
									<span class="pc-mtext">Cash Advance Monitoring</span>

								</a>
							</li>
							<li class="pc-item pc-caption">
								<label>PURCHASE REQUISITION</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<?php if($_SESSION['position'] !== 'Manager') { ?>
							<li class="pc-item">
								<a href="add_purchase_requisition" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-file-circle-plus"></i>
									</span>
									<span class="pc-mtext">Add PR</span>
								</a>
							</li>
							<?php } ?>
							<li class="pc-item">
								<a href="purchase_requisition" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-files"></i>
									</span>
									<span class="pc-mtext">PR List</span>
								</a>
							</li>

							<li class="pc-item pc-caption">
								<label>ACCREDITATION</label>
							</li>
							<?php if($_SESSION['position'] !== 'Manager') { ?>
							<li class="pc-item">
								<a href="add_supplier" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-element-plus"></use>
										</svg>
									</span>
									<span class="pc-mtext">Add Supplier</span>
								</a>
							</li>
							<?php } ?>
							<?php if($_SESSION['position'] !== 'Purchasing Staff') { ?>
							<li class="pc-item">
								<a href="supplier_approvals" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-file-signature"></i>
									</span>
									<span class="pc-mtext">Supplier Accreditation</span>
									<span class="pc-badge">0</span>
								</a>
							</li>
							<?php } ?>
							<li class="pc-item">
								<a href="suppliers" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-clipboard-list-check"></i>
									</span>
									<span class="pc-mtext">Supplier List</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_renewal" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-clipboard-list"></i>
									</span>
									<span class="pc-mtext">Supplier Renewal</span>
									<span id="renewalCount" class="pc-badge">0</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_pending_verification" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-clipboard-question"></i>
									</span>
									<span class="pc-mtext">Pending Verification</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="supplier_rejected" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-folder-open"></use>
										</svg>
									</span>
									<span class="pc-mtext">Supplier Rejected</span>
								</a>
							</li>
								
    						
							<li class="pc-item pc-caption">
								<label>ITEM</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<?php if($_SESSION['position'] !== 'Manager') { ?>
							<li class="pc-item">
								<a href="add_item" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-basket-shopping-plus"></i>
									</span>
									<span class="pc-mtext">Add Item</span>
								</a>
							</li>
							
							<?php } ?>
							<li class="pc-item">
								<a href="items" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-basket-shopping"></i>
									</span>
									<span class="pc-mtext">Item List</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="manage_item_categories.php" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-basket-shopping"></i>
									</span>
									<span class="pc-mtext">Manage Categories</span>
								</a>
							</li>

							<li class="pc-item pc-caption">
								<label>CANVASSING</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<?php if($_SESSION['position'] !== 'Manager') { ?>
							<li class="pc-item">
								<a href="canvass" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-layer-plus"></i>
									</span>
									<span class="pc-mtext">Create Canvass</span>
								</a>
							</li>
						
							<?php } ?>
							<?php if($_SESSION['position'] !== 'Purchasing Staff') { ?>
							<li class="pc-item">
								<a href="canvass_approval" class="pc-link">
									<span class="pc-micon">
										
										<i class="fa-solid fa-signature"></i>
									</span>
									<span class="pc-mtext">Canvass Approval</span>
									<?php if ($isCanvassManager) { ?>
										<span class="pc-badge canvass-count-user">0</span>
									<?php } ?>
								</a>
							</li>
						
							
							<?php } ?>
							<li class="pc-item">
								<a href="canvass_completed" class="pc-link">
									<span class="pc-micon">
										<svg class="pc-icon">
											<use xlink:href="#custom-layer"></use>
										</svg>
									</span>
									<span class="pc-mtext">Canvass Completed</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_history" class="pc-link">
									<span class="pc-micon">
										<i class="fa-duotone fa-solid fa-layer-group"></i>
									</span>
									<span class="pc-mtext">Canvass History</span>
								</a>
							</li>
							<li class="pc-item">
								<a href="canvass_rejected" class="pc-link">
									<span class="pc-micon">
										<i class="fa-regular fa-file-exclamation"></i>
									</span>
									<span class="pc-mtext">Canvass Rejected</span>
								</a>
							</li>
							<?php if($_SESSION['position'] !== 'Manager') { ?>
						
							<li class="pc-item pc-caption">
    							<label>CASH ADVANCE</label>
    							<svg class="pc-icon">
    								<use xlink:href="#custom-box-1"></use>
    							</svg>
    						</li>
							<li class="pc-item">
    							<a href="add_cash_advance" class="pc-link">
    								<span class="pc-micon">
    									<i class="fa-solid fa-file-invoice-dollar"></i>
    								</span>
    								<span class="pc-mtext">Add CA</span>
    							</a>
    						</li>
    						<li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">For Liquidation</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
    						<li class="pc-item">
								<a href="my_cash_advance_requests" class="pc-link">
									<span class="pc-micon"><i class="fa-solid fa-clock-rotate-left"></i></span>
									<span class="pc-mtext">My Requests</span>
								</a>
							</li>
							<?php } ?>
							<?php if($_SESSION['position'] !== 'Purchasing Staff') { ?>
						
							<li class="pc-item pc-caption">
    							<label>CASH ADVANCE</label>
    							<svg class="pc-icon">
    								<use xlink:href="#custom-box-1"></use>
    							</svg>
    						</li>
							<li class="pc-item"><a href="purchasing_transactions" class="pc-link"><span class="pc-micon"><i class="fa-solid fa-table-list"></i></span><span class="pc-mtext">Purchasing Approvals</span><span class="pc-badge purchasing-approval-badge <?= $purchasingApprovalPendingCount > 0 ? '' : 'd-none' ?>"><?= $purchasingApprovalPendingCount ?></span></a></li>
							<?php } ?>
							
							<li class="pc-item pc-caption">
								<label>Purchase Order</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="purchase_order" class="pc-link">
									<span class="pc-micon">
										<i class="fa-solid fa-file-invoice"></i>
									</span>
									<span class="pc-mtext">Add PO</span>
								</a>
							</li>
							
							<li class="pc-item pc-caption">
								<label>Reports</label>
								<svg class="pc-icon">
									<use xlink:href="#custom-box-1"></use>
								</svg>
							</li>
							<li class="pc-item">
								<a href="completed_po_selected_items" class="pc-link">
									<span class="pc-micon">
										<i class="fa-solid fa-file-invoice"></i>
									</span>
									<span class="pc-mtext">Completed PO & Selected Items</span>
								</a>
							</li>
							
						</ul>
					
				</div>
			<?php } ?>
			</div>
		</nav>
		<!-- [ Sidebar Menu ] end -->
		<!-- [ Header Topbar ] start -->
		<header class="pc-header no-print">
			<div class="header-wrapper no-print">
				<!-- [Mobile Media Block] start -->
				<div class="me-auto pc-mob-drp">
					<ul class="list-unstyled">
						<!-- ======= Menu collapse Icon ===== -->
						<li class="pc-h-item pc-sidebar-collapse">
							<a href="#" class="pc-head-link ms-0" id="sidebar-hide">
								<i class="ti ti-menu-2"></i>
							</a>
						</li>
						<li class="pc-h-item pc-sidebar-popup">
							<a href="#" class="pc-head-link ms-0" id="mobile-collapse">
								<i class="ti ti-menu-2"></i>
							</a>
						</li>
						<li class="dropdown pc-h-item">
							<a class="pc-head-link dropdown-toggle arrow-none m-0 trig-drp-search" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
								<svg class="pc-icon">
									<use xlink:href="#custom-search-normal-1"></use>
								</svg>
							</a>
							<div class="dropdown-menu pc-h-dropdown drp-search">
								<form class="px-3 py-2">
									<input type="search" id="globalSearchBox" class="form-control border-0 shadow-none" placeholder="Search here. . ." autocomplete="off" />
								</form>

								<!-- Loading spinner -->
								<div id="globalSearchLoading" class="px-3 py-2 text-center" style="display: none;">
									<div class="spinner-border text-primary spinner-border-sm" role="status">
										<span class="visually-hidden">Loading...</span>
									</div>
								</div>

								<!-- Search Results -->
								<div id="globalSearchResults" class="px-3 py-2" style="max-height: 300px; overflow-y: auto;"></div>
							</div>
						</li>

					</ul>
				</div>
				<!-- [Mobile Media Block end] -->
				<div class="ms-auto">
					<ul class="list-unstyled">
					    <li class="dropdown pc-h-item">
							<a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-label="System updates">
								<i class="ti ti-speakerphone f-20"></i>
								<span id="system-update-badge" class="badge bg-warning text-dark pc-h-badge<?= $systemUpdateUnreadCount ? '' : ' d-none' ?>"><?= $systemUpdateUnreadCount ?></span>
							</a>
							<div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
								<div class="dropdown-header d-flex align-items-center justify-content-between">
									<h5 class="m-0">System Updates</h5>
									<?php if ($systemUpdateUnreadCount): ?>
										<button type="button" id="mark-all-system-updates-read" class="btn btn-link btn-sm">Mark all read</button>
									<?php endif; ?>
								</div>
								<div class="dropdown-body text-wrap header-notification-scroll" style="max-height: calc(100vh - 215px)">
									<?php if (!$systemUpdates): ?>
										<p class="text-center text-muted my-3">No system updates</p>
									<?php else: ?>
										<?php foreach ($systemUpdates as $systemUpdate): ?>
											<?php $updateColor = ['info' => 'primary', 'success' => 'success', 'warning' => 'warning', 'critical' => 'danger'][$systemUpdate['severity']] ?? 'primary'; ?>
											<div class="card mb-2 system-update-notification-card <?= $systemUpdate['is_read'] ? 'opacity-50' : '' ?>" data-id="<?= (int)$systemUpdate['update_id'] ?>" style="cursor: pointer;">
												<div class="card-body py-2 px-3">
													<div class="d-flex align-items-start">
														<i class="ti ti-speakerphone text-<?= $updateColor ?> f-20 me-2 mt-1"></i>
														<div class="flex-grow-1 overflow-hidden">
															<h6 class="mb-1 fw-semibold text-truncate"><?= htmlspecialchars($systemUpdate['title']) ?></h6>
															<small class="text-muted d-block text-truncate"><?= htmlspecialchars($systemUpdate['message']) ?></small>
															<small class="text-muted d-block mt-1"><?= time_elapsed_string($systemUpdate['published_at']) ?></small>
														</div>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<div class="text-center py-2 border-top">
									<a href="system_updates" class="btn btn-link btn-sm">View all system updates</a>
								</div>
							</div>
						</li>
						<li class="dropdown pc-h-item">
							<a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
								<svg class="pc-icon">
									<use xlink:href="#custom-notification"></use>
								</svg>
								<span id="notif-badge" class="badge bg-success pc-h-badge">
									<?= $notificationUnreadCount > 0 ? $notificationUnreadCount : '' ?>
								</span>
							</a>
							<div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
								<div class="dropdown-header d-flex align-items-center justify-content-between">
									<h5 class="m-0">Notifications</h5>
									<button type="button" id="mark-all-notifications-read" class="btn btn-link btn-sm <?= $notificationUnreadCount === 0 ? 'd-none' : '' ?>">Mark all read</button>
								</div>
								<div class="dropdown-body text-wrap header-notification-scroll">
									<div id="notifications-container">
										<?php if (empty($notifications)): ?>
											<p class="notification-empty-state text-center text-muted my-0">No notifications yet</p>
										<?php else: ?>
											<?php foreach ($notifications as $notif): ?>
												<div class="card mb-2 notification-card <?= $notif['is_read'] ? 'opacity-50' : '' ?>"
													data-id="<?= $notif['id'] ?>"
													data-link="<?= htmlspecialchars($notif['link'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
													<div class="card-body py-2 px-3">
														<div class="d-flex align-items-center">
															<svg class="pc-icon me-2 
															 <?= $notif['type'] === 'success' ? 'text-success'
																: ($notif['type'] === 'warning' ? 'text-warning'
																	: ($notif['type'] === 'error' ? 'text-danger'
																		: 'text-primary')) ?>"
																style="width: 20px; height: 20px;">
																<use xlink:href="#custom-layer"></use>
															</svg>
															<div class="flex-grow-1">
																<h6 class="mb-1 fw-semibold"><?= htmlspecialchars($notif['title']) ?></h6>
																<small class="text-muted d-block"><?= htmlspecialchars($notif['message']) ?></small>
																<small class="text-muted d-block mt-1"><?= time_elapsed_string($notif['created_at']) ?></small>
															</div>
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
								</div>
								<div class="d-flex align-items-center justify-content-center gap-3 py-2 border-top">
									<a href="notifications" class="btn btn-link btn-sm">View all</a>
									<!--<a href="notifications?filter=unread" class="btn btn-link btn-sm">Unread</a>-->
									<button type="button" id="refresh-notifications" class="btn btn-link btn-sm">Refresh</button>
								</div>
							</div>
						</li>

						<?php
						// Helper function for "x minutes ago"
						function time_elapsed_string($datetime)
						{
							$time = strtotime($datetime);
							$diff = time() - $time;
							if ($diff < 60) return $diff . ' sec ago';
							if ($diff < 3600) return floor($diff / 60) . ' min ago';
							if ($diff < 86400) return floor($diff / 3600) . ' hour ago';
							return date('M d, Y', $time);
						}
						?>
						<li class="dropdown pc-h-item header-user-profile">
							<a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" data-bs-auto-close="outside" aria-expanded="false">
								<img src="<?php echo $user['user_pfp']; ?>" alt="user-image" class="user-avtar" />
							</a>
							<div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
								<div class="dropdown-header d-flex align-items-center justify-content-between">
									<h5 class="m-0">Profile</h5>
								</div>
								<div class="dropdown-body">
									<div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">
										<div class="d-flex mb-1">
											<div class="flex-shrink-0">
												<img src="<?php echo $user['user_pfp']; ?>" alt="user-image" class="user-avtar wid-35" />
											</div>
											<div class="flex-grow-1 ms-3">
												<h6 class="mb-1"><?php echo $user['full_name']; ?></h6>
												<span><?php echo $user['position']; ?></span>
											</div>
										</div>
										<hr class="border-secondary border-opacity-50" />
										<div class="card" hidden>
											<div class="card-body py-3">
												<div class="d-flex align-items-center justify-content-between">
													<h5 class="mb-0 d-inline-flex align-items-center">
														<svg class="pc-icon text-muted me-2">
															<use xlink:href="#custom-notification-outline"></use>
														</svg>
														Notification
													</h5>
													<div class="form-check form-switch form-check-reverse m-0">
														<input class="form-check-input f-18" type="checkbox" role="switch" />
													</div>
												</div>
											</div>
										</div>
										<p class="text-span">Manage</p>
										<a href="user_profile?tab=settings" class="dropdown-item">
											<span>
												<svg class="pc-icon text-muted me-2">
													<use xlink:href="#custom-setting-outline"></use>
												</svg>
												<span>Settings</span>
											</span>
										</a>
										<a href="user_change_password" class="dropdown-item">
											<span>
												<svg class="pc-icon text-muted me-2">
													<use xlink:href="#custom-lock-outline"></use>
												</svg>
												<span>Change Password</span>
											</span>
										</a>
										<p class="text-span" hidden>Team</p>
										<a href="#" class="dropdown-item" hidden>
											<span>
												<svg class="pc-icon text-muted me-2">
													<use xlink:href="#custom-profile-2user-outline"></use>
												</svg>
												<span>UI Design team</span>
											</span>
											<div class="user-group">
												<img src="assets/images/user/avatar-1.jpg" alt="user-image" class="avtar" />
												<span class="avtar bg-danger text-white">K</span>
												<span class="avtar bg-success text-white">
													<svg class="pc-icon m-0">
														<use xlink:href="#custom-user"></use>
													</svg>
												</span>
												<span class="avtar bg-light-primary text-primary">+2</span>
											</div>
										</a>
										<a href="#" class="dropdown-item" hidden>
											<span>
												<svg class="pc-icon text-muted me-2">
													<use xlink:href="#custom-profile-2user-outline"></use>
												</svg>
												<span>Friends Groups</span>
											</span>
											<div class="user-group">
												<img src="assets/images/user/avatar-1.jpg" alt="user-image" class="avtar" />
												<span class="avtar bg-danger text-white">K</span>
												<span class="avtar bg-success text-white">
													<svg class="pc-icon m-0">
														<use xlink:href="#custom-user"></use>
													</svg>
												</span>
											</div>
										</a>
										<a href="#" class="dropdown-item" hidden>
											<span>
												<svg class="pc-icon text-muted me-2">
													<use xlink:href="#custom-add-outline"></use>
												</svg>
												<span>Add new</span>
											</span>
											<div class="user-group">
												<span class="avtar bg-primary text-white">
													<svg class="pc-icon m-0">
														<use xlink:href="#custom-add-outline"></use>
													</svg>
												</span>
											</div>
										</a>
										<hr class="border-secondary border-opacity-50" />
										<div class="d-grid mb-3">
											<a href="logout" class="btn btn-primary">
												<svg class="pc-icon me-2">
													<use xlink:href="#custom-logout-1-outline"></use>
												</svg>
												Logout
											</a>
										</div>
										<div class="card border-0 shadow-none drp-upgrade-card mb-0" style="background-image: url(assets/images/layout/img-profile-card.jpg)" hidden>
											<div class="card-body">
												<div class="user-group">
													<!--<img src="assets/images/user/avatar-1.jpg" alt="user-image" class="avtar" />-->
													<!--<img src="assets/images/user/avatar-2.jpg" alt="user-image" class="avtar" />-->
													<!--<img src="assets/images/user/avatar-3.jpg" alt="user-image" class="avtar" />-->
													<!--<img src="assets/images/user/avatar-4.jpg" alt="user-image" class="avtar" />-->
													<!--<img src="assets/images/user/avatar-5.jpg" alt="user-image" class="avtar" />-->
													<!--<span class="avtar bg-light-primary text-primary">+20</span>-->
												</div>
												<h3 class="my-3 text-dark">245.3k <small class="text-muted">Followers</small></h3>
												<div class="btn btn btn-warning">
													<svg class="pc-icon me-2">
														<use xlink:href="#custom-logout-1-outline"></use>
													</svg>
													Upgrade to Business
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</li>
					</ul>
				</div>
			</div>
		</header>
		<!-- [ Header ] end -->
		<script src="https://js.pusher.com/7.0/pusher.min.js" defer></script>
		<script>
			document.addEventListener("DOMContentLoaded", () => {
				const currentPath = window.location.pathname.split('/').pop().replace(/\.php$/, '');
				document.querySelectorAll('.pc-sidebar .pc-link[href]').forEach((link) => {
					const linkPath = link.getAttribute('href').split('?')[0].split('/').pop().replace(/\.php$/, '');
					if (linkPath && linkPath === currentPath) {
						link.classList.add('active');
						link.closest('.pc-item')?.classList.add('active');
					}
				});

				const userPosition = '<?= $_SESSION['position'] ?? '' ?>';
				const userId = "<?= $_SESSION['user_id'] ?? '' ?>";

				// ==== 1️⃣ Fetch all counts in parallel ====
				// ==== 1️⃣ Fetch all counts in parallel ====
				Promise.all([
					fetch('data/approval_counts.php').then(r => r.json()).catch(() => ({})),
					fetch('data/get_canvass_counts.php').then(r => r.json()).catch(() => ({})),
					fetch('data/get_canvass_count_user.php').then(r => r.json()).catch(() => ({
						total: 0
					})),
					fetch('data/supplier_renewal_counts.php').then(r => r.json()).catch(() => []),
					fetch('data/supplier_memorandum_renewal_counts.php').then(r => r.json()).catch(() => [])
				]).then(([approval, canvass, canvassUser, renewal, memoRenewal]) => {
					const getVal = (obj, key) => (obj && obj[key] !== undefined ? obj[key] : '0');
					const setText = (selector, value) => {
						document.querySelectorAll(selector).forEach((element) => {
							element.textContent = value;
						});
					};

					setText('#approvalCount', getVal(approval, userPosition));
					setText('#canvassCount', getVal(canvass, userPosition));
					setText('.canvass-count-user', canvassUser.total ?? '0');
					setText('#renewalCount', renewal.length ?? '0');
					setText('#memoRenewalCount', memoRenewal.length ?? '0');
				});

				// Notifications
				const notificationContainer = document.getElementById('notifications-container');
				const notificationBadge = document.getElementById('notif-badge');
				const markAllNotificationsRead = document.getElementById('mark-all-notifications-read');
				const refreshNotificationsButton = document.getElementById('refresh-notifications');

				function notificationColorClass(type) {
					if (type === 'success') return 'text-success';
					if (type === 'warning') return 'text-warning';
					if (type === 'error') return 'text-danger';
					return 'text-primary';
				}

				function setUnreadCount(count) {
					const unreadCount = Number(count) || 0;
					if (!notificationBadge) return;
					notificationBadge.textContent = unreadCount > 0 ? unreadCount : '';
					markAllNotificationsRead?.classList.toggle('d-none', unreadCount === 0);
				}

				function renderNotificationCard(notif) {
					const card = document.createElement('div');
					const notificationId = notif.id || notif.notification_id || '';
					card.className = `card mb-2 notification-card ${Number(notif.is_read) ? 'opacity-50' : ''}`;
					card.dataset.id = notificationId;
					card.dataset.link = notif.link || '';
					card.innerHTML = `
						<div class="card-body py-2 px-3">
							<div class="d-flex align-items-center">
								<svg class="pc-icon me-2 ${notificationColorClass(notif.type)}" style="width:20px;height:20px;">
									<use xlink:href="#custom-layer"></use>
								</svg>
								<div class="flex-grow-1">
									<h6 class="mb-1 fw-semibold">${escapeHtml(notif.title || 'Notification')}</h6>
									<small class="text-muted d-block">${escapeHtml(notif.message || '')}</small>
									<small class="text-muted d-block mt-1">${timeElapsedString(notif.created_at)}</small>
								</div>
							</div>
						</div>`;
					return card;
				}

				function renderNotifications(notifications) {
					if (!notificationContainer) return;
					notificationContainer.innerHTML = '';
					if (!notifications.length) {
						notificationContainer.innerHTML = '<p class="notification-empty-state text-center text-muted my-0">No notifications yet</p>';
						return;
					}
					notifications.forEach(notif => notificationContainer.appendChild(renderNotificationCard(notif)));
				}

				function notificationExists(notificationId) {
					return Array.from(document.querySelectorAll('.notification-card'))
						.some(card => card.dataset.id === String(notificationId));
				}

				function refreshNotifications() {
					return $.getJSON('data/fetch_notifications.php')
						.done(function(response) {
							const notifications = Array.isArray(response.notifications) ? response.notifications : [];
							renderNotifications(notifications);
							setUnreadCount(response.unread_count ?? notifications.filter(n => Number(n.is_read) === 0).length);
						});
				}

				// $(document).on('click', '.notification-card', function() {
				// 	const notifCard = $(this);
				// 	const notifId = notifCard.data('id');
				// 	const notifLink = notifCard.data('link');
				// 	if (!notifId) return;

				// 	$.post('data/update_mark_read.php', { id: notifId }, function(response) {
				// 		const res = typeof response === 'string' ? JSON.parse(response) : response;
				// 		if (res.success) {
				// 			notifCard.addClass('opacity-50');
				// 			refreshNotifications().always(function() {
				// 				if (notifLink) window.location.href = notifLink;
				// 			});
				// 		}
				// 	}, 'json');
				// });
				$(document).on('click', '.notification-card', function() {
					const notifCard = $(this);
					const notifId = notifCard.data('id');
					const notifLink = notifCard.data('link');
					if (!notifId) return;

					$.post('data/update_mark_read.php', { id: notifId }, function(response) {
						const res = typeof response === 'string' ? JSON.parse(response) : response;
						if (res.success) {
							notifCard.addClass('opacity-50');
							if (notifLink) {
								window.location.href = notifLink;
								return;
							}
							refreshNotifications();
						}
					}, 'json');
				});

				markAllNotificationsRead?.addEventListener('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					$.post('data/update_mark_all_read.php', {}, function(response) {
						const res = typeof response === 'string' ? JSON.parse(response) : response;
						if (!res.success) return;
						document.querySelectorAll('.notification-card').forEach(card => card.classList.add('opacity-50'));
						setUnreadCount(0);
					}, 'json');
				});

				refreshNotificationsButton?.addEventListener('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					refreshNotifications();
				});

				// System update notifications
				$(document).on('click', '.system-update-notification-card', function() {
					const updateId = $(this).data('id');
					$.post('data/mark_system_update_read.php', { id: updateId })
						.always(function() {
							window.location.href = 'system_updates#update-' + updateId;
						});
				});

				$('#mark-all-system-updates-read').on('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					$.post('data/mark_system_update_read.php', { all: '1' }, function(result) {
						if (typeof result === 'string') result = JSON.parse(result);
						if (!result.success) return;
						$('.system-update-notification-card').addClass('opacity-50');
						$('#system-update-badge').text('').addClass('d-none');
						$('#mark-all-system-updates-read').remove();
					});
				});

				const pusher = new Pusher('7f506782df003022bff5', {
					cluster: 'ap1',
					authEndpoint: 'pusher-auth.php'
				});

				const channel = pusher.subscribe('private-user-' + userId);
				channel.bind('my-event', function(data) {
					if (!data?.notifications || !Array.isArray(data.notifications)) return;
					const notifications = data.notifications;

					notifications.forEach(notif => {
						const notifId = notif.id || notif.notification_id;
						if (notifId && notificationExists(notifId)) return;
						notificationContainer?.querySelector('.notification-empty-state')?.remove();
						notificationContainer?.prepend(renderNotificationCard(notif));
					});

					const currentCount = parseInt(notificationBadge.textContent) || 0;
					const newUnread = notifications.filter(n => !n.is_read).length;
					setUnreadCount(currentCount + newUnread);
				});

				// Helpers
				function escapeHtml(text) {
					const div = document.createElement('div');
					div.textContent = text;
					return div.innerHTML;
				}

				function timeElapsedString(dateString) {
					if (!dateString) return "Just now";
					const date = new Date(dateString);
					if (Number.isNaN(date.getTime())) return "Just now";
					const seconds = Math.floor((new Date() - date) / 1000);
					const minutes = Math.floor(seconds / 60);
					if (minutes < 1) return "Just now";
					if (minutes < 60) return `${minutes} min ago`;
					const hours = Math.floor(minutes / 60);
					if (hours < 24) return `${hours} hours ago`;
					const days = Math.floor(hours / 24);
					return `${days} days ago`;
				}

				// ==== 4️⃣ Global Search with Debounce ====
				const searchBox = document.getElementById('globalSearchBox');
				const resultsDiv = document.getElementById('globalSearchResults');
				const loadingDiv = document.getElementById('globalSearchLoading');
				let timeout;

				searchBox.addEventListener('input', function() {
					clearTimeout(timeout);
					const query = this.value.trim();

					if (query.length < 2) {
						resultsDiv.innerHTML = '';
						loadingDiv.style.display = 'none';
						return;
					}

					loadingDiv.style.display = 'block';
					resultsDiv.innerHTML = '';

					timeout = setTimeout(() => {
						fetch('data/view_global_search.php?q=' + encodeURIComponent(query))
							.then(r => r.text())
							.then(data => {
								loadingDiv.style.display = 'none';
								resultsDiv.innerHTML = data;
							})
							.catch(err => {
								loadingDiv.style.display = 'none';
								resultsDiv.innerHTML = '<p class="text-danger">Search failed.</p>';
								console.error('Search error:', err);
							});
					}, 400); // debounce delay
				});
			});
		</script>
