<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Authentication check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    $_SESSION['login_error'] =
        'Please log in to access your dashboard.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Role check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'member'
) {
    http_response_code(403);

    exit('Access denied.');
}


/*
|--------------------------------------------------------------------------
| Logged-in user
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get member information
|--------------------------------------------------------------------------
*/

try {

    $memberStatement = $pdo->prepare(
        'SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.status AS account_status,

            m.member_id,
            m.phone,
            m.date_of_birth,
            m.address,
            m.registration_date

         FROM users u

         INNER JOIN members m
            ON u.user_id = m.user_id

         WHERE u.user_id = :user_id

         LIMIT 1'
    );

    $memberStatement->execute([
        'user_id' => $userId
    ]);

    $member = $memberStatement->fetch();


    if (!$member) {

        session_unset();
        session_destroy();

        header('Location: ../auth/login.php');
        exit;
    }


    $memberId = (int) $member['member_id'];


    /*
    |--------------------------------------------------------------------------
    | Get latest membership
    |--------------------------------------------------------------------------
    */

    $membershipStatement = $pdo->prepare(
        'SELECT
            membership_id,
            membership_type,
            access_type,
            start_date,
            end_date,
            status,
            remaining_visits

         FROM memberships

         WHERE member_id = :member_id

         ORDER BY membership_id DESC

         LIMIT 1'
    );

    $membershipStatement->execute([
        'member_id' => $memberId
    ]);

    $membership = $membershipStatement->fetch();


    /*
    |--------------------------------------------------------------------------
    | Count bookings
    |--------------------------------------------------------------------------
    */

    $bookingStatement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM bookings
         WHERE member_id = :member_id
         AND status = :status'
    );

    $bookingStatement->execute([
        'member_id' => $memberId,
        'status' => 'confirmed'
    ]);

    $bookingCount =
        (int) $bookingStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Count attendance
    |--------------------------------------------------------------------------
    */

    $attendanceStatement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM attendance
         WHERE member_id = :member_id
         AND status = :status'
    );

    $attendanceStatement->execute([
        'member_id' => $memberId,
        'status' => 'present'
    ]);

    $attendanceCount =
        (int) $attendanceStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Count unread notifications
    |--------------------------------------------------------------------------
    */

    $notificationStatement = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM notifications
         WHERE user_id = :user_id
         AND is_read = 0'
    );

    $notificationStatement->execute([
        'user_id' => $userId
    ]);

    $notificationCount =
        (int) $notificationStatement->fetchColumn();


} catch (PDOException $exception) {

    error_log(
        'Member dashboard error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load your dashboard. Please try again later.'
    );
}


/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function displayValue(?string $value): string
{
    if (
        $value === null ||
        trim($value) === ''
    ) {
        return 'Not available';
    }

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatMembershipType(?string $type): string
{
    if (!$type) {
        return 'No Membership';
    }

    return ucwords(
        str_replace('_', ' ', $type)
    );
}


function membershipStatusClass(?string $status): string
{
    return match ($status) {
        'active' => 'status-active',
        'pending' => 'status-pending',
        'paused' => 'status-paused',
        'expired' => 'status-expired',
        'cancelled' => 'status-cancelled',
        default => 'status-pending'
    };
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
        Member Dashboard | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body class="dashboard-page">


<!-- =====================================================
     TOP NAVIGATION
===================================================== -->

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
                    Member Portal
                </small>

            </div>

        </a>


        <div class="dashboard-user">

            <div class="user-text">

                <span>
                    Signed in as
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $member['first_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </strong>

            </div>


            <div class="user-avatar">

                <?php
                echo strtoupper(
                    substr(
                        $member['first_name'],
                        0,
                        1
                    )
                );
                ?>

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


<!-- =====================================================
     DASHBOARD
===================================================== -->

<main class="dashboard-main">

    <div class="container">


        <!-- WELCOME -->

        <section class="dashboard-welcome">

            <div>

                <span class="eyebrow">
                    MEMBER DASHBOARD
                </span>

                <h1>
                    Welcome back,
                    <?php
                    echo htmlspecialchars(
                        $member['first_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>.
                </h1>

                <p>
                    Manage your membership,
                    bookings, attendance and payments
                    from one place.
                </p>

            </div>


            <?php if ($membership): ?>

                <span
                    class="membership-status
                    <?php
                    echo membershipStatusClass(
                        $membership['status']
                    );
                    ?>"
                >

                    <?php
                    echo strtoupper(
                        displayValue(
                            $membership['status']
                        )
                    );
                    ?>

                </span>

            <?php endif; ?>

        </section>


        <!-- SUMMARY CARDS -->

        <section class="dashboard-summary">

            <article class="summary-card">

                <span class="summary-label">
                    Membership
                </span>

                <strong>
                    <?php
                    echo formatMembershipType(
                        $membership['membership_type']
                            ?? null
                    );
                    ?>
                </strong>

                <small>
                    Current membership plan
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Attendance
                </span>

                <strong>
                    <?php
                    echo $attendanceCount;
                    ?>
                </strong>

                <small>
                    Recorded gym visits
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Upcoming Bookings
                </span>

                <strong>
                    <?php
                    echo $bookingCount;
                    ?>
                </strong>

                <small>
                    Confirmed classes
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Notifications
                </span>

                <strong>
                    <?php
                    echo $notificationCount;
                    ?>
                </strong>

                <small>
                    Unread notifications
                </small>

            </article>

        </section>


        <!-- MAIN GRID -->

        <section class="dashboard-content-grid">


            <!-- MEMBERSHIP DETAILS -->

            <article class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            MEMBERSHIP
                        </span>

                        <h2>
                            Membership Details
                        </h2>

                    </div>

                    <a
                        href="../membership/index.php"
                        class="panel-link"
                    >
                        Manage
                    </a>

                </div>


                <?php if ($membership): ?>

                    <div class="detail-list">

                        <div>

                            <span>
                                Membership Type
                            </span>

                            <strong>
                                <?php
                                echo formatMembershipType(
                                    $membership[
                                        'membership_type'
                                    ]
                                );
                                ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Access
                            </span>

                            <strong>
                                <?php
                                echo formatMembershipType(
                                    $membership[
                                        'access_type'
                                    ]
                                );
                                ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Start Date
                            </span>

                            <strong>
                                <?php
                                echo displayValue(
                                    $membership[
                                        'start_date'
                                    ]
                                );
                                ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Expiry Date
                            </span>

                            <strong>
                                <?php
                                echo displayValue(
                                    $membership[
                                        'end_date'
                                    ]
                                );
                                ?>
                            </strong>

                        </div>

                    </div>


                <?php else: ?>

                    <p class="empty-message">
                        No membership record is
                        currently available.
                    </p>

                <?php endif; ?>

            </article>


            <!-- QUICK ACTIONS -->

            <article class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            QUICK ACCESS
                        </span>

                        <h2>
                            What would you like to do?
                        </h2>

                    </div>

                </div>


                <div class="quick-actions">

                    <a
                        href="../booking/index.php"
                        class="quick-action"
                    >

                        <span class="quick-icon">
                            📅
                        </span>

                        <div>

                            <strong>
                                Book a Class
                            </strong>

                            <small>
                                View available sessions
                            </small>

                        </div>

                    </a>


                    <a
                        href="../payment/index.php"
                        class="quick-action"
                    >

                        <span class="quick-icon">
                            💳
                        </span>

                        <div>

                            <strong>
                                Make Payment
                            </strong>

                            <small>
                                Membership and visit payments
                            </small>

                        </div>

                    </a>


                    <a
                        href="../attendance/index.php"
                        class="quick-action"
                    >

                        <span class="quick-icon">
                            📊
                        </span>

                        <div>

                            <strong>
                                Attendance
                            </strong>

                            <small>
                                View your gym visit history
                            </small>

                        </div>

                    </a>


                    <a
                        href="../notifications/index.php"
                        class="quick-action"
                    >

                        <span class="quick-icon">
                            🔔
                        </span>

                        <div>

                            <strong>
                                Notifications
                            </strong>

                            <small>
                                View messages and reminders
                            </small>

                        </div>

                    </a>

                </div>

            </article>

        </section>


        <!-- MEMBER PROFILE -->

        <section class="dashboard-panel profile-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        PROFILE
                    </span>

                    <h2>
                        Member Information
                    </h2>

                </div>

            </div>


            <div class="profile-grid">

                <div>

                    <span>
                        Full Name
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $member['first_name']
                            . ' '
                            . $member['last_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Email
                    </span>

                    <strong>
                        <?php
                        echo displayValue(
                            $member['email']
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Phone
                    </span>

                    <strong>
                        <?php
                        echo displayValue(
                            $member['phone']
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Member Since
                    </span>

                    <strong>
                        <?php
                        echo displayValue(
                            $member[
                                'registration_date'
                            ]
                        );
                        ?>
                    </strong>

                </div>

            </div>

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
            Member Portal
        </p>

    </div>

</footer>


</body>

</html>