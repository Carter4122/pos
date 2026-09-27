
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

// Log out anyone whose account has been deactivated
if (isset($_SESSION["user_id"])) {

    $check = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $check->bind_param("i", $_SESSION["user_id"]);
    $check->execute();

    $account = $check->get_result()->fetch_assoc();

    $check->close();

    if (!$account || $account["status"] != "active") {
        session_unset();
        session_destroy();
        header("Location: index.html");
        exit();
    }
}

$low_stock_count = 0;
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE stock_quantity <= reorder_level
     AND status = 'active'"
);

if ($result) {
    $low_stock_count = (int) $result->fetch_assoc()["total"];
}

$shop_result = $conn->query("SELECT * FROM shop_settings WHERE id = 1");
$shop = $shop_result ? $shop_result->fetch_assoc() : null;

$product_count = 0;
$product_count_result = $conn->query("SELECT COUNT(*) AS total FROM products");

if ($product_count_result) {
    $product_count = (int) $product_count_result->fetch_assoc()["total"];
}

$transaction_summary_result = $conn->query(
    "SELECT
        COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_count,
        COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN total_amount ELSE 0 END), 0) AS today_total,
        COALESCE(SUM(CASE WHEN YEARWEEK(sale_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END), 0) AS week_count,
        COALESCE(SUM(CASE WHEN YEARWEEK(sale_date, 1) = YEARWEEK(CURDATE(), 1) THEN total_amount ELSE 0 END), 0) AS week_total,
        COALESCE(SUM(CASE WHEN YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) THEN 1 ELSE 0 END), 0) AS month_count,
        COALESCE(SUM(CASE WHEN YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE()) THEN total_amount ELSE 0 END), 0) AS month_total
     FROM sales
     WHERE status = 'completed'"
);
$transaction_summary = $transaction_summary_result
    ? $transaction_summary_result->fetch_assoc()
    : [
        "today_count" => 0,
        "today_total" => 0,
        "week_count" => 0,
        "week_total" => 0,
        "month_count" => 0,
        "month_total" => 0
    ];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>POS</title>

    <!-- Bootstrap styles for this project-->
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Script for printing -->
    <script>
        function printContent(el) {
            var restorepage = document.body.innerHTML;
            var printcontent = document.getElementById(el).innerHTML;
            document.body.innerHTML = printcontent;
            window.print();
            document.body.innerHTML = restorepage;
        }
    </script>

</head>

<div id="wrapper">
    <div class="sidebar sidebar-dark bg-primary">

        <form action="home.php" method="GET">
            <li class="nav-item action">

                <center>
                    <img src="./image/logo.jpg" class="img-responsive img-thumbnail" width="90" height="70">
                </center>
                <small class="nav-link text-center font-weight-bold">DASHBOARD</small>
            </li>
            <li class="nav-item active">
                <hr>
               <a href="service_info.php" class="btn nav-link">SERVICE INFO</a>

            <?php if ($_SESSION["role"] == "admin"): ?>
<a href="products.php" class="btn nav-link bg-gradient-primary">
    PRODUCT DEFINITION
</a>
<?php endif; ?>

              <a href="attendance.php" class="btn nav-link">ATTENDANCE</a>

                <a href="orders.php" class="btn nav-link bg-gradient-primary">
    ORDER
</a>

              <a href="sales_history.php"
   class="btn nav-link bg-gradient-primary">
    REPORT
</a>

                <a href="configuration.php" class="btn nav-link bg-gradient-primary">CONFIGURATION</a>

              

                <a href="logout.php" class="btn nav-link bg-gradient-primary">
                    LOGOUT
                </a>

            </li>
        </form>
    </div>

    <body>

        <div id="content-wrapper">
            <nav class="navbar bg-white topbar navbar-fixed-top mb-4 shadow static-top ">
                <h4>POS</h4>
            </nav>

            <div class="container-fluid">
                <?php if ($shop): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong><?php echo htmlspecialchars($shop["shop_name"]); ?></strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <strong>Address</strong><br>
                                    <?php echo htmlspecialchars($shop["address"]); ?>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Phone</strong><br>
                                    <?php echo htmlspecialchars($shop["phone"]); ?>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Opening Hours</strong><br>
                                    <?php echo htmlspecialchars($shop["opening_hours"]); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <h5 class="mb-3">Transaction Summary</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <h6>Today's Sales</h6>
                                <h3>GH₵ <?php echo number_format((float) $transaction_summary["today_total"], 2); ?></h3>
                                <p class="mb-0">Transactions: <?php echo (int) $transaction_summary["today_count"]; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <h6>This Week's Sales</h6>
                                <h3>GH₵ <?php echo number_format((float) $transaction_summary["week_total"], 2); ?></h3>
                                <p class="mb-0">Transactions: <?php echo (int) $transaction_summary["week_count"]; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <h6>This Month's Sales</h6>
                                <h3>GH₵ <?php echo number_format((float) $transaction_summary["month_total"], 2); ?></h3>
                                <p class="mb-0">Transactions: <?php echo (int) $transaction_summary["month_count"]; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="products.php" style="text-decoration:none;">
                            <div class="card shadow">
                                <div class="card-body">
                                    <h6>Low Stock Products</h6>
                                    <h3 class="<?php echo $low_stock_count > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $low_stock_count; ?>
                                    </h3>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-4 mb-3">
                        <a href="products.php" style="text-decoration:none;">
                            <div class="card shadow">
                                <div class="card-body">
                                    <h6>Products in System</h6>
                                    <h3><?php echo $product_count; ?></h3>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>


            <!-- Bootstrap core JavaScript-->
            <script src="./bootstrap/vendor/jquery/jquery.min.js"></script>
            

            <!-- Core plugin JavaScript-->
            <script src="./bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>
            <script src="./bootstrap/js/jquery.js"></script>

            <!-- Custom scripts for all pages-->
            <script src="./bootstrap/js/bootstrap.min.js"></script>
</div>
</div>
    </body>

</html>