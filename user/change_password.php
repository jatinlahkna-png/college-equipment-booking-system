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

$user_id = intval($_SESSION["user_id"]);

$userName =
    $_SESSION["name"] ?? "User";

$userRole =
    $_SESSION["role"] ?? "student";

$message = "";
$error = "";


// ==============================================
// HANDLE PASSWORD CHANGE
// ==============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password =
        $_POST["current_password"] ?? "";

    $new_password =
        $_POST["new_password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    if ($current_password === "") {

        $error =
            "Please enter your current password.";

    } elseif (strlen($new_password) < 6) {

        $error =
            "New password must contain at least 6 characters.";

    } elseif (
        $new_password !==
        $confirm_password
    ) {

        $error =
            "New password and confirm password do not match.";

    } else {


        // ======================================
        // GET CURRENT PASSWORD HASH
        // ======================================

        $sql = "
            SELECT password
            FROM users
            WHERE user_id = ?
            LIMIT 1
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

            $user =
                mysqli_fetch_assoc(
                    $result
                );

            mysqli_stmt_close(
                $stmt
            );


            if (!$user) {

                $error =
                    "User account not found.";

            } elseif (
                !password_verify(
                    $current_password,
                    $user["password"]
                )
            ) {

                $error =
                    "Current password is incorrect.";

            } else {


                // ==================================
                // SAVE NEW PASSWORD
                // ==================================

                $new_hash =
                    password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                $update_sql = "
                    UPDATE users
                    SET
                        password = ?,
                        must_change_password = 0
                    WHERE user_id = ?
                ";

                $update_stmt =
                    mysqli_prepare(
                        $conn,
                        $update_sql
                    );

                if (
                    $update_stmt
                    === false
                ) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "si",
                        $new_hash,
                        $user_id
                    );


                    if (
                        mysqli_stmt_execute(
                            $update_stmt
                        )
                    ) {

                        $message =
                            "Password changed successfully.";

                        $_SESSION[
                            "must_change_password"
                        ] = 0;

                    } else {

                        $error =
                            "Unable to change password.";
                    }

                    mysqli_stmt_close(
                        $update_stmt
                    );
                }
            }
        }
    }
}


// ==============================================
// CHECK FORCED PASSWORD CHANGE
// ==============================================

$mustChangePassword =
    isset(
        $_SESSION[
            "must_change_password"
        ]
    )
    &&
    intval(
        $_SESSION[
            "must_change_password"
        ]
    ) === 1;

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
        Change Password | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=17"
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

                <a href="my_bookings.php">
                    📅 &nbsp; My Bookings
                </a>

            </li>


            <li>

                <a href="recommendations.php">
                    ⭐ &nbsp; Recommendations
                </a>

            </li>


            <li>

                <a
                    href="change_password.php"
                    class="active"
                >
                    🔐 &nbsp; Change Password
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


        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <div class="topbar-title">
                    Change Password
                </div>

                <small style="color:#64748b;">
                    Manage your account security
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
                    🔐 Change Your Password
                </h1>

                <p>
                    Keep your account secure by
                    choosing a private password.
                </p>

            </section>


            <!-- =========================================
                 FORCED PASSWORD NOTICE
            ========================================== -->

            <?php if (
                $mustChangePassword
                &&
                $message === ""
            ): ?>

                <div class="password-required-notice">

                    <div>
                        ⚠️
                    </div>

                    <div>

                        <strong>
                            Password Change Required
                        </strong>

                        <p>
                            You are using your temporary
                            password. Create a new password
                            before continuing to the system.
                        </p>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =========================================
                 MESSAGE
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


            <div class="password-page-layout">


                <!-- =====================================
                     PASSWORD CARD
                ====================================== -->

                <div class="card password-main-card">


                    <div class="password-card-heading">

                        <div class="password-card-icon">
                            🔑
                        </div>

                        <div>

                            <h2>
                                Update Password
                            </h2>

                            <p>
                                Enter your current password
                                and create a new one.
                            </p>

                        </div>

                    </div>


                    <?php if (
                        $message === ""
                    ): ?>


                        <form
                            method="POST"
                            class="change-password-form"
                            onsubmit="
                                return validatePasswordForm();
                            "
                        >


                            <!-- CURRENT PASSWORD -->

                            <div class="password-field-group">

                                <label>
                                    Current / Temporary Password
                                </label>

                                <div class="password-input-wrapper">

                                    <input
                                        type="password"
                                        name="current_password"
                                        id="current_password"
                                        placeholder="Enter current password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="
                                            togglePassword(
                                                'current_password',
                                                this
                                            );
                                        "
                                    >
                                        👁
                                    </button>

                                </div>

                                <small>
                                    If this is your first login,
                                    enter the temporary password
                                    given by the administrator.
                                </small>

                            </div>


                            <!-- NEW PASSWORD -->

                            <div class="password-field-group">

                                <label>
                                    New Password
                                </label>

                                <div class="password-input-wrapper">

                                    <input
                                        type="password"
                                        name="new_password"
                                        id="new_password"
                                        minlength="6"
                                        placeholder="Create new password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="
                                            togglePassword(
                                                'new_password',
                                                this
                                            );
                                        "
                                    >
                                        👁
                                    </button>

                                </div>

                                <small>
                                    Password must contain
                                    at least 6 characters.
                                </small>

                            </div>


                            <!-- CONFIRM PASSWORD -->

                            <div class="password-field-group">

                                <label>
                                    Confirm New Password
                                </label>

                                <div class="password-input-wrapper">

                                    <input
                                        type="password"
                                        name="confirm_password"
                                        id="confirm_password"
                                        minlength="6"
                                        placeholder="Enter new password again"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="
                                            togglePassword(
                                                'confirm_password',
                                                this
                                            );
                                        "
                                    >
                                        👁
                                    </button>

                                </div>

                                <small
                                    id="passwordMatchText"
                                >
                                    Re-enter your new password.
                                </small>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary password-save-btn"
                            >
                                🔐 Change Password
                            </button>


                        </form>


                    <?php else: ?>


                        <div class="password-success-state">

                            <div>
                                ✅
                            </div>

                            <h2>
                                Password Updated
                            </h2>

                            <p>
                                Your password has been
                                changed successfully.
                            </p>

                            <a
                                href="dashboard.php"
                                class="btn btn-primary"
                            >
                                Continue to Dashboard →
                            </a>

                        </div>


                    <?php endif; ?>


                </div>


                <!-- =====================================
                     SECURITY TIPS
                ====================================== -->

                <div class="password-security-card">

                    <div class="password-security-icon">
                        🛡️
                    </div>

                    <h3>
                        Password Tips
                    </h3>

                    <p>
                        Follow these simple steps
                        to keep your account secure.
                    </p>


                    <div class="password-tip">

                        <span>
                            ✓
                        </span>

                        Use at least 6 characters.

                    </div>


                    <div class="password-tip">

                        <span>
                            ✓
                        </span>

                        Avoid using your College ID
                        as your new password.

                    </div>


                    <div class="password-tip">

                        <span>
                            ✓
                        </span>

                        Use a password that is
                        difficult for others to guess.

                    </div>


                    <div class="password-tip">

                        <span>
                            ✓
                        </span>

                        Never share your password
                        with another user.

                    </div>


                    <?php if (
                        !$mustChangePassword
                    ): ?>

                        <a
                            href="dashboard.php"
                            class="password-back-link"
                        >
                            ← Back to Dashboard
                        </a>

                    <?php endif; ?>


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
                Account Security

            </div>


        </div>

    </main>

</div>


<script>

// ==============================================
// SHOW / HIDE PASSWORD
// ==============================================

function togglePassword(inputId, button) {

    const input =
        document.getElementById(inputId);

    if (input.type === "password") {

        input.type = "text";
        button.textContent = "🙈";

    } else {

        input.type = "password";
        button.textContent = "👁";
    }
}


// ==============================================
// CHECK PASSWORD MATCH
// ==============================================

const newPassword =
    document.getElementById(
        "new_password"
    );

const confirmPassword =
    document.getElementById(
        "confirm_password"
    );

const matchText =
    document.getElementById(
        "passwordMatchText"
    );


function checkPasswordMatch() {

    if (
        !newPassword ||
        !confirmPassword ||
        !matchText
    ) {
        return;
    }

    if (
        confirmPassword.value === ""
    ) {

        matchText.textContent =
            "Re-enter your new password.";

        matchText.className = "";

        return;
    }

    if (
        newPassword.value ===
        confirmPassword.value
    ) {

        matchText.textContent =
            "✓ Passwords match";

        matchText.className =
            "password-match-success";

    } else {

        matchText.textContent =
            "Passwords do not match";

        matchText.className =
            "password-match-error";
    }
}


if (
    newPassword &&
    confirmPassword
) {

    newPassword.addEventListener(
        "input",
        checkPasswordMatch
    );

    confirmPassword.addEventListener(
        "input",
        checkPasswordMatch
    );
}


// ==============================================
// FORM VALIDATION
// ==============================================

function validatePasswordForm() {

    const newPass =
        document.getElementById(
            "new_password"
        ).value;

    const confirmPass =
        document.getElementById(
            "confirm_password"
        ).value;

    if (newPass.length < 6) {

        alert(
            "New password must contain at least 6 characters."
        );

        return false;
    }

    if (
        newPass !==
        confirmPass
    ) {

        alert(
            "New password and confirm password do not match."
        );

        return false;
    }

    return true;
}

</script>

</body>

</html>