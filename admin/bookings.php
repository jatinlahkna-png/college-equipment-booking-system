<?php

session_start();

require_once "../includes/db.php";

// ==================================================
// CHECK LOGIN
// ==================================================

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// ==================================================
// ONLY ADMIN
// ==================================================

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";

$message = "";
$error = "";

// ==================================================
// APPROVE / REJECT BOOKING
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_id =
        intval($_POST["booking_id"] ?? 0);

    $action =
        $_POST["action"] ?? "";

    $admin_remark =
        trim($_POST["admin_remark"] ?? "");

    if ($booking_id <= 0) {

        $error =
            "Invalid booking ID.";

    } elseif (
        $action !== "approve" &&
        $action !== "reject"
    ) {

        $error =
            "Invalid action.";

    } else {

        $new_status =
            $action === "approve"
            ? "approved"
            : "rejected";

        $sql = "
            UPDATE bookings
            SET
                status = ?,
                admin_remark = ?
            WHERE booking_id = ?
        ";

        $stmt =
            mysqli_prepare(
                $conn,
                $sql
            );

        if ($stmt === false) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $new_status,
                $admin_remark,
                $booking_id
            );

            if (
                mysqli_stmt_execute(
                    $stmt
                )
            ) {

                if (
                    $new_status ===
                    "approved"
                ) {

                    $message =
                        "Booking approved successfully.";

                } else {

                    $message =
                        "Booking rejected successfully.";
                }

            } else {

                $error =
                    "Unable to update booking: "
                    . mysqli_stmt_error(
                        $stmt
                    );
            }

            mysqli_stmt_close(
                $stmt
            );
        }
    }
}


// ==================================================
// GET BOOKINGS
// ==================================================

$sql = "
    SELECT
        b.booking_id,
        b.quantity,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.purpose,
        b.status,
        b.admin_remark,
        b.created_at,
        u.name AS user_name,
        u.email AS user_email,
        u.role AS user_role,
        e.equipment_name,
        c.category_name

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.user_id

    INNER JOIN equipment e
        ON b.equipment_id =
           e.equipment_id

    INNER JOIN categories c
        ON e.category_id =
           c.category_id

    ORDER BY
        b.booking_id DESC
";

$result =
    mysqli_query(
        $conn,
        $sql
    );

if ($result === false) {

    die(
        "Booking query failed: "
        . mysqli_error($conn)
    );
}


// ==================================================
// BOOKING COUNTS
// ==================================================

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$rejectedBookings = 0;

$countSql = "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')
            AS pending,
        SUM(status = 'approved')
            AS approved,
        SUM(status = 'rejected')
            AS rejected
    FROM bookings
";

$countResult =
    mysqli_query(
        $conn,
        $countSql
    );

if ($countResult) {

    $counts =
        mysqli_fetch_assoc(
            $countResult
        );

    $totalBookings =
        intval(
            $counts["total"] ?? 0
        );

    $pendingBookings =
        intval(
            $counts["pending"] ?? 0
        );

    $approvedBookings =
        intval(
            $counts["approved"] ?? 0
        );

    $rejectedBookings =
        intval(
            $counts["rejected"] ?? 0
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
        Bookings | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=9"
    >

</head>

<body>

<div class="app-layout">


    <!-- =====================================
         SIDEBAR
    ====================================== -->

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
                <a href="dashboard.php">
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
                <a
                    href="bookings.php"
                    class="active"
                >
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


    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Booking Management
                </div>

                <small style="color:#64748b;">
                    Review and manage equipment requests
                </small>

            </div>


            <div class="user-info">

                <div style="text-align:right;">

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $adminName
                        );
                        ?>
                    </strong>

                    <div style="
                        font-size:12px;
                        color:#64748b;
                    ">
                        System Administrator
                    </div>

                </div>

                <span class="role-badge">
                    ADMIN
                </span>

            </div>

        </header>


        <div class="content">


            <!-- =====================================
                 PAGE HEADER
            ====================================== -->

            <section class="welcome-box">

                <h1>
                    📅 Booking Management
                </h1>

                <p>
                    Review booking requests,
                    approve or reject reservations,
                    and view booking activity.
                </p>

            </section>


            <!-- SUCCESS MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="alert alert-success">

                    ✅
                    <?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- ERROR MESSAGE -->

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">

                    ⚠️
                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- =====================================
                 BOOKING STATS
            ====================================== -->

            <div class="booking-stat-grid">


                <div class="booking-stat-card">

                    <div class="booking-stat-icon">
                        📋
                    </div>

                    <div>

                        <span>
                            Total Bookings
                        </span>

                        <strong>
                            <?php
                            echo $totalBookings;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="booking-stat-card">

                    <div class="booking-stat-icon">
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


                <div class="booking-stat-card">

                    <div class="booking-stat-icon">
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


                <div class="booking-stat-card">

                    <div class="booking-stat-icon">
                        ❌
                    </div>

                    <div>

                        <span>
                            Rejected
                        </span>

                        <strong>
                            <?php
                            echo $rejectedBookings;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- =====================================
                 BOOKING TABLE
            ====================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Booking Requests
                        </h2>

                        <p>
                            View all booking requests
                            submitted by students and faculty.
                        </p>

                    </div>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table class="booking-table">

                            <thead>

                            <tr>

                                <th>ID</th>

                                <th>User</th>

                                <th>Role</th>

                                <th>Equipment</th>

                                <th>Category</th>

                                <th>Qty</th>

                                <th>Date</th>

                                <th>Time</th>

                                <th>Purpose</th>

                                <th>Status</th>

                                <th>Remark</th>

                                <th>Action</th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $booking =
                                mysqli_fetch_assoc(
                                    $result
                                )
                            ): ?>


                                <tr>


                                    <!-- ID -->

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


                                    <!-- USER -->

                                    <td>

                                        <strong
                                            style="
                                                color:#134e4a;
                                            "
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $booking[
                                                    "user_name"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <br>

                                        <small
                                            style="
                                                color:#64748b;
                                            "
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $booking[
                                                    "user_email"
                                                ]
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <!-- ROLE -->

                                    <td>

                                        <?php

                                        $role =
                                            strtolower(
                                                $booking[
                                                    "user_role"
                                                ]
                                            );

                                        ?>

                                        <span
                                            class="badge badge-info"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $role
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- EQUIPMENT -->

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


                                    <!-- CATEGORY -->

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking[
                                                "category_name"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- QUANTITY -->

                                    <td>

                                        <span
                                            class="quantity-chip"
                                        >

                                            <?php
                                            echo intval(
                                                $booking[
                                                    "quantity"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking[
                                                "booking_date"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- TIME -->

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

                                        <br>

                                        <span>
                                            to
                                        </span>

                                        <br>

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


                                    <!-- PURPOSE -->

                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $booking[
                                                    "purpose"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $booking[
                                                    "purpose"
                                                ]
                                            );

                                        } else {

                                            echo "<span style='color:#94a3b8;'>-</span>";
                                        }

                                        ?>

                                    </td>


                                    <!-- STATUS -->

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


                                    <!-- ADMIN REMARK -->

                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $booking[
                                                    "admin_remark"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $booking[
                                                    "admin_remark"
                                                ]
                                            );

                                        } else {

                                            echo "<span style='color:#94a3b8;'>-</span>";
                                        }

                                        ?>

                                    </td>


                                    <!-- ACTION -->

                                    <td>

                                        <?php if (
                                            $booking[
                                                "status"
                                            ] ===
                                            "pending"
                                        ): ?>


                                            <form
                                                method="POST"
                                                class="booking-action-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="booking_id"
                                                    value="<?php
                                                    echo intval(
                                                        $booking[
                                                            "booking_id"
                                                        ]
                                                    );
                                                    ?>"
                                                >


                                                <textarea
                                                    name="admin_remark"
                                                    rows="2"
                                                    placeholder="Optional remark"
                                                ></textarea>


                                                <div
                                                    class="booking-action-buttons"
                                                >

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="approve"
                                                        class="booking-approve-btn"
                                                        onclick="
                                                            return confirm(
                                                                'Approve this booking?'
                                                            );
                                                        "
                                                    >
                                                        ✓ Approve
                                                    </button>


                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="reject"
                                                        class="booking-reject-btn"
                                                        onclick="
                                                            return confirm(
                                                                'Reject this booking?'
                                                            );
                                                        "
                                                    >
                                                        ✕ Reject
                                                    </button>

                                                </div>

                                            </form>


                                        <?php else: ?>


                                            <span
                                                class="booking-completed-text"
                                            >
                                                ✓ Processed
                                            </span>


                                        <?php endif; ?>

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
                            No Booking Requests
                        </h3>

                        <p>
                            Booking requests will
                            appear here when users
                            reserve equipment.
                        </p>

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
                Booking Management

            </div>


        </div>

    </main>

</div>

</body>

</html>