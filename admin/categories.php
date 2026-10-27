<?php

session_start();

require_once "../includes/db.php";

// --------------------------------------------------
// CHECK LOGIN
// --------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// --------------------------------------------------
// CHECK ADMIN ROLE
// --------------------------------------------------

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";

$message = "";
$error = "";

// --------------------------------------------------
// HANDLE POST REQUEST
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // ----------------------------------------------
    // ADD CATEGORY
    // ----------------------------------------------

    if ($action === "add") {

        $category_name =
            trim($_POST["category_name"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        if ($category_name === "") {

            $error =
                "Category name is required.";

        } else {

            // CHECK DUPLICATE CATEGORY

            $check_sql = "
                SELECT category_id
                FROM categories
                WHERE category_name = ?
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
                    "s",
                    $category_name
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
                        "This category already exists.";

                } else {

                    // INSERT CATEGORY

                    $insert_sql = "
                        INSERT INTO categories
                        (
                            category_name,
                            description
                        )
                        VALUES (?, ?)
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
                            "ss",
                            $category_name,
                            $description
                        );

                        if (
                            mysqli_stmt_execute(
                                $insert_stmt
                            )
                        ) {

                            $message =
                                "Category added successfully.";

                        } else {

                            $error =
                                "Unable to add category.";
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


    // ----------------------------------------------
    // DELETE CATEGORY
    // ----------------------------------------------

    elseif ($action === "delete") {

        $category_id =
            intval(
                $_POST["category_id"]
                ?? 0
            );

        if ($category_id > 0) {

            // CHECK IF EQUIPMENT USES CATEGORY

            $check_sql = "
                SELECT equipment_id
                FROM equipment
                WHERE category_id = ?
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
                    $category_id
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
                        "This category cannot be deleted because equipment is using it.";

                } else {

                    // DELETE CATEGORY

                    $delete_sql = "
                        DELETE FROM categories
                        WHERE category_id = ?
                    ";

                    $delete_stmt =
                        mysqli_prepare(
                            $conn,
                            $delete_sql
                        );

                    if ($delete_stmt === false) {

                        $error =
                            "Database error: "
                            . mysqli_error($conn);

                    } else {

                        mysqli_stmt_bind_param(
                            $delete_stmt,
                            "i",
                            $category_id
                        );

                        if (
                            mysqli_stmt_execute(
                                $delete_stmt
                            )
                        ) {

                            $message =
                                "Category deleted successfully.";

                        } else {

                            $error =
                                "Unable to delete category.";
                        }

                        mysqli_stmt_close(
                            $delete_stmt
                        );
                    }
                }

                mysqli_stmt_close(
                    $check_stmt
                );
            }
        }
    }
}


// --------------------------------------------------
// GET ALL CATEGORIES
// --------------------------------------------------

$sql = "
    SELECT
        category_id,
        category_name,
        description
    FROM categories
    ORDER BY category_id DESC
";

$result =
    mysqli_query(
        $conn,
        $sql
    );

if ($result === false) {

    die(
        "Category query failed: "
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
        Categories | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=8"
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
                <a
                    href="categories.php"
                    class="active"
                >
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
                    Category Management
                </div>

                <small style="color:#64748b;">
                    Organize equipment into categories
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


            <!-- PAGE HEADER -->

            <section class="welcome-box">

                <h1>
                    📂 Category Management
                </h1>

                <p>
                    Create and manage equipment categories
                    to keep college resources organized.
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
                 ADD CATEGORY
            ====================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            ➕ Add New Category
                        </h2>

                        <p>
                            Create a category for grouping
                            related college equipment.
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    class="category-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >


                    <div class="category-form-grid">


                        <div class="form-group">

                            <label
                                for="category_name"
                            >
                                Category Name
                            </label>

                            <input
                                type="text"
                                id="category_name"
                                name="category_name"
                                maxlength="100"
                                placeholder="Example: Projectors"
                                required
                            >

                        </div>


                        <div class="form-group category-description">

                            <label
                                for="description"
                            >
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="3"
                                placeholder="Describe the type of equipment in this category..."
                            ></textarea>

                        </div>


                    </div>


                    <div style="
                        margin-top:8px;
                    ">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ➕ Add Category
                        </button>

                    </div>

                </form>

            </div>


            <!-- =====================================
                 CATEGORY LIST
            ====================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Existing Categories
                        </h2>

                        <p>
                            View and manage all equipment
                            categories in the system.
                        </p>

                    </div>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                            <tr>

                                <th>ID</th>

                                <th>
                                    Category Name
                                </th>

                                <th>
                                    Description
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $category =
                                mysqli_fetch_assoc(
                                    $result
                                )
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>
                                            #<?php
                                            echo intval(
                                                $category[
                                                    "category_id"
                                                ]
                                            );
                                            ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <div class="category-name-cell">

                                            <div class="category-icon">
                                                📂
                                            </div>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $category[
                                                        "category_name"
                                                    ]
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </td>


                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $category[
                                                    "description"
                                                ]
                                            )
                                        ) {

                                            echo htmlspecialchars(
                                                $category[
                                                    "description"
                                                ]
                                            );

                                        } else {

                                            echo "<span style='color:#94a3b8;'>No description</span>";
                                        }

                                        ?>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this category?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="category_id"
                                                value="<?php
                                                echo intval(
                                                    $category[
                                                        "category_id"
                                                    ]
                                                );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="equipment-action-btn delete-action-btn"
                                            >
                                                🗑️ Delete
                                            </button>

                                        </form>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="empty-state">

                        <div>
                            📂
                        </div>

                        <h3>
                            No Categories Found
                        </h3>

                        <p>
                            Add your first category
                            using the form above.
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
                Category Management

            </div>


        </div>

    </main>

</div>

</body>

</html>