<?php

session_start();
require_once "../includes/db.php";

// ==========================================
// ADMIN LOGIN CHECK
// ==========================================

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";


// ==========================================
// DASHBOARD COUNTS
// ==========================================

$totalUsers = 0;
$totalEquipment = 0;
$pendingBookings = 0;
$activeMaintenance = 0;


// TOTAL USERS
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalUsers = $row["total"];
}


// TOTAL EQUIPMENT
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM equipment"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEquipment = $row["total"];
}


// PENDING BOOKINGS
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE status = 'pending'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $pendingBookings = $row["total"];
}


// ACTIVE MAINTENANCE
$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM maintenance
     WHERE status IN ('pending', 'in_progress')"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $activeMaintenance = $row["total"];
}


// ==========================================
// RECENT BOOKINGS
// ==========================================

$recentBookings = mysqli_query(
    $conn,
    "
    SELECT
        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status,
        u.name AS user_name,
        u.role,
        e.equipment_name

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.user_id

    INNER JOIN equipment e
        ON b.equipment_id = e.equipment_id

    ORDER BY b.booking_id DESC

    LIMIT 6
    "
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=6"
    >

</head>

<body>

<div class="app-layout">

    <!-- ==========================
         SIDEBAR
    =========================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div style="
                font-size: 38px;
                margin-bottom: 10px;
            ">
                🎓
            </div>

            College Equipment
            <span>Booking System</span>

        </div>


        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php" class="active">
                    🏠 &nbsp; Dashboard
                </a>
            </li>

            <li>
                <a href="equipment.php">
                    💻 &nbsp; Equipment
                </a>
            </li>

            <li>
                <a href="categories.php">
                    📂 &nbsp; Categories
                </a>
            </li>

            <li>
                <a href="bookings.php">
                    📅 &nbsp; Bookings
                </a>
            </li>

            <li>
                <a href="users.php">
                    👥 &nbsp; Users
                </a>
            </li>

            <li>
                <a href="maintenance.php">
                    🔧 &nbsp; Maintenance
                </a>
            </li>

            <li style="
                margin-top: 30px;
                border-top: 1px solid rgba(255,255,255,0.15);
                padding-top: 18px;
            ">

                <a href="../auth/logout.php">
                    🚪 &nbsp; Logout
                </a>

            </li>

        </ul>

    </aside>


    <!-- ==========================
         MAIN CONTENT
    =========================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Admin Dashboard
                </div>

                <small style="color: #64748b;">
                    College Equipment Management Portal
                </small>

            </div>


            <div class="user-info">

                <div style="text-align: right;">

                    <strong>
                        <?php
                        echo htmlspecialchars($adminName);
                        ?>
                    </strong>

                    <div style="
                        font-size: 12px;
                        color: #64748b;
                    ">
                        System Administrator
                    </div>

                </div>

                <span class="role-badge">
                    ADMIN
                </span>

            </div>

        </header>


        <!-- ==========================
             PAGE CONTENT
        =========================== -->

        <div class="content">


            <!-- WELCOME -->

            <section class="welcome-box">

                <h1>

                    Welcome back,
                    <?php
                    echo htmlspecialchars($adminName);
                    ?>! 👋

                </h1>

                <p>
                    Manage college equipment, users,
                    bookings and maintenance from one
                    central dashboard.
                </p>

            </section>


            <!-- ==========================
                 STAT CARDS
            =========================== -->

            <div class="dashboard-grid">


                <!-- TOTAL USERS -->

                <div class="stat-card">

                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">

                        <div>

                            <div class="stat-title">
                                Total Users
                            </div>

                            <div class="stat-number">
                                <?php echo $totalUsers; ?>
                            </div>

                        </div>

                        <div style="
                            width: 55px;
                            height: 55px;
                            background: #e8f7f4;
                            color: #0f766e;
                            border-radius: 14px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 27px;
                        ">
                            👥
                        </div>

                    </div>

                    <div style="
                        margin-top: 12px;
                        color: #64748b;
                        font-size: 13px;
                    ">
                        Students, faculty and admins
                    </div>

                </div>


                <!-- EQUIPMENT -->

                <div class="stat-card">

                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">

                        <div>

                            <div class="stat-title">
                                Equipment
                            </div>

                            <div class="stat-number">
                                <?php echo $totalEquipment; ?>
                            </div>

                        </div>

                        <div style="
                            width: 55px;
                            height: 55px;
                            background: #e8f7f4;
                            color: #0f766e;
                            border-radius: 14px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 27px;
                        ">
                            💻
                        </div>

                    </div>

                    <div style="
                        margin-top: 12px;
                        color: #64748b;
                        font-size: 13px;
                    ">
                        Total registered equipment
                    </div>

                </div>


                <!-- PENDING BOOKINGS -->

                <div class="stat-card">

                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">

                        <div>

                            <div class="stat-title">
                                Pending Bookings
                            </div>

                            <div class="stat-number">
                                <?php echo $pendingBookings; ?>
                            </div>

                        </div>

                        <div style="
                            width: 55px;
                            height: 55px;
                            background: #e8f7f4;
                            color: #0f766e;
                            border-radius: 14px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 27px;
                        ">
                            📅
                        </div>

                    </div>

                    <div style="
                        margin-top: 12px;
                        color: #64748b;
                        font-size: 13px;
                    ">
                        Waiting for your approval
                    </div>

                </div>


                <!-- MAINTENANCE -->

                <div class="stat-card">

                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">

                        <div>

                            <div class="stat-title">
                                In Maintenance
                            </div>

                            <div class="stat-number">
                                <?php echo $activeMaintenance; ?>
                            </div>

                        </div>

                        <div style="
                            width: 55px;
                            height: 55px;
                            background: #e8f7f4;
                            color: #0f766e;
                            border-radius: 14px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 27px;
                        ">
                            🔧
                        </div>

                    </div>

                    <div style="
                        margin-top: 12px;
                        color: #64748b;
                        font-size: 13px;
                    ">
                        Active maintenance records
                    </div>

                </div>

            </div>


            <!-- ==========================
                 QUICK ACTIONS
            =========================== -->

            <div class="card">

                <div>

                    <h2 style="margin-bottom: 2px;">
                        Quick Actions
                    </h2>

                    <p style="
                        color: #64748b;
                        font-size: 14px;
                    ">
                        Frequently used administrative tools
                    </p>

                </div>


                <div class="quick-actions-grid">

                    <a
                        href="equipment.php"
                        class="quick-action"
                    >
                        <span class="quick-action-icon">
                            ➕
                        </span>

                        Add Equipment
                    </a>


                    <a
                        href="bookings.php"
                        class="quick-action"
                    >
                        <span class="quick-action-icon">
                            📅
                        </span>

                        Review Bookings
                    </a>


                    <a
                        href="users.php"
                        class="quick-action"
                    >
                        <span class="quick-action-icon">
                            👥
                        </span>

                        Manage Users
                    </a>


                    <a
                        href="maintenance.php"
                        class="quick-action"
                    >
                        <span class="quick-action-icon">
                            🔧
                        </span>

                        Maintenance
                    </a>

                </div>

            </div>


            <!-- ==========================
                 RECENT BOOKINGS
            =========================== -->

            <div class="card">

                <div style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 15px;
                    flex-wrap: wrap;
                    margin-bottom: 15px;
                ">

                    <div>

                        <h2 style="margin-bottom: 2px;">
                            Recent Booking Requests
                        </h2>

                        <p style="
                            color: #64748b;
                            font-size: 14px;
                        ">
                            Latest equipment booking activity
                        </p>

                    </div>


                    <a
                        href="bookings.php"
                        class="btn btn-primary"
                    >
                        View All Bookings
                    </a>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>User</th>

                                <th>Equipment</th>

                                <th>Date</th>

                                <th>Time</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        if (
                            $recentBookings &&
                            mysqli_num_rows($recentBookings) > 0
                        ):

                            while (
                                $booking =
                                mysqli_fetch_assoc($recentBookings)
                            ):

                        ?>

                            <tr>

                                <td>
                                    #<?php
                                    echo htmlspecialchars(
                                        $booking["booking_id"]
                                    );
                                    ?>
                                </td>


                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $booking["user_name"]
                                        );
                                        ?>
                                    </strong>

                                    <br>

                                    <small style="
                                        color: #64748b;
                                        text-transform: capitalize;
                                    ">
                                        <?php
                                        echo htmlspecialchars(
                                            $booking["role"]
                                        );
                                        ?>
                                    </small>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking["equipment_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking["booking_date"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        substr(
                                            $booking["start_time"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo htmlspecialchars(
                                        substr(
                                            $booking["end_time"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                <?php

                                $status =
                                    strtolower(
                                        $booking["status"]
                                    );

                                $badgeClass = "badge-info";

                                if ($status === "approved") {

                                    $badgeClass =
                                        "badge-success";

                                } elseif ($status === "pending") {

                                    $badgeClass =
                                        "badge-warning";

                                } elseif (
                                    $status === "rejected" ||
                                    $status === "cancelled"
                                ) {

                                    $badgeClass =
                                        "badge-danger";
                                }

                                ?>

                                    <span
                                        class="badge <?php
                                        echo $badgeClass;
                                        ?>"
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst($status)
                                        );
                                        ?>
                                    </span>

                                </td>

                            </tr>

                        <?php

                            endwhile;

                        else:

                        ?>

                            <tr>

                                <td
                                    colspan="6"
                                    style="
                                        text-align: center;
                                        padding: 30px;
                                        color: #64748b;
                                    "
                                >
                                    No booking records found.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- FOOTER -->

            <div style="
                text-align: center;
                color: #94a3b8;
                font-size: 13px;
                padding: 10px 0 20px;
            ">

                College Equipment Booking System
                &nbsp;•&nbsp;
                Administration Portal

            </div>

        </div>

    </main>

</div>

</body>
</html>