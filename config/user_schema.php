<?php
function getUserSchema(PDO $pdo): array
{
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
    $nameColumn = in_array('full_name', $columns, true)
        ? 'full_name'
        : (in_array('name', $columns, true) ? 'name' : null);

    if ($nameColumn === null || !in_array('email', $columns, true) || !in_array('password', $columns, true)) {
        throw new RuntimeException("Bảng users thiếu các cột bắt buộc");
    }

    return [
        "columns" => $columns,
        "name" => $nameColumn,
        "username" => in_array('username', $columns, true) ? 'username' : null
    ];
}
