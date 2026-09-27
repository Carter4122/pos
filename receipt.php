<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

if (!isset($_GET["sale_id"])) {
    die("Sale not found.");
}

$sale_id = intval($_GET["sale_id"]);

// Get sale
$stmt = $conn->prepare("
    SELECT sales.*, users.full_name
    FROM sales
    INNER JOIN users ON sales.user_id = users.id
    WHERE sales.id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();

$sale_result = $stmt->get_result();

if ($sale_result->num_rows == 0) {
    die("Sale not found.");
}

$sale = $sale_result->fetch_assoc();

$stmt->close();

// Who cancelled it (only for cancelled sales)
$cancelled_by_name = "";

if ($sale["status"] == "cancelled" && $sale["cancelled_by"]) {

    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $sale["cancelled_by"]);
    $stmt->execute();

    $canceller = $stmt->get_result()->fetch_assoc();

    $cancelled_by_name = $canceller["full_name"] ?? "";

    $stmt->close();
}


// Get sale items
$stmt = $conn->prepare("
    SELECT sale_items.*, products.product_name
    FROM sale_items
    INNER JOIN products
        ON sale_items.product_id = products.id
    WHERE sale_items.sale_id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();

$items = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Receipt <?php echo htmlspecialchars($sale["invoice_number"]); ?>
    </title>

    <link href="./bootstrap/css/bootstrap.min.css"
          rel="stylesheet">

    <style>

        body {
            background: #f5f5f5;
        }

        .receipt {
            max-width: 500px;
            margin: 30px auto;
            background: white;
            padding: 30px;
        }

        .receipt-header {
            text-align: center;
        }

        .receipt-total {
            font-size: 20px;
            font-weight: bold;
        }

        @media print {

            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                margin: 0;
                max-width: 100%;
                box-shadow: none;
            }

        }

    </style>

</head>

<body>

<div class="receipt shadow">

<?php if ($sale["status"] == "cancelled"): ?>
    <div class="alert alert-danger text-center">
        <strong>CANCELLED SALE</strong><br>
        This receipt is no longer valid.

        <?php if ($sale["cancel_reason"]): ?>
            <br>Reason: <?php echo htmlspecialchars($sale["cancel_reason"]); ?>
        <?php endif; ?>

        <?php if ($sale["cancelled_at"]): ?>
            <br>Cancelled on <?php echo htmlspecialchars($sale["cancelled_at"]); ?>
            <?php if ($cancelled_by_name != ""): ?>
                by <?php echo htmlspecialchars($cancelled_by_name); ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

   <div class="receipt-header">

    <h4>

    <p>BINEY MART</p>

    <p>Tel: 0247962620</p>

    <p>Sales Receipt</p>

    <hr>
</h4>
</div>


    <p>
        <strong>Invoice:</strong>
        <?php echo htmlspecialchars($sale["invoice_number"]); ?>
    </p>

    <p>
        <strong>Date:</strong>
        <?php echo $sale["sale_date"]; ?>
    </p>

    <p>
        <strong>Cashier:</strong>
        <?php echo htmlspecialchars($sale["full_name"]); ?>
    </p>


    <hr>


    <table class="table">

        <thead>

            <tr>

                <th>Product</th>

                <th>Qty</th>

                <th>Price</th>

                <th>Total</th>

            </tr>

        </thead>

        <tbody>

        <?php while ($item = $items->fetch_assoc()): ?>

            <tr>

                <td>
                    <?php echo htmlspecialchars($item["product_name"]); ?>
                </td>

                <td>
                    <?php echo $item["quantity"]; ?>
                </td>

                <td>
                    GH₵ <?php echo number_format($item["unit_price"], 2); ?>
                </td>

                <td>
                    GH₵ <?php echo number_format($item["subtotal"], 2); ?>
                </td>

            </tr>

        <?php endwhile; ?>

        </tbody>

    </table>


    <hr>


    <p>
        <strong>Total:</strong>

        GH₵
        <?php echo number_format($sale["total_amount"], 2); ?>
    </p>


    <p>
        <strong>Amount Paid:</strong>

        GH₵
        <?php echo number_format($sale["amount_paid"], 2); ?>
    </p>


    <p>
        <strong>Change:</strong>

        GH₵
        <?php echo number_format($sale["change_amount"], 2); ?>
    </p>


    <p>
        <strong>Payment:</strong>
        <?php echo htmlspecialchars($sale["payment_method"]); ?>
    </p>


    <hr>


    <div class="receipt-header">

        <p>
            <strong>Thank you for your purchase!</strong>
        </p>

    </div>


    <div class="text-center no-print">

        <button
            onclick="window.print()"
            class="btn btn-primary">
            🖨 Print Receipt
        </button>

        <a
            href="orders.php"
            class="btn btn-secondary">
            New Sale
        </a>

    </div>

</div>

</body>

</html>