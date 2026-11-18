<?php

function ensureItemCategoryTables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_category_pairs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_category VARCHAR(255) NOT NULL,
            sub_category VARCHAR(255) NOT NULL,
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_item_category_pair (product_category, sub_category),
            INDEX idx_item_category_pairs_product (product_category),
            INDEX idx_item_category_pairs_sub (sub_category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_sub_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_category VARCHAR(255) NOT NULL DEFAULT '',
            sub_category VARCHAR(255) NOT NULL,
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_item_sub_category_pair (item_category, sub_category),
            INDEX idx_item_sub_categories_category (item_category),
            INDEX idx_item_sub_categories_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    ensureItemSubCategorySchema($pdo);

    $pdo->exec("
        INSERT IGNORE INTO item_category_pairs (product_category, sub_category)
        SELECT DISTINCT TRIM(product_category), TRIM(sub_category)
        FROM items
        WHERE COALESCE(TRIM(product_category), '') != ''
            AND COALESCE(TRIM(sub_category), '') != ''
    ");

    $pdo->exec("
        UPDATE item_sub_categories sc
        INNER JOIN (
            SELECT TRIM(sub_category) AS sub_category, MIN(TRIM(item_category)) AS item_category
            FROM items
            WHERE COALESCE(TRIM(item_category), '') != ''
                AND COALESCE(TRIM(sub_category), '') != ''
            GROUP BY TRIM(sub_category)
        ) existing_items ON existing_items.sub_category = sc.sub_category
        SET sc.item_category = existing_items.item_category
        WHERE COALESCE(TRIM(sc.item_category), '') = ''
    ");

    $pdo->exec("
        INSERT IGNORE INTO item_sub_categories (item_category, sub_category)
        SELECT DISTINCT TRIM(item_category), TRIM(sub_category)
        FROM items
        WHERE COALESCE(TRIM(item_category), '') != ''
            AND COALESCE(TRIM(sub_category), '') != ''
    ");
}

function ensureItemSubCategorySchema(PDO $pdo): void
{
    if (!tableColumnExists($pdo, 'item_sub_categories', 'item_category')) {
        $pdo->exec("
            ALTER TABLE item_sub_categories
            ADD COLUMN item_category VARCHAR(255) NOT NULL DEFAULT '' AFTER id
        ");
    }

    if (tableIndexExists($pdo, 'item_sub_categories', 'uq_item_sub_category')) {
        $pdo->exec("ALTER TABLE item_sub_categories DROP INDEX uq_item_sub_category");
    }

    $pdo->exec("
        DELETE sc_blank
        FROM item_sub_categories sc_blank
        INNER JOIN item_sub_categories sc_mapped
            ON sc_mapped.sub_category = sc_blank.sub_category
            AND COALESCE(TRIM(sc_blank.item_category), '') = ''
            AND COALESCE(TRIM(sc_mapped.item_category), '') != ''
    ");

    $pdo->exec("
        UPDATE item_sub_categories sc
        INNER JOIN (
            SELECT TRIM(sub_category) AS sub_category, MIN(TRIM(item_category)) AS item_category
            FROM items
            WHERE COALESCE(TRIM(item_category), '') != ''
                AND COALESCE(TRIM(sub_category), '') != ''
            GROUP BY TRIM(sub_category)
        ) existing_items ON existing_items.sub_category = sc.sub_category
        SET sc.item_category = existing_items.item_category
        WHERE COALESCE(TRIM(sc.item_category), '') = ''
    ");

    $pdo->exec("
        DELETE sc_duplicate
        FROM item_sub_categories sc_duplicate
        INNER JOIN item_sub_categories sc_keep
            ON sc_keep.item_category = sc_duplicate.item_category
            AND sc_keep.sub_category = sc_duplicate.sub_category
            AND sc_keep.id < sc_duplicate.id
    ");

    if (!tableIndexExists($pdo, 'item_sub_categories', 'idx_item_sub_categories_category')) {
        execIgnoringDuplicateIndex($pdo, "ALTER TABLE item_sub_categories ADD INDEX idx_item_sub_categories_category (item_category)");
    }

    if (!tableIndexExists($pdo, 'item_sub_categories', 'uq_item_sub_category_pair')) {
        execIgnoringDuplicateIndex($pdo, "ALTER TABLE item_sub_categories ADD UNIQUE KEY uq_item_sub_category_pair (item_category, sub_category)");
    }
}

function execIgnoringDuplicateIndex(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1061) {
            throw $e;
        }
    }
}

function tableColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tableName, $columnName]);

    return (int)$stmt->fetchColumn() > 0;
}

function tableIndexExists(PDO $pdo, string $tableName, string $indexName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
    ");
    $stmt->execute([$tableName, $indexName]);

    return (int)$stmt->fetchColumn() > 0;
}

function getManagedItemCategories(PDO $pdo): array
{
    ensureItemCategoryTables($pdo);

    $stmt = $pdo->query("
        SELECT category_name
        FROM item_categories
        WHERE category_name IS NOT NULL AND category_name != ''
        ORDER BY category_name ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getManagedProductCategories(PDO $pdo): array
{
    ensureItemCategoryTables($pdo);

    $stmt = $pdo->query("
        SELECT DISTINCT product_category
        FROM item_category_pairs
        WHERE product_category IS NOT NULL AND product_category != ''
        ORDER BY product_category ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getManagedSubCategoryOptions(PDO $pdo): array
{
    ensureItemCategoryTables($pdo);

    $stmt = $pdo->query("
        SELECT
            item_category,
            sub_category
        FROM item_sub_categories
        WHERE sub_category IS NOT NULL AND sub_category != ''
        ORDER BY item_category ASC, sub_category ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function itemSubCategoryExists(PDO $pdo, string $subCategory, ?string $itemCategory = null): bool
{
    ensureItemCategoryTables($pdo);

    if ($itemCategory !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM item_sub_categories
            WHERE item_category = ?
                AND sub_category = ?
        ");
        $stmt->execute([$itemCategory, $subCategory]);

        return (int)$stmt->fetchColumn() > 0;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_sub_categories WHERE sub_category = ?");
    $stmt->execute([$subCategory]);

    return (int)$stmt->fetchColumn() > 0;
}

function itemCategoryExists(PDO $pdo, string $categoryName): bool
{
    ensureItemCategoryTables($pdo);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_categories WHERE category_name = ?");
    $stmt->execute([$categoryName]);

    return (int)$stmt->fetchColumn() > 0;
}

function itemCategoryPairExists(PDO $pdo, string $productCategory, string $subCategory): bool
{
    ensureItemCategoryTables($pdo);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM item_category_pairs
        WHERE product_category = ?
            AND sub_category = ?
    ");
    $stmt->execute([$productCategory, $subCategory]);

    return (int)$stmt->fetchColumn() > 0;
}
