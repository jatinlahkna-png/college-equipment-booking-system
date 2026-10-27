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

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";

$message = "";
$error = "";


// ==============================================
// HANDLE ACTIONS
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    // ==========================================
    // START MAINTENANCE
    // ==========================================

    if ($action === "start") {

        $equipment_id =
            intval($_POST["equipment_id"] ?? 0);

        $issue =
            trim($_POST["issue"] ?? "");

        $maintenance_date =
            $_POST["maintenance_date"] ?? "";

        $expected_end_date =
            $_POST["expected_end_date"] ?? "";

        $remarks =
            trim($_POST["remarks"] ?? "");


        if ($equipment_id <= 0) {

            $error =
                "Please select equipment.";

        } elseif ($issue === "") {

            $error =
                "Please enter the maintenance issue.";

        } elseif ($maintenance_date === "") {

            $error =
                "Please select the maintenance start date.";

        } elseif ($expected_end_date === "") {

            $error =
                "Please select the expected completion date.";

        } elseif (
            $expected_end_date <
            $maintenance_date
        ) {

            $error =
                "Expected completion date cannot be before the start date.";

        } else {

            // CHECK ACTIVE MAINTENANCE

            $check_sql = "
                SELECT maintenance_id
                FROM maintenance
                WHERE equipment_id = ?
                AND status IN (
                    'pending',
                    'in_progress'
                )
                LIMIT 1
            ";

            $check_stmt =
                mysqli_prepare(
                    $conn,
                    $check_sql
                );

            if ($check_stmt === false) {

                $error =
                    "Database error: "
                    . mysqli_error($conn);

            } else {

                mysqli_stmt_bind_param(
                    $check_stmt,
                    "i",
                    $equipment_id
                );

                mysqli_stmt_execute(
                    $check_stmt
                );

                $check_result =
                    mysqli_stmt_get_result(
                        $check_stmt
                    );


                if (
                    mysqli_num_rows(
                        $check_result
                    ) > 0
                ) {

                    $error =
                        "This equipment already has an active maintenance record.";

                } else {

                    $status =
                        "in_progress";


                    // INSERT MAINTENANCE

                    $insert_sql = "
                        INSERT INTO maintenance
                        (
                            equipment_id,
                            issue,
                            maintenance_date,
                            expected_end_date,
                            status,
                            remarks
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?)
                    ";

                    $insert_stmt =
                        mysqli_prepare(
                            $conn,
                            $insert_sql
                        );

                    if ($insert_stmt === false) {

                        $error =
                            "Database error: "
                            . mysqli_error($conn);

                    } else {

                        mysqli_stmt_bind_param(
                            $insert_stmt,
                            "isssss",
                            $equipment_id,
                            $issue,
                            $maintenance_date,
                            $expected_end_date,
                            $status,
                            $remarks
                        );


                        if (
                            mysqli_stmt_execute(
                                $insert_stmt
                            )
                        ) {

                            // MAKE EQUIPMENT UNAVAILABLE

                            $update_sql = "
                                UPDATE equipment
                                SET status = 'maintenance'
                                WHERE equipment_id = ?
                            ";

                            $update_stmt =
                                mysqli_prepare(
                                    $conn,
                                    $update_sql
                                );

                            if ($update_stmt !== false) {

                                mysqli_stmt_bind_param(
                                    $update_stmt,
                                    "i",
                                    $equipment_id
                                );

                                mysqli_stmt_execute(
                                    $update_stmt
                                );

                                mysqli_stmt_close(
                                    $update_stmt
                                );
                            }


                            $message =
                                "Maintenance started successfully. Equipment is now unavailable for booking.";

                        } else {

                            $error =
                                "Unable to create maintenance record: "
                                . mysqli_stmt_error(
                                    $insert_stmt
                                );
                        }

                        mysqli_stmt_close(
                            $insert_stmt
                        );
                    }
                }

                mysqli_stmt_close(
                    $check_stmt
                );
            }
        }
    }


    // ==========================================
    // COMPLETE MAINTENANCE
    // ==========================================

    elseif ($action === "complete") {

        $maintenance_id =
            intval(
                $_POST["maintenance_id"] ?? 0
            );

        if ($maintenance_id <= 0) {

            $error =
                "Invalid maintenance record.";

        } else {

            // FIND EQUIPMENT

            $find_sql = "
                SELECT equipment_id
                FROM maintenance
                WHERE maintenance_id = ?
                AND status IN (
                    'pending',
                    'in_progress'
                )
                LIMIT 1
            ";

            $find_stmt =
                mysqli_prepare(
                    $conn,
                    $find_sql
                );

            if ($find_stmt === false) {

                $error =
                    "Database error: "
                    . mysqli_error($conn);

            } else {

                mysqli_stmt_bind_param(
                    $find_stmt,
                    "i",
                    $maintenance_id
                );

                mysqli_stmt_execute(
                    $find_stmt
                );

                $find_result =
                    mysqli_stmt_get_result(
                        $find_stmt
                    );

                $maintenance =
                    mysqli_fetch_assoc(
                        $find_result
                    );

                mysqli_stmt_close(
                    $find_stmt
                );


                if (!$maintenance) {

                    $error =
                        "Maintenance record not found or already completed.";

                } else {

                    $equipment_id =
                        intval(
                            $maintenance["equipment_id"]
                        );


                    // COMPLETE MAINTENANCE

                    $complete_sql = "
                        UPDATE maintenance
                        SET status = 'completed'
                        WHERE maintenance_id = ?
                    ";

                    $complete_stmt =
                        mysqli_prepare(
                            $conn,
                            $complete_sql
                        );

                    if ($complete_stmt === false) {

                        $error =
                            "Database error: "
                            . mysqli_error($conn);

                    } else {

                        mysqli_stmt_bind_param(
                            $complete_stmt,
                            "i",
                            $maintenance_id
                        );


                        if (
                            mysqli_stmt_execute(
                                $complete_stmt
                            )
                        ) {

                            // MAKE EQUIPMENT AVAILABLE AGAIN

                            $equipment_sql = "
                                UPDATE equipment
                                SET status = 'available'
                                WHERE equipment_id = ?
                            ";

                            $equipment_stmt =
                                mysqli_prepare(
                                    $conn,
                                    $equipment_sql
                                );

                            if ($equipment_stmt !== false) {

                                mysqli_stmt_bind_param(
                                    $equipment_stmt,
                                    "i",
                                    $equipment_id
                                );

                                mysqli_stmt_execute(
                                    $equipment_stmt
                                );

                                mysqli_stmt_close(
                                    $equipment_stmt
                                );
                            }


                            $message =
                                "Maintenance completed successfully. Equipment is available again.";

                        } else {

                            $error =
                                "Unable to complete maintenance.";
                        }

                        mysqli_stmt_close(
                            $complete_stmt
                        );
                    }
                }
            }
        }
    }
}


// ==============================================
// MAINTENANCE COUNTS
// ==============================================

$totalMaintenance = 0;
$activeMaintenance = 0;
$completedMaintenance = 0;
$overdueMaintenance = 0;

$count_sql = "
    SELECT

        COUNT(*) AS total,

        SUM(
            status IN (
                'pending',
                'in_progress'
            )
        ) AS active,

        SUM(
            status = 'completed'
        ) AS completed,

        SUM(
            status IN (
                'pending',
                'in_progress'
            )
            AND expected_end_date < CURDATE()
        ) AS overdue

    FROM maintenance
";

$count_result =
    mysqli_query(
        $conn,
        $count_sql
    );

if ($count_result) {

    $counts =
        mysqli_fetch_assoc(
            $count_result
        );

    $totalMaintenance =
        intval(
            $counts["total"] ?? 0
        );

    $activeMaintenance =
        intval(
            $counts["active"] ?? 0
        );

    $completedMaintenance =
        intval(
            $counts["completed"] ?? 0
        );

    $overdueMaintenance =
        intval(
            $counts["overdue"] ?? 0
        );
}


// ==============================================
// GET EQUIPMENT
// ==============================================

$equipment_sql = "
    SELECT
        equipment_id,
        equipment_name,
        status
    FROM equipment
    ORDER BY equipment_name ASC
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
// GET MAINTENANCE HISTORY
// ==============================================

$history_sql = "
    SELECT

        m.maintenance_id,
        m.issue,
        m.maintenance_date,
        m.expected_end_date,
        m.status,
        m.remarks,

        e.equipment_name

    FROM maintenance m

    INNER JOIN equipment e
        ON m.equipment_id =
           e.equipment_id

    ORDER BY
        m.maintenance_id DESC
";

$history_result =
    mysqli_query(
        $conn,
        $history_sql
    );

if ($history_result === false) {

    die(
        "Maintenance query failed: "
        . mysqli_error($conn)
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
        Maintenance | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=11"
    >

</head>

<body>

<div class="app-layout">


    <!-- ======================================
         SIDEBAR
    ======================================= -->

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
                <a
                    href="maintenance.php"
                    class="active"
                >
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


    <!-- ======================================
         MAIN CONTENT
    ======================================= -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Maintenance Management
                </div>

                <small style="color:#64748b;">
                    Track and manage equipment maintenance
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


            <!-- ======================================
                 PAGE HEADER
            ======================================= -->

            <section class="welcome-box">

                <h1>
                    🔧 Equipment Maintenance
                </h1>

                <p>
                    Record equipment issues,
                    track repairs and return
                    serviced equipment to availability.
                </p>

            </section>


            <!-- ======================================
                 MESSAGES
            ======================================= -->

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


            <!-- ======================================
                 STATS
            ======================================= -->

            <div class="maintenance-stat-grid">


                <div class="maintenance-stat-card">

                    <div class="maintenance-stat-icon">
                        📋
                    </div>

                    <div>

                        <span>
                            Total Records
                        </span>

                        <strong>
                            <?php
                            echo $totalMaintenance;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="maintenance-stat-card">

                    <div class="maintenance-stat-icon">
                        🔧
                    </div>

                    <div>

                        <span>
                            Active
                        </span>

                        <strong>
                            <?php
                            echo $activeMaintenance;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="maintenance-stat-card">

                    <div class="maintenance-stat-icon">
                        ✅
                    </div>

                    <div>

                        <span>
                            Completed
                        </span>

                        <strong>
                            <?php
                            echo $completedMaintenance;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="maintenance-stat-card">

                    <div class="maintenance-stat-icon">
                        ⚠️
                    </div>

                    <div>

                        <span>
                            Overdue
                        </span>

                        <strong>
                            <?php
                            echo $overdueMaintenance;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- ======================================
                 START MAINTENANCE
            ======================================= -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            🔧 Start Maintenance
                        </h2>

                        <p>
                            Record equipment that needs
                            repair or servicing.
                        </p>

                    </div>

                </div>


                <div class="maintenance-notice">

                    ℹ️ Starting maintenance will
                    automatically mark the equipment as
                    <strong>unavailable for booking</strong>.

                </div>


                <form
                    method="POST"
                    class="maintenance-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="start"
                    >


                    <div class="maintenance-form-grid">


                        <!-- EQUIPMENT -->

                        <div class="form-group">

                            <label>
                                Equipment
                            </label>

                            <select
                                name="equipment_id"
                                required
                            >

                                <option value="">
                                    -- Select Equipment --
                                </option>


                                <?php while (
                                    $equipment =
                                    mysqli_fetch_assoc(
                                        $equipment_result
                                    )
                                ): ?>

                                    <option
                                        value="<?php
                                        echo intval(
                                            $equipment[
                                                "equipment_id"
                                            ]
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "equipment_name"
                                            ]
                                        );
                                        ?>

                                        -

                                        <?php
                                        echo htmlspecialchars(
                                            strtoupper(
                                                $equipment[
                                                    "status"
                                                ]
                                            )
                                        );
                                        ?>

                                    </option>

                                <?php endwhile; ?>


                            </select>

                        </div>


                        <!-- START DATE -->

                        <div class="form-group">

                            <label>
                                Start Date
                            </label>

                            <input
                                type="date"
                                name="maintenance_date"
                                required
                            >

                        </div>


                        <!-- EXPECTED COMPLETION -->

                        <div class="form-group">

                            <label>
                                Expected Completion
                            </label>

                            <input
                                type="date"
                                name="expected_end_date"
                                required
                            >

                        </div>


                        <!-- ISSUE -->

                        <div class="
                            form-group
                            maintenance-wide-field
                        ">

                            <label>
                                Issue / Reason
                            </label>

                            <textarea
                                name="issue"
                                rows="4"
                                placeholder="Example: HDMI port not working"
                                required
                            ></textarea>

                        </div>


                        <!-- REMARKS -->

                        <div class="
                            form-group
                            maintenance-wide-field
                        ">

                            <label>
                                Remarks
                            </label>

                            <textarea
                                name="remarks"
                                rows="3"
                                placeholder="Example: Sent to service centre"
                            ></textarea>

                        </div>


                    </div>


                    <div style="margin-top:8px;">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            onclick="
                                return confirm(
                                    'Start maintenance for this equipment?'
                                );
                            "
                        >
                            🔧 Start Maintenance
                        </button>

                    </div>

                </form>

            </div>


            <!-- ======================================
                 MAINTENANCE RECORDS
            ======================================= -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Maintenance Records
                        </h2>

                        <p>
                            View current and previous
                            maintenance activity.
                        </p>

                    </div>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $history_result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table class="maintenance-table">

                            <thead>

                            <tr>

                                <th>ID</th>

                                <th>
                                    Equipment
                                </th>

                                <th>
                                    Issue
                                </th>

                                <th>
                                    Start Date
                                </th>

                                <th>
                                    Expected Completion
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Remarks
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $record =
                                mysqli_fetch_assoc(
                                    $history_result
                                )
                            ): ?>


                                <tr>


                                    <!-- ID -->

                                    <td>

                                        <strong>
                                            #<?php
                                            echo intval(
                                                $record[
                                                    "maintenance_id"
                                                ]
                                            );
                                            ?>
                                        </strong>

                                    </td>


                                    <!-- EQUIPMENT -->

                                    <td>

                                        <div class="maintenance-equipment">

                                            <div class="maintenance-equipment-icon">
                                                💻
                                            </div>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $record[
                                                        "equipment_name"
                                                    ]
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </td>


                                    <!-- ISSUE -->

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $record[
                                                "issue"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- START DATE -->

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $record[
                                                "maintenance_date"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- EXPECTED END -->

                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $record[
                                                    "expected_end_date"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $record[
                                                    "expected_end_date"
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
                                                $record[
                                                    "status"
                                                ]
                                            );

                                        $statusClass =
                                            "badge-info";

                                        if (
                                            $status ===
                                            "completed"
                                        ) {

                                            $statusClass =
                                                "badge-success";

                                        } elseif (
                                            $status ===
                                            "in_progress"
                                        ) {

                                            $statusClass =
                                                "badge-warning";

                                        } elseif (
                                            $status ===
                                            "pending"
                                        ) {

                                            $statusClass =
                                                "badge-warning";
                                        }

                                        ?>

                                        <span
                                            class="badge <?php
                                            echo $statusClass;
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                ucwords(
                                                    str_replace(
                                                        "_",
                                                        " ",
                                                        $status
                                                    )
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- REMARKS -->

                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $record[
                                                    "remarks"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $record[
                                                    "remarks"
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
                                            $record[
                                                "status"
                                            ] ===
                                            "completed"
                                        ): ?>


                                            <span
                                                class="maintenance-completed"
                                            >
                                                ✓ Completed
                                            </span>


                                        <?php else: ?>


                                            <form
                                                method="POST"
                                                class="maintenance-action-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="complete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="maintenance_id"
                                                    value="<?php
                                                    echo intval(
                                                        $record[
                                                            "maintenance_id"
                                                        ]
                                                    );
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="maintenance-complete-btn"
                                                    onclick="
                                                        return confirm(
                                                            'Mark this maintenance as completed?'
                                                        );
                                                    "
                                                >
                                                    ✓ Complete
                                                </button>

                                            </form>


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
                            🔧
                        </div>

                        <h3>
                            No Maintenance Records
                        </h3>

                        <p>
                            Maintenance records will
                            appear here after equipment
                            is sent for repair.
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
                Maintenance Management

            </div>


        </div>

    </main>

</div>

</body>

</html>