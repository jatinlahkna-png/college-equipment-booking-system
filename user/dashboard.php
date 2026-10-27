<?php

session_start();

require_once "../includes/db.php";

// =====================================================
// CHECK LOGIN
// =====================================================

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// =====================================================
// CHECK STUDENT / FACULTY
// =====================================================

if (
    $_SESSION["role"] !== "student" &&
    $_SESSION["role"] !== "faculty"
) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$user_id = intval($_SESSION["user_id"]);

$userName =
    $_SESSION["name"] ?? "User";

$userRole =
    $_SESSION["role"] ?? "student";


// =====================================================
// DASHBOARD COUNTS
// =====================================================

$totalAvailable = 0;
$myBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;


// AVAILABLE EQUIPMENT
// Students should not count faculty-only equipment

if ($userRole === "student") {

    $available_sql = "
        SELECT COUNT(*) AS total
        FROM equipment
        WHERE status = 'available'
        AND access_level = 'everyone'
    ";

} else {

    $available_sql = "
        SELECT COUNT(*) AS total
        FROM equipment
        WHERE status = 'available'
    ";
}

$available_result =
    mysqli_query(
        $conn,
        $available_sql
    );

if ($available_result) {

    $available_data =
        mysqli_fetch_assoc(
            $available_result
        );

    $totalAvailable =
        intval(
            $available_data["total"] ?? 0
        );
}


// MY BOOKING COUNTS

$booking_count_sql = "
    SELECT
        COUNT(*) AS total,

        SUM(
            status = 'pending'
        ) AS pending,

        SUM(
            status = 'approved'
        ) AS approved

    FROM bookings

    WHERE user_id = ?
";

$booking_count_stmt =
    mysqli_prepare(
        $conn,
        $booking_count_sql
    );

if ($booking_count_stmt !== false) {

    mysqli_stmt_bind_param(
        $booking_count_stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute(
        $booking_count_stmt
    );

    $booking_count_result =
        mysqli_stmt_get_result(
            $booking_count_stmt
        );

    $booking_counts =
        mysqli_fetch_assoc(
            $booking_count_result
        );

    $myBookings =
        intval(
            $booking_counts["total"] ?? 0
        );

    $pendingBookings =
        intval(
            $booking_counts["pending"] ?? 0
        );

    $approvedBookings =
        intval(
            $booking_counts["approved"] ?? 0
        );

    mysqli_stmt_close(
        $booking_count_stmt
    );
}


// =====================================================
// RECENT BOOKINGS
// =====================================================

$recent_sql = "
    SELECT

        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.quantity,
        b.status,

        e.equipment_name

    FROM bookings b

    INNER JOIN equipment e
        ON b.equipment_id =
           e.equipment_id

    WHERE b.user_id = ?

    ORDER BY
        b.booking_id DESC

    LIMIT 5
";

$recent_stmt =
    mysqli_prepare(
        $conn,
        $recent_sql
    );

$recent_result = null;

if ($recent_stmt !== false) {

    mysqli_stmt_bind_param(
        $recent_stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute(
        $recent_stmt
    );

    $recent_result =
        mysqli_stmt_get_result(
            $recent_stmt
        );
}

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
        Dashboard | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=13"
    >

</head>

<body>

<div class="app-layout">


    <!-- =========================================
         SIDEBAR
    ========================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div style="
                font-size:38px;
                margin-bottom:10px;
            ">
                🎓
            </div>

            College Equipment

            <span>
                Booking System
            </span>

        </div>


        <ul class="sidebar-menu">

            <li>

                <a
                    href="dashboard.php"
                    class="active"
                >
                    🏠 &nbsp; Dashboard
                </a>

            </li>


            <li>

                <a href="equipment.php">
                    💻 &nbsp; Browse Equipment
                </a>

            </li>


            <li>

                <a href="my_bookings.php">
                    📅 &nbsp; My Bookings
                </a>

            </li>


            <li>

                <a href="recommendations.php">
                    ⭐ &nbsp; Recommendations
                </a>

            </li>


            <li style="
                margin-top:30px;
                border-top:1px solid
                rgba(255,255,255,0.15);
                padding-top:18px;
            ">

                <a href="../auth/logout.php">
                    🚪 &nbsp; Logout
                </a>

            </li>

        </ul>

    </aside>


    <!-- =========================================
         MAIN CONTENT
    ========================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    User Dashboard
                </div>

                <small style="color:#64748b;">

                    <?php
                    echo
                        $userRole === "faculty"
                        ? "Faculty Equipment Portal"
                        : "Student Equipment Portal";
                    ?>

                </small>

            </div>


            <div class="user-info">

                <div style="text-align:right;">

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $userName
                        );
                        ?>

                    </strong>

                    <div style="
                        font-size:12px;
                        color:#64748b;
                    ">

                        <?php
                        echo htmlspecialchars(
                            ucfirst(
                                $userRole
                            )
                        );
                        ?>

                    </div>

                </div>


                <span class="role-badge">

                    <?php
                    echo htmlspecialchars(
                        strtoupper(
                            $userRole
                        )
                    );
                    ?>

                </span>

            </div>

        </header>


        <div class="content">


            <!-- =========================================
                 WELCOME
            ========================================== -->

            <section class="welcome-box">

                <h1>

                    👋 Welcome,
                    <?php
                    echo htmlspecialchars(
                        $userName
                    );
                    ?>!

                </h1>


                <p>

                    Find equipment, make bookings,
                    track requests and get smart
                    recommendations from one place.

                </p>

            </section>


            <!-- =========================================
                 STATS
            ========================================== -->

            <div class="user-dashboard-grid">


                <div class="user-dashboard-stat">

                    <div class="user-dashboard-stat-icon">
                        💻
                    </div>

                    <div>

                        <span>
                            Available Equipment
                        </span>

                        <strong>
                            <?php
                            echo $totalAvailable;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-dashboard-stat">

                    <div class="user-dashboard-stat-icon">
                        📅
                    </div>

                    <div>

                        <span>
                            My Bookings
                        </span>

                        <strong>
                            <?php
                            echo $myBookings;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-dashboard-stat">

                    <div class="user-dashboard-stat-icon">
                        ⏳
                    </div>

                    <div>

                        <span>
                            Pending
                        </span>

                        <strong>
                            <?php
                            echo $pendingBookings;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-dashboard-stat">

                    <div class="user-dashboard-stat-icon">
                        ✅
                    </div>

                    <div>

                        <span>
                            Approved
                        </span>

                        <strong>
                            <?php
                            echo $approvedBookings;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- =========================================
                 QUICK ACTIONS
            ========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Choose what you want to do.
                        </p>

                    </div>

                </div>


                <div class="student-action-grid">


                    <a
                        href="equipment.php"
                        class="student-action-card"
                    >

                        <div class="student-action-icon">
                            💻
                        </div>

                        <div>

                            <h3>
                                Browse Equipment
                            </h3>

                            <p>
                                View available equipment
                                and make a booking request.
                            </p>

                        </div>

                    </a>


                    <a
                        href="my_bookings.php"
                        class="student-action-card"
                    >

                        <div class="student-action-icon">
                            📅
                        </div>

                        <div>

                            <h3>
                                My Bookings
                            </h3>

                            <p>
                                Track approved, pending
                                and previous bookings.
                            </p>

                        </div>

                    </a>


                    <a
                        href="recommendations.php"
                        class="student-action-card"
                    >

                        <div class="student-action-icon">
                            ⭐
                        </div>

                        <div>

                            <h3>
                                Smart Recommendation
                            </h3>

                            <p>
                                Tell the system your activity
                                and get suitable equipment.
                            </p>

                        </div>

                    </a>


                </div>

            </div>


            <!-- =========================================
                 SMART FEATURE
            ========================================== -->

            <div class="smart-feature-card">


                <div class="smart-feature-icon">
                    ✨
                </div>


                <div class="smart-feature-content">

                    <span>
                        SMART FEATURE
                    </span>

                    <h2>
                        Not sure which equipment you need?
                    </h2>

                    <p>

                        Enter activities such as
                        presentation, photography,
                        coding, seminar, online meeting
                        or sports event and the system
                        will suggest suitable equipment.

                    </p>

                </div>


                <a
                    href="recommendations.php"
                    class="smart-feature-btn"
                >
                    Get Recommendation →
                </a>


            </div>


            <!-- =========================================
                 RECENT BOOKINGS
            ========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Recent Bookings
                        </h2>

                        <p>
                            Your latest equipment requests.
                        </p>

                    </div>


                    <a
                        href="my_bookings.php"
                        class="dashboard-view-all"
                    >
                        View All →
                    </a>

                </div>


                <?php if (
                    $recent_result &&
                    mysqli_num_rows(
                        $recent_result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table class="user-dashboard-table">

                            <thead>

                            <tr>

                                <th>
                                    Booking ID
                                </th>

                                <th>
                                    Equipment
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Time
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $booking =
                                mysqli_fetch_assoc(
                                    $recent_result
                                )
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>
                                            #<?php
                                            echo intval(
                                                $booking[
                                                    "booking_id"
                                                ]
                                            );
                                            ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $booking[
                                                    "equipment_name"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking[
                                                "booking_date"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <td class="booking-time">

                                        <?php
                                        echo htmlspecialchars(
                                            substr(
                                                $booking[
                                                    "start_time"
                                                ],
                                                0,
                                                5
                                            )
                                        );
                                        ?>

                                        <span>
                                            →
                                        </span>

                                        <?php
                                        echo htmlspecialchars(
                                            substr(
                                                $booking[
                                                    "end_time"
                                                ],
                                                0,
                                                5
                                            )
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <span class="quantity-chip">

                                            <?php
                                            echo intval(
                                                $booking[
                                                    "quantity"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php

                                        $status =
                                            strtolower(
                                                $booking[
                                                    "status"
                                                ]
                                            );

                                        $statusClass =
                                            "badge-info";

                                        if (
                                            $status ===
                                            "approved"
                                        ) {

                                            $statusClass =
                                                "badge-success";

                                        } elseif (
                                            $status ===
                                            "pending"
                                        ) {

                                            $statusClass =
                                                "badge-warning";

                                        } elseif (
                                            $status ===
                                            "rejected" ||
                                            $status ===
                                            "cancelled"
                                        ) {

                                            $statusClass =
                                                "badge-danger";
                                        }

                                        ?>

                                        <span
                                            class="badge <?php
                                            echo $statusClass;
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $status
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="empty-state">

                        <div>
                            📅
                        </div>

                        <h3>
                            No Bookings Yet
                        </h3>

                        <p>
                            Browse equipment and submit
                            your first booking request.
                        </p>

                        <br>

                        <a
                            href="equipment.php"
                            class="btn btn-primary"
                        >
                            Browse Equipment
                        </a>

                    </div>


                <?php endif; ?>


            </div>


            <!-- FOOTER -->

            <div style="
                text-align:center;
                color:#94a3b8;
                font-size:13px;
                padding:10px 0 20px;
            ">

                College Equipment Booking System

                &nbsp;•&nbsp;

                <?php
                echo htmlspecialchars(
                    ucfirst(
                        $userRole
                    )
                );
                ?> Portal

            </div>


        </div>

    </main>

</div>


<?php

if ($recent_stmt !== false) {
    mysqli_stmt_close(
        $recent_stmt
    );
}

?>

</body>

</html>