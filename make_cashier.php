<?php

require_once "db.php";

$username = "cashier1";
$password = "cashier123";
$full_name = "Test Cashier";
$role = "cashier";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, password, full_name, role)
        VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param("ssss", $username, $hashed_password, $full_name, $role);

if ($stmt->execute()) {
    echo "Cashier account created!<br>";
    echo "Username: " . $username . "<br>";
    echo "Password: " . $password;
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>