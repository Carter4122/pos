<?php

require_once "db.php";

$username = "admin";
$password = "admin123";
$full_name = "System Administrator";
$role = "admin";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users
        SET password = ?, full_name = ?, role = ?
        WHERE username = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssss",
    $hashed_password,
    $full_name,
    $role,
    $username
);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        echo "Admin password updated successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123";
    } else {
        echo "The admin account was not found.";
    }

} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>