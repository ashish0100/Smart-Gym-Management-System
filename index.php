<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>World Fitness Australia</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="navbar">

    <div class="container nav-container">

        <a href="index.php" class="logo">
            <span class="logo-icon">✚</span>
            <div>
                <strong>World Fitness Australia</strong>
                <small>Smart Gym Management</small>
            </div>
        </a>

        <nav>
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#membership">Membership</a>
            <a href="#about">About</a>

            <a href="auth/login.php" class="login-link">
                Login
            </a>

            <a href="auth/register.php" class="btn btn-small">
                Join Now
            </a>
        </nav>

    </div>

</header>


<main>

<section class="hero" id="home">

    <div class="container hero-grid">

        <div class="hero-content">

            <span class="eyebrow">
                SMARTER FITNESS. SIMPLER MANAGEMENT.
            </span>

            <h1>
                Your fitness journey
                <span>starts here.</span>
            </h1>

            <p>
                Manage your membership, book gym classes,
                track your attendance and access your fitness
                information from one convenient platform.
            </p>

            <div class="hero-actions">

                <a href="auth/register.php"
                   class="btn">
                    Become a Member
                </a>

                <a href="#membership"
                   class="btn btn-outline">
                    View Memberships
                </a>

            </div>

            <div class="hero-stats">

                <div>
                    <strong>Flexible</strong>
                    <span>Membership Options</span>
                </div>

                <div>
                    <strong>Easy</strong>
                    <span>Class Booking</span>
                </div>

                <div>
                    <strong>Secure</strong>
                    <span>Member Access</span>
                </div>

            </div>

        </div>


        <div class="hero-card">

            <div class="dashboard-preview">

                <div class="preview-header">

                    <div>
                        <small>MEMBER DASHBOARD</small>
                        <h3>Welcome to World Fitness</h3>
                    </div>

                    <span class="status">
                        ACTIVE
                    </span>

                </div>


                <div class="preview-grid">

                    <div class="preview-item">
                        <span>Membership</span>
                        <strong>Premium Plan</strong>
                    </div>

                    <div class="preview-item">
                        <span>Attendance</span>
                        <strong>12 Visits</strong>
                    </div>

                    <div class="preview-item">
                        <span>Booked Classes</span>
                        <strong>3 Classes</strong>
                    </div>

                    <div class="preview-item">
                        <span>Visit Pack</span>
                        <strong>10 Remaining</strong>
                    </div>

                </div>

                <div class="preview-actions">

                    <span>Book a Class</span>
                    <span>Make Payment</span>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="features section" id="features">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">
                EVERYTHING IN ONE PLACE
            </span>

            <h2>
                A smarter way to manage your gym experience
            </h2>

            <p>
                World Fitness Australia gives members and staff
                simple digital tools for everyday gym activities.
            </p>

        </div>


        <div class="feature-grid">

            <article class="feature-card">
                <div class="feature-icon">👤</div>

                <h3>Membership Management</h3>

                <p>
                    View membership details, check expiry dates,
                    renew plans and manage membership status.
                </p>
            </article>


            <article class="feature-card">
                <div class="feature-icon">📅</div>

                <h3>Class Booking</h3>

                <p>
                    Find available gym classes, check session
                    information and book suitable training times.
                </p>
            </article>


            <article class="feature-card">
                <div class="feature-icon">💳</div>

                <h3>Payments</h3>

                <p>
                    Manage membership payments, visit packs
                    and access your digital transaction history.
                </p>
            </article>


            <article class="feature-card">
                <div class="feature-icon">📊</div>

                <h3>Attendance Tracking</h3>

                <p>
                    Track gym visits and maintain an organised
                    history of your fitness activity.
                </p>
            </article>

        </div>

    </div>

</section>


<section class="membership section" id="membership">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">
                MEMBERSHIP OPTIONS
            </span>

            <h2>
                Choose a plan that works for you
            </h2>

        </div>


        <div class="membership-grid">

            <article class="plan-card">

                <h3>Weekly</h3>

                <p>
                    Flexible short-term access to World Fitness.
                </p>

                <a href="auth/register.php"
                   class="btn btn-outline">
                    Get Started
                </a>

            </article>


            <article class="plan-card featured">

                <span class="popular">
                    MOST POPULAR
                </span>

                <h3>Monthly</h3>

                <p>
                    Our balanced membership for regular gym users.
                </p>

                <a href="auth/register.php"
                   class="btn">
                    Join Now
                </a>

            </article>


            <article class="plan-card">

                <h3>Annual</h3>

                <p>
                    Long-term access for committed members.
                </p>

                <a href="auth/register.php"
                   class="btn btn-outline">
                    Get Started
                </a>

            </article>

        </div>

    </div>

</section>


<section class="about section" id="about">

    <div class="container about-content">

        <div>

            <span class="eyebrow">
                WORLD FITNESS AUSTRALIA
            </span>

            <h2>
                Simple technology.
                Better fitness management.
            </h2>

        </div>

        <p>
            The Smart Gym Management System replaces manual
            membership records, paper receipts and traditional
            booking processes with an organised web-based system
            designed for members, trainers and administrators.
        </p>

    </div>

</section>

</main>


<footer>

    <div class="container footer-content">

        <div>
            <strong>World Fitness Australia</strong>
            <p>Smart Gym Management System</p>
        </div>

        <p>
            ICT308 Project 2
        </p>

    </div>

</footer>


</body>
</html>