<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

$message = "";

// Save changes (admin only)
if (
    $_SERVER["REQUEST_METHOD"] == "POST"
    && isset($_POST["save_shop"])
    && $_SESSION["role"] == "admin"
) {

    $shop_name = trim($_POST["shop_name"]);
    $address = trim($_POST["address"]);
    $phone = trim($_POST["phone"]);
    $opening_hours = trim($_POST["opening_hours"]);

    if ($shop_name == "") {

        $message = "Shop name cannot be empty.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE shop_settings
             SET shop_name = ?, address = ?, phone = ?, opening_hours = ?
             WHERE id = 1"
        );

        $stmt->bind_param("ssss", $shop_name, $address, $phone, $opening_hours);

        if ($stmt->execute()) {
            $message = "Shop details saved.";
        } else {
            $message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Load the shop details (after saving, so the page shows the latest values)
$result = $conn->query("SELECT * FROM shop_settings WHERE id = 1");

$shop = $result->fetch_assoc();

if (!$shop) {
    die("Shop details not found. Check the shop_settings table.");
}

$product_count_result = $conn->query("SELECT COUNT(*) AS total FROM products");
$product_count = $product_count_result
    ? (int) $product_count_result->fetch_assoc()["total"]
    : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Info - POS</title>
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4" style="max-width: 700px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Service Info</h2>
        <a href="home.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message != ""): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Shop details (everyone can see this) -->
    <div class="card shadow mb-4">
        <div class="card-header"><strong>Shop Details</strong></div>
        <div class="card-body">

            <h4><?php echo htmlspecialchars($shop["shop_name"]); ?></h4>

            <p class="mb-1">
                <strong>Address:</strong>
                <?php echo htmlspecialchars($shop["address"]); ?>
            </p>

            <p class="mb-1">
                <strong>Phone:</strong>
                <?php echo htmlspecialchars($shop["phone"]); ?>
            </p>

            <p class="mb-0">
                <strong>Opening Hours:</strong>
                <?php echo htmlspecialchars($shop["opening_hours"]); ?>
            </p>

            <p class="mb-0">
                <strong>Products in System:</strong>
                <?php echo $product_count; ?>
            </p>

        </div>
    </div>

    <!-- Edit form (admin only) -->
    <?php if ($_SESSION["role"] == "admin"): ?>

    <div class="card shadow">
        <div class="card-header"><strong>Edit Shop Details</strong></div>
        <div class="card-body">
            <form method="POST">

                <div class="form-group">
                    <label>Shop Name</label>
                    <input type="text" name="shop_name" class="form-control" required
                           value="<?php echo htmlspecialchars($shop["shop_name"]); ?>">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control"
                           value="<?php echo htmlspecialchars($shop["address"]); ?>">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?php echo htmlspecialchars($shop["phone"]); ?>">
                </div>

                <div class="form-group">
                    <label>Opening Hours</label>
                    <input type="text" name="opening_hours" class="form-control"
                           value="<?php echo htmlspecialchars($shop["opening_hours"]); ?>">
                </div>

                <button type="submit" name="save_shop" class="btn btn-primary">
                    Save Changes
                </button>

            </form>
        </div>
    </div>

    <?php endif; ?>

</div>
</body>
</html>
