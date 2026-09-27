<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit();
}

require_once "db.php";

$message = "";
$user_id = $_SESSION["user_id"];

// Find out whether this user is currently clocked in
$stmt = $conn->prepare(
    "SELECT id, clock_in FROM attendance
     WHERE user_id = ? AND clock_out IS NULL
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$open_shift = $stmt->get_result()->fetch_assoc();

$stmt->close();

// Clock in
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["clock_in"])) {

    if ($open_shift) {

        $message = "You are already clocked in.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO attendance (user_id, clock_in) VALUES (?, NOW())"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: attendance.php");
        exit();
    }
}

// Clock out
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["clock_out"])) {

    if (!$open_shift) {

        $message = "You are not clocked in.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE attendance SET clock_out = NOW() WHERE id = ?"
        );
        $stmt->bind_param("i", $open_shift["id"]);
        $stmt->execute();
        $stmt->close();

        header("Location: attendance.php");
        exit();
    }
}

// Load records: admins see everyone, others see only their own
if ($_SESSION["role"] == "admin") {

    $records = $conn->query(
        "SELECT attendance.*, users.full_name
         FROM attendance
         INNER JOIN users ON attendance.user_id = users.id
         ORDER BY attendance.id DESC
         LIMIT 100"
    );

} else {

    $stmt = $conn->prepare(
        "SELECT attendance.*, users.full_name
         FROM attendance
         INNER JOIN users ON attendance.user_id = users.id
         WHERE attendance.user_id = ?
         ORDER BY attendance.id DESC
         LIMIT 100"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $records = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - POS</title>
    <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Attendance</h2>
        <a href="home.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message != ""): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Clock in / out -->
    <div class="card shadow mb-4">
        <div class="card-body text-center">

            <?php if ($open_shift): ?>

                <p>
                    You clocked in at
                    <strong><?php echo htmlspecialchars($open_shift["clock_in"]); ?></strong>
                    <br>
                    Elapsed time: <strong id="elapsed-time">00:00:00</strong>
                </p>

                <script>
                    const clockInTime = <?php echo (int) strtotime($open_shift["clock_in"]); ?> * 1000;

                    function updateElapsedTime() {
                        const elapsedSeconds = Math.max(0, Math.floor((Date.now() - clockInTime) / 1000));
                        const hours = String(Math.floor(elapsedSeconds / 3600)).padStart(2, "0");
                        const minutes = String(Math.floor((elapsedSeconds % 3600) / 60)).padStart(2, "0");
                        const seconds = String(elapsedSeconds % 60).padStart(2, "0");

                        document.getElementById("elapsed-time").textContent =
                            `${hours}:${minutes}:${seconds}`;
                    }

                    updateElapsedTime();
                    setInterval(updateElapsedTime, 1000);
                </script>

                <form method="POST">
                    <button type="submit" name="clock_out" class="btn btn-danger btn-lg">
                        Clock Out
                    </button>
                </form>

            <?php else: ?>

                <p>You are not clocked in.</p>

                <form method="POST">
                    <button type="submit" name="clock_in" class="btn btn-success btn-lg">
                        Clock In
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </div>

    <!-- Records -->
    <div class="card shadow">
        <div class="card-header">
            <strong>
                <?php echo $_SESSION["role"] == "admin" ? "All Staff Records" : "My Records"; ?>
            </strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Time Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $records->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record["full_name"]); ?></td>
                                <td><?php echo htmlspecialchars($record["clock_in"]); ?></td>
                                <td>
                                    <?php echo $record["clock_out"]
                                        ? htmlspecialchars($record["clock_out"])
                                        : "Still clocked in"; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($record["clock_out"]) {
                                        $minutes_worked = (int) floor(
                                            (strtotime($record["clock_out"]) - strtotime($record["clock_in"])) / 60
                                        );
                                        echo intdiv($minutes_worked, 60) . "h " . ($minutes_worked % 60) . "m";
                                    }
                                    ?>
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