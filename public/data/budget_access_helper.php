<?php

function ensureBudgetUserAccessTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budget_user_access (
            access_id INT AUTO_INCREMENT PRIMARY KEY,
            budget_id INT NOT NULL,
            user_id INT NOT NULL,
            granted_by INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_budget_user_access (budget_id, user_id),
            INDEX idx_budget_user_access_user (user_id),
            INDEX idx_budget_user_access_granted_by (granted_by),
            CONSTRAINT fk_budget_user_access_budget FOREIGN KEY (budget_id) REFERENCES budgets (budget_id) ON DELETE CASCADE,
            CONSTRAINT fk_budget_user_access_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
            CONSTRAINT fk_budget_user_access_grantor FOREIGN KEY (granted_by) REFERENCES users (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function budgetAccessCanManage(array $session): bool
{
    return ($session['position'] ?? '') === 'Administrator'
        || ($session['position'] ?? '') === 'Accounting'
        || in_array(strtoupper(trim((string)($session['department'] ?? ''))), ['ACCOUNTING', 'FINANCE'], true);
}

function budgetAccessCanManageAllDealers(array $session): bool
{
    return ($session['position'] ?? '') === 'Administrator';
}

function budgetAccessIsAssignedPic(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN department_pic_assignments dpa
            ON dpa.pic_user_id = u.user_id
           AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(u.department))
           AND UPPER(TRIM(dpa.dealer)) = UPPER(TRIM(u.dealer))
        WHERE u.user_id = :user_id
          AND u.is_active = 1
    ");
    $stmt->execute([':user_id' => $userId]);

    return (int)$stmt->fetchColumn() > 0;
}

function budgetAccessGetAllowedBudget(
    PDO $pdo,
    int $budgetId,
    int $userId,
    string $department,
    string $dealer
): ?array {
    $stmt = $pdo->prepare("
        SELECT
            b.budget_id,
            b.budget_code,
            b.budget_activity,
            b.approved_budget,
            b.budget_department,
            b.budget_dealer,
            CASE
                WHEN UPPER(TRIM(b.budget_department)) = UPPER(TRIM(:department_select)) THEN 'native'
                ELSE 'granted'
            END AS access_source
        FROM budgets b
        WHERE b.budget_id = :budget_id
          AND UPPER(TRIM(b.budget_dealer)) = UPPER(TRIM(:dealer))
          AND (
              UPPER(TRIM(b.budget_department)) = UPPER(TRIM(:department_access))
              OR (
                  EXISTS (
                      SELECT 1
                      FROM budget_user_access bua
                      WHERE bua.budget_id = b.budget_id
                        AND bua.user_id = :user_id_grant
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM users u
                      INNER JOIN department_pic_assignments dpa
                          ON dpa.pic_user_id = u.user_id
                         AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(u.department))
                         AND UPPER(TRIM(dpa.dealer)) = UPPER(TRIM(u.dealer))
                      WHERE u.user_id = :user_id_pic
                        AND u.is_active = 1
                  )
              )
          )
        LIMIT 1
    ");
    $stmt->execute([
        ':department_select' => $department,
        ':budget_id' => $budgetId,
        ':dealer' => $dealer,
        ':department_access' => $department,
        ':user_id_grant' => $userId,
        ':user_id_pic' => $userId,
    ]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    return $budget ?: null;
}

function budgetAccessGetUserBudgets(
    PDO $pdo,
    int $userId,
    string $department,
    string $dealer,
    string $search = ''
): array {
    $sql = "
        SELECT
            b.budget_id,
            b.budget_code,
            b.budget_activity,
            b.approved_budget,
            b.budget_department,
            b.budget_dealer,
            CASE
                WHEN UPPER(TRIM(b.budget_department)) = UPPER(TRIM(:department_select)) THEN 'native'
                ELSE 'granted'
            END AS access_source
        FROM budgets b
        WHERE UPPER(TRIM(b.budget_dealer)) = UPPER(TRIM(:dealer))
          AND (
              UPPER(TRIM(b.budget_department)) = UPPER(TRIM(:department_access))
              OR (
                  EXISTS (
                      SELECT 1 FROM budget_user_access bua
                      WHERE bua.budget_id = b.budget_id AND bua.user_id = :user_id_grant
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM users u
                      INNER JOIN department_pic_assignments dpa
                          ON dpa.pic_user_id = u.user_id
                         AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(u.department))
                         AND UPPER(TRIM(dpa.dealer)) = UPPER(TRIM(u.dealer))
                      WHERE u.user_id = :user_id_pic AND u.is_active = 1
                  )
              )
          )
    ";
    $params = [
        ':department_select' => $department,
        ':dealer' => $dealer,
        ':department_access' => $department,
        ':user_id_grant' => $userId,
        ':user_id_pic' => $userId,
    ];

    if ($search !== '') {
        $sql .= " AND (b.budget_code LIKE :search OR b.budget_activity LIKE :search OR b.budget_department LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY b.budget_department ASC, b.budget_code ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
