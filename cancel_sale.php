<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

if ($_SESSION["role"] != "admin") {
    header("Location: home.php");
    exit();
}

// Only accept a form submission that includes a sale id
if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST["sale_id"])) {
    header("Location: sales_history.php");
    exit();
}

require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$sale_id = intval($_POST["sale_id"]);
$cancel_reason = trim($_POST["cancel_reason"] ?? "");

if ($cancel_reason == "") {
    die("A reason is required to cancel a sale.");
}

$cancel_reason = substr($cancel_reason, 0, 255);

$conn->begin_transaction();

try {

    // Find the sale and make sure it is not already cancelled
    $stmt = $conn->prepare("SELECT status FROM sales WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $stmt->close();

    if ($result->num_rows == 0) {
        throw new Exception("Sale not found.");
    }

    $sale = $result->fetch_assoc();

    if ($sale["status"] == "cancelled") {
        throw new Exception("This sale is already cancelled.");
    }

    // Get the items that were sold
    $stmt = $conn->prepare(
        "SELECT product_id, quantity FROM sale_items WHERE sale_id = ?"
    );
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();

    $items = $stmt->get_result();

    $stmt->close();

    // Put the stock back, one item at a time
    while ($item = $items->fetch_assoc()) {

        $stmt = $conn->prepare(
            "UPDATE products
             SET stock_quantity = stock_quantity + ?
             WHERE id = ?"
        );
        $stmt->bind_param("ii", $item["quantity"], $item["product_id"]);
        $stmt->execute();
        $stmt->close();
    }

    // Mark the sale as cancelled (the record is kept, not deleted)
    $stmt = $conn->prepare(
        "UPDATE sales
         SET status = 'cancelled',
             cancelled_by = ?,
             cancelled_at = NOW(),
             cancel_reason = ?
         WHERE id = ?"
    );
    $stmt->bind_param("isi", $_SESSION["user_id"], $cancel_reason, $sale_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    header("Location: sales_history.php");
    exit();

} catch (Exception $e) {

    $conn->rollback();

    die("Could not cancel sale: " . htmlspecialchars($e->getMessage()));
}

?>