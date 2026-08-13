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

$error = $_SESSION['register_error'] ?? '';

unset($_SESSION['register_error']);

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
        Register | World Fitness Australia
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

    <section class="auth-wrapper register-wrapper">

        <!-- LEFT SIDE -->

        <div class="auth-intro">

            <span class="eyebrow">
                JOIN WORLD FITNESS
            </span>

            <h1>
                Start your fitness journey.
            </h1>

            <p>
                Create your World Fitness Australia account
                to manage your membership, book classes,
                track attendance and access gym services
                online.
            </p>


            <div class="auth-benefits">

                <div>

                    <span>✓</span>

                    <p>
                        Manage your membership online
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Book available gym classes
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        View payments and attendance
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Receive membership notifications
                    </p>

                </div>

            </div>

        </div>


        <!-- REGISTER CARD -->

        <div class="auth-card register-card">

            <div class="auth-card-header">

                <div class="auth-tabs">

                    <a
                        href="login.php"
                        class="auth-tab"
                    >
                        Login
                    </a>


                    <a
                        href="register.php"
                        class="auth-tab active"
                    >
                        Register
                    </a>

                </div>


                <h2>
                    Create your account
                </h2>


                <p>
                    Enter your details to register
                    as a World Fitness member.
                </p>

            </div>


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


            <form
                action="register_process.php"
                method="POST"
                class="auth-form"
            >

                <!-- NAME -->

                <div class="form-row">

                    <div class="form-group">

                        <label for="first_name">
                            First Name
                        </label>

                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            placeholder="Ashish"
                            maxlength="50"
                            autocomplete="given-name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="last_name">
                            Last Name
                        </label>

                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            placeholder="Karki"
                            maxlength="50"
                            autocomplete="family-name"
                            required
                        >

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        maxlength="150"
                        autocomplete="email"
                        required
                    >

                </div>


                <!-- PHONE + DOB -->

                <div class="form-row">

                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="04XX XXX XXX"
                            maxlength="20"
                            autocomplete="tel"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="date_of_birth">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            required
                        >

                    </div>

                </div>


                <!-- ADDRESS -->

                <div class="form-group">

                    <label for="address">
                        Address
                    </label>

                    <input
                        type="text"
                        id="address"
                        name="address"
                        placeholder="Enter your address"
                        maxlength="255"
                        autocomplete="street-address"
                        required
                    >

                </div>


                <!-- MEMBERSHIP -->

                <div class="form-group">

                    <label for="membership_type">
                        Membership Type
                    </label>

                    <select
                        id="membership_type"
                        name="membership_type"
                        required
                    >

                        <option value="">
                            Select a membership
                        </option>

                        <option value="weekly">
                            Weekly Membership
                        </option>

                        <option value="monthly">
                            Monthly Membership
                        </option>

                        <option value="annual">
                            Annual Membership
                        </option>

                    </select>

                </div>


                <!-- PASSWORD -->

                <div class="form-row">

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Repeat password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <!-- TERMS -->

                <div class="terms-row">

                    <label class="checkbox-label">

                        <input
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                        >

                        <span>
                            I agree to the membership terms
                            and privacy requirements.
                        </span>

                    </label>

                </div>


                <!-- BUTTON -->

                <button
                    type="submit"
                    class="btn auth-submit"
                >
                    Create Account
                </button>

            </form>


            <div class="auth-footer">

                <p>

                    Already have an account?

                    <a href="login.php">
                        Sign in
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
            Member Registration
        </p>

    </div>

</footer>

</body>

</html>