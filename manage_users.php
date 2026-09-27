<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

if ($_SESSION["role"] != "admin") {
    header("Location: home.php");
    exit();
}

require_once "db.php";

$message = "";

// Add user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"])) {

    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]);
    $password = $_POST["password"];
    $role = $_POST["role"];

    if ($role != "admin" && $role != "cashier") {

        $message = "Invalid role.";

    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters.";

    } else {

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, full_name, role)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param("ssss", $username, $hashed_password, $full_name, $role);

        if ($stmt->execute()) {
            $message = "User added successfully!";
        } else {
            $message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Activate / deactivate user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_user"])) {

    $target_id = intval($_POST["user_id"]);

    if ($target_id == $_SESSION["user_id"]) {

        $message = "You cannot deactivate your own account.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE users
             SET status = IF(status = 'active', 'inactive', 'active')
             WHERE id = ?"
        );

        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $stmt->close();

        $message = "User status updated.";
    }
}

// Get all users
$users = $conn->query("SELECT id, username, full_name, role, status FROM users ORDER BY id ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - POS</title>
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Users</h2>
        <a href="home.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message != ""): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Add User -->
    <div class="card shadow mb-4">
        <div class="card-header"><strong>Add New User</strong></div>
        <div class="card-body">
            <form method="POST">
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Password (at least 8 characters)</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                </div>

                <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
            </form>
        </div>
    </div>

    <!-- User List -->
    <div class="card shadow">
        <div class="card-header"><strong>Users</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user["id"]; ?></td>
                            <td><?php echo htmlspecialchars($user["full_name"]); ?></td>
                            <td><?php echo htmlspecialchars($user["username"]); ?></td>
                            <td><?php echo htmlspecialchars($user["role"]); ?></td>
                            <td>
                                <?php if ($user["status"] == "active"): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($user["id"] != $_SESSION["user_id"]): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                        <button
                                            type="submit"
                                            name="toggle_user"
                                            class="btn btn-sm <?php echo $user["status"] == "active" ? "btn-warning" : "btn-success"; ?>">
                                            <?php echo $user["status"] == "active" ? "Deactivate" : "Activate"; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
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