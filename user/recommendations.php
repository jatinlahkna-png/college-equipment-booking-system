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

$purpose = "";
$recommendations = [];
$message = "";


// ==============================================
// HANDLE RECOMMENDATION REQUEST
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $purpose = strtolower(
        trim($_POST["purpose"] ?? "")
    );

    if ($purpose === "") {

        $message =
            "Please enter what activity you are planning.";

    } else {

        // ==========================================
        // SMART KEYWORD RULES
        // ==========================================

        $keywords = [];


        // PRESENTATION / SEMINAR

        if (
            strpos($purpose, "presentation") !== false ||
            strpos($purpose, "seminar") !== false ||
            strpos($purpose, "class") !== false ||
            strpos($purpose, "lecture") !== false
        ) {

            $keywords = [
                "projector",
                "microphone",
                "speaker",
                "laptop"
            ];
        }


        // PHOTOGRAPHY

        elseif (
            strpos($purpose, "photo") !== false ||
            strpos($purpose, "photography") !== false ||
            strpos($purpose, "photoshoot") !== false
        ) {

            $keywords = [
                "camera",
                "tripod",
                "light"
            ];
        }


        // VIDEO RECORDING

        elseif (
            strpos($purpose, "video") !== false ||
            strpos($purpose, "recording") !== false ||
            strpos($purpose, "shoot") !== false
        ) {

            $keywords = [
                "camera",
                "microphone",
                "tripod",
                "light"
            ];
        }


        // CODING / PROGRAMMING

        elseif (
            strpos($purpose, "coding") !== false ||
            strpos($purpose, "programming") !== false ||
            strpos($purpose, "software") !== false ||
            strpos($purpose, "computer") !== false
        ) {

            $keywords = [
                "laptop",
                "computer",
                "desktop"
            ];
        }


        // ONLINE MEETING

        elseif (
            strpos($purpose, "meeting") !== false ||
            strpos($purpose, "online") !== false ||
            strpos($purpose, "zoom") !== false ||
            strpos($purpose, "conference") !== false
        ) {

            $keywords = [
                "laptop",
                "microphone",
                "camera",
                "speaker"
            ];
        }


        // SPORTS

        elseif (
            strpos($purpose, "sport") !== false ||
            strpos($purpose, "game") !== false ||
            strpos($purpose, "football") !== false ||
            strpos($purpose, "cricket") !== false ||
            strpos($purpose, "badminton") !== false
        ) {

            $keywords = [
                "football",
                "cricket",
                "badminton",
                "sports"
            ];
        }


        // GENERAL FALLBACK

        else {

            $words =
                preg_split(
                    '/\s+/',
                    $purpose
                );

            foreach ($words as $word) {

                $word = trim($word);

                if (strlen($word) >= 3) {

                    $keywords[] =
                        $word;
                }
            }
        }


        // REMOVE DUPLICATE KEYWORDS

        $keywords =
            array_values(
                array_unique(
                    $keywords
                )
            );


        // ==========================================
        // SEARCH EQUIPMENT DATABASE
        // ==========================================

        if (!empty($keywords)) {

            $conditions = [];
            $parameters = [];
            $types = "";

            foreach ($keywords as $keyword) {

                $conditions[] = "
                    (
                        LOWER(e.equipment_name) LIKE ?
                        OR LOWER(e.description) LIKE ?
                        OR LOWER(c.category_name) LIKE ?
                    )
                ";

                $search_word =
                    "%"
                    . strtolower($keyword)
                    . "%";

                $parameters[] =
                    $search_word;

                $parameters[] =
                    $search_word;

                $parameters[] =
                    $search_word;

                $types .= "sss";
            }


            $sql = "
                SELECT DISTINCT

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

                WHERE
                    "
                    . implode(
                        " OR ",
                        $conditions
                    )
                    . "

                ORDER BY

                    CASE
                        WHEN e.status = 'available'
                        THEN 0
                        ELSE 1
                    END,

                    e.equipment_name ASC
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
                $types,
                ...$parameters
            );


            mysqli_stmt_execute(
                $stmt
            );


            $result =
                mysqli_stmt_get_result(
                    $stmt
                );


            while (
                $row =
                mysqli_fetch_assoc(
                    $result
                )
            ) {

                $recommendations[] =
                    $row;
            }


            mysqli_stmt_close(
                $stmt
            );
        }


        if (empty($recommendations)) {

            $message =
                "No matching equipment was found. Try words such as presentation, photography, coding, seminar, video, meeting or sports.";
        }
    }
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
        Smart Recommendation | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=16"
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
                    💻 &nbsp; Browse Equipment
                </a>

            </li>


            <li>

                <a href="my_bookings.php">
                    📅 &nbsp; My Bookings
                </a>

            </li>


            <li>

                <a
                    href="recommendations.php"
                    class="active"
                >
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
         MAIN
    =========================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Smart Recommendation
                </div>

                <small style="color:#64748b;">
                    Find the right equipment for your activity
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
                    ⭐ Smart Equipment Recommendation
                </h1>

                <p>
                    Tell us what you are planning and
                    we'll find suitable equipment from
                    the college inventory.
                </p>

            </section>


            <!-- ==========================================
                 SMART SEARCH
            =========================================== -->

            <div class="recommendation-search-card">


                <div class="recommendation-search-icon">
                    ✨
                </div>


                <div class="recommendation-search-content">

                    <span class="recommendation-label">
                        SMART EQUIPMENT FINDER
                    </span>

                    <h2>
                        What are you planning?
                    </h2>

                    <p>
                        Describe your activity in simple
                        words. The system will match it
                        with suitable equipment.
                    </p>


                    <form
                        method="POST"
                        class="recommendation-form"
                    >

                        <textarea
                            name="purpose"
                            rows="4"
                            maxlength="255"
                            placeholder="Example: I need equipment for a classroom presentation"
                            required
                        ><?php
                            echo htmlspecialchars(
                                $purpose
                            );
                        ?></textarea>


                        <button
                            type="submit"
                            class="recommendation-submit-btn"
                        >
                            ✨ Get Recommendations
                        </button>

                    </form>

                </div>

            </div>


            <!-- ==========================================
                 EXAMPLES
            =========================================== -->

            <div class="recommendation-example-grid">


                <div class="recommendation-example">
                    <span>📽️</span>

                    <div>
                        <strong>
                            Presentation
                        </strong>

                        <small>
                            Projector, speaker, laptop
                        </small>
                    </div>
                </div>


                <div class="recommendation-example">
                    <span>📷</span>

                    <div>
                        <strong>
                            Photography
                        </strong>

                        <small>
                            Camera, tripod, lights
                        </small>
                    </div>
                </div>


                <div class="recommendation-example">
                    <span>💻</span>

                    <div>
                        <strong>
                            Coding
                        </strong>

                        <small>
                            Laptop or computer
                        </small>
                    </div>
                </div>


                <div class="recommendation-example">
                    <span>🏏</span>

                    <div>
                        <strong>
                            Sports
                        </strong>

                        <small>
                            Sports equipment
                        </small>
                    </div>
                </div>


            </div>


            <!-- ==========================================
                 MESSAGE
            =========================================== -->

            <?php if ($message !== ""): ?>

                <div class="alert alert-warning">

                    💡
                    <?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- ==========================================
                 RESULTS
            =========================================== -->

            <?php if (
                !empty(
                    $recommendations
                )
            ): ?>


                <div class="card">


                    <div class="section-heading">

                        <div>

                            <h2>
                                ⭐ Recommended Equipment
                            </h2>

                            <p>

                                Found

                                <strong>
                                    <?php
                                    echo count(
                                        $recommendations
                                    );
                                    ?>
                                </strong>

                                matching item(s) for

                                "<strong><?php
                                echo htmlspecialchars(
                                    $purpose
                                );
                                ?></strong>"

                            </p>

                        </div>


                        <a
                            href="equipment.php"
                            class="btn btn-primary"
                        >
                            View All Equipment
                        </a>

                    </div>


                    <div class="recommendation-result-grid">


                        <?php foreach (
                            $recommendations
                            as $equipment
                        ): ?>


                            <?php

                            $canBook = true;

                            if (
                                $equipment[
                                    "status"
                                ]
                                !== "available"
                            ) {

                                $canBook = false;
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

                                $canBook = false;
                            }

                            ?>


                            <div class="recommendation-result-card">


                                <!-- IMAGE -->

                                <div class="recommendation-result-image">


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

                                        <div class="recommendation-no-image">
                                            💻
                                        </div>

                                    <?php endif; ?>


                                    <div class="recommendation-match-badge">
                                        ✨ Recommended
                                    </div>


                                </div>


                                <!-- CONTENT -->

                                <div class="recommendation-result-body">


                                    <span class="recommendation-category">

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "category_name"
                                            ]
                                        );
                                        ?>

                                    </span>


                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "equipment_name"
                                            ]
                                        );
                                        ?>

                                    </h3>


                                    <p>

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
                                            ) > 110
                                        ) {

                                            echo htmlspecialchars(
                                                substr(
                                                    $description,
                                                    0,
                                                    110
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

                                    <div class="recommendation-details">


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


                                    <div class="recommendation-location">

                                        📍

                                        <?php
                                        echo htmlspecialchars(
                                            $equipment[
                                                "location"
                                            ]
                                        );
                                        ?>

                                    </div>


                                    <!-- BADGES -->

                                    <div class="recommendation-badges">


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


                                    <!-- ACTION -->

                                    <?php if ($canBook): ?>


                                        <a
                                            href="equipment.php"
                                            class="recommendation-book-btn"
                                        >
                                            📅 Book Equipment
                                        </a>


                                    <?php elseif (
                                        $equipment[
                                            "access_level"
                                        ]
                                        === "faculty_only"
                                        &&
                                        $userRole
                                        !== "faculty"
                                    ): ?>


                                        <div class="recommendation-locked">

                                            🔒 Faculty members only

                                        </div>


                                    <?php else: ?>


                                        <div class="recommendation-unavailable">

                                            🚫 Currently unavailable

                                        </div>


                                    <?php endif; ?>


                                </div>

                            </div>


                        <?php endforeach; ?>


                    </div>

                </div>


            <?php elseif (
                $_SERVER[
                    "REQUEST_METHOD"
                ] !== "POST"
            ): ?>


                <div class="recommendation-start-state">

                    <div>
                        💡
                    </div>

                    <h3>
                        Start with your activity
                    </h3>

                    <p>
                        Enter something like
                        "classroom presentation",
                        "photography event",
                        "coding lab" or
                        "sports activity".
                    </p>

                </div>


            <?php endif; ?>


            <!-- FOOTER -->

            <div style="
                text-align:center;
                color:#94a3b8;
                font-size:13px;
                padding:10px 0 20px;
            ">

                College Equipment Booking System
                &nbsp;•&nbsp;
                Smart Recommendation

            </div>


        </div>

    </main>

</div>

</body>

</html>