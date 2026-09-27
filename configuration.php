<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - POS</title>
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4" style="max-width: 600px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Configuration</h2>
        <a href="home.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card shadow">
        <div class="card-body">

            <a href="change_password.php" class="btn btn-primary btn-block mb-3">
                Change Password
            </a>

            <?php if ($_SESSION["role"] == "admin"): ?>
                <a href="manage_users.php" class="btn btn-primary btn-block">
                    Manage Users
                </a>
            <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>