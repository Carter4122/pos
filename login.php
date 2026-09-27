<?php

session_start();

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"]) && $user["status"] == "active") {

            // Create login session
            $_SESSION["user_id"] = $user["id"];
                session_regenerate_id(true);
            $_SESSION["username"] = $user["username"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            // Go to dashboard
            header("Location: home.php");
            exit();

        } else {

            echo "Invalid username or password.";

        }

    } else {

        echo "Invalid username or password.";

    }

    $stmt->close();
    $conn->close();

} else {

    header("Location: index.html");
    exit();

}

?>