<?php

session_start();
require_once "../includes/db.php";

// =====================================================
// ADMIN LOGIN CHECK
// =====================================================

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


// =====================================================
// HANDLE POST REQUESTS
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    // =================================================
    // ADD SINGLE USER
    // =================================================

    if ($action === "add") {

        $name =
            trim($_POST["name"] ?? "");

        $college_id =
            strtoupper(
                trim($_POST["college_id"] ?? "")
            );

        $email =
            strtolower(
                trim($_POST["email"] ?? "")
            );

        $role =
            strtolower(
                trim($_POST["role"] ?? "")
            );

        $phone =
            trim($_POST["phone"] ?? "");

        $department =
            trim($_POST["department"] ?? "");


        if ($name === "") {

            $error =
                "Name is required.";

        } elseif ($college_id === "") {

            $error =
                "College ID is required.";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Please enter a valid email.";

        } elseif (
            $role !== "student" &&
            $role !== "faculty"
        ) {

            $error =
                "Please select Student or Faculty.";

        } else {

            // DUPLICATE CHECK

            $check_sql = "
                SELECT user_id
                FROM users
                WHERE email = ?
                OR college_id = ?
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
                    "ss",
                    $email,
                    $college_id
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
                        "Email or College ID already exists.";

                } else {

                    // COLLEGE ID = TEMP PASSWORD

                    $temporary_password =
                        $college_id;

                    $hashed_password =
                        password_hash(
                            $temporary_password,
                            PASSWORD_DEFAULT
                        );

                    $status = "active";

                    $must_change_password = 1;


                    $insert_sql = "
                        INSERT INTO users
                        (
                            college_id,
                            name,
                            email,
                            password,
                            role,
                            phone,
                            department,
                            status,
                            must_change_password
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                            "ssssssssi",
                            $college_id,
                            $name,
                            $email,
                            $hashed_password,
                            $role,
                            $phone,
                            $department,
                            $status,
                            $must_change_password
                        );


                        if (
                            mysqli_stmt_execute(
                                $insert_stmt
                            )
                        ) {

                            $message =
                                ucfirst($role)
                                . " account created successfully. "
                                . "Temporary password is the College ID: "
                                . $college_id;

                        } else {

                            $error =
                                "Unable to create user: "
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


    // =================================================
    // BULK CSV IMPORT
    // =================================================

    elseif ($action === "import") {

        if (
            !isset($_FILES["csv_file"]) ||
            $_FILES["csv_file"]["error"]
            !== UPLOAD_ERR_OK
        ) {

            $error =
                "Please select a CSV file.";

        } else {

            $extension =
                strtolower(
                    pathinfo(
                        $_FILES["csv_file"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== "csv") {

                $error =
                    "Only CSV files are allowed.";

            } else {

                $file =
                    fopen(
                        $_FILES["csv_file"]["tmp_name"],
                        "r"
                    );

                if ($file === false) {

                    $error =
                        "Unable to read CSV file.";

                } else {

                    $added = 0;
                    $duplicates = 0;
                    $invalid = 0;

                    // SKIP HEADER
                    fgetcsv($file);


                    while (
                        ($row = fgetcsv($file))
                        !== false
                    ) {

                        if (count($row) < 6) {

                            $invalid++;
                            continue;
                        }


                        $name =
                            trim($row[0]);

                        $college_id =
                            strtoupper(
                                trim($row[1])
                            );

                        $email =
                            strtolower(
                                trim($row[2])
                            );

                        $role =
                            strtolower(
                                trim($row[3])
                            );

                        $phone =
                            trim($row[4]);

                        $department =
                            trim($row[5]);


                        // VALIDATION

                        if (
                            $name === "" ||
                            $college_id === "" ||
                            !filter_var(
                                $email,
                                FILTER_VALIDATE_EMAIL
                            ) ||
                            (
                                $role !== "student" &&
                                $role !== "faculty"
                            )
                        ) {

                            $invalid++;
                            continue;
                        }


                        // DUPLICATE CHECK

                        $check_sql = "
                            SELECT user_id
                            FROM users
                            WHERE email = ?
                            OR college_id = ?
                        ";

                        $check_stmt =
                            mysqli_prepare(
                                $conn,
                                $check_sql
                            );

                        if ($check_stmt === false) {

                            $invalid++;
                            continue;
                        }

                        mysqli_stmt_bind_param(
                            $check_stmt,
                            "ss",
                            $email,
                            $college_id
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

                            $duplicates++;

                            mysqli_stmt_close(
                                $check_stmt
                            );

                            continue;
                        }

                        mysqli_stmt_close(
                            $check_stmt
                        );


                        // TEMP PASSWORD

                        $temporary_password =
                            $college_id;

                        $hashed_password =
                            password_hash(
                                $temporary_password,
                                PASSWORD_DEFAULT
                            );

                        $status = "active";
                        $must_change_password = 1;


                        // INSERT USER

                        $insert_sql = "
                            INSERT INTO users
                            (
                                college_id,
                                name,
                                email,
                                password,
                                role,
                                phone,
                                department,
                                status,
                                must_change_password
                            )
                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ";

                        $insert_stmt =
                            mysqli_prepare(
                                $conn,
                                $insert_sql
                            );

                        if ($insert_stmt === false) {

                            $invalid++;
                            continue;
                        }

                        mysqli_stmt_bind_param(
                            $insert_stmt,
                            "ssssssssi",
                            $college_id,
                            $name,
                            $email,
                            $hashed_password,
                            $role,
                            $phone,
                            $department,
                            $status,
                            $must_change_password
                        );


                        if (
                            mysqli_stmt_execute(
                                $insert_stmt
                            )
                        ) {

                            $added++;

                        } else {

                            $invalid++;
                        }

                        mysqli_stmt_close(
                            $insert_stmt
                        );
                    }


                    fclose($file);


                    $message =
                        "CSV Import Completed! "
                        . "Users Added: "
                        . $added
                        . " | Duplicates Skipped: "
                        . $duplicates
                        . " | Invalid Rows: "
                        . $invalid
                        . ". Imported users use their College ID as the temporary password.";
                }
            }
        }
    }


    // =================================================
    // ACTIVATE / DEACTIVATE
    // =================================================

    elseif ($action === "status") {

        $user_id =
            intval(
                $_POST["user_id"] ?? 0
            );

        $new_status =
            $_POST["new_status"] ?? "";


        if (
            $user_id <= 0 ||
            !in_array(
                $new_status,
                ["active", "inactive"],
                true
            )
        ) {

            $error =
                "Invalid status request.";

        } else {

            $sql = "
                UPDATE users
                SET status = ?
                WHERE user_id = ?
                AND role IN ('student', 'faculty')
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
                    "si",
                    $new_status,
                    $user_id
                );


                if (
                    mysqli_stmt_execute(
                        $stmt
                    )
                ) {

                    $message =
                        "User status updated successfully.";

                } else {

                    $error =
                        "Unable to update user status.";
                }

                mysqli_stmt_close(
                    $stmt
                );
            }
        }
    }


    // =================================================
    // RESET PASSWORD
    // =================================================

    elseif ($action === "reset_password") {

        $user_id =
            intval(
                $_POST["user_id"] ?? 0
            );


        $find_sql = "
            SELECT
                college_id,
                role
            FROM users
            WHERE user_id = ?
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
                $user_id
            );

            mysqli_stmt_execute(
                $find_stmt
            );

            $find_result =
                mysqli_stmt_get_result(
                    $find_stmt
                );

            $user =
                mysqli_fetch_assoc(
                    $find_result
                );

            mysqli_stmt_close(
                $find_stmt
            );


            if (!$user) {

                $error =
                    "User not found.";

            } elseif (
                $user["role"] === "admin"
            ) {

                $error =
                    "Admin password cannot be reset here.";

            } elseif (
                empty($user["college_id"])
            ) {

                $error =
                    "This user does not have a College ID.";

            } else {

                $temporary_password =
                    $user["college_id"];

                $hashed_password =
                    password_hash(
                        $temporary_password,
                        PASSWORD_DEFAULT
                    );


                $reset_sql = "
                    UPDATE users

                    SET
                        password = ?,
                        must_change_password = 1

                    WHERE user_id = ?

                    AND role IN (
                        'student',
                        'faculty'
                    )
                ";

                $reset_stmt =
                    mysqli_prepare(
                        $conn,
                        $reset_sql
                    );

                if ($reset_stmt === false) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $reset_stmt,
                        "si",
                        $hashed_password,
                        $user_id
                    );


                    if (
                        mysqli_stmt_execute(
                            $reset_stmt
                        )
                    ) {

                        $message =
                            "Password reset successfully. "
                            . "Temporary password: "
                            . $temporary_password;

                    } else {

                        $error =
                            "Unable to reset password.";
                    }

                    mysqli_stmt_close(
                        $reset_stmt
                    );
                }
            }
        }
    }
}


// =====================================================
// USER COUNTS
// =====================================================

$totalUsers = 0;
$totalStudents = 0;
$totalFaculty = 0;
$inactiveUsers = 0;

$count_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(role = 'student') AS students,
        SUM(role = 'faculty') AS faculty,
        SUM(
            status = 'inactive'
            AND role IN ('student', 'faculty')
        ) AS inactive
    FROM users
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

    $totalUsers =
        intval(
            $counts["total"] ?? 0
        );

    $totalStudents =
        intval(
            $counts["students"] ?? 0
        );

    $totalFaculty =
        intval(
            $counts["faculty"] ?? 0
        );

    $inactiveUsers =
        intval(
            $counts["inactive"] ?? 0
        );
}


// =====================================================
// GET USERS
// =====================================================

$users_sql = "
    SELECT
        user_id,
        college_id,
        name,
        email,
        role,
        phone,
        department,
        status,
        must_change_password,
        created_at

    FROM users

    ORDER BY
        CASE
            WHEN role = 'admin' THEN 1
            WHEN role = 'faculty' THEN 2
            ELSE 3
        END,
        name ASC
";

$users_result =
    mysqli_query(
        $conn,
        $users_sql
    );

if ($users_result === false) {

    die(
        "User query failed: "
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
        Users | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=10"
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
                <a
                    href="users.php"
                    class="active"
                >
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


    <!-- ==========================================
         MAIN CONTENT
    =========================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    User Management
                </div>

                <small style="color:#64748b;">
                    Manage students and faculty accounts
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


            <!-- ==========================================
                 WELCOME
            =========================================== -->

            <section class="welcome-box">

                <h1>
                    👥 User Management
                </h1>

                <p>
                    Create student and faculty accounts,
                    import users in bulk and manage
                    account access.
                </p>

            </section>


            <!-- MESSAGES -->

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
                 USER STATISTICS
            =========================================== -->

            <div class="user-stat-grid">


                <div class="user-stat-card">

                    <div class="user-stat-icon">
                        👥
                    </div>

                    <div>

                        <span>
                            Total Users
                        </span>

                        <strong>
                            <?php
                            echo $totalUsers;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon">
                        🎓
                    </div>

                    <div>

                        <span>
                            Students
                        </span>

                        <strong>
                            <?php
                            echo $totalStudents;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon">
                        👨‍🏫
                    </div>

                    <div>

                        <span>
                            Faculty
                        </span>

                        <strong>
                            <?php
                            echo $totalFaculty;
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon">
                        🔒
                    </div>

                    <div>

                        <span>
                            Inactive
                        </span>

                        <strong>
                            <?php
                            echo $inactiveUsers;
                            ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- ==========================================
                 ADD USER
            =========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            ➕ Add Single User
                        </h2>

                        <p>
                            Create a student or faculty
                            account manually.
                        </p>

                    </div>

                </div>


                <div class="user-info-notice">

                    🔑 The user's
                    <strong>College ID</strong>
                    will be their temporary password.
                    They will be asked to change it
                    after their first login.

                </div>


                <form
                    method="POST"
                    class="user-management-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >


                    <div class="user-form-grid">


                        <div class="form-group">

                            <label>
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                placeholder="Enter full name"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                College ID
                            </label>

                            <input
                                type="text"
                                name="college_id"
                                placeholder="Example: STU2026001"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="student@college.com"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Role
                            </label>

                            <select
                                name="role"
                                required
                            >

                                <option value="">
                                    -- Select Role --
                                </option>

                                <option value="student">
                                    Student
                                </option>

                                <option value="faculty">
                                    Faculty
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                placeholder="Phone number"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Department
                            </label>

                            <input
                                type="text"
                                name="department"
                                placeholder="Example: Computer Science"
                            >

                        </div>


                    </div>


                    <div style="margin-top:8px;">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ➕ Add User
                        </button>

                    </div>

                </form>

            </div>


            <!-- ==========================================
                 BULK CSV IMPORT
            =========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📥 Bulk User Import
                        </h2>

                        <p>
                            Add multiple students or
                            faculty members using a CSV file.
                        </p>

                    </div>

                </div>


                <div class="csv-layout">


                    <div class="csv-upload-area">

                        <div class="csv-icon">
                            📄
                        </div>

                        <h3>
                            Upload CSV File
                        </h3>

                        <p>
                            Select your prepared user
                            CSV file below.
                        </p>


                        <form
                            method="POST"
                            enctype="multipart/form-data"
                            class="csv-import-form"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="import"
                            >

                            <input
                                type="file"
                                name="csv_file"
                                accept=".csv"
                                required
                            >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                📥 Import Users
                            </button>

                        </form>

                    </div>


                    <div class="csv-format-box">

                        <strong>
                            Required CSV Format
                        </strong>

                        <div class="csv-code">
                            name,college_id,email,role,phone,department
                        </div>

                        <p>
                            Accepted roles:
                            <strong>student</strong>
                            or
                            <strong>faculty</strong>.
                        </p>

                        <p>
                            Temporary password =
                            user's College ID.
                        </p>

                    </div>


                </div>

            </div>


            <!-- ==========================================
                 REGISTERED USERS
            =========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Registered Users
                        </h2>

                        <p>
                            View account information,
                            account status and password status.
                        </p>

                    </div>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $users_result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table class="users-table">

                            <thead>

                            <tr>

                                <th>ID</th>
                                <th>College ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Password</th>
                                <th>Actions</th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $user =
                                mysqli_fetch_assoc(
                                    $users_result
                                )
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>
                                            #<?php
                                            echo intval(
                                                $user[
                                                    "user_id"
                                                ]
                                            );
                                            ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $user[
                                                    "college_id"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $user[
                                                    "college_id"
                                                ]
                                            );

                                        } else {

                                            echo "<span style='color:#94a3b8;'>-</span>";
                                        }

                                        ?>

                                    </td>


                                    <td>

                                        <div class="user-name-cell">

                                            <div class="user-avatar">

                                                <?php
                                                echo htmlspecialchars(
                                                    strtoupper(
                                                        substr(
                                                            $user[
                                                                "name"
                                                            ],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                );
                                                ?>

                                            </div>


                                            <div>

                                                <strong>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $user[
                                                            "name"
                                                        ]
                                                    );
                                                    ?>

                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $user[
                                                            "phone"
                                                        ]
                                                    )
                                                ): ?>

                                                    <small>

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $user[
                                                                "phone"
                                                            ]
                                                        );
                                                        ?>

                                                    </small>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $user[
                                                "email"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        $role =
                                            strtolower(
                                                $user[
                                                    "role"
                                                ]
                                            );

                                        if (
                                            $role ===
                                            "admin"
                                        ) {

                                            $roleClass =
                                                "badge-purple";

                                        } elseif (
                                            $role ===
                                            "faculty"
                                        ) {

                                            $roleClass =
                                                "badge-info";

                                        } else {

                                            $roleClass =
                                                "badge-success";
                                        }

                                        ?>

                                        <span
                                            class="badge <?php
                                            echo $roleClass;
                                            ?>"
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


                                    <td>

                                        <?php

                                        echo !empty(
                                            $user[
                                                "department"
                                            ]
                                        )
                                            ? htmlspecialchars(
                                                $user[
                                                    "department"
                                                ]
                                            )
                                            : "<span style='color:#94a3b8;'>-</span>";

                                        ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $user[
                                                "status"
                                            ] ===
                                            "active"
                                        ): ?>

                                            <span
                                                class="badge badge-success"
                                            >
                                                ● Active
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge badge-danger"
                                            >
                                                ● Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            intval(
                                                $user[
                                                    "must_change_password"
                                                ]
                                            ) === 1
                                        ): ?>

                                            <span
                                                class="badge badge-warning"
                                            >
                                                ⚠ Change Required
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge badge-success"
                                            >
                                                ✓ Normal
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>


                                        <?php if (
                                            $user["role"]
                                            === "admin"
                                        ): ?>


                                            <span
                                                class="protected-admin"
                                            >
                                                🔐 Protected Admin
                                            </span>


                                        <?php else: ?>


                                            <div class="user-action-buttons">


                                                <!-- STATUS -->

                                                <?php if (
                                                    $user[
                                                        "status"
                                                    ] ===
                                                    "active"
                                                ): ?>


                                                    <form
                                                        method="POST"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="status"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?php
                                                            echo intval(
                                                                $user[
                                                                    "user_id"
                                                                ]
                                                            );
                                                            ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="inactive"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="user-action-btn deactivate-btn"
                                                            onclick="
                                                                return confirm(
                                                                    'Deactivate this user?'
                                                                );
                                                            "
                                                        >
                                                            ⏸ Deactivate
                                                        </button>

                                                    </form>


                                                <?php else: ?>


                                                    <form
                                                        method="POST"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="status"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?php
                                                            echo intval(
                                                                $user[
                                                                    "user_id"
                                                                ]
                                                            );
                                                            ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="active"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="user-action-btn activate-btn"
                                                        >
                                                            ✓ Activate
                                                        </button>

                                                    </form>


                                                <?php endif; ?>


                                                <!-- RESET PASSWORD -->

                                                <form
                                                    method="POST"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="reset_password"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?php
                                                        echo intval(
                                                            $user[
                                                                "user_id"
                                                            ]
                                                        );
                                                        ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="user-action-btn reset-btn"
                                                        onclick="
                                                            return confirm(
                                                                'Reset password to this user College ID?'
                                                            );
                                                        "
                                                    >
                                                        🔑 Reset
                                                    </button>

                                                </form>


                                            </div>


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
                            👥
                        </div>

                        <h3>
                            No Users Found
                        </h3>

                        <p>
                            Add a student or faculty
                            account using the form above.
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
                User Management

            </div>


        </div>

    </main>

</div>

</body>

</html>