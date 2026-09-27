<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

$from_date = $_GET["from_date"] ?? "";
$to_date = $_GET["to_date"] ?? "";

// Only accept dates in YYYY-MM-DD format
if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)
) {
    $from_date = "";
    $to_date = "";
}

if ($from_date != "" && $to_date != "") {
    $sales_label = "Selected Period Sales";
    $paid_label = "Amount Received";
    $change_label = "Change Given";
    $week_label = "This Week";
    $month_label = "This Month";
    $total_label = "Total Sales";
} else {
    $sales_label = "Today's Sales";
    $paid_label = "Amount Received Today";
    $change_label = "Change Given Today";
    $week_label = "This Week";
    $month_label = "This Month";
    $total_label = "Total Sales";
}





$sql = "
    SELECT 
        sales.id,
        sales.invoice_number,
        sales.total_amount,
        sales.amount_paid,
        sales.change_amount,
        sales.payment_method,
        sales.status,
        sales.sale_date,
        users.full_name
    FROM sales
    INNER JOIN users ON sales.user_id = users.id
";

if ($from_date != "" && $to_date != "") {

    $sql .= "
        WHERE DATE(sales.sale_date)
        BETWEEN '$from_date' AND '$to_date'
    ";
}

$sql .= " ORDER BY sales.id DESC";

$result = $conn->query($sql);

// Sales summary

// Summary values

$today_sales = 0;
$today_paid = 0;
$today_change = 0;

$week_sales = 0;
$month_sales = 0;
$total_sales = 0;


// Payment method totals
$cash_sales = 0;
$mobile_money_sales = 0;
$card_sales = 0;

$payment_sql = "
    SELECT 
        payment_method,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE status = 'completed'
";

if ($from_date != "" && $to_date != "") {
    $payment_sql .= "
        AND DATE(sale_date)
        BETWEEN '$from_date' AND '$to_date'
    ";
}

$payment_sql .= "
    GROUP BY payment_method
";

$payment_result = $conn->query($payment_sql);

if ($payment_result) {
    while ($payment = $payment_result->fetch_assoc()) {

        if ($payment["payment_method"] == "Cash") {
            $cash_sales = $payment["total"];
        }

        if ($payment["payment_method"] == "Mobile Money") {
            $mobile_money_sales = $payment["total"];
        }

        if ($payment["payment_method"] == "Card") {
            $card_sales = $payment["total"];
        }
    }
}

// If date range is selected

if ($from_date != "" && $to_date != "") {

    // Selected period sales
    $sql_period = "
        SELECT 
            COALESCE(SUM(total_amount), 0) AS sales,
            COALESCE(SUM(amount_paid), 0) AS paid,
            COALESCE(SUM(change_amount), 0) AS change_given
        FROM sales
        WHERE DATE(sale_date)
        BETWEEN '$from_date' AND '$to_date'
        AND status = 'completed'
    ";

    $result_period = $conn->query($sql_period);

    if ($result_period) {

        $period = $result_period->fetch_assoc();

        $today_sales = $period["sales"];
        $today_paid = $period["paid"];
        $today_change = $period["change_given"];

        $week_sales = $period["sales"];
        $month_sales = $period["sales"];
        $total_sales = $period["sales"];
    }


// If no date range is selected

} else {

    // Today's sales
    $sql_today = "
        SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales
        WHERE DATE(sale_date) = CURDATE() AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed'
    ";

    $result_today = $conn->query($sql_today);

    if ($result_today) {
        $today_sales = $result_today->fetch_assoc()["total"];
    }


    // Today's amount received
    $sql_today_paid = "
        SELECT COALESCE(SUM(amount_paid), 0) AS total
        FROM sales
        WHERE DATE(sale_date) = CURDATE() AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed'
    ";

    $result_today_paid = $conn->query($sql_today_paid);

    if ($result_today_paid) {
        $today_paid = $result_today_paid->fetch_assoc()["total"];
    }


    // Today's change given
    $sql_today_change = "
        SELECT COALESCE(SUM(change_amount), 0) AS total
        FROM sales
        WHERE DATE(sale_date) = CURDATE() AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed' AND status = 'completed'
    ";

    $result_today_change = $conn->query($sql_today_change);

    if ($result_today_change) {
        $today_change = $result_today_change->fetch_assoc()["total"];
    }


    // This week's sales
    $sql_week = "
        SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales
        WHERE YEARWEEK(sale_date, 1)
        = YEARWEEK(CURDATE(), 1)
        AND status = 'completed'
    ";

    $result_week = $conn->query($sql_week);

    if ($result_week) {
        $week_sales = $result_week->fetch_assoc()["total"];
    }


    // This month's sales
    $sql_month = "
        SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales
        WHERE YEAR(sale_date) = YEAR(CURDATE())
        AND MONTH(sale_date) = MONTH(CURDATE())
        AND status = 'completed'
    ";

    $result_month = $conn->query($sql_month);

    if ($result_month) {
        $month_sales = $result_month->fetch_assoc()["total"];
    }


    // All-time sales
    $sql_total = "
        SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales
        WHERE status = 'completed'
    ";

    $result_total = $conn->query($sql_total);

    if ($result_total) {
        $total_sales = $result_total->fetch_assoc()["total"];
    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Sales History</title>

    <link href="./bootstrap/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Sales History</h2>

        <div>

            <a href="orders.php"
               class="btn btn-primary">
                New Sale
            </a>

            <a href="home.php"
               class="btn btn-secondary">
                Dashboard
            </a>

        </div>

    </div>

<div class="row mb-4">

<div class="col-md-3 mb-3">

    <div class="card shadow">

        <div class="card-body">

            <h6><?php echo $paid_label; ?></h6>

            <h4>
                GH₵ <?php echo number_format($today_paid, 2); ?>
            </h4>

        </div>

    </div>

</div>





    <div class="col-md-3 mb-3">

        <div class="card shadow border-left-primary">

            <div class="card-body">

                <h6><?php echo $sales_label; ?></h6>

                <h4>
                    GH₵ <?php echo number_format($today_sales, 2); ?>
                </h4>

            </div>

        </div>

    </div>


    <div class="col-md-3 mb-3">

        <div class="card shadow">

            <div class="card-body">

                <h6><?php echo $change_label; ?></h6>

                <h4>
                    GH₵ <?php echo number_format($today_change, 2); ?>
                </h4>

            </div>

        </div>

    </div>


    <div class="col-md-3 mb-3">

        <div class="card shadow">

            <div class="card-body">

                <h6><?php echo $week_label; ?></h6>

                <h4>
                    GH₵ <?php echo number_format($week_sales, 2); ?>
                </h4>

            </div>

        </div>

    </div>


    <div class="col-md-3 mb-3">

        <div class="card shadow">

            <div class="card-body">

                <h6><?php echo $month_label; ?></h6>

                <h4>
                    GH₵ <?php echo number_format($month_sales, 2); ?>
                </h4>

            </div>

        </div>

    </div>


    <div class="col-md-3 mb-3">

        <div class="card shadow">

            <div class="card-body">

                <h6><?php echo $total_label; ?></h6>

                <h4>
                    GH₵ <?php echo number_format($total_sales, 2); ?>
                </h4>

            </div>

        </div>

    </div>

</div>

<div class="row mb-4">

    <div class="col-md-4 mb-3">
        <div class="card shadow">
            <div class="card-body">
                <h6>Cash Sales</h6>
                <h4>
                    GH₵ <?php echo number_format($cash_sales, 2); ?>
                </h4>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card shadow">
            <div class="card-body">
                <h6>Mobile Money Sales</h6>
                <h4>
                    GH₵ <?php echo number_format($mobile_money_sales, 2); ?>
                </h4>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card shadow">
            <div class="card-body">
                <h6>Card Sales</h6>
                <h4>
                    GH₵ <?php echo number_format($card_sales, 2); ?>
                </h4>
            </div>
        </div>
    </div>

</div>





<div class="card shadow mb-4">

    <div class="card-body">

        <h5 class="mb-3">Filter Sales</h5>

        <form method="GET" action="sales_history.php">

            <div class="row">

                <div class="col-md-4">

                    <label>From Date</label>

                    <input
                        type="date"
                        name="from_date"
                        class="form-control"
                        value="<?php echo htmlspecialchars($from_date); ?>"
                        required>

                </div>

                <div class="col-md-4">

                    <label>To Date</label>

                    <input
                        type="date"
                        name="to_date"
                        class="form-control"
                        value="<?php echo htmlspecialchars($to_date); ?>"
                        required>

                </div>

                <div class="col-md-4 d-flex align-items-end mt-3 mt-md-0">

                    <button type="submit" class="btn btn-primary mr-2">
                        Search
                    </button>

                    <a href="sales_history.php" class="btn btn-secondary">
                        Clear
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

    <div class="card shadow">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>

                            <th>Invoice</th>

                            <th>Date</th>

                            <th>Cashier</th>

                            <th>Total</th>

                            <th>Paid</th>

                            <th>Change</th>

                            <th>Payment</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($sale = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $sale["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($sale["invoice_number"]); ?>
                                </td>

                                <td>
                                    <?php echo $sale["sale_date"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($sale["full_name"]); ?>
                                </td>

                                <td>
                                    GH₵
                                    <?php echo number_format($sale["total_amount"], 2); ?>
                                </td>

                                <td>
                                    GH₵
                                    <?php echo number_format($sale["amount_paid"], 2); ?>
                                </td>

                                <td>
                                    GH₵
                                    <?php echo number_format($sale["change_amount"], 2); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($sale["payment_method"]); ?>
                                </td>
                                    
                                <td>
    <?php if ($sale["status"] == "cancelled"): ?>
        <span class="badge badge-danger">Cancelled</span>
    <?php else: ?>
        <span class="badge badge-success">Completed</span>
    <?php endif; ?>
</td>


                                <td>

                                    <a
                                        href="receipt.php?sale_id=<?php echo $sale["id"]; ?>"
                                        class="btn btn-sm btn-primary">
                                        View Receipt
                                    </a>
<?php if ($_SESSION["role"] == "admin" && $sale["status"] != "cancelled"): ?>
    <form method="POST" action="cancel_sale.php" style="display:inline;"
                    onsubmit="var r = prompt('Reason for cancelling this sale:'); if (!r || r.trim() === '') { return false; } this.cancel_reason.value = r; return true;">
        <input type="hidden" name="sale_id" value="<?php echo $sale["id"]; ?>">
                <input type="hidden" name="cancel_reason" value="">
        <button type="submit" class="btn btn-sm btn-danger">Cancel Sale</button>
    </form>
<?php endif; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="10"
                                class="text-center">

                                No sales recorded yet.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>