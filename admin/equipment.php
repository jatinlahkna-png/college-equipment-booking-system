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

// ==============================================
// ONLY ADMIN
// ==============================================

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user/dashboard.php");
    exit();
}

$adminName = $_SESSION["name"] ?? "Administrator";

$message = "";
$error = "";

// ==============================================
// HANDLE POST REQUEST
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // ==========================================
    // ADD EQUIPMENT
    // ==========================================

    if ($action === "add") {

        $category_id =
            intval($_POST["category_id"] ?? 0);

        $equipment_name =
            trim($_POST["equipment_name"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        $quantity =
            intval($_POST["quantity"] ?? 0);

        $location =
            trim($_POST["location"] ?? "");

        $equipment_condition =
            trim(
                $_POST["equipment_condition"]
                ?? "Good"
            );

        $access_level =
            $_POST["access_level"] ?? "everyone";


        // VALIDATION

        if ($category_id <= 0) {

            $error =
                "Please select a category.";

        } elseif ($equipment_name === "") {

            $error =
                "Equipment name is required.";

        } elseif ($quantity <= 0) {

            $error =
                "Quantity must be at least 1.";

        } elseif ($location === "") {

            $error =
                "Location is required.";

        } elseif (
            $access_level !== "everyone" &&
            $access_level !== "faculty_only"
        ) {

            $error =
                "Invalid booking access level.";

        } else {

            // ======================================
            // IMAGE UPLOAD
            // ======================================

            $image_name = null;

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
                            $allowed_types
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

                        $extension =
                            strtolower(
                                pathinfo(
                                    $_FILES["image"]["name"],
                                    PATHINFO_EXTENSION
                                )
                            );

                        $image_name =
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
                            . $image_name;

                        if (
                            !move_uploaded_file(
                                $_FILES["image"]["tmp_name"],
                                $upload_path
                            )
                        ) {

                            $error =
                                "Unable to save the image.";
                        }
                    }
                }
            }


            // ======================================
            // INSERT EQUIPMENT
            // ======================================

            if ($error === "") {

                $sql = "
                    INSERT INTO equipment
                    (
                        category_id,
                        equipment_name,
                        description,
                        quantity,
                        location,
                        equipment_condition,
                        status,
                        access_level,
                        image
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, 'available', ?, ?)
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
                        "ississss",
                        $category_id,
                        $equipment_name,
                        $description,
                        $quantity,
                        $location,
                        $equipment_condition,
                        $access_level,
                        $image_name
                    );

                    if (
                        mysqli_stmt_execute(
                            $stmt
                        )
                    ) {

                        $message =
                            "Equipment added successfully.";

                    } else {

                        $error =
                            "Unable to add equipment: "
                            . mysqli_stmt_error(
                                $stmt
                            );

                        if (
                            $image_name !== null &&
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
                    }

                    mysqli_stmt_close(
                        $stmt
                    );
                }
            }
        }
    }


    // ==========================================
    // DELETE EQUIPMENT
    // ==========================================

    elseif ($action === "delete") {

        $equipment_id =
            intval(
                $_POST["equipment_id"] ?? 0
            );

        if ($equipment_id > 0) {

            // GET IMAGE

            $image_sql = "
                SELECT image
                FROM equipment
                WHERE equipment_id = ?
            ";

            $image_stmt =
                mysqli_prepare(
                    $conn,
                    $image_sql
                );

            $image_name = null;

            if ($image_stmt !== false) {

                mysqli_stmt_bind_param(
                    $image_stmt,
                    "i",
                    $equipment_id
                );

                mysqli_stmt_execute(
                    $image_stmt
                );

                $image_result =
                    mysqli_stmt_get_result(
                        $image_stmt
                    );

                if ($image_result) {

                    $equipment =
                        mysqli_fetch_assoc(
                            $image_result
                        );

                    if ($equipment) {

                        $image_name =
                            $equipment["image"];
                    }
                }

                mysqli_stmt_close(
                    $image_stmt
                );
            }


            // DELETE

            $delete_sql = "
                DELETE FROM equipment
                WHERE equipment_id = ?
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
                    $equipment_id
                );

                if (
                    mysqli_stmt_execute(
                        $delete_stmt
                    )
                ) {

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

                    $message =
                        "Equipment deleted successfully.";

                } else {

                    $error =
                        "Unable to delete equipment: "
                        . mysqli_stmt_error(
                            $delete_stmt
                        );
                }

                mysqli_stmt_close(
                    $delete_stmt
                );
            }
        }
    }
}


// ==============================================
// GET CATEGORIES
// ==============================================

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
        e.equipment_id DESC
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
        Equipment | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=18"
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
                font-size: 38px;
                margin-bottom: 10px;
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
                margin-top: 30px;
                border-top: 1px solid
                rgba(255,255,255,0.15);
                padding-top: 18px;
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
                    Equipment Management
                </div>

                <small style="color:#64748b;">
                    Add and manage college equipment
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
                    💻 Equipment Management
                </h1>

                <p>
                    Add new equipment, upload images,
                    control booking access and manage
                    existing college resources.
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
                 ADD EQUIPMENT
            ====================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            ➕ Add New Equipment
                        </h2>

                        <p>
                            Enter the details of the
                            equipment you want to add.
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="equipment-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >


                    <div class="equipment-form-grid">


                        <div class="form-group">

                            <label>
                                Category
                            </label>

                            <select
                                name="category_id"
                                required
                            >

                                <option value="">
                                    -- Select Category --
                                </option>

                                <?php while (
                                    $category =
                                    mysqli_fetch_assoc(
                                        $category_result
                                    )
                                ): ?>

                                    <option
                                        value="<?php
                                        echo $category[
                                            "category_id"
                                        ];
                                        ?>"
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


                        <div class="form-group">

                            <label>
                                Equipment Name
                            </label>

                            <input
                                type="text"
                                name="equipment_name"
                                maxlength="150"
                                placeholder="Example: Epson Projector"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Quantity
                            </label>

                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                value="1"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Location
                            </label>

                            <input
                                type="text"
                                name="location"
                                placeholder="Example: Computer Lab 1"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Condition
                            </label>

                            <select
                                name="equipment_condition"
                            >

                                <option value="Good">
                                    Good
                                </option>

                                <option value="New">
                                    New
                                </option>

                                <option value="Fair">
                                    Fair
                                </option>

                                <option value="Damaged">
                                    Damaged
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Booking Access
                            </label>

                            <select
                                name="access_level"
                                required
                            >

                                <option value="everyone">
                                    👥 Everyone
                                </option>

                                <option value="faculty_only">
                                    🔒 Faculty Only
                                </option>

                            </select>

                            <small class="form-help">
                                Faculty Only equipment
                                cannot be booked by students.
                            </small>

                        </div>


                        <div class="form-group equipment-description">

                            <label>
                                Description
                            </label>

                            <textarea
                                name="description"
                                rows="4"
                                placeholder="Enter equipment description..."
                            ></textarea>

                        </div>


                        <div class="form-group">

                            <label>
                                Equipment Image
                            </label>

                            <input
                                type="file"
                                name="image"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <small class="form-help">
                                JPG, PNG or WEBP.
                                Maximum 5 MB.
                            </small>

                        </div>


                    </div>


                    <div style="
                        margin-top: 8px;
                    ">

                        <button
                            type="submit"
                            class="btn btn-primary equipment-add-btn"
                        >
                            ➕ Add Equipment
                        </button>

                    </div>

                </form>

            </div>


            <!-- =====================================
                 EQUIPMENT LIST
            ====================================== -->

            <div class="card">

                <div class="section-heading">

                    <div>

                        <h2>
                            📋 Equipment List
                        </h2>

                        <p>
                            View and manage all equipment
                            currently registered in the system.
                        </p>

                    </div>

                </div>


                <?php if (
                    mysqli_num_rows(
                        $equipment_result
                    ) > 0
                ): ?>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                            <tr>

                                <th>Sr. No.</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th>Access</th>
                                <th>Actions</th>

                            </tr>

                            </thead>


                            <tbody>

                            <?php $srNo = 1; ?>


                            <?php while (
                                $equipment =
                                mysqli_fetch_assoc(
                                    $equipment_result
                                )
                            ): ?>


                                <tr>


                                    <!-- SERIAL NUMBER -->

                                    <td>

                                        <strong>
                                            <?php
                                            echo $srNo;
                                            ?>
                                        </strong>

                                    </td>


                                    <!-- IMAGE -->

                                    <td>

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
                                                class="equipment-table-image"
                                                alt="Equipment"
                                            >

                                        <?php else: ?>

                                            <div class="no-equipment-image">
                                                💻
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- NAME -->

                                    <td>

                                        <strong
                                            style="
                                                color:#134e4a;
                                            "
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $equipment[
                                                    "equipment_name"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <?php if (
                                            !empty(
                                                $equipment[
                                                    "description"
                                                ]
                                            )
                                        ): ?>

                                            <br>

                                            <small
                                                style="
                                                    color:#64748b;
                                                "
                                            >

                                                <?php

                                                $shortDescription =
                                                    $equipment[
                                                        "description"
                                                    ];

                                                if (
                                                    strlen(
                                                        $shortDescription
                                                    ) > 45
                                                ) {

                                                    $shortDescription =
                                                        substr(
                                                            $shortDescription,
                                                            0,
                                                            45
                                                        )
                                                        . "...";
                                                }

                                                echo htmlspecialchars(
                                                    $shortDescription
                                                );

                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "category_name"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- QUANTITY -->

                                    <td>

                                        <span class="quantity-chip">

                                            <?php
                                            echo intval(
                                                $equipment[
                                                    "quantity"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- LOCATION -->

                                    <td>

                                        📍

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "location"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <!-- CONDITION -->

                                    <td>

                                        <?php

                                        $condition =
                                            strtolower(
                                                $equipment[
                                                    "equipment_condition"
                                                ]
                                            );

                                        $conditionClass =
                                            "badge-info";

                                        if (
                                            $condition === "new" ||
                                            $condition === "good"
                                        ) {

                                            $conditionClass =
                                                "badge-success";

                                        } elseif (
                                            $condition === "fair"
                                        ) {

                                            $conditionClass =
                                                "badge-warning";

                                        } elseif (
                                            $condition === "damaged"
                                        ) {

                                            $conditionClass =
                                                "badge-danger";
                                        }

                                        ?>

                                        <span
                                            class="badge <?php
                                            echo $conditionClass;
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $equipment[
                                                        "equipment_condition"
                                                    ]
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php

                                        $equipmentStatus =
                                            strtolower(
                                                $equipment[
                                                    "status"
                                                ]
                                            );

                                        $statusClass =
                                            "badge-info";

                                        if (
                                            $equipmentStatus ===
                                            "available"
                                        ) {

                                            $statusClass =
                                                "badge-success";

                                        } elseif (
                                            $equipmentStatus ===
                                            "maintenance"
                                        ) {

                                            $statusClass =
                                                "badge-warning";

                                        } elseif (
                                            $equipmentStatus ===
                                            "unavailable"
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
                                                    $equipmentStatus
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- ACCESS -->

                                    <td>

                                        <?php if (
                                            $equipment[
                                                "access_level"
                                            ] ===
                                            "faculty_only"
                                        ): ?>

                                            <span class="badge badge-purple">
                                                🔒 Faculty Only
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-info">
                                                👥 Everyone
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="equipment-actions">

                                            <a
                                                href="edit_equipment.php?id=<?php
                                                echo intval(
                                                    $equipment[
                                                        "equipment_id"
                                                    ]
                                                );
                                                ?>"
                                                class="equipment-action-btn"
                                            >
                                                ✏️ Edit
                                            </a>


                                            <form
                                                method="POST"
                                                onsubmit="
                                                    return confirm(
                                                        'Delete this equipment?'
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
                                                    name="equipment_id"
                                                    value="<?php
                                                    echo intval(
                                                        $equipment[
                                                            "equipment_id"
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

                                        </div>

                                    </td>


                                </tr>


                                <?php $srNo++; ?>


                            <?php endwhile; ?>


                            </tbody>

                        </table>

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
                            Add your first equipment
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
                Equipment Management

            </div>


        </div>

    </main>

</div>

</body>

</html>