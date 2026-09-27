<?php

date_default_timezone_set("Africa/Accra");

$host = "localhost";
$dbname = "pos-system";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$quotedDbname = "`" . str_replace("`", "``", $dbname) . "`";
if (!$conn->query("CREATE DATABASE IF NOT EXISTS $quotedDbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Database setup failed: " . $conn->error);
}

if (!$conn->select_db($dbname)) {
    die("Database selection failed: " . $conn->error);
}

$schemaStatements = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(150) NOT NULL,
        product_code VARCHAR(100) NOT NULL UNIQUE,
        category VARCHAR(100) NOT NULL,
        description TEXT NULL,
        cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock_quantity INT NOT NULL DEFAULT 0,
        reorder_level INT NOT NULL DEFAULT 0,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS sales (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(40) NOT NULL UNIQUE,
        user_id INT UNSIGNED NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        amount_paid DECIMAL(10,2) NOT NULL,
        change_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status ENUM('completed', 'cancelled') NOT NULL DEFAULT 'completed',
        sale_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cancelled_by INT UNSIGNED NULL,
        cancelled_at DATETIME NULL,
        cancel_reason VARCHAR(255) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS sale_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sale_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        clock_in DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        clock_out DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS shop_settings (
        id TINYINT UNSIGNED PRIMARY KEY,
        shop_name VARCHAR(150) NOT NULL,
        address VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        opening_hours VARCHAR(150) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schemaStatements as $schemaStatement) {
    if (!$conn->query($schemaStatement)) {
        die("Database table setup failed: " . $conn->error);
    }
}

$conn->query(
    "INSERT INTO shop_settings (id, shop_name) VALUES (1, 'My POS Store')
     ON DUPLICATE KEY UPDATE id = id"
);

$adminResult = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($adminResult && $adminResult->num_rows === 0) {
    $adminPassword = password_hash("admin123", PASSWORD_DEFAULT);
    $adminStatement = $conn->prepare(
        "INSERT INTO users (username, password, full_name, role, status)
         VALUES ('admin', ?, 'System Administrator', 'admin', 'active')"
    );
    $adminStatement->bind_param("s", $adminPassword);
    $adminStatement->execute();
    $adminStatement->close();
}
?>