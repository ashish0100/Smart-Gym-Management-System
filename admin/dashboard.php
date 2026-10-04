<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-19 - Improved Administrator Dashboard
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Authentication
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
| Administrator Role Protection
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);

    exit(
        'Access denied. Administrator access is required.'
    );
}


$adminName =
    (string) (
        $_SESSION['first_name']
        ?? 'Administrator'
    );


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function adminEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function adminDate(
    ?string $value,
    bool $withTime = false
): string {

    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return adminEscape($value);
    }

    return $withTime
        ? date('d M Y, g:i A', $timestamp)
        : date('d M Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| Dashboard Data
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Members
    |--------------------------------------------------------------------------
    */

    $totalMembers =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM members'
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Trainers
    |--------------------------------------------------------------------------
    */

    $totalTrainers =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM trainers'
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Memberships
    |--------------------------------------------------------------------------
    */

    $activeMemberships =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM memberships
                 WHERE status = 'active'"
            )
            ->fetchColumn();


    $pendingMemberships =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM memberships
                 WHERE status = 'pending'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    $confirmedBookings =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE status = 'confirmed'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Upcoming Classes
    |--------------------------------------------------------------------------
    */

    $upcomingClasses =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM classes
                 WHERE class_date >= CURDATE()
                 AND status IN (
                    'available',
                    'full'
                 )"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Completed Revenue
    |--------------------------------------------------------------------------
    */

    $totalRevenue =
        (float) $pdo
            ->query(
                "SELECT
                    COALESCE(
                        SUM(amount),
                        0
                    )
                 FROM payments
                 WHERE payment_status = 'completed'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Pending Payments
    |--------------------------------------------------------------------------
    */

    $pendingPayments =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM payments
                 WHERE payment_status = 'pending'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    $attendanceStatement =
        $pdo->query(
            "SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'present'
                        THEN 1
                        ELSE 0
                    END
                ) AS present_count,

                SUM(
                    CASE
                        WHEN status = 'late'
                        THEN 1
                        ELSE 0
                    END
                ) AS late_count,

                SUM(
                    CASE
                        WHEN status = 'absent'
                        THEN 1
                        ELSE 0
                    END
                ) AS absent_count

             FROM attendance"
        );


    $attendanceStats =
        $attendanceStatement->fetch(
            PDO::FETCH_ASSOC
        );


    $totalAttendance =
        (int) (
            $attendanceStats['total']
            ?? 0
        );


    $presentAttendance =
        (int) (
            $attendanceStats['present_count']
            ?? 0
        );


    $lateAttendance =
        (int) (
            $attendanceStats['late_count']
            ?? 0
        );


    $absentAttendance =
        (int) (
            $attendanceStats['absent_count']
            ?? 0
        );


    $attendanceRate =
        $totalAttendance > 0

            ? round(
                (
                    (
                        $presentAttendance +
                        $lateAttendance
                    )
                    /
                    $totalAttendance
                )
                * 100
            )

            : 0;


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    $unreadNotifications =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM notifications
                 WHERE is_read = 0"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Recent Members
    |--------------------------------------------------------------------------
    */

    $recentMemberStatement =
        $pdo->query(
            'SELECT

                u.first_name,
                u.last_name,
                u.email,
                m.registration_date

             FROM members m

             INNER JOIN users u
                ON m.user_id = u.user_id

             ORDER BY
                m.member_id DESC

             LIMIT 5'
        );


    $recentMembers =
        $recentMemberStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Recent Payments
    |--------------------------------------------------------------------------
    */

    $recentPaymentStatement =
        $pdo->query(
            'SELECT

                p.payment_id,
                p.amount,
                p.payment_method,
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

             ORDER BY
                p.payment_id DESC

             LIMIT 5'
        );


    $recentPayments =
        $recentPaymentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Recent Notifications
    |--------------------------------------------------------------------------
    */

    $recentNotificationStatement =
        $pdo->query(
            'SELECT

                n.notification_id,
                n.title,
                n.notification_type,
                n.is_read,
                n.created_at,

                u.first_name,
                u.last_name

             FROM notifications n

             INNER JOIN users u
                ON n.user_id = u.user_id

             ORDER BY
                n.notification_id DESC

             LIMIT 5'
        );


    $recentNotifications =
        $recentNotificationStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Recent Attendance
    |--------------------------------------------------------------------------
    */

    $recentAttendanceStatement =
        $pdo->query(
            "SELECT

                a.attendance_id,
                a.attendance_date,
                a.check_in_time,
                a.status,

                COALESCE(
                    c.class_name,
                    'General Gym Visit'
                ) AS class_name,

                u.first_name,
                u.last_name

             FROM attendance a

             INNER JOIN members m
                ON a.member_id = m.member_id

             INNER JOIN users u
                ON m.user_id = u.user_id

             LEFT JOIN classes c
                ON a.class_id = c.class_id

             ORDER BY
                a.attendance_id DESC

             LIMIT 5"
        );


    $recentAttendance =
        $recentAttendanceStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $exception
) {

    error_log(
        'Admin dashboard error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the administrator dashboard.'
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
        href="../css/style.css?v=12"
    >

</head>


<body
    class="
        dashboard-page
        admin-improved-page
    "
>


<!-- ================================================================
     NAVIGATION
================================================================ -->

<header class="dashboard-navbar">

    <div
        class="
            container
            dashboard-nav-container
        "
    >

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
                        rx="2"
                    ></rect>

                    <rect
                        x="16"
                        y="20"
                        width="7"
                        height="24"
                        rx="2"
                    ></rect>

                    <rect
                        x="41"
                        y="20"
                        width="7"
                        height="24"
                        rx="2"
                    ></rect>

                    <rect
                        x="49"
                        y="24"
                        width="8"
                        height="16"
                        rx="2"
                    ></rect>

                    <rect
                        x="22"
                        y="29"
                        width="20"
                        height="6"
                        rx="2"
                    ></rect>

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
                    <?=
                        adminEscape(
                            $adminName
                        )
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


<!-- ================================================================
     MAIN
================================================================ -->

<main class="dashboard-main">

<div class="container">


    <!-- HERO -->

    <section class="dashboard-welcome">

        <div>

            <span class="eyebrow">
                ADMIN DASHBOARD
            </span>

            <h1>
                Gym overview.
            </h1>

            <p>
                Monitor memberships, classes,
                bookings, attendance, payments and
                communication across World Fitness Australia.
            </p>

        </div>


        <span
            class="
                membership-status
                status-active
            "
        >
            SYSTEM ONLINE
        </span>

    </section>


    <!-- ============================================================
         PRIMARY SUMMARY
    ============================================================ -->

    <section class="dashboard-summary">


        <article class="summary-card">

            <span class="summary-label">
                Total Members
            </span>

            <strong>
                <?= $totalMembers ?>
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
                <?= $activeMemberships ?>
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
                <?= $confirmedBookings ?>
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

                $<?=
                    number_format(
                        $totalRevenue,
                        2
                    )
                ?>

            </strong>

            <small>
                Recorded completed payments
            </small>

        </article>


    </section>


    <!-- ============================================================
         OPERATIONAL STATISTICS
    ============================================================ -->

    <section class="admin-improved-stats">


        <div>

            <span>
                Trainers
            </span>

            <strong>
                <?= $totalTrainers ?>
            </strong>

            <small>
                Registered trainers
            </small>

        </div>


        <div>

            <span>
                Upcoming Classes
            </span>

            <strong>
                <?= $upcomingClasses ?>
            </strong>

            <small>
                Future sessions
            </small>

        </div>


        <div>

            <span>
                Attendance Records
            </span>

            <strong>
                <?= $totalAttendance ?>
            </strong>

            <small>
                Recorded check-ins
            </small>

        </div>


        <div>

            <span>
                Attendance Rate
            </span>

            <strong>
                <?= $attendanceRate ?>%
            </strong>

            <small>
                Present and late
            </small>

        </div>


        <div>

            <span>
                Pending Memberships
            </span>

            <strong>
                <?= $pendingMemberships ?>
            </strong>

            <small>
                Awaiting activation
            </small>

        </div>


        <div>

            <span>
                Unread Notifications
            </span>

            <strong>
                <?= $unreadNotifications ?>
            </strong>

            <small>
                User messages unread
            </small>

        </div>


    </section>


    <!-- ============================================================
         ATTENTION REQUIRED
    ============================================================ -->

    <section
        class="
            dashboard-panel
            admin-improved-attention-panel
        "
    >

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    OPERATIONS
                </span>

                <h2>
                    Attention Required
                </h2>

            </div>

        </div>


        <div class="admin-improved-attention-grid">


            <a
                href="../membership/index.php"
                class="admin-improved-attention-card"
            >

                <span class="admin-improved-attention-icon">
                    🎫
                </span>

                <div>

                    <strong>
                        <?= $pendingMemberships ?>
                    </strong>

                    <span>
                        Pending Memberships
                    </span>

                    <small>
                        Review and activate plans
                    </small>

                </div>

            </a>


            <a
                href="../payment/index.php"
                class="admin-improved-attention-card"
            >

                <span class="admin-improved-attention-icon">
                    💳
                </span>

                <div>

                    <strong>
                        <?= $pendingPayments ?>
                    </strong>

                    <span>
                        Pending Payments
                    </span>

                    <small>
                        Review payment status
                    </small>

                </div>

            </a>


            <a
                href="../notifications/index.php"
                class="admin-improved-attention-card"
            >

                <span class="admin-improved-attention-icon">
                    🔔
                </span>

                <div>

                    <strong>
                        <?= $unreadNotifications ?>
                    </strong>

                    <span>
                        Unread Notifications
                    </span>

                    <small>
                        Review communication activity
                    </small>

                </div>

            </a>


            <a
                href="classes.php"
                class="admin-improved-attention-card"
            >

                <span class="admin-improved-attention-icon">
                    📅
                </span>

                <div>

                    <strong>
                        <?= $upcomingClasses ?>
                    </strong>

                    <span>
                        Upcoming Classes
                    </span>

                    <small>
                        Review future gym sessions
                    </small>

                </div>

            </a>


        </div>

    </section>


    <!-- ============================================================
         ADMINISTRATION AREAS
    ============================================================ -->

    <section
        class="
            dashboard-panel
            admin-management-panel
        "
    >

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


            <!-- MEMBER MANAGEMENT -->

            <div
                class="
                    admin-management-card
                    admin-improved-static-card
                "
            >

                <span class="quick-icon">
                    👥
                </span>

                <div>

                    <strong>
                        Member Management
                    </strong>

                    <small>
                        <?= $totalMembers ?>
                        registered members
                    </small>

                </div>

            </div>


            <!-- TRAINERS -->

            <div
                class="
                    admin-management-card
                    admin-improved-static-card
                "
            >

                <span class="quick-icon">
                    🏋️
                </span>

                <div>

                    <strong>
                        Trainer Management
                    </strong>

                    <small>
                        <?= $totalTrainers ?>
                        registered trainers
                    </small>

                </div>

            </div>


            <!-- CLASSES -->

            <a
                href="classes.php"
                class="
                    admin-management-card
                    admin-management-link
                "
            >

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

            </a>


            <!-- MEMBERSHIPS -->

            <a
                href="../membership/index.php"
                class="
                    admin-management-card
                    admin-management-link
                "
            >

                <span class="quick-icon">
                    🎫
                </span>

                <div>

                    <strong>
                        Membership Management
                    </strong>

                    <small>
                        Activate, renew and manage plans
                    </small>

                </div>

            </a>


            <!-- PAYMENTS -->

            <a
                href="../payment/index.php"
                class="
                    admin-management-card
                    admin-management-link
                "
            >

                <span class="quick-icon">
                    💳
                </span>

                <div>

                    <strong>
                        Payment Management
                    </strong>

                    <small>
                        Record and review member payments
                    </small>

                </div>

            </a>


            <!-- ATTENDANCE -->

            <a
                href="../attendance/index.php"
                class="
                    admin-management-card
                    admin-management-link
                "
            >

                <span class="quick-icon">
                    ✅
                </span>

                <div>

                    <strong>
                        Attendance Management
                    </strong>

                    <small>
                        Review gym and class attendance
                    </small>

                </div>

            </a>


            <!-- NOTIFICATIONS -->

            <a
                href="../notifications/index.php"
                class="
                    admin-management-card
                    admin-management-link
                "
            >

                <span class="quick-icon">
                    🔔
                </span>

                <div>

                    <strong>
                        Notifications
                    </strong>

                    <small>
                        Send and manage user communication
                    </small>

                </div>

            </a>


            <!-- REPORTING NEXT TASK -->

            <div
                class="
                    admin-management-card
                    admin-improved-coming-card
                "
            >

                <span class="quick-icon">
                    📊
                </span>

                <div>

                    <strong>
                        Reporting & Analytics
                    </strong>

                    <small>
                        Sprint 2 reporting module
                    </small>

                </div>

            </div>


        </div>

    </section>


    <!-- ============================================================
         ATTENDANCE SNAPSHOT
    ============================================================ -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    ATTENDANCE
                </span>

                <h2>
                    Attendance Snapshot
                </h2>

            </div>


            <a
                href="../attendance/index.php"
                class="panel-action-link"
            >
                View Attendance
            </a>

        </div>


        <div class="admin-improved-attendance-grid">


            <div>

                <span>
                    Present
                </span>

                <strong>
                    <?= $presentAttendance ?>
                </strong>

            </div>


            <div>

                <span>
                    Late
                </span>

                <strong>
                    <?= $lateAttendance ?>
                </strong>

            </div>


            <div>

                <span>
                    Absent
                </span>

                <strong>
                    <?= $absentAttendance ?>
                </strong>

            </div>


            <div>

                <span>
                    Attendance Rate
                </span>

                <strong>
                    <?= $attendanceRate ?>%
                </strong>

            </div>


        </div>

    </section>


    <!-- ============================================================
         RECENT MEMBERS + PAYMENTS
    ============================================================ -->

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


                    <?php foreach (
                        $recentMembers
                        as $recentMember
                    ): ?>


                        <div class="admin-list-row">


                            <div>

                                <strong>

                                    <?=
                                        adminEscape(
                                            $recentMember[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $recentMember[
                                                'last_name'
                                            ]
                                        )
                                    ?>

                                </strong>


                                <span>

                                    <?=
                                        adminEscape(
                                            $recentMember[
                                                'email'
                                            ]
                                        )
                                    ?>

                                </span>

                            </div>


                            <small>

                                <?=
                                    adminDate(
                                        $recentMember[
                                            'registration_date'
                                        ]
                                    )
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


                <a
                    href="../payment/index.php"
                    class="panel-action-link"
                >
                    View Payments
                </a>

            </div>


            <?php if ($recentPayments): ?>


                <div class="admin-list">


                    <?php foreach (
                        $recentPayments
                        as $payment
                    ): ?>


                        <div class="admin-list-row">


                            <div>

                                <strong>

                                    $<?=
                                        number_format(
                                            (float)
                                            $payment[
                                                'amount'
                                            ],
                                            2
                                        )
                                    ?>

                                </strong>


                                <span>

                                    <?=
                                        adminEscape(
                                            $payment[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $payment[
                                                'last_name'
                                            ]
                                        )
                                    ?>

                                </span>


                                <small>

                                    <?=
                                        adminEscape(
                                            ucfirst(
                                                (string)
                                                $payment[
                                                    'payment_method'
                                                ]
                                            )
                                        )
                                    ?>

                                </small>

                            </div>


                            <div
                                class="
                                    admin-improved-row-right
                                "
                            >

                                <span
                                    class="
                                        admin-improved-status
                                        admin-improved-status-<?=
                                            adminEscape(
                                                strtolower(
                                                    (string)
                                                    $payment[
                                                        'payment_status'
                                                    ]
                                                )
                                            )
                                        ?>
                                    "
                                >

                                    <?=
                                        adminEscape(
                                            strtoupper(
                                                (string)
                                                $payment[
                                                    'payment_status'
                                                ]
                                            )
                                        )
                                    ?>

                                </span>


                                <small>

                                    <?=
                                        adminDate(
                                            $payment[
                                                'payment_date'
                                            ]
                                        )
                                    ?>

                                </small>

                            </div>


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


    <!-- ============================================================
         NOTIFICATIONS + ATTENDANCE ACTIVITY
    ============================================================ -->

    <section class="dashboard-content-grid">


        <!-- NOTIFICATIONS -->

        <article class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        NOTIFICATIONS
                    </span>

                    <h2>
                        Recent Communication
                    </h2>

                </div>


                <a
                    href="../notifications/index.php"
                    class="panel-action-link"
                >
                    View Notifications
                </a>

            </div>


            <?php if ($recentNotifications): ?>


                <div class="admin-list">


                    <?php foreach (
                        $recentNotifications
                        as $notification
                    ): ?>


                        <div class="admin-list-row">


                            <div>

                                <strong>

                                    <?=
                                        adminEscape(
                                            $notification[
                                                'title'
                                            ]
                                        )
                                    ?>

                                </strong>


                                <span>

                                    To:
                                    <?=
                                        adminEscape(
                                            $notification[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $notification[
                                                'last_name'
                                            ]
                                        )
                                    ?>

                                </span>


                                <small>

                                    <?=
                                        adminEscape(
                                            ucfirst(
                                                $notification[
                                                    'notification_type'
                                                ]
                                            )
                                        )
                                    ?>

                                </small>

                            </div>


                            <div
                                class="
                                    admin-improved-row-right
                                "
                            >

                                <span
                                    class="
                                        admin-improved-status
                                        <?=
                                            (int)
                                            $notification[
                                                'is_read'
                                            ] === 1

                                                ? 'admin-improved-status-completed'

                                                : 'admin-improved-status-pending'
                                        ?>
                                    "
                                >

                                    <?=
                                        (int)
                                        $notification[
                                            'is_read'
                                        ] === 1

                                            ? 'READ'

                                            : 'UNREAD'
                                    ?>

                                </span>


                                <small>

                                    <?=
                                        adminDate(
                                            $notification[
                                                'created_at'
                                            ],
                                            true
                                        )
                                    ?>

                                </small>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <p class="empty-message">
                    No notifications have been recorded yet.
                </p>


            <?php endif; ?>


        </article>


        <!-- ATTENDANCE -->

        <article class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        ATTENDANCE
                    </span>

                    <h2>
                        Recent Check-ins
                    </h2>

                </div>


                <a
                    href="../attendance/index.php"
                    class="panel-action-link"
                >
                    View Attendance
                </a>

            </div>


            <?php if ($recentAttendance): ?>


                <div class="admin-list">


                    <?php foreach (
                        $recentAttendance
                        as $attendance
                    ): ?>


                        <div class="admin-list-row">


                            <div>

                                <strong>

                                    <?=
                                        adminEscape(
                                            $attendance[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $attendance[
                                                'last_name'
                                            ]
                                        )
                                    ?>

                                </strong>


                                <span>

                                    <?=
                                        adminEscape(
                                            $attendance[
                                                'class_name'
                                            ]
                                        )
                                    ?>

                                </span>


                                <small>

                                    <?=
                                        adminDate(
                                            $attendance[
                                                'attendance_date'
                                            ]
                                        )
                                    ?>

                                </small>

                            </div>


                            <span
                                class="
                                    admin-improved-status
                                    admin-improved-attendance-<?=
                                        adminEscape(
                                            strtolower(
                                                $attendance[
                                                    'status'
                                                ]
                                            )
                                        )
                                    ?>
                                "
                            >

                                <?=
                                    adminEscape(
                                        strtoupper(
                                            $attendance[
                                                'status'
                                            ]
                                        )
                                    )
                                ?>

                            </span>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <p class="empty-message">
                    No attendance records yet.
                </p>


            <?php endif; ?>


        </article>


    </section>


</div>

</main>


<!-- ================================================================
     FOOTER
================================================================ -->

<footer>

    <div
        class="
            container
            footer-content
        "
    >

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