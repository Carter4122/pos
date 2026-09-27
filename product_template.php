<?php

session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied.");
}

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=product_upload_template.csv");

$output = fopen("php://output", "wb");
fputcsv($output, [
    "product_name",
    "product_code",
    "category",
    "description",
    "cost_price",
    "selling_price",
    "stock_quantity",
    "reorder_level"
]);
fclose($output);