<?php

session_start();

require_once "../includes/db.php";

// ==============================================
// CHECK LOGIN
// ==============================================

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Only student / faculty
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

$message = "";
$error = "";


// ==============================================
// CANCEL BOOKING
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";

    $booking_id =
        intval(
            $_POST["booking_id"] ?? 0
        );


    if (
        $action === "cancel" &&
        $booking_id > 0
    ) {

        /*
         * Security:
         * user_id is included in WHERE.
         *
         * A user cannot cancel another
         * user's booking.
         *
         * Only pending bookings can
         * be cancelled.
         */

        $sql = "
            UPDATE bookings

            SET status = 'cancelled'

            WHERE booking_id = ?

            AND user_id = ?

            AND status = 'pending'
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
                "ii",
                $booking_id,
                $user_id
            );


            if (
                mysqli_stmt_execute(
                    $stmt
                )
            ) {

                if (
                    mysqli_stmt_affected_rows(
                        $stmt
                    ) > 0
                ) {

                    $message =
                        "Booking cancelled successfully.";

                } else {

                    $error =
                        "This booking cannot be cancelled.";
                }

            } else {

                $error =
                    "Unable to cancel booking: "
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


// ==============================================
// BOOKING COUNTS
// ==============================================

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$cancelledRejected = 0;

$count_sql = "
    SELECT

        COUNT(*) AS total,

        SUM(
            status = 'pending'
        ) AS pending,

        SUM(
            status = 'approved'
        ) AS approved,

        SUM(
            status IN (
                'cancelled',
                'rejected'
            )
        ) AS cancelled_rejected

    FROM bookings

    WHERE user_id = ?
";

$count_stmt =
    mysqli_prepare(
        $conn,
        $count_sql
    );

if ($count_stmt !== false) {

    mysqli_stmt_bind_param(
        $count_stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute(
        $count_stmt
    );

    $count_result =
        mysqli_stmt_get_result(
            $count_stmt
        );

    $counts =
        mysqli_fetch_assoc(
            $count_result
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

    $cancelledRejected =
        intval(
            $counts[
                "cancelled_rejected"
            ] ?? 0
        );

    mysqli_stmt_close(
        $count_stmt
    );
}


// ==============================================
// GET USER BOOKINGS
// ==============================================

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

        e.equipment_name,
        e.location,

        c.category_name

    FROM bookings b

    INNER JOIN equipment e
        ON b.equipment_id =
           e.equipment_id

    INNER JOIN categories c
        ON e.category_id =
           c.category_id

    WHERE b.user_id = ?

    ORDER BY
        b.booking_id DESC
";

$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );

if ($stmt === false) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute(
    $stmt
);

$result =
    mysqli_stmt_get_result(
        $stmt
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
        My Bookings | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=15"
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

                <a href="dashboard.php">
                    🏠 &nbsp; Dashboard
                </a>

            </li>


            <li>

                <a href="equipment.php">
                    💻 &nbsp; Browse Equipment
                </a>

            </li>


            <li>

                <a
                    href="my_bookings.php"
                    class="active"
                >
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
                    My Bookings
                </div>

                <small style="color:#64748b;">
                    Track your equipment requests
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
                 HEADER
            ========================================== -->

            <section class="welcome-box">

                <h1>
                    📅 My Equipment Bookings
                </h1>

                <p>
                    View your requests, approval status
                    and administrator remarks.
                </p>

            </section>


            <!-- =========================================
                 MESSAGES
            ========================================== -->

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


            <!-- =========================================
                 STATS
            ========================================== -->

            <div class="my-booking-stat-grid">


                <div class="my-booking-stat-card">

                    <div class="my-booking-stat-icon">
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


                <div class="my-booking-stat-card">

                    <div class="my-booking-stat-icon">
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


                <div class="my-booking-stat-card">

                    <div class="my-booking-stat-icon">
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


                <div class="my-booking-stat-card">

                    <div class="my-booking-stat-icon">
                        ❌
                    </div>

                    <div>

                        <span>
                            Cancelled / Rejected
                        </span>

                        <strong>
                            <?php
                            echo $cancelledRejected;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- =========================================
                 BOOKING LIST
            ========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            Booking History
                        </h2>

                        <p>
                            Your latest booking request
                            appears first.
                        </p>

                    </div>


                    <a
                        href="equipment.php"
                        class="btn btn-primary"
                    >
                        + Book Equipment
                    </a>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table class="my-bookings-table">


                            <thead>

                            <tr>

                                <th>
                                    Booking
                                </th>

                                <th>
                                    Equipment
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Qty
                                </th>

                                <th>
                                    Location
                                </th>

                                <th>
                                    Purpose
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Admin Remark
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $booking =
                                mysqli_fetch_assoc(
                                    $result
                                )
                            ): ?>


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


                                <tr>


                                    <!-- BOOKING ID -->

                                    <td>

                                        <div class="booking-id-cell">

                                            <strong>
                                                #<?php
                                                echo intval(
                                                    $booking[
                                                        "booking_id"
                                                    ]
                                                );
                                                ?>
                                            </strong>

                                            <small>

                                                <?php

                                                if (
                                                    !empty(
                                                        $booking[
                                                            "created_at"
                                                        ]
                                                    )
                                                ) {

                                                    echo htmlspecialchars(
                                                        date(
                                                            "d M Y",
                                                            strtotime(
                                                                $booking[
                                                                    "created_at"
                                                                ]
                                                            )
                                                        )
                                                    );

                                                }

                                                ?>

                                            </small>

                                        </div>

                                    </td>


                                    <!-- EQUIPMENT -->

                                    <td>

                                        <div class="my-booking-equipment">

                                            <div class="my-booking-equipment-icon">
                                                💻
                                            </div>

                                            <div>

                                                <strong>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $booking[
                                                            "equipment_name"
                                                        ]
                                                    );
                                                    ?>

                                                </strong>

                                                <small>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $booking[
                                                            "category_name"
                                                        ]
                                                    );
                                                    ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- DATE / TIME -->

                                    <td>

                                        <div class="booking-date-cell">

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    date(
                                                        "d M Y",
                                                        strtotime(
                                                            $booking[
                                                                "booking_date"
                                                            ]
                                                        )
                                                    )
                                                );
                                                ?>

                                            </strong>

                                            <span>

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

                                                -

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

                                            </span>

                                        </div>

                                    </td>


                                    <!-- QUANTITY -->

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


                                    <!-- LOCATION -->

                                    <td>

                                        <span class="booking-location">

                                            📍

                                            <?php
                                            echo htmlspecialchars(
                                                $booking[
                                                    "location"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- PURPOSE -->

                                    <td>

                                        <div class="booking-purpose">

                                            <?php

                                            $purpose =
                                                $booking[
                                                    "purpose"
                                                ];

                                            if (
                                                strlen(
                                                    $purpose
                                                ) > 90
                                            ) {

                                                echo htmlspecialchars(
                                                    substr(
                                                        $purpose,
                                                        0,
                                                        90
                                                    )
                                                )
                                                . "...";

                                            } else {

                                                echo htmlspecialchars(
                                                    $purpose
                                                );
                                            }

                                            ?>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

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

                                        <?php if (
                                            !empty(
                                                $booking[
                                                    "admin_remark"
                                                ]
                                            )
                                        ): ?>

                                            <div class="admin-remark-box">

                                                <?php
                                                echo htmlspecialchars(
                                                    $booking[
                                                        "admin_remark"
                                                    ]
                                                );
                                                ?>

                                            </div>

                                        <?php else: ?>

                                            <span class="no-remark">
                                                No remark
                                            </span>

                                        <?php endif; ?>

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
                                                class="cancel-booking-form"
                                                onsubmit="
                                                    return confirm(
                                                        'Are you sure you want to cancel this booking?'
                                                    );
                                                "
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

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="cancel"
                                                >

                                                <button
                                                    type="submit"
                                                    class="cancel-booking-btn"
                                                >
                                                    Cancel
                                                </button>

                                            </form>


                                        <?php elseif (
                                            $booking[
                                                "status"
                                            ] ===
                                            "approved"
                                        ): ?>


                                            <span class="booking-action-complete">
                                                ✓ Approved
                                            </span>


                                        <?php elseif (
                                            $booking[
                                                "status"
                                            ] ===
                                            "rejected"
                                        ): ?>


                                            <span class="booking-no-action">
                                                Rejected
                                            </span>


                                        <?php elseif (
                                            $booking[
                                                "status"
                                            ] ===
                                            "cancelled"
                                        ): ?>


                                            <span class="booking-no-action">
                                                Cancelled
                                            </span>


                                        <?php else: ?>


                                            <span class="booking-no-action">
                                                No action
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
                            No Bookings Yet
                        </h3>

                        <p>
                            You have not submitted any
                            equipment booking requests yet.
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


            <!-- =========================================
                 INFO BOX
            ========================================== -->

            <div class="booking-info-box">

                <div>
                    💡
                </div>

                <div>

                    <strong>
                        Booking Information
                    </strong>

                    <p>
                        Pending bookings can be cancelled.
                        Approved bookings have already been
                        accepted by the administrator.
                    </p>

                </div>

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
                My Bookings

            </div>


        </div>

    </main>

</div>


<?php

mysqli_stmt_close(
    $stmt
);

?>

</body>

</html>