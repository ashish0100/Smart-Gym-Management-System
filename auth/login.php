<?php

session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'member';

    if ($role === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($role === 'trainer') {
        header('Location: ../trainer/dashboard.php');
    } else {
        header('Location: ../member/dashboard.php');
    }

    exit;
}

$error = $_SESSION['login_error'] ?? '';

$success =
    $_SESSION['register_success']
    ?? $_SESSION['login_success']
    ?? '';

unset($_SESSION['login_error']);
unset($_SESSION['register_success']);
unset($_SESSION['login_success']);

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
        Login | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>

<body class="auth-page">

<header class="navbar">

    <div class="container nav-container">

        <a
            href="../index.php"
            class="logo"
        >

            <span class="logo-icon">

                <svg
                    viewBox="0 0 64 64"
                    aria-hidden="true"
                >

                    <rect
                        x="7"
                        y="24"
                        width="8"
                        height="16"
                        rx="2">
                    </rect>

                    <rect
                        x="16"
                        y="20"
                        width="7"
                        height="24"
                        rx="2">
                    </rect>

                    <rect
                        x="41"
                        y="20"
                        width="7"
                        height="24"
                        rx="2">
                    </rect>

                    <rect
                        x="49"
                        y="24"
                        width="8"
                        height="16"
                        rx="2">
                    </rect>

                    <rect
                        x="22"
                        y="29"
                        width="20"
                        height="6"
                        rx="2">
                    </rect>

                </svg>

            </span>

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Smart Gym Management
                </small>

            </div>

        </a>


        <nav>

            <a href="../index.php">
                Back to Home
            </a>

        </nav>

    </div>

</header>


<main class="auth-main">

    <section class="auth-wrapper">

        <!-- LEFT SIDE -->

        <div class="auth-intro">

            <span class="eyebrow">
                WORLD FITNESS AUSTRALIA
            </span>

            <h1>
                Welcome back.
            </h1>

            <p>
                Sign in to manage your membership,
                book gym classes, view attendance,
                make payments and access your
                personal fitness dashboard.
            </p>


            <div class="auth-benefits">

                <div>

                    <span>✓</span>

                    <p>
                        Secure member access
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Manage memberships online
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Book and manage gym classes
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Track payments and attendance
                    </p>

                </div>

            </div>

        </div>


        <!-- LOGIN CARD -->

        <div class="auth-card">

            <div class="auth-card-header">

                <div class="auth-tabs">

                    <a
                        href="login.php"
                        class="auth-tab active"
                    >
                        Login
                    </a>


                    <a
                        href="register.php"
                        class="auth-tab"
                    >
                        Register
                    </a>

                </div>


                <h2>
                    Sign in to your account
                </h2>


                <p>
                    Enter your registered email
                    address and password.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($error): ?>

                <div class="alert alert-error">

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- SUCCESS -->

            <?php if ($success): ?>

                <div class="alert alert-success">

                    <?php
                    echo htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->

            <form
                action="login_process.php"
                method="POST"
                class="auth-form"
            >

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        autocomplete="email"
                        maxlength="150"
                        required
                    >

                </div>


                <div class="form-group">

                    <div class="label-row">

                        <label for="password">
                            Password
                        </label>


                        <a
                            href="#"
                            class="form-link"
                        >
                            Forgot Password?
                        </a>

                    </div>


                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="remember-row">

                    <label class="checkbox-label">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        <span>
                            Remember me
                        </span>

                    </label>

                </div>


                <button
                    type="submit"
                    class="btn auth-submit"
                >
                    Login
                </button>

            </form>


            <div class="auth-footer">

                <p>

                    Don't have an account?

                    <a href="register.php">
                        Create an account
                    </a>

                </p>

            </div>

        </div>

    </section>

</main>


<footer>

    <div class="container footer-content">

        <div>

            <strong>
                World Fitness Australia
            </strong>

            <p>
                Smart Gym Management System
            </p>

        </div>


        <p>
            Secure Member Portal
        </p>

    </div>

</footer>

</body>

</html>