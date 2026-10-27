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
// CHECK ADMIN
// ==================================================

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";

// ==================================================
// GET EQUIPMENT ID
// ==================================================

$equipment_id = intval($_GET["id"] ?? 0);

if ($equipment_id <= 0) {
    die("Invalid equipment ID.");
}

$message = "";
$error = "";


// ==================================================
// UPDATE EQUIPMENT
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $equipment_name =
        trim($_POST["equipment_name"] ?? "");

    $category_id =
        intval($_POST["category_id"] ?? 0);

    $description =
        trim($_POST["description"] ?? "");

    $quantity =
        intval($_POST["quantity"] ?? 0);

    $location =
        trim($_POST["location"] ?? "");

    $equipment_condition =
        trim(
            $_POST["equipment_condition"] ?? "Good"
        );

    $status =
        trim(
            $_POST["status"] ?? "available"
        );

    $access_level =
        trim(
            $_POST["access_level"] ?? "everyone"
        );


    // ==================================================
    // VALIDATION
    // ==================================================

    if ($equipment_name === "") {

        $error =
            "Equipment name is required.";

    } elseif ($category_id <= 0) {

        $error =
            "Please select a category.";

    } elseif ($quantity <= 0) {

        $error =
            "Quantity must be at least 1.";

    } elseif ($location === "") {

        $error =
            "Location is required.";

    } elseif (
        !in_array(
            $equipment_condition,
            ["New", "Good", "Fair", "Damaged"],
            true
        )
    ) {

        $error =
            "Invalid equipment condition.";

    } elseif (
        !in_array(
            $status,
            [
                "available",
                "unavailable",
                "maintenance"
            ],
            true
        )
    ) {

        $error =
            "Invalid equipment status.";

    } elseif (
        !in_array(
            $access_level,
            [
                "everyone",
                "faculty_only"
            ],
            true
        )
    ) {

        $error =
            "Invalid booking access.";

    } else {


        // ==================================================
        // CHECK CATEGORY EXISTS
        // ==================================================

        $category_check_sql = "
            SELECT category_id
            FROM categories
            WHERE category_id = ?
            LIMIT 1
        ";

        $category_check_stmt =
            mysqli_prepare(
                $conn,
                $category_check_sql
            );

        if ($category_check_stmt === false) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $category_check_stmt,
                "i",
                $category_id
            );

            mysqli_stmt_execute(
                $category_check_stmt
            );

            $category_check_result =
                mysqli_stmt_get_result(
                    $category_check_stmt
                );

            if (
                mysqli_num_rows(
                    $category_check_result
                ) === 0
            ) {

                $error =
                    "Selected category does not exist.";
            }

            mysqli_stmt_close(
                $category_check_stmt
            );
        }


        // ==================================================
        // GET CURRENT IMAGE
        // ==================================================

        if ($error === "") {

            $old_sql = "
                SELECT image
                FROM equipment
                WHERE equipment_id = ?
                LIMIT 1
            ";

            $old_stmt =
                mysqli_prepare(
                    $conn,
                    $old_sql
                );

            if ($old_stmt === false) {

                $error =
                    "Database error: "
                    . mysqli_error($conn);

            } else {

                mysqli_stmt_bind_param(
                    $old_stmt,
                    "i",
                    $equipment_id
                );

                mysqli_stmt_execute(
                    $old_stmt
                );

                $old_result =
                    mysqli_stmt_get_result(
                        $old_stmt
                    );

                $old_equipment =
                    mysqli_fetch_assoc(
                        $old_result
                    );

                mysqli_stmt_close(
                    $old_stmt
                );


                if (!$old_equipment) {

                    $error =
                        "Equipment not found.";

                } else {

                    $image_name =
                        $old_equipment["image"];


                    // ==================================================
                    // NEW IMAGE
                    // ==================================================

                    if (
                        isset($_FILES["image"]) &&
                        $_FILES["image"]["error"]
                        !== UPLOAD_ERR_NO_FILE
                    ) {

                        if (
                            $_FILES["image"]["error"]
                            !== UPLOAD_ERR_OK
                        ) {

                            $error =
                                "Error uploading image.";

                        } else {

                            $allowed_types = [
                                "image/jpeg",
                                "image/png",
                                "image/webp"
                            ];

                            $file_type =
                                mime_content_type(
                                    $_FILES["image"]["tmp_name"]
                                );

                            if (
                                !in_array(
                                    $file_type,
                                    $allowed_types,
                                    true
                                )
                            ) {

                                $error =
                                    "Only JPG, PNG and WEBP images are allowed.";

                            } elseif (
                                $_FILES["image"]["size"]
                                > 5 * 1024 * 1024
                            ) {

                                $error =
                                    "Image must be smaller than 5 MB.";

                            } else {

                                if (
                                    $file_type === "image/jpeg"
                                ) {

                                    $extension = "jpg";

                                } elseif (
                                    $file_type === "image/png"
                                ) {

                                    $extension = "png";

                                } else {

                                    $extension = "webp";
                                }


                                $new_image_name =
                                    uniqid(
                                        "equipment_",
                                        true
                                    )
                                    . "."
                                    . $extension;


                                $upload_directory =
                                    "../uploads/";


                                if (
                                    !is_dir(
                                        $upload_directory
                                    )
                                ) {

                                    mkdir(
                                        $upload_directory,
                                        0777,
                                        true
                                    );
                                }


                                $upload_path =
                                    $upload_directory
                                    . $new_image_name;


                                if (
                                    move_uploaded_file(
                                        $_FILES["image"]["tmp_name"],
                                        $upload_path
                                    )
                                ) {

                                    // DELETE OLD IMAGE

                                    if (
                                        !empty($image_name) &&
                                        file_exists(
                                            "../uploads/"
                                            . $image_name
                                        )
                                    ) {

                                        unlink(
                                            "../uploads/"
                                            . $image_name
                                        );
                                    }

                                    $image_name =
                                        $new_image_name;

                                } else {

                                    $error =
                                        "Unable to save the new image.";
                                }
                            }
                        }
                    }


                    // ==================================================
                    // UPDATE DATABASE
                    // ==================================================

                    if ($error === "") {

                        $sql = "
                            UPDATE equipment

                            SET
                                category_id = ?,
                                equipment_name = ?,
                                description = ?,
                                quantity = ?,
                                location = ?,
                                equipment_condition = ?,
                                status = ?,
                                access_level = ?,
                                image = ?

                            WHERE equipment_id = ?
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
                                "ississsssi",
                                $category_id,
                                $equipment_name,
                                $description,
                                $quantity,
                                $location,
                                $equipment_condition,
                                $status,
                                $access_level,
                                $image_name,
                                $equipment_id
                            );


                            if (
                                mysqli_stmt_execute(
                                    $stmt
                                )
                            ) {

                                $message =
                                    "Equipment updated successfully.";

                            } else {

                                $error =
                                    "Unable to update equipment: "
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
            }
        }
    }
}


// ==================================================
// GET CATEGORIES
// ==================================================

$category_sql = "
    SELECT
        category_id,
        category_name
    FROM categories
    ORDER BY category_name ASC
";

$category_result =
    mysqli_query(
        $conn,
        $category_sql
    );

if ($category_result === false) {

    die(
        "Category query failed: "
        . mysqli_error($conn)
    );
}


// ==================================================
// GET EQUIPMENT
// ==================================================

$equipment_sql = "
    SELECT
        equipment_id,
        category_id,
        equipment_name,
        description,
        quantity,
        location,
        equipment_condition,
        status,
        access_level,
        image

    FROM equipment

    WHERE equipment_id = ?
";

$stmt =
    mysqli_prepare(
        $conn,
        $equipment_sql
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
    $equipment_id
);

mysqli_stmt_execute(
    $stmt
);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

$equipment =
    mysqli_fetch_assoc(
        $result
    );

mysqli_stmt_close(
    $stmt
);

if (!$equipment) {
    die("Equipment not found.");
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
        Edit Equipment | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=12"
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

                <a
                    href="equipment.php"
                    class="active"
                >
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
                    Edit Equipment
                </div>

                <small style="color:#64748b;">
                    Update equipment information
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


            <!-- =========================================
                 PAGE HEADER
            ========================================== -->

            <section class="welcome-box">

                <h1>
                    ✏️ Edit Equipment
                </h1>

                <p>
                    Update details, availability,
                    booking access and equipment image.
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
                 EQUIPMENT SUMMARY
            ========================================== -->

            <div class="edit-equipment-summary">


                <div class="edit-summary-image">

                    <?php if (
                        !empty(
                            $equipment["image"]
                        )
                    ): ?>

                        <img
                            src="../uploads/<?php
                            echo htmlspecialchars(
                                $equipment["image"]
                            );
                            ?>"
                            alt="Equipment"
                        >

                    <?php else: ?>

                        <div class="edit-no-image">
                            💻
                        </div>

                    <?php endif; ?>

                </div>


                <div class="edit-summary-info">

                    <span>
                        Editing Equipment
                    </span>

                    <h2>

                        <?php
                        echo htmlspecialchars(
                            $equipment[
                                "equipment_name"
                            ]
                        );
                        ?>

                    </h2>

                    <p>
                        Equipment ID:
                        <strong>
                            #<?php
                            echo intval(
                                $equipment[
                                    "equipment_id"
                                ]
                            );
                            ?>
                        </strong>
                    </p>

                </div>


                <a
                    href="equipment.php"
                    class="btn btn-primary"
                >
                    ← Back to Equipment
                </a>


            </div>


            <!-- =========================================
                 EDIT FORM
            ========================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            Equipment Information
                        </h2>

                        <p>
                            Modify the information below
                            and save your changes.
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="edit-equipment-form"
                >


                    <div class="edit-equipment-grid">


                        <!-- CATEGORY -->

                        <div class="form-group">

                            <label for="category_id">
                                Category
                            </label>

                            <select
                                name="category_id"
                                id="category_id"
                                required
                            >

                                <?php while (
                                    $category =
                                    mysqli_fetch_assoc(
                                        $category_result
                                    )
                                ): ?>

                                    <option
                                        value="<?php
                                        echo intval(
                                            $category[
                                                "category_id"
                                            ]
                                        );
                                        ?>"

                                        <?php
                                        if (
                                            $category[
                                                "category_id"
                                            ] ==
                                            $equipment[
                                                "category_id"
                                            ]
                                        ) {
                                            echo "selected";
                                        }
                                        ?>
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $category[
                                                "category_name"
                                            ]
                                        );
                                        ?>

                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>


                        <!-- EQUIPMENT NAME -->

                        <div class="form-group">

                            <label for="equipment_name">
                                Equipment Name
                            </label>

                            <input
                                type="text"
                                id="equipment_name"
                                name="equipment_name"
                                maxlength="150"
                                value="<?php
                                echo htmlspecialchars(
                                    $equipment[
                                        "equipment_name"
                                    ]
                                );
                                ?>"
                                required
                            >

                        </div>


                        <!-- QUANTITY -->

                        <div class="form-group">

                            <label for="quantity">
                                Quantity
                            </label>

                            <input
                                type="number"
                                id="quantity"
                                name="quantity"
                                min="1"
                                value="<?php
                                echo intval(
                                    $equipment[
                                        "quantity"
                                    ]
                                );
                                ?>"
                                required
                            >

                        </div>


                        <!-- LOCATION -->

                        <div class="form-group">

                            <label for="location">
                                Location
                            </label>

                            <input
                                type="text"
                                id="location"
                                name="location"
                                maxlength="150"
                                value="<?php
                                echo htmlspecialchars(
                                    $equipment[
                                        "location"
                                    ]
                                );
                                ?>"
                                required
                            >

                        </div>


                        <!-- CONDITION -->

                        <div class="form-group">

                            <label for="equipment_condition">
                                Condition
                            </label>

                            <select
                                id="equipment_condition"
                                name="equipment_condition"
                                required
                            >

                                <option
                                    value="New"
                                    <?php
                                    echo
                                    $equipment[
                                        "equipment_condition"
                                    ] === "New"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    New
                                </option>

                                <option
                                    value="Good"
                                    <?php
                                    echo
                                    $equipment[
                                        "equipment_condition"
                                    ] === "Good"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Good
                                </option>

                                <option
                                    value="Fair"
                                    <?php
                                    echo
                                    $equipment[
                                        "equipment_condition"
                                    ] === "Fair"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Fair
                                </option>

                                <option
                                    value="Damaged"
                                    <?php
                                    echo
                                    $equipment[
                                        "equipment_condition"
                                    ] === "Damaged"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Damaged
                                </option>

                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label for="status">
                                Equipment Status
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >

                                <option
                                    value="available"
                                    <?php
                                    echo
                                    $equipment["status"]
                                    === "available"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Available
                                </option>


                                <option
                                    value="unavailable"
                                    <?php
                                    echo
                                    $equipment["status"]
                                    === "unavailable"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Unavailable
                                </option>


                                <?php if (
                                    $equipment["status"]
                                    === "maintenance"
                                ): ?>

                                    <option
                                        value="maintenance"
                                        selected
                                    >
                                        Maintenance
                                    </option>

                                <?php endif; ?>

                            </select>

                            <?php if (
                                $equipment["status"]
                                === "maintenance"
                            ): ?>

                                <small class="form-help">
                                    Maintenance status is normally
                                    controlled from the Maintenance page.
                                </small>

                            <?php endif; ?>

                        </div>


                        <!-- BOOKING ACCESS -->

                        <div class="form-group">

                            <label for="access_level">
                                Booking Access
                            </label>

                            <select
                                id="access_level"
                                name="access_level"
                                required
                            >

                                <option
                                    value="everyone"
                                    <?php
                                    echo
                                    $equipment[
                                        "access_level"
                                    ] === "everyone"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Everyone
                                </option>


                                <option
                                    value="faculty_only"
                                    <?php
                                    echo
                                    $equipment[
                                        "access_level"
                                    ] === "faculty_only"
                                    ? "selected"
                                    : "";
                                    ?>
                                >
                                    Faculty Only
                                </option>

                            </select>

                            <small class="form-help">
                                Faculty Only equipment cannot
                                be booked by students.
                            </small>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="
                            form-group
                            edit-description
                        ">

                            <label for="description">
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="5"
                                placeholder="Enter equipment description"
                            ><?php
                            echo htmlspecialchars(
                                $equipment[
                                    "description"
                                ] ?? ""
                            );
                            ?></textarea>

                        </div>


                    </div>


                    <!-- =========================================
                         IMAGE SECTION
                    ========================================== -->

                    <div class="edit-image-section">

                        <div>

                            <h3>
                                Equipment Image
                            </h3>

                            <p>
                                Upload a new image only if
                                you want to replace the
                                current one.
                            </p>

                        </div>


                        <div class="edit-current-image">

                            <?php if (
                                !empty(
                                    $equipment["image"]
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
                                    alt="Current Equipment Image"
                                >

                            <?php else: ?>

                                <div class="edit-image-placeholder">

                                    <span>
                                        💻
                                    </span>

                                    No image uploaded

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group">

                            <label for="image">
                                Replace Image
                            </label>

                            <input
                                type="file"
                                id="image"
                                name="image"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <small class="form-help">
                                JPG, PNG or WEBP.
                                Maximum size: 5 MB.
                                Leave empty to keep current image.
                            </small>

                        </div>

                    </div>


                    <!-- =========================================
                         BUTTONS
                    ========================================== -->

                    <div class="edit-form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            💾 Save Changes
                        </button>


                        <a
                            href="equipment.php"
                            class="edit-cancel-btn"
                        >
                            Cancel
                        </a>

                    </div>


                </form>

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
                Edit Equipment

            </div>


        </div>

    </main>

</div>

</body>

</html>