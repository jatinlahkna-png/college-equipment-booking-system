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

if (
    $_SESSION["role"] !== "student" &&
    $_SESSION["role"] !== "faculty"
) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$userName = $_SESSION["name"] ?? "User";
$userRole = $_SESSION["role"] ?? "student";

$message = "";
$error = "";


// ==============================================
// HANDLE BOOKING
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $equipment_id =
        intval($_POST["equipment_id"] ?? 0);

    $quantity =
        intval($_POST["quantity"] ?? 0);

    $booking_date =
        trim($_POST["booking_date"] ?? "");

    $start_time =
        trim($_POST["start_time"] ?? "");

    $end_time =
        trim($_POST["end_time"] ?? "");

    $purpose =
        trim($_POST["purpose"] ?? "");


    // ==========================================
    // VALIDATION
    // ==========================================

    if ($equipment_id <= 0) {

        $error =
            "Please select valid equipment.";

    } elseif ($quantity <= 0) {

        $error =
            "Quantity must be at least 1.";

    } elseif ($booking_date === "") {

        $error =
            "Please select a booking date.";

    } elseif ($start_time === "") {

        $error =
            "Please select a start time.";

    } elseif ($end_time === "") {

        $error =
            "Please select an end time.";

    } elseif ($purpose === "") {

        $error =
            "Please enter the purpose of booking.";

    } elseif ($start_time >= $end_time) {

        $error =
            "End time must be later than start time.";

    } elseif ($booking_date < date("Y-m-d")) {

        $error =
            "Booking date cannot be in the past.";

    } else {


        // ======================================
        // GET EQUIPMENT
        // ======================================

        $equipment_sql = "
            SELECT
                equipment_id,
                equipment_name,
                quantity,
                status,
                access_level
            FROM equipment
            WHERE equipment_id = ?
            LIMIT 1
        ";

        $equipment_stmt =
            mysqli_prepare(
                $conn,
                $equipment_sql
            );

        if ($equipment_stmt === false) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $equipment_stmt,
                "i",
                $equipment_id
            );

            mysqli_stmt_execute(
                $equipment_stmt
            );

            $equipment_result =
                mysqli_stmt_get_result(
                    $equipment_stmt
                );

            $equipment =
                mysqli_fetch_assoc(
                    $equipment_result
                );

            mysqli_stmt_close(
                $equipment_stmt
            );


            // ==================================
            // ACCESS CHECK
            // ==================================

            if (!$equipment) {

                $error =
                    "Equipment not found.";

            } elseif (
                $equipment["status"]
                !== "available"
            ) {

                $error =
                    "This equipment is currently unavailable.";

            } elseif (
                $equipment["access_level"]
                === "faculty_only"
                &&
                $userRole !== "faculty"
            ) {

                $error =
                    "This equipment can only be booked by faculty members.";

            } elseif (
                $quantity >
                intval($equipment["quantity"])
            ) {

                $error =
                    "Requested quantity is greater than total quantity.";

            } else {


                // ==================================
                // CHECK OVERLAPPING BOOKINGS
                // ==================================

                $availability_sql = "
                    SELECT
                        COALESCE(
                            SUM(quantity),
                            0
                        ) AS booked_quantity

                    FROM bookings

                    WHERE equipment_id = ?

                    AND booking_date = ?

                    AND status IN (
                        'pending',
                        'approved'
                    )

                    AND start_time < ?

                    AND end_time > ?
                ";

                $availability_stmt =
                    mysqli_prepare(
                        $conn,
                        $availability_sql
                    );

                if (
                    $availability_stmt
                    === false
                ) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $availability_stmt,
                        "isss",
                        $equipment_id,
                        $booking_date,
                        $end_time,
                        $start_time
                    );

                    mysqli_stmt_execute(
                        $availability_stmt
                    );

                    $availability_result =
                        mysqli_stmt_get_result(
                            $availability_stmt
                        );

                    $availability =
                        mysqli_fetch_assoc(
                            $availability_result
                        );

                    mysqli_stmt_close(
                        $availability_stmt
                    );


                    $booked_quantity =
                        intval(
                            $availability[
                                "booked_quantity"
                            ] ?? 0
                        );

                    $remaining_quantity =
                        intval(
                            $equipment["quantity"]
                        )
                        -
                        $booked_quantity;


                    if (
                        $quantity >
                        $remaining_quantity
                    ) {

                        $error =
                            "Only "
                            . max(
                                0,
                                $remaining_quantity
                            )
                            . " unit(s) are available for the selected date and time.";

                    } else {


                        // ==================================
                        // INSERT BOOKING
                        // ==================================

                        $insert_sql = "
                            INSERT INTO bookings
                            (
                                user_id,
                                equipment_id,
                                quantity,
                                booking_date,
                                start_time,
                                end_time,
                                purpose,
                                status
                            )

                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, 'pending')
                        ";

                        $insert_stmt =
                            mysqli_prepare(
                                $conn,
                                $insert_sql
                            );

                        if (
                            $insert_stmt
                            === false
                        ) {

                            $error =
                                "Database error: "
                                . mysqli_error($conn);

                        } else {

                            $sessionUserId =
                                intval(
                                    $_SESSION[
                                        "user_id"
                                    ]
                                );

                            mysqli_stmt_bind_param(
                                $insert_stmt,
                                "iiissss",
                                $sessionUserId,
                                $equipment_id,
                                $quantity,
                                $booking_date,
                                $start_time,
                                $end_time,
                                $purpose
                            );


                            if (
                                mysqli_stmt_execute(
                                    $insert_stmt
                                )
                            ) {

                                $message =
                                    "Booking submitted successfully! Your request is now pending admin approval.";

                            } else {

                                $error =
                                    "Unable to submit booking: "
                                    . mysqli_stmt_error(
                                        $insert_stmt
                                    );
                            }

                            mysqli_stmt_close(
                                $insert_stmt
                            );
                        }
                    }
                }
            }
        }
    }
}


// ==============================================
// GET EQUIPMENT
// ==============================================

$equipment_sql = "
    SELECT
        e.equipment_id,
        e.equipment_name,
        e.description,
        e.quantity,
        e.location,
        e.equipment_condition,
        e.status,
        e.access_level,
        e.image,
        c.category_name

    FROM equipment e

    INNER JOIN categories c
        ON e.category_id =
           c.category_id

    ORDER BY
        e.equipment_name ASC
";

$equipment_result =
    mysqli_query(
        $conn,
        $equipment_sql
    );

if ($equipment_result === false) {

    die(
        "Equipment query failed: "
        . mysqli_error($conn)
    );
}


// ==============================================
// COUNTS
// ==============================================

$totalEquipment = 0;
$availableEquipment = 0;
$facultyOnly = 0;

$count_sql = "
    SELECT

        COUNT(*) AS total,

        SUM(
            status = 'available'
        ) AS available,

        SUM(
            access_level = 'faculty_only'
        ) AS faculty_only

    FROM equipment
";

$count_result =
    mysqli_query(
        $conn,
        $count_sql
    );

if ($count_result) {

    $count_data =
        mysqli_fetch_assoc(
            $count_result
        );

    $totalEquipment =
        intval(
            $count_data["total"] ?? 0
        );

    $availableEquipment =
        intval(
            $count_data["available"] ?? 0
        );

    $facultyOnly =
        intval(
            $count_data[
                "faculty_only"
            ] ?? 0
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
        Browse Equipment | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=14"
    >

</head>

<body>

<div class="app-layout">


    <!-- ==========================================
         SIDEBAR
    =========================================== -->

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

                <a
                    href="equipment.php"
                    class="active"
                >
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


    <!-- ==========================================
         MAIN CONTENT
    =========================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Browse Equipment
                </div>

                <small style="color:#64748b;">
                    Find and book college equipment
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


            <!-- ==========================================
                 WELCOME
            =========================================== -->

            <section class="welcome-box">

                <h1>
                    💻 Browse & Book Equipment
                </h1>

                <p>
                    Explore college equipment and
                    submit your booking request.
                </p>

            </section>


            <!-- ==========================================
                 MESSAGES
            =========================================== -->

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


            <!-- ==========================================
                 STATS
            =========================================== -->

            <div class="browse-stat-grid">


                <div class="browse-stat-card">

                    <div class="browse-stat-icon">
                        💻
                    </div>

                    <div>

                        <span>
                            Total Equipment
                        </span>

                        <strong>
                            <?php
                            echo $totalEquipment;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="browse-stat-card">

                    <div class="browse-stat-icon">
                        ✅
                    </div>

                    <div>

                        <span>
                            Available
                        </span>

                        <strong>
                            <?php
                            echo $availableEquipment;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="browse-stat-card">

                    <div class="browse-stat-icon">
                        🔒
                    </div>

                    <div>

                        <span>
                            Faculty Only
                        </span>

                        <strong>
                            <?php
                            echo $facultyOnly;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- ==========================================
                 EQUIPMENT SECTION
            =========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            Equipment Collection
                        </h2>

                        <p>

                            <?php if (
                                $userRole ===
                                "student"
                            ): ?>

                                Faculty-only equipment
                                can be viewed but cannot
                                be booked by students.

                            <?php else: ?>

                                As a faculty member,
                                you can book both general
                                and faculty-only equipment.

                            <?php endif; ?>

                        </p>

                    </div>


                    <a
                        href="recommendations.php"
                        class="btn btn-primary"
                    >
                        ⭐ Smart Recommendation
                    </a>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $equipment_result
                    ) > 0
                ): ?>


                    <div class="equipment-card-grid">


                        <?php while (
                            $equipment =
                            mysqli_fetch_assoc(
                                $equipment_result
                            )
                        ): ?>


                            <?php

                            $can_book = true;

                            if (
                                $equipment["status"]
                                !== "available"
                            ) {

                                $can_book = false;
                            }

                            if (
                                $equipment[
                                    "access_level"
                                ]
                                === "faculty_only"
                                &&
                                $userRole
                                !== "faculty"
                            ) {

                                $can_book = false;
                            }

                            ?>


                            <div class="booking-equipment-card">


                                <!-- IMAGE -->

                                <div class="booking-equipment-image">


                                    <?php if (
                                        !empty(
                                            $equipment[
                                                "image"
                                            ]
                                        )
                                    ): ?>

                                        <img
                                            src="../uploads/<?php
                                            echo htmlspecialchars(
                                                $equipment[
                                                    "image"
                                                ]
                                            );
                                            ?>"
                                            alt="<?php
                                            echo htmlspecialchars(
                                                $equipment[
                                                    "equipment_name"
                                                ]
                                            );
                                            ?>"
                                        >

                                    <?php else: ?>

                                        <div class="booking-no-image">
                                            💻
                                        </div>

                                    <?php endif; ?>


                                    <!-- STATUS -->

                                    <div class="equipment-status-overlay">

                                        <?php if (
                                            $equipment[
                                                "status"
                                            ] ===
                                            "available"
                                        ): ?>

                                            <span class="
                                                badge
                                                badge-success
                                            ">
                                                Available
                                            </span>

                                        <?php elseif (
                                            $equipment[
                                                "status"
                                            ] ===
                                            "maintenance"
                                        ): ?>

                                            <span class="
                                                badge
                                                badge-warning
                                            ">
                                                Maintenance
                                            </span>

                                        <?php else: ?>

                                            <span class="
                                                badge
                                                badge-danger
                                            ">
                                                Unavailable
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                </div>


                                <!-- BODY -->

                                <div class="booking-equipment-body">


                                    <div class="booking-equipment-category">

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "category_name"
                                            ]
                                        );
                                        ?>

                                    </div>


                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "equipment_name"
                                            ]
                                        );
                                        ?>

                                    </h3>


                                    <p class="booking-equipment-description">

                                        <?php

                                        $description =
                                            trim(
                                                $equipment[
                                                    "description"
                                                ] ?? ""
                                            );

                                        if (
                                            $description
                                            === ""
                                        ) {

                                            echo
                                                "No description available.";

                                        } elseif (
                                            strlen(
                                                $description
                                            ) > 120
                                        ) {

                                            echo htmlspecialchars(
                                                substr(
                                                    $description,
                                                    0,
                                                    120
                                                )
                                            )
                                            . "...";

                                        } else {

                                            echo htmlspecialchars(
                                                $description
                                            );
                                        }

                                        ?>

                                    </p>


                                    <!-- DETAILS -->

                                    <div class="equipment-detail-grid">


                                        <div>

                                            <span>
                                                Quantity
                                            </span>

                                            <strong>
                                                <?php
                                                echo intval(
                                                    $equipment[
                                                        "quantity"
                                                    ]
                                                );
                                                ?>
                                            </strong>

                                        </div>


                                        <div>

                                            <span>
                                                Condition
                                            </span>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $equipment[
                                                        "equipment_condition"
                                                    ]
                                                );
                                                ?>

                                            </strong>

                                        </div>


                                    </div>


                                    <div class="equipment-location">

                                        📍

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "location"
                                            ]
                                        );
                                        ?>

                                    </div>


                                    <!-- ACCESS -->

                                    <div class="equipment-access-row">


                                        <?php if (
                                            $equipment[
                                                "access_level"
                                            ]
                                            ===
                                            "faculty_only"
                                        ): ?>

                                            <span class="
                                                badge
                                                badge-purple
                                            ">
                                                🔒 Faculty Only
                                            </span>

                                        <?php else: ?>

                                            <span class="
                                                badge
                                                badge-info
                                            ">
                                                👥 Everyone
                                            </span>

                                        <?php endif; ?>


                                    </div>


                                    <!-- ==================================
                                         BOOKING
                                    =================================== -->

                                    <?php if ($can_book): ?>


                                        <details class="booking-details">

                                            <summary>
                                                📅 Book This Equipment
                                            </summary>


                                            <form
                                                method="POST"
                                                class="equipment-booking-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="equipment_id"
                                                    value="<?php
                                                    echo intval(
                                                        $equipment[
                                                            "equipment_id"
                                                        ]
                                                    );
                                                    ?>"
                                                >


                                                <div class="booking-form-row">


                                                    <div class="form-group">

                                                        <label>
                                                            Quantity
                                                        </label>

                                                        <input
                                                            type="number"
                                                            name="quantity"
                                                            min="1"
                                                            max="<?php
                                                            echo intval(
                                                                $equipment[
                                                                    "quantity"
                                                                ]
                                                            );
                                                            ?>"
                                                            value="1"
                                                            required
                                                        >

                                                    </div>


                                                    <div class="form-group">

                                                        <label>
                                                            Date
                                                        </label>

                                                        <input
                                                            type="date"
                                                            name="booking_date"
                                                            min="<?php
                                                            echo date(
                                                                "Y-m-d"
                                                            );
                                                            ?>"
                                                            required
                                                        >

                                                    </div>


                                                </div>


                                                <div class="booking-form-row">


                                                    <div class="form-group">

                                                        <label>
                                                            Start Time
                                                        </label>

                                                        <input
                                                            type="time"
                                                            name="start_time"
                                                            required
                                                        >

                                                    </div>


                                                    <div class="form-group">

                                                        <label>
                                                            End Time
                                                        </label>

                                                        <input
                                                            type="time"
                                                            name="end_time"
                                                            required
                                                        >

                                                    </div>


                                                </div>


                                                <div class="form-group">

                                                    <label>
                                                        Purpose
                                                    </label>

                                                    <textarea
                                                        name="purpose"
                                                        rows="3"
                                                        maxlength="255"
                                                        placeholder="Example: Classroom presentation"
                                                        required
                                                    ></textarea>

                                                </div>


                                                <button
                                                    type="submit"
                                                    class="btn btn-primary booking-submit-btn"
                                                    onclick="
                                                        return confirm(
                                                            'Submit this booking request?'
                                                        );
                                                    "
                                                >
                                                    Submit Booking Request
                                                </button>


                                            </form>

                                        </details>


                                    <?php else: ?>


                                        <div class="booking-disabled-box">


                                            <?php if (
                                                $equipment[
                                                    "status"
                                                ]
                                                !== "available"
                                            ): ?>

                                                <strong>
                                                    🚫 Currently Unavailable
                                                </strong>

                                                <span>
                                                    This equipment cannot
                                                    be booked right now.
                                                </span>


                                            <?php elseif (
                                                $equipment[
                                                    "access_level"
                                                ]
                                                ===
                                                "faculty_only"
                                                &&
                                                $userRole
                                                !== "faculty"
                                            ): ?>

                                                <strong>
                                                    🔒 Faculty Only
                                                </strong>

                                                <span>
                                                    Students cannot book
                                                    this equipment.
                                                </span>

                                            <?php endif; ?>


                                        </div>


                                    <?php endif; ?>


                                </div>

                            </div>


                        <?php endwhile; ?>


                    </div>


                <?php else: ?>


                    <div class="empty-state">

                        <div>
                            💻
                        </div>

                        <h3>
                            No Equipment Found
                        </h3>

                        <p>
                            Equipment added by the
                            administrator will appear here.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


            <div style="
                text-align:center;
                color:#94a3b8;
                font-size:13px;
                padding:10px 0 20px;
            ">

                College Equipment Booking System
                &nbsp;•&nbsp;
                Browse Equipment

            </div>


        </div>

    </main>

</div>

</body>

</html>