<?php

session_start();

// Only authenticated users can manage products.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

if ($_SESSION["role"] != "admin") {
    header("Location: home.php");
    exit();
}



require_once "db.php";
require_once "product_import.php";

$message = "";
$importErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_products"])) {
    if (!isset($_FILES["product_file"]) || $_FILES["product_file"]["error"] !== UPLOAD_ERR_OK) {
        $message = "Choose a valid CSV or XLSX file to upload.";
    } elseif ($_FILES["product_file"]["size"] > 5 * 1024 * 1024) {
        $message = "The upload is too large. Maximum file size is 5 MB.";
    } else {
        $extension = strtolower(pathinfo($_FILES["product_file"]["name"], PATHINFO_EXTENSION));

        try {
            if (!in_array($extension, ["csv", "xlsx"], true)) {
                throw new RuntimeException("Use a .csv or .xlsx file.");
            }

            $rows = readProductImportRows($_FILES["product_file"]["tmp_name"], $extension);
            if (!$rows) {
                throw new RuntimeException("The file has no data rows.");
            }

            $requiredHeaders = ["product_name", "selling_price"];
            $supportedHeaders = [
                "product_name", "product_code", "category", "description",
                "cost_price", "selling_price", "stock_quantity", "reorder_level"
            ];

            $header = findProductImportHeader($rows);

            if ($header === null) {
                $detectedHeaders = array_map("normalizeProductImportHeader", $rows[0] ?? []);
                throw new RuntimeException(
                    "Could not find Product Name and Selling Price columns. " .
                    "Detected first row: " . implode(", ", array_filter($detectedHeaders)) . ". " .
                    "You can also download the template for the supported column names."
                );
            }

            $rows = array_slice($rows, $header["row_index"] + 1);

            if (count($rows) > 5000) {
                throw new RuntimeException("A single upload can contain at most 5,000 products.");
            }

            $insertProduct = $conn->prepare(
                "INSERT IGNORE INTO products
                 (product_name, product_code, category, description,
                  cost_price, selling_price, stock_quantity, reorder_level)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $inserted = 0;
            $skipped = 0;

            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2;
                if (!array_filter($row, static function ($value) {
                    return trim((string) $value) !== "";
                })) {
                    continue;
                }

                $values = [];
                foreach ($supportedHeaders as $field) {
                    $values[$field] = isset($header["positions"][$field])
                        ? trim((string) ($row[$header["positions"][$field]] ?? ""))
                        : "";
                }

                $name = $values["product_name"];
                $code = $values["product_code"];
                if ($code === "") {
                    $code = "AUTO-" . strtoupper(bin2hex(random_bytes(6)));
                }
                $category = $values["category"] !== "" ? $values["category"] : "General";
                $description = $values["description"];
                $costPrice = $values["cost_price"] !== "" ? $values["cost_price"] : "0";
                $sellingPrice = $values["selling_price"];
                $stock = $values["stock_quantity"] !== ""
                    ? filter_var($values["stock_quantity"], FILTER_VALIDATE_INT)
                    : 0;
                $reorderLevel = $values["reorder_level"] !== ""
                    ? filter_var($values["reorder_level"], FILTER_VALIDATE_INT)
                    : 5;

                $validPrice = static function ($value) {
                    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 99999999.99;
                };

                if (
                    $name === "" || $code === "" || strlen($name) > 150 || strlen($code) > 100 ||
                    strlen($category) > 100 || !$validPrice($costPrice) || !$validPrice($sellingPrice) ||
                    $stock === false || $stock < 0 || $reorderLevel === false || $reorderLevel < 0
                ) {
                    $skipped++;
                    if (count($importErrors) < 10) {
                        $importErrors[] = "Row $lineNumber has missing or invalid product fields.";
                    }
                    continue;
                }

                try {
                    $costPrice = (float) $costPrice;
                    $sellingPrice = (float) $sellingPrice;
                    $insertProduct->bind_param(
                        "ssssddii",
                        $name,
                        $code,
                        $category,
                        $description,
                        $costPrice,
                        $sellingPrice,
                        $stock,
                        $reorderLevel
                    );
                    $insertProduct->execute();

                    if ($insertProduct->affected_rows === 1) {
                        $inserted++;
                    } else {
                        $skipped++;
                        if (count($importErrors) < 10) {
                            $importErrors[] = "Row $lineNumber was skipped because its product code already exists.";
                        }
                    }
                } catch (mysqli_sql_exception $exception) {
                    $skipped++;
                    if (count($importErrors) < 10) {
                        $importErrors[] = "Row $lineNumber could not be imported.";
                    }
                }
            }

            $insertProduct->close();
            $message = "$inserted product(s) imported; $skipped row(s) skipped.";
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }
    }
}

// Process the add-product form submission.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_product"])) {

    $product_name = trim($_POST["product_name"]);
    $product_code = trim($_POST["product_code"]);
    $category = trim($_POST["category"]);
    $description = trim($_POST["description"]);
    $cost_price = $_POST["cost_price"];
    $selling_price = $_POST["selling_price"];
    $stock_quantity = $_POST["stock_quantity"];
    $reorder_level = $_POST["reorder_level"];

    $sql = "INSERT INTO products
            (product_name, product_code, category, description,
             cost_price, selling_price, stock_quantity, reorder_level)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssddii",
        $product_name,
        $product_code,
        $category,
        $description,
        $cost_price,
        $selling_price,
        $stock_quantity,
        $reorder_level
    );

    if ($stmt->execute()) {
        $message = "Product added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Toggle a product between active and inactive status.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_status"])) {

    $product_id = intval($_POST["product_id"]);

    $stmt = $conn->prepare(
        "UPDATE products
         SET status = IF(status = 'active', 'inactive', 'active')
         WHERE id = ?"
    );

    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        $message = "Product status updated.";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Load products for display in the table below.
$result = $conn->query("SELECT * FROM products ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Product Definition - POS</title>

    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-actions {
            min-width: 112px;
        }

        .product-actions .btn {
            display: block;
            width: 100%;
            margin: 0 0 6px;
            white-space: nowrap;
        }

        .product-actions form {
            margin: 0;
        }
    </style>

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Product Definition</h2>

        <a href="home.php" class="btn btn-secondary">
            Back to Dashboard
        </a>

    </div>

    <?php if ($message != ""): ?>

        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($importErrors): ?>
        <div class="alert alert-warning">
            <?php foreach ($importErrors as $importError): ?>
                <div><?php echo htmlspecialchars($importError); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <!-- Form for creating a new product. -->

    <div class="card shadow mb-4">

        <div class="card-header">
            <strong>Add New Product</strong>
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label>Product Name</label>

                        <input
                            type="text"
                            name="product_name"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label>Product Code</label>

                        <input
                            type="text"
                            name="product_code"
                            class="form-control"
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label>Category</label>

                        <input
                            type="text"
                            name="category"
                            class="form-control"
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label>Description</label>

                        <input
                            type="text"
                            name="description"
                            class="form-control"
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label>Cost Price</label>

                        <input
                            type="number"
                            step="0.01"
                            name="cost_price"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label>Selling Price</label>

                        <input
                            type="number"
                            step="0.01"
                            name="selling_price"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label>Stock Quantity</label>

                        <input
                            type="number"
                            name="stock_quantity"
                            class="form-control"
                            value="0"
                            required
                        >

                    </div>


                    <div class="col-md-3 mb-3">

                        <label>Reorder Level</label>

                        <input
                            type="number"
                            name="reorder_level"
                            class="form-control"
                            value="5"
                            required
                        >

                    </div>

                </div>

        <button type="submit" name="add_product" class="btn btn-primary">
    Add Product
</button>

            </form>

        </div>

    </div>


    <div class="card shadow mb-4">
        <div class="card-header"><strong>Bulk Upload Products</strong></div>
        <div class="card-body">
            <p>Use the downloadable template. CSV files open in Excel; XLSX uploads require PHP's ZIP extension.</p>
            <p><a href="product_template.php" class="btn btn-outline-primary btn-sm">Download CSV Template</a></p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_file">Product file</label>
                    <input type="file" id="product_file" name="product_file" class="form-control-file" accept=".csv,.xlsx" required>
                </div>
                <button type="submit" name="import_products" class="btn btn-primary">Upload Products</button>
            </form>
        </div>
    </div>

    <!-- Table showing all products and their current status. -->

    <div class="card shadow">

        <div class="card-header">
            <strong>Products</strong>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th class="product-actions">Action</th>
                        </tr>

                    </thead>
                    <tbody>

                    <?php while ($product = $result->fetch_assoc()): ?>

                        <tr>

                            <!-- Product status can be changed without leaving this page. -->
                            <td>
                                <?php echo $product["id"]; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($product["product_name"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($product["product_code"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($product["category"]); ?>
                            </td>

                            <td>
                                GH₵ <?php echo number_format($product["cost_price"], 2); ?>
                            </td>

                            <td>
                                GH₵ <?php echo number_format($product["selling_price"], 2); ?>
                            </td>

                            <td>
                                <?php echo $product["stock_quantity"]; ?>

                                <?php if ($product["stock_quantity"] <= $product["reorder_level"]): ?>
                                    <span class="badge badge-danger">Low</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($product["status"]); ?>
                            </td>

                            <td class="product-actions">
                                <a href="edit_product.php?id=<?php echo $product["id"]; ?>" class="btn btn-sm btn-primary">
                                    Edit
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="product_id" value="<?php echo $product["id"]; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm btn-secondary">
                                        <?php echo $product["status"] === "active" ? "Deactivate" : "Activate"; ?>
                                    </button>
                                </form>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>