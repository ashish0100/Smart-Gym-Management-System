<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-20 - Reporting & Analytics
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
        'Please log in to access reports.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Administrator Only
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

function reportEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function reportDate(?string $value): string
{
    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return reportEscape($value);
    }

    return date(
        'd M Y',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| Report Data
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Core Totals
    |--------------------------------------------------------------------------
    */

    $totalMembers =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM members'
            )
            ->fetchColumn();


    $totalTrainers =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM trainers'
            )
            ->fetchColumn();


    $totalClasses =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM classes'
            )
            ->fetchColumn();


    $totalBookings =
        (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM bookings'
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Membership Report
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


    $pausedMemberships =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM memberships
                 WHERE status = 'paused'"
            )
            ->fetchColumn();


    $expiredMemberships =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM memberships
                 WHERE status = 'expired'"
            )
            ->fetchColumn();


    $cancelledMemberships =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM memberships
                 WHERE status = 'cancelled'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Payment Report
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


    $completedPayments =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM payments
                 WHERE payment_status = 'completed'"
            )
            ->fetchColumn();


    $pendingPayments =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM payments
                 WHERE payment_status = 'pending'"
            )
            ->fetchColumn();


    $failedPayments =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM payments
                 WHERE payment_status = 'failed'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Booking Report
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


    $cancelledBookings =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE status = 'cancelled'"
            )
            ->fetchColumn();


    $completedBookings =
        (int) $pdo
            ->query(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE status = 'completed'"
            )
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Attendance Report
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


    $attendanceTotal =
        (int) (
            $attendanceStats['total']
            ?? 0
        );


    $presentCount =
        (int) (
            $attendanceStats['present_count']
            ?? 0
        );


    $lateCount =
        (int) (
            $attendanceStats['late_count']
            ?? 0
        );


    $absentCount =
        (int) (
            $attendanceStats['absent_count']
            ?? 0
        );


    $attendanceRate =
        $attendanceTotal > 0
            ? round(
                (
                    (
                        $presentCount +
                        $lateCount
                    )
                    /
                    $attendanceTotal
                )
                * 100
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | Upcoming Classes
    |--------------------------------------------------------------------------
    */

    $upcomingClassStatement =
        $pdo->query(
            "SELECT

                c.class_name,
                c.class_date,
                c.start_time,
                c.end_time,
                c.location,
                c.capacity,
                c.status,

                COUNT(
                    CASE
                        WHEN b.status = 'confirmed'
                        THEN 1
                    END
                ) AS booked

             FROM classes c

             LEFT JOIN bookings b
                ON c.class_id = b.class_id

             WHERE c.class_date >= CURDATE()

             GROUP BY

                c.class_id,
                c.class_name,
                c.class_date,
                c.start_time,
                c.end_time,
                c.location,
                c.capacity,
                c.status

             ORDER BY

                c.class_date ASC,
                c.start_time ASC

             LIMIT 10"
        );


    $upcomingClassReport =
        $upcomingClassStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Recent Payments
    |--------------------------------------------------------------------------
    */

    $paymentReportStatement =
        $pdo->query(
            'SELECT

                p.amount,
                p.payment_method,
                p.payment_status,
                p.payment_date,

                u.first_name,
                u.last_name

             FROM payments p

             INNER JOIN members m
                ON p.member_id = m.member_id

             INNER JOIN users u
                ON m.user_id = u.user_id

             ORDER BY
                p.payment_id DESC

             LIMIT 10'
        );


    $paymentReport =
        $paymentReportStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Recent Memberships
    |--------------------------------------------------------------------------
    */

    $membershipReportStatement =
        $pdo->query(
            'SELECT

                ms.membership_id,
                ms.membership_type,
                ms.access_type,
                ms.start_date,
                ms.end_date,
                ms.status,

                u.first_name,
                u.last_name

             FROM memberships ms

             INNER JOIN members m
                ON ms.member_id = m.member_id

             INNER JOIN users u
                ON m.user_id = u.user_id

             ORDER BY
                ms.membership_id DESC

             LIMIT 10'
        );


    $membershipReport =
        $membershipReportStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $exception
) {

    error_log(
        'Reporting error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load reporting and analytics.'
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
        Reports & Analytics | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css?v=13"
    >

</head>


<body
    class="
        dashboard-page
        reporting-page
    "
>


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

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Reporting & Analytics
                </small>

            </div>

        </a>


        <div class="dashboard-user">

            <div class="user-text">

                <span>
                    Administrator
                </span>

                <strong>
                    <?= reportEscape($adminName) ?>
                </strong>

            </div>


            <a
                href="dashboard.php"
                class="logout-button"
            >
                Dashboard
            </a>


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


    <section class="dashboard-welcome">

        <div>

            <span class="eyebrow">
                REPORTING & ANALYTICS
            </span>

            <h1>
                Gym performance.
            </h1>

            <p>
                Review members, memberships, bookings,
                payments, attendance and class performance.
            </p>

        </div>


        <button
            type="button"
            class="report-print-button"
            onclick="window.print();"
        >
            Print Report
        </button>

    </section>


    <!-- MAIN SUMMARY -->

    <section class="dashboard-summary">


        <article class="summary-card">

            <span class="summary-label">
                Members
            </span>

            <strong>
                <?= $totalMembers ?>
            </strong>

            <small>
                Registered members
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Trainers
            </span>

            <strong>
                <?= $totalTrainers ?>
            </strong>

            <small>
                Registered trainers
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Classes
            </span>

            <strong>
                <?= $totalClasses ?>
            </strong>

            <small>
                Created gym sessions
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Revenue
            </span>

            <strong>
                $<?= number_format($totalRevenue, 2) ?>
            </strong>

            <small>
                Completed payments
            </small>

        </article>


    </section>


    <!-- MEMBERSHIP ANALYTICS -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    MEMBERSHIPS
                </span>

                <h2>
                    Membership Analytics
                </h2>

            </div>

        </div>


        <div class="report-metric-grid">

            <div>
                <span>Active</span>
                <strong><?= $activeMemberships ?></strong>
            </div>

            <div>
                <span>Pending</span>
                <strong><?= $pendingMemberships ?></strong>
            </div>

            <div>
                <span>Paused</span>
                <strong><?= $pausedMemberships ?></strong>
            </div>

            <div>
                <span>Expired</span>
                <strong><?= $expiredMemberships ?></strong>
            </div>

            <div>
                <span>Cancelled</span>
                <strong><?= $cancelledMemberships ?></strong>
            </div>

        </div>

    </section>


    <!-- PAYMENT + BOOKING -->

    <section class="dashboard-content-grid">


        <article class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        PAYMENTS
                    </span>

                    <h2>
                        Payment Performance
                    </h2>

                </div>

            </div>


            <div class="report-vertical-stats">

                <div>

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?= $completedPayments ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?= $pendingPayments ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Failed
                    </span>

                    <strong>
                        <?= $failedPayments ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Revenue
                    </span>

                    <strong>
                        $<?= number_format($totalRevenue, 2) ?>
                    </strong>

                </div>

            </div>

        </article>


        <article class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        BOOKINGS
                    </span>

                    <h2>
                        Booking Performance
                    </h2>

                </div>

            </div>


            <div class="report-vertical-stats">

                <div>

                    <span>
                        Total
                    </span>

                    <strong>
                        <?= $totalBookings ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Confirmed
                    </span>

                    <strong>
                        <?= $confirmedBookings ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?= $completedBookings ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Cancelled
                    </span>

                    <strong>
                        <?= $cancelledBookings ?>
                    </strong>

                </div>

            </div>

        </article>


    </section>


    <!-- ATTENDANCE -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    ATTENDANCE
                </span>

                <h2>
                    Attendance Performance
                </h2>

            </div>

        </div>


        <div class="report-attendance-grid">


            <div>

                <span>
                    Total Records
                </span>

                <strong>
                    <?= $attendanceTotal ?>
                </strong>

            </div>


            <div>

                <span>
                    Present
                </span>

                <strong>
                    <?= $presentCount ?>
                </strong>

            </div>


            <div>

                <span>
                    Late
                </span>

                <strong>
                    <?= $lateCount ?>
                </strong>

            </div>


            <div>

                <span>
                    Absent
                </span>

                <strong>
                    <?= $absentCount ?>
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


    <!-- UPCOMING CLASSES -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    CLASSES
                </span>

                <h2>
                    Upcoming Class Report
                </h2>

            </div>

        </div>


        <?php if ($upcomingClassReport): ?>


            <div class="report-table-wrap">

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>Class</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Bookings</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $upcomingClassReport
                        as $class
                    ): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= reportEscape($class['class_name']) ?>
                                </strong>

                                <small>

                                    <?= reportEscape($class['start_time']) ?>

                                    -

                                    <?= reportEscape($class['end_time']) ?>

                                </small>

                            </td>


                            <td>
                                <?= reportDate($class['class_date']) ?>
                            </td>


                            <td>
                                <?= reportEscape($class['location']) ?>
                            </td>


                            <td>

                                <?= (int) $class['booked'] ?>

                                /

                                <?= (int) $class['capacity'] ?>

                            </td>


                            <td>

                                <span class="report-status">

                                    <?= reportEscape(
                                        strtoupper(
                                            $class['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <p class="empty-message">
                No upcoming classes available.
            </p>


        <?php endif; ?>

    </section>


    <!-- MEMBERSHIP HISTORY -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    MEMBERSHIP REPORT
                </span>

                <h2>
                    Recent Memberships
                </h2>

            </div>

        </div>


        <?php if ($membershipReport): ?>


            <div class="report-table-wrap">

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>Member</th>
                            <th>Type</th>
                            <th>Access</th>
                            <th>Period</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $membershipReport
                        as $membership
                    ): ?>

                        <tr>

                            <td>

                                <?=
                                    reportEscape(
                                        $membership['first_name']
                                        . ' '
                                        . $membership['last_name']
                                    )
                                ?>

                            </td>


                            <td>

                                <?= reportEscape(
                                    ucfirst(
                                        $membership['membership_type']
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= reportEscape(
                                    ucfirst(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $membership['access_type']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= reportDate(
                                    $membership['start_date']
                                ) ?>

                                <br>

                                <small>

                                    to

                                    <?= reportDate(
                                        $membership['end_date']
                                    ) ?>

                                </small>

                            </td>


                            <td>

                                <span class="report-status">

                                    <?= reportEscape(
                                        strtoupper(
                                            $membership['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <p class="empty-message">
                No membership information available.
            </p>

        <?php endif; ?>

    </section>


    <!-- PAYMENT HISTORY -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    PAYMENT REPORT
                </span>

                <h2>
                    Recent Payment Transactions
                </h2>

            </div>

        </div>


        <?php if ($paymentReport): ?>


            <div class="report-table-wrap">

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>Member</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $paymentReport
                        as $payment
                    ): ?>

                        <tr>

                            <td>

                                <?=
                                    reportEscape(
                                        $payment['first_name']
                                        . ' '
                                        . $payment['last_name']
                                    )
                                ?>

                            </td>


                            <td>

                                <strong>

                                    $<?= number_format(
                                        (float)
                                        $payment['amount'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= reportEscape(
                                    ucfirst(
                                        $payment['payment_method']
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= reportDate(
                                    $payment['payment_date']
                                ) ?>

                            </td>


                            <td>

                                <span class="report-status">

                                    <?= reportEscape(
                                        strtoupper(
                                            $payment['payment_status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <p class="empty-message">
                No payment transactions available.
            </p>

        <?php endif; ?>

    </section>


</div>

</main>


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
            Reporting & Analytics
        </p>

    </div>

</footer>


</body>

</html>