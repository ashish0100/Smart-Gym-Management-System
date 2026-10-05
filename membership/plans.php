<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Membership Plans | World Fitness Australia</title>

    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

<header class="navbar">

    <div class="container nav-container">

        <a href="../index.php" class="logo">

            <span class="logo-icon">✚</span>

            <div>
                <strong>World Fitness Australia</strong>
                <small>Smart Gym Management</small>
            </div>

        </a>

        <nav>

            <a href="../index.php">Home</a>

            <a href="../index.php#features">
                Features
            </a>

            <a href="plans.php">
                Membership
            </a>

            <a href="../auth/login.php"
               class="login-link">
                Login
            </a>

            <a href="../auth/register.php"
               class="btn btn-small">
                Join Now
            </a>

        </nav>

    </div>

</header>


<main>

<section class="section">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">
                WORLD FITNESS MEMBERSHIPS
            </span>

            <h1>
                Find the membership plan
                that works for you
            </h1>

            <p>
                Compare our membership options and choose
                a plan based on your schedule, fitness goals
                and preferred level of commitment.
            </p>

        </div>


        <div class="membership-grid">

            <!-- WEEKLY -->

            <article class="plan-card">

                <div class="plan-header">

                    <span class="plan-label">
                        FLEXIBLE
                    </span>

                    <h3>
                        Weekly
                    </h3>

                </div>

                <p class="plan-description">
                    A flexible option for short-term access
                    or members who do not want a long-term
                    membership commitment.
                </p>

                <ul class="plan-features">

                    <li>
                        ✓ Full gym access
                    </li>

                    <li>
                        ✓ Class booking access
                    </li>

                    <li>
                        ✓ Member dashboard
                    </li>

                    <li>
                        ✓ Attendance tracking
                    </li>

                    <li>
                        ✓ Online membership management
                    </li>

                </ul>

                <a href="../auth/register.php"
                   class="btn btn-outline plan-button">
                    Choose Weekly
                </a>

            </article>


            <!-- MONTHLY -->

            <article class="plan-card featured">

                <span class="popular">
                    MOST POPULAR
                </span>

                <div class="plan-header">

                    <span class="plan-label">
                        BEST VALUE
                    </span>

                    <h3>
                        Monthly
                    </h3>

                </div>

                <p class="plan-description">
                    Designed for regular gym users who want
                    convenient access without committing
                    to a full year.
                </p>

                <ul class="plan-features">

                    <li>
                        ✓ Full gym access
                    </li>

                    <li>
                        ✓ Unlimited class booking
                    </li>

                    <li>
                        ✓ Member dashboard
                    </li>

                    <li>
                        ✓ Attendance history
                    </li>

                    <li>
                        ✓ Payment history
                    </li>

                </ul>

                <a href="../auth/register.php"
                   class="btn plan-button">
                    Choose Monthly
                </a>

            </article>


            <!-- ANNUAL -->

            <article class="plan-card">

                <div class="plan-header">

                    <span class="plan-label">
                        COMMITTED
                    </span>

                    <h3>
                        Annual
                    </h3>

                </div>

                <p class="plan-description">
                    A long-term membership option for
                    committed members who want simple
                    year-round gym access.
                </p>

                <ul class="plan-features">

                    <li>
                        ✓ Full gym access
                    </li>

                    <li>
                        ✓ Unlimited class booking
                    </li>

                    <li>
                        ✓ Member dashboard
                    </li>

                    <li>
                        ✓ Attendance history
                    </li>

                    <li>
                        ✓ Long-term membership access
                    </li>

                </ul>

                <a href="../auth/register.php"
                   class="btn btn-outline plan-button">
                    Choose Annual
                </a>

            </article>

        </div>

    </div>

</section>


<section class="about section">

    <div class="container about-content">

        <div>

            <span class="eyebrow">
                SIMPLE MEMBERSHIP MANAGEMENT
            </span>

            <h2>
                Manage everything online
            </h2>

        </div>

        <p>
            Members can access their membership information,
            bookings, attendance history and account details
            through the World Fitness Australia digital platform.
        </p>

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
            ICT308 Project 2
        </p>

    </div>

</footer>

</body>

</html>