<?php

function marketplace_table_columns(mysqli $conn, string $table): array
{
    static $cache = [];

    if (!isset($cache[$table])) {
        $safe_table = str_replace('`', '``', $table);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safe_table}`");
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = $row;
        }
        $cache[$table] = $columns;
    }

    return $cache[$table];
}

function marketplace_first_existing_column(mysqli $conn, string $table, array $candidates): ?string
{
    $columns = marketplace_table_columns($conn, $table);
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function marketplace_order_total_column(mysqli $conn): string
{
    $column = marketplace_first_existing_column($conn, 'orders', ['total_price', 'total']);
    if ($column === null) {
        throw new RuntimeException('Orders table is missing a total column.');
    }

    return $column;
}

function marketplace_order_date_column(mysqli $conn): ?string
{
    return marketplace_first_existing_column($conn, 'orders', ['order_date', 'created_at']);
}
