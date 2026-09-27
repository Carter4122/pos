<?php

session_start();

// Only authenticated users can edit products.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

// Only administrators can edit product details.
if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: home.php");
    exit();
}

require_once "db.php";

$product_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$product_id = $product_id ?: filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
$message = "";

if (!$product_id) {
    exit("Invalid product ID.");
}

// Load the selected product before displaying the edit form.
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    exit("Product not found.");
}

// Save the edited product details.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_name = trim($_POST["product_name"]);
    $product_code = trim($_POST["product_code"]);
    $category = trim($_POST["category"]);
    $description = trim($_POST["description"]);
    $cost_price = (float) $_POST["cost_price"];
    $selling_price = (float) $_POST["selling_price"];
    $stock_quantity = (int) $_POST["stock_quantity"];
    $reorder_level = (int) $_POST["reorder_level"];

    $stmt = $conn->prepare(
        "UPDATE products
         SET product_name = ?, product_code = ?, category = ?, description = ?,
             cost_price = ?, selling_price = ?, stock_quantity = ?, reorder_level = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "ssssddiii",
        $product_name,
        $product_code,
        $category,
        $description,
        $cost_price,
        $selling_price,
        $stock_quantity,
        $reorder_level,
        $product_id
    );

    if ($stmt->execute()) {
        header("Location: products.php");
        exit();
    }

    $message = "Error: " . $stmt->error;
    $stmt->close();

    $product["product_name"] = $product_name;
    $product["product_code"] = $product_code;
    $product["category"] = $category;
    $product["description"] = $description;
    $product["cost_price"] = $cost_price;
    $product["selling_price"] = $selling_price;
    $product["stock_quantity"] = $stock_quantity;
    $product["reorder_level"] = $reorder_level;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Edit Product</h2>
        <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Product Name</label>
                        <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($product["product_name"]); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Product Code</label>
                        <input type="text" name="product_code" class="form-control" value="<?php echo htmlspecialchars($product["product_code"]); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Category</label>
                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product["category"]); ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($product["description"]); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Cost Price</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo htmlspecialchars($product["cost_price"]); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Selling Price</label>
                        <input type="number" step="0.01" name="selling_price" class="form-control" value="<?php echo htmlspecialchars($product["selling_price"]); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock_quantity" class="form-control" value="<?php echo htmlspecialchars($product["stock_quantity"]); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="<?php echo htmlspecialchars($product["reorder_level"]); ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
