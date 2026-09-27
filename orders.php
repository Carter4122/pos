<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";

$invoice_number = "";

// Process sale
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["complete_sale"])) {
    $product_ids = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $amount_paid = (float) ($_POST["amount_paid"] ?? 0);
    $payment_method = $_POST["payment_method"] ?? "Cash";
    $total_amount = 0;
    $items = [];

    try {
        $conn->begin_transaction();

        // Validate products and calculate the sale total.
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = (int) $product_ids[$i];
            $quantity = (int) ($quantities[$i] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $stmt = $conn->prepare(
                "SELECT * FROM products WHERE id = ? AND status = 'active'"
            );
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            if ((int) $product["stock_quantity"] < $quantity) {
                throw new Exception("Not enough stock for " . $product["product_name"] . ".");
            }

            $unit_price = (float) $product["selling_price"];
            $subtotal = $unit_price * $quantity;
            $total_amount += $subtotal;

            $items[] = [
                "product_id" => $product_id,
                "quantity" => $quantity,
                "unit_price" => $unit_price,
                "subtotal" => $subtotal
            ];
        }

        if (!$items) {
            throw new Exception("Select at least one product.");
        }

        if ($amount_paid < $total_amount) {
            throw new Exception("Amount paid is less than the sale total.");
        }

        $change_amount = $amount_paid - $total_amount;
        $invoice_number = "INV-" . date("YmdHis") . "-" . random_int(100, 999);

        $stmt = $conn->prepare(
            "INSERT INTO sales
            (invoice_number, user_id, total_amount, amount_paid,
             change_amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $user_id = (int) $_SESSION["user_id"];
        $stmt->bind_param(
            "siddds",
            $invoice_number,
            $user_id,
            $total_amount,
            $amount_paid,
            $change_amount,
            $payment_method
        );
        $stmt->execute();
        $sale_id = $conn->insert_id;
        $stmt->close();

        foreach ($items as $item) {
            $stmt = $conn->prepare(
                "INSERT INTO sale_items
                (sale_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiidd",
                $sale_id,
                $item["product_id"],
                $item["quantity"],
                $item["unit_price"],
                $item["subtotal"]
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "UPDATE products
                 SET stock_quantity = stock_quantity - ?
                 WHERE id = ?"
            );
            $stmt->bind_param("ii", $item["quantity"], $item["product_id"]);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        header("Location: receipt.php?sale_id=" . $sale_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Sale failed: " . $e->getMessage();
    }
}


// Get active products
$products = $conn->query(
    "SELECT * FROM products
     WHERE status = 'active'
     ORDER BY product_name ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Orders - POS</title>

    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Order / Sales</h2>

        <a href="home.php" class="btn btn-secondary">
            Back to Dashboard
        </a>

    </div>


    <?php if ($message != ""): ?>

        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <div class="card shadow mb-4">

            <div class="card-header">
                <strong>Select Products</strong>
            </div>

            <div class="card-body">

                <div id="products">

                    <div class="row product-row mb-3">

                        <div class="col-md-7">

                            <select
                                name="product_id[]"
                                class="form-control product-select"
                                required
                            >

                                <option value="">
                                    Select Product
                                </option>

                                <?php while ($product = $products->fetch_assoc()): ?>

                                    <option
                                        value="<?php echo $product["id"]; ?>"
                                        data-price="<?php echo $product["selling_price"]; ?>"
                                    >

                                        <?php echo htmlspecialchars($product["product_name"]); ?>

                                        - GH₵
                                        <?php echo number_format($product["selling_price"], 2); ?>

                                        (Stock:
                                        <?php echo $product["stock_quantity"]; ?>)

                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>


                        <div class="col-md-3">

                            <input
                                type="number"
                                name="quantity[]"
                                class="form-control"
                                min="1"
                                value="1"
                                required
                            >

                        </div>


                        <div class="col-md-2">

                            <button
                                type="button"
                                class="btn btn-danger remove-row"
                            >
                                Remove
                            </button>

                        </div>

                    </div>

                </div>


                <button
                    type="button"
                    id="add-product"
                    class="btn btn-primary"
                >
                    + Add Product
                </button>

            </div>

        </div>


        <div class="card shadow">

            <div class="card-header">
                <strong>Payment</strong>
            </div>

            <div class="card-body">

                <div class="form-group">

                    <label>Amount Paid</label>
                    <div class="alert alert-primary">
    <strong>Total: GH₵ <span id="total">0.00</span></strong>
</div>

                    <input
                        
    type="number"
    name="amount_paid"
    id="amount_paid"
    step="0.01"
    min="0"
    class="form-control"
    required
>

<div class="mt-2">
    <strong>Change: GH₵ <span id="change">0.00</span></strong>
</div>

</div>

                <div class="form-group">

                    <label>Payment Method</label>

                    <select
                        name="payment_method"
                        class="form-control"
                    >

                        <option value="Cash">
                            Cash
                        </option>

                        <option value="Mobile Money">
                            Mobile Money
                        </option>

                        <option value="Card">
                            Card
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    name="complete_sale"
                    class="btn btn-success btn-lg"
                >
                    Complete Sale
                </button>

            </div>

        </div>

    </form>

</div>


<script>

document.getElementById("add-product").addEventListener("click", function () {

    let container = document.getElementById("products");

    let firstRow = document.querySelector(".product-row");

    let newRow = firstRow.cloneNode(true);

    newRow.querySelector("select").value = "";
    newRow.querySelector("input").value = 1;

    container.appendChild(newRow);

});


document.addEventListener("click", function (event) {

    if (event.target.classList.contains("remove-row")) {

        let rows = document.querySelectorAll(".product-row");

        if (rows.length > 1) {

            event.target.closest(".product-row").remove();

        }

    }

});


function calculateTotal() {

    let total = 0;

    document.querySelectorAll(".product-row").forEach(function(row) {

        let select = row.querySelector(".product-select");
        let quantity = row.querySelector("input[name='quantity[]']").value;

        if (select.value !== "") {

            let price =
                parseFloat(
                    select.options[select.selectedIndex].dataset.price
                ) || 0;

            quantity = parseInt(quantity) || 0;

            total += price * quantity;
        }

    });

    document.getElementById("total").textContent =
        total.toFixed(2);

    calculateChange();
}


function calculateChange() {

    let total =
        parseFloat(document.getElementById("total").textContent) || 0;

    let amountPaid =
        parseFloat(document.getElementById("amount_paid").value) || 0;

    let change = amountPaid - total;

    if (change < 0) {
        change = 0;
    }

    document.getElementById("change").textContent =
        change.toFixed(2);
}


document.addEventListener("change", function(event) {

    if (
        event.target.classList.contains("product-select") ||
        event.target.name === "quantity[]"
    ) {
        calculateTotal();
    }

});


document.addEventListener("input", function(event) {

    if (event.target.name === "quantity[]") {
        calculateTotal();
    }

    if (event.target.id === "amount_paid") {
        calculateChange();
    }

});


</script>

</body>

</html>