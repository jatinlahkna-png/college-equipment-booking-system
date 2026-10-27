<?php

session_start();

require_once "../includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter email and password.";

    } else {

        $sql = "
            SELECT
                user_id,
                college_id,
                name,
                email,
                password,
                role,
                status,
                must_change_password
            FROM users
            WHERE email = ?
            AND status = 'active'
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) === 1) {

                $user = mysqli_fetch_assoc($result);

                if (password_verify($password, $user["password"])) {

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["must_change_password"] =
                        intval($user["must_change_password"]);

                    if (
                        $user["role"] !== "admin" &&
                        intval($user["must_change_password"]) === 1
                    ) {
                        header("Location: ../user/change_password.php");
                        exit();
                    }

                    if ($user["role"] === "admin") {
                        header("Location: ../admin/dashboard.php");
                        exit();
                    }

                    if (
                        $user["role"] === "student" ||
                        $user["role"] === "faculty"
                    ) {
                        header("Location: ../user/dashboard.php");
                        exit();
                    }

                    session_unset();
                    session_destroy();

                    $error = "Invalid account role.";

                } else {

                    $error = "Invalid email or password.";
                }

            } else {

                $error = "Invalid email or password.";
            }

            mysqli_stmt_close($stmt);
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
        Login | College Equipment Booking System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body class="modern-login-page">

<div class="modern-login-wrapper">

    <!-- LEFT SIDE -->
    <div class="login-left">

        <div class="login-overlay"></div>

        <div class="login-left-content">

            <div class="college-logo">
                🎓
            </div>

            <h1>
                COLLEGE
                <span>EQUIPMENT</span>
                BOOKING SYSTEM
            </h1>

            <div class="gold-line"></div>

            <h3>
                Smart. Simple. Hassle Free.
            </h3>

            <p>
                Manage, book and utilize college
                equipment efficiently.
            </p>

            <div class="feature-grid">

                <div class="feature-item">

                    <div class="feature-icon">
                        📅
                    </div>

                    <div>
                        <strong>Easy Booking</strong>

                        <small>
                            Book equipment in just a few clicks
                        </small>
                    </div>

                </div>

                <div class="feature-item">

                    <div class="feature-icon">
                        🛡️
                    </div>

                    <div>
                        <strong>Secure & Reliable</strong>

                        <small>
                            Safe role-based account access
                        </small>
                    </div>

                </div>

                <div class="feature-item">

                    <div class="feature-icon">
                        💻
                    </div>

                    <div>
                        <strong>Wide Range</strong>

                        <small>
                            Access different college equipment
                        </small>
                    </div>

                </div>

                <div class="feature-item">

                    <div class="feature-icon">
                        🕒
                    </div>

                    <div>
                        <strong>Real-Time Availability</strong>

                        <small>
                            Check availability before booking
                        </small>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- RIGHT SIDE -->
    <div class="login-right">

        <div class="modern-login-card">

            <div class="login-lock">
                🔒
            </div>

            <h2>
                Welcome Back!
            </h2>

            <p class="login-subtitle">
                Sign in to continue to your account
            </p>

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="login-form-group">

                    <label>
                        Email Address
                    </label>

                    <div class="input-box">

                        <span>
                            ✉
                        </span>

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["email"] ?? ""
                                );
                            ?>"
                            required
                        >

                    </div>

                </div>


                <div class="login-form-group">

                    <label>
                        Password
                    </label>

                    <div class="input-box">

                        <span>
                            🔒
                        </span>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                        >
                            👁
                        </button>

                    </div>

                </div>


                <div class="login-options">

                    <label class="remember-box">

                        <input
                            type="checkbox"
                            name="remember"
                        >

                        <span>
                            Remember me
                        </span>

                    </label>

                    <span class="forgot-link">
                        Forgot Password?
                    </span>

                </div>


                <button
                    type="submit"
                    class="modern-login-button"
                >
                    ➜ SIGN IN
                </button>

            </form>


            <div class="login-divider">

                <span>
                    or
                </span>

            </div>


            <div class="first-user-box">

                👥

                <span>
                    First time user?
                    Contact Administrator
                </span>

            </div>

        </div>

    </div>

</div>


<script>

function togglePassword() {

    const password =
        document.getElementById("password");

    if (password.type === "password") {
        password.type = "text";
    } else {
        password.type = "password";
    }
}

</script>

</body>

</html>