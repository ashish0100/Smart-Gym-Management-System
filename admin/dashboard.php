<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Authentication Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    $_SESSION['login_error'] =
        'Please log in to access the administration area.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin Role Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);

    exit('Access denied. Administrator access is required.');
}


/*
|--------------------------------------------------------------------------
| Current Admin
|--------------------------------------------------------------------------
*/

$adminName =
    $_SESSION['first_name'] ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| Load Dashboard Statistics
|--------------------------------------------------------------------------
*/

try {

    // Total members
    $totalMembers = (int) $pdo
        ->query(
            'SELECT COUNT(*)
             FROM members'
        )
        ->fetchColumn();


    // Total trainers
    $totalTrainers = (int) $pdo
        ->query(
            'SELECT COUNT(*)
             FROM trainers'
        )
        ->fetchColumn();


    // Active memberships
    $activeMemberships = (int) $pdo
        ->query(
            "SELECT COUNT(*)
             FROM memberships
             WHERE status = 'active'"
        )
        ->fetchColumn();


    // Pending memberships
    $pendingMemberships = (int) $pdo
        ->query(
            "SELECT COUNT(*)
             FROM memberships
             WHERE status = 'pending'"
        )
        ->fetchColumn();


    // Confirmed bookings
    $confirmedBookings = (int) $pdo
        ->query(
            "SELECT COUNT(*)
             FROM bookings
             WHERE status = 'confirmed'"
        )
        ->fetchColumn();


    // Upcoming classes
    $upcomingClasses = (int) $pdo
        ->query(
            "SELECT COUNT(*)
             FROM classes
             WHERE class_date >= CURDATE()
             AND status IN ('available', 'full')"
        )
        ->fetchColumn();


    // Completed revenue
    $totalRevenue = (float) $pdo
        ->query(
            "SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE payment_status = 'completed'"
        )
        ->fetchColumn();


    // Recent members
    $recentMemberStatement = $pdo->query(
        'SELECT
            u.first_name,
            u.last_name,
            u.email,
            m.registration_date

         FROM members m

         INNER JOIN users u
            ON m.user_id = u.user_id

         ORDER BY m.member_id DESC

         LIMIT 5'
    );

    $recentMembers =
        $recentMemberStatement->fetchAll();


    // Recent payments
    $recentPaymentStatement = $pdo->query(
        'SELECT
            p.amount,
            p.payment_status,
            p.payment_date,
            p.transaction_reference,
            u.first_name,
            u.last_name

         FROM payments p

         INNER JOIN members m
            ON p.member_id = m.member_id

         INNER JOIN users u
            ON m.user_id = u.user_id

         ORDER BY p.payment_id DESC

         LIMIT 5'
    );

    $recentPayments =
        $recentPaymentStatement->fetchAll();


} catch (PDOException $exception) {

    error_log(
        'Admin dashboard error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the administrator dashboard.'
    );
}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function adminEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
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
        Admin Dashboard | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body class="dashboard-page">


<header class="dashboard-navbar">

    <div class="container dashboard-nav-container">

        <a
            href="dashboard.php"
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
                    Administration Portal
                </small>

            </div>

        </a>


        <div class="dashboard-user">

            <div class="user-text">

                <span>
                    Administrator
                </span>

                <strong>
                    <?php
                    echo adminEscape($adminName);
                    ?>
                </strong>

            </div>


            <div class="user-avatar">
                A
            </div>


            <a
                href="../auth/logout.php"
                class="logout-button"
            >
                Logout
            </a>

        </div>

    </div>

</header>


<main class="dashboard-main">

    <div class="container">


        <!-- WELCOME -->

        <section class="dashboard-welcome">

            <div>

                <span class="eyebrow">
                    ADMIN DASHBOARD
                </span>

                <h1>
                    Gym overview.
                </h1>

                <p>
                    Monitor members, memberships,
                    bookings, trainers and payments
                    across World Fitness Australia.
                </p>

            </div>


            <span
                class="membership-status status-active"
            >
                SYSTEM ONLINE
            </span>

        </section>


        <!-- SUMMARY -->

        <section class="dashboard-summary">

            <article class="summary-card">

                <span class="summary-label">
                    Total Members
                </span>

                <strong>
                    <?php echo $totalMembers; ?>
                </strong>

                <small>
                    Registered gym members
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Active Memberships
                </span>

                <strong>
                    <?php echo $activeMemberships; ?>
                </strong>

                <small>
                    Currently active plans
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Confirmed Bookings
                </span>

                <strong>
                    <?php echo $confirmedBookings; ?>
                </strong>

                <small>
                    Current class bookings
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Completed Revenue
                </span>

                <strong>
                    $<?php
                    echo number_format(
                        $totalRevenue,
                        2
                    );
                    ?>
                </strong>

                <small>
                    Recorded completed payments
                </small>

            </article>

        </section>


        <!-- SECOND SUMMARY -->

        <section class="admin-mini-summary">

            <div>
                <span>Trainers</span>
                <strong>
                    <?php echo $totalTrainers; ?>
                </strong>
            </div>

            <div>
                <span>Pending Memberships</span>
                <strong>
                    <?php echo $pendingMemberships; ?>
                </strong>
            </div>

            <div>
                <span>Upcoming Classes</span>
                <strong>
                    <?php echo $upcomingClasses; ?>
                </strong>
            </div>

        </section>


        <!-- MANAGEMENT AREAS -->

        <section class="dashboard-panel admin-management-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        MANAGEMENT
                    </span>

                    <h2>
                        Administration Areas
                    </h2>

                </div>

            </div>


            <div class="admin-management-grid">

                <div class="admin-management-card">

                    <span class="quick-icon">
                        👥
                    </span>

                    <div>

                        <strong>
                            Member Management
                        </strong>

                        <small>
                            View and manage registered members
                        </small>

                    </div>

                </div>


                <div class="admin-management-card">

                    <span class="quick-icon">
                        🏋️
                    </span>

                    <div>

                        <strong>
                            Trainer Management
                        </strong>

                        <small>
                            Manage trainers and staff
                        </small>

                    </div>

                </div>


                <div class="admin-management-card">

                    <span class="quick-icon">
                        📅
                    </span>

                    <div>

                        <strong>
                            Class Management
                        </strong>

                        <small>
                            Create and manage gym sessions
                        </small>

                    </div>

                </div>


                <div class="admin-management-card">

                    <span class="quick-icon">
                        💳
                    </span>

                    <div>

                        <strong>
                            Payment Management
                        </strong>

                        <small>
                            Review member payments
                        </small>

                    </div>

                </div>


                <div class="admin-management-card">

                    <span class="quick-icon">
                        📊
                    </span>

                    <div>

                        <strong>
                            Reports
                        </strong>

                        <small>
                            Review gym performance data
                        </small>

                    </div>

                </div>


                <div class="admin-management-card">

                    <span class="quick-icon">
                        🔔
                    </span>

                    <div>

                        <strong>
                            Notifications
                        </strong>

                        <small>
                            Manage member communication
                        </small>

                    </div>

                </div>

            </div>

        </section>


        <!-- RECENT ACTIVITY -->

        <section class="dashboard-content-grid">


            <!-- RECENT MEMBERS -->

            <article class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            MEMBERS
                        </span>

                        <h2>
                            Recent Registrations
                        </h2>

                    </div>

                </div>


                <?php if ($recentMembers): ?>

                    <div class="admin-list">

                        <?php
                        foreach ($recentMembers as $recentMember):
                        ?>

                            <div class="admin-list-row">

                                <div>

                                    <strong>
                                        <?php
                                        echo adminEscape(
                                            $recentMember[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $recentMember[
                                                'last_name'
                                            ]
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo adminEscape(
                                            $recentMember[
                                                'email'
                                            ]
                                        );
                                        ?>
                                    </span>

                                </div>

                                <small>
                                    <?php
                                    echo adminEscape(
                                        $recentMember[
                                            'registration_date'
                                        ]
                                    );
                                    ?>
                                </small>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="empty-message">
                        No member registrations yet.
                    </p>

                <?php endif; ?>

            </article>


            <!-- RECENT PAYMENTS -->

            <article class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            PAYMENTS
                        </span>

                        <h2>
                            Recent Transactions
                        </h2>

                    </div>

                </div>


                <?php if ($recentPayments): ?>

                    <div class="admin-list">

                        <?php
                        foreach ($recentPayments as $payment):
                        ?>

                            <div class="admin-list-row">

                                <div>

                                    <strong>

                                        $<?php
                                        echo number_format(
                                            (float)
                                            $payment['amount'],
                                            2
                                        );
                                        ?>

                                    </strong>

                                    <span>

                                        <?php
                                        echo adminEscape(
                                            $payment[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $payment[
                                                'last_name'
                                            ]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <small>

                                    <?php
                                    echo strtoupper(
                                        adminEscape(
                                            $payment[
                                                'payment_status'
                                            ]
                                        )
                                    );
                                    ?>

                                </small>

                            </div>

                        <?php endforeach; ?>

                    </div>


                <?php else: ?>

                    <p class="empty-message">
                        No payment transactions yet.
                    </p>

                <?php endif; ?>

            </article>

        </section>


    </div>

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
            Administration Portal
        </p>

    </div>

</footer>


</body>

</html>