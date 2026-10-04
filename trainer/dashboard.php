<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-18 - Improved Trainer Dashboard
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
        'Please log in to access the trainer portal.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Trainer Role Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'trainer'
) {

    http_response_code(403);

    exit(
        'Access denied. Trainer access is required.'
    );
}


$userId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function trainerEscape(
    ?string $value
): string {

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


function trainerFormat(
    ?string $value
): string {

    if (
        $value === null ||
        trim($value) === ''
    ) {
        return 'Not available';
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            $value
        )
    );
}


function trainerDate(
    ?string $value
): string {

    if (!$value) {
        return 'Not available';
    }

    $timestamp =
        strtotime($value);

    if (
        $timestamp === false
    ) {
        return trainerEscape(
            $value
        );
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function trainerTime(
    ?string $value
): string {

    if (!$value) {
        return 'Not available';
    }

    $timestamp =
        strtotime($value);

    if (
        $timestamp === false
    ) {
        return trainerEscape(
            $value
        );
    }

    return date(
        'g:i A',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| Load Trainer
|--------------------------------------------------------------------------
*/

try {

    $trainerStatement =
        $pdo->prepare(
            'SELECT

                u.user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.status AS account_status,

                t.trainer_id,
                t.phone,
                t.specialisation,
                t.qualification,
                t.availability,
                t.employment_status

             FROM users u

             INNER JOIN trainers t
                ON u.user_id =
                    t.user_id

             WHERE u.user_id =
                :user_id

             LIMIT 1'
        );


    $trainerStatement->execute(
        [
            'user_id' =>
                $userId
        ]
    );


    $trainer =
        $trainerStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$trainer) {

        http_response_code(404);

        exit(
            'Trainer profile could not be found.'
        );
    }


    $trainerId =
        (int)
        $trainer[
            'trainer_id'
        ];


    /*
    |--------------------------------------------------------------------------
    | Total Assigned Classes
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            'SELECT COUNT(*)

             FROM classes

             WHERE trainer_id =
                :trainer_id'
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $totalClasses =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Today's Classes
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            "SELECT COUNT(*)

             FROM classes

             WHERE trainer_id =
                :trainer_id

             AND class_date =
                CURDATE()

             AND status IN (
                'available',
                'full'
             )"
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $todayClasses =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Upcoming Classes
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            "SELECT COUNT(*)

             FROM classes

             WHERE trainer_id =
                :trainer_id

             AND class_date >=
                CURDATE()

             AND status IN (
                'available',
                'full'
             )"
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $upcomingClasses =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Confirmed Bookings
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            "SELECT COUNT(*)

             FROM bookings b

             INNER JOIN classes c
                ON b.class_id =
                    c.class_id

             WHERE c.trainer_id =
                :trainer_id

             AND b.status =
                'confirmed'"
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $confirmedBookings =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Unique Members
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            "SELECT
                COUNT(
                    DISTINCT b.member_id
                )

             FROM bookings b

             INNER JOIN classes c
                ON b.class_id =
                    c.class_id

             WHERE c.trainer_id =
                :trainer_id

             AND b.status =
                'confirmed'"
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $uniqueMembers =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Attendance Statistics
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            "SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN a.status =
                            'present'
                        THEN 1
                        ELSE 0
                    END
                ) AS present_count,

                SUM(
                    CASE
                        WHEN a.status =
                            'late'
                        THEN 1
                        ELSE 0
                    END
                ) AS late_count,

                SUM(
                    CASE
                        WHEN a.status =
                            'absent'
                        THEN 1
                        ELSE 0
                    END
                ) AS absent_count

             FROM attendance a

             INNER JOIN classes c
                ON a.class_id =
                    c.class_id

             WHERE c.trainer_id =
                :trainer_id"
        );


    $statement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $attendanceStats =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );


    $attendanceTotal =
        (int) (
            $attendanceStats[
                'total'
            ] ?? 0
        );


    $presentCount =
        (int) (
            $attendanceStats[
                'present_count'
            ] ?? 0
        );


    $lateCount =
        (int) (
            $attendanceStats[
                'late_count'
            ] ?? 0
        );


    $absentCount =
        (int) (
            $attendanceStats[
                'absent_count'
            ] ?? 0
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
    | Unread Notifications
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            'SELECT COUNT(*)

             FROM notifications

             WHERE user_id =
                :user_id

             AND is_read = 0'
        );


    $statement->execute(
        [
            'user_id' =>
                $userId
        ]
    );


    $unreadNotifications =
        (int)
        $statement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Upcoming Schedule
    |--------------------------------------------------------------------------
    */

    $upcomingStatement =
        $pdo->prepare(
            "SELECT

                c.class_id,
                c.class_name,
                c.description,
                c.class_date,
                c.start_time,
                c.end_time,
                c.capacity,
                c.location,
                c.status,

                COUNT(
                    CASE
                        WHEN b.status =
                            'confirmed'
                        THEN 1
                    END
                ) AS booked_members

             FROM classes c

             LEFT JOIN bookings b
                ON c.class_id =
                    b.class_id

             WHERE c.trainer_id =
                :trainer_id

             AND c.class_date >=
                CURDATE()

             AND c.status IN (
                'available',
                'full'
             )

             GROUP BY

                c.class_id,
                c.class_name,
                c.description,
                c.class_date,
                c.start_time,
                c.end_time,
                c.capacity,
                c.location,
                c.status

             ORDER BY

                c.class_date ASC,
                c.start_time ASC

             LIMIT 6"
        );


    $upcomingStatement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $classSchedule =
        $upcomingStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    $nextClass =
        $classSchedule[0] ?? null;


    /*
    |--------------------------------------------------------------------------
    | Recent Attendance
    |--------------------------------------------------------------------------
    */

    $attendanceHistoryStatement =
        $pdo->prepare(
            'SELECT

                a.attendance_id,
                a.attendance_date,
                a.check_in_time,
                a.status,

                c.class_name,

                u.first_name,
                u.last_name

             FROM attendance a

             INNER JOIN classes c
                ON a.class_id =
                    c.class_id

             INNER JOIN members m
                ON a.member_id =
                    m.member_id

             INNER JOIN users u
                ON m.user_id =
                    u.user_id

             WHERE c.trainer_id =
                :trainer_id

             ORDER BY

                a.attendance_date DESC,
                a.attendance_id DESC

             LIMIT 6'
        );


    $attendanceHistoryStatement->execute(
        [
            'trainer_id' =>
                $trainerId
        ]
    );


    $recentAttendance =
        $attendanceHistoryStatement
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


} catch (
    PDOException $exception
) {

    error_log(
        'Trainer dashboard error: ' .
        $exception->getMessage()
    );


    http_response_code(500);


    exit(
        'Unable to load the trainer dashboard. Please try again later.'
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
        Trainer Dashboard | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css?v=11"
    >

</head>


<body
    class="
        dashboard-page
        trainer-dashboard-improved
    "
>


<!--
==========================================================================
NAVIGATION
==========================================================================
-->

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
                    Trainer Portal
                </small>

            </div>

        </a>


        <div class="dashboard-user">


            <div class="user-text">

                <span>
                    Trainer
                </span>

                <strong>
                    <?=
                        trainerEscape(
                            $trainer[
                                'first_name'
                            ]
                        )
                    ?>
                </strong>

            </div>


            <div class="user-avatar">

                <?=
                    strtoupper(
                        substr(
                            (string)
                            $trainer[
                                'first_name'
                            ],
                            0,
                            1
                        )
                    )
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


<!--
==========================================================================
MAIN
==========================================================================
-->

<main class="dashboard-main">

<div class="container">


    <!-- HERO -->

    <section class="dashboard-welcome">

        <div>

            <span class="eyebrow">
                TRAINER DASHBOARD
            </span>

            <h1>

                Welcome,
                <?=
                    trainerEscape(
                        $trainer[
                            'first_name'
                        ]
                    )
                ?>.

            </h1>


            <p>
                Monitor your schedule, members,
                bookings, attendance and notifications
                from one trainer workspace.
            </p>

        </div>


        <span
            class="
                membership-status
                status-active
            "
        >

            <?=
                strtoupper(
                    trainerFormat(
                        $trainer[
                            'employment_status'
                        ]
                    )
                )
            ?>

        </span>

    </section>


    <!-- MAIN STATISTICS -->

    <section class="dashboard-summary">


        <article class="summary-card">

            <span class="summary-label">
                Today's Classes
            </span>

            <strong>
                <?= $todayClasses ?>
            </strong>

            <small>
                Sessions scheduled today
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Upcoming Classes
            </span>

            <strong>
                <?= $upcomingClasses ?>
            </strong>

            <small>
                Future assigned sessions
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
                Members booked into your classes
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Attendance Rate
            </span>

            <strong>
                <?= $attendanceRate ?>%
            </strong>

            <small>
                Present and late attendance
            </small>

        </article>


    </section>


    <!-- SECONDARY STATS -->

    <section class="trainer-improved-mini-grid">


        <div>

            <span>
                Assigned Classes
            </span>

            <strong>
                <?= $totalClasses ?>
            </strong>

        </div>


        <div>

            <span>
                Unique Members
            </span>

            <strong>
                <?= $uniqueMembers ?>
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
                Unread Notifications
            </span>

            <strong>
                <?= $unreadNotifications ?>
            </strong>

        </div>


    </section>


    <!-- NEXT CLASS + PROFILE -->

    <section class="dashboard-content-grid">


        <!-- NEXT CLASS -->

        <article
            class="
                dashboard-panel
                trainer-next-class-panel
            "
        >

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        NEXT SESSION
                    </span>

                    <h2>
                        Next Class
                    </h2>

                </div>

            </div>


            <?php if ($nextClass): ?>


                <div class="trainer-next-class">


                    <span
                        class="
                            trainer-class-status
                        "
                    >

                        <?=
                            strtoupper(
                                trainerEscape(
                                    $nextClass[
                                        'status'
                                    ]
                                )
                            )
                        ?>

                    </span>


                    <h3>

                        <?=
                            trainerEscape(
                                $nextClass[
                                    'class_name'
                                ]
                            )
                        ?>

                    </h3>


                    <p>

                        <?=
                            trainerDate(
                                $nextClass[
                                    'class_date'
                                ]
                            )
                        ?>

                        ·

                        <?=
                            trainerTime(
                                $nextClass[
                                    'start_time'
                                ]
                            )
                        ?>

                        -

                        <?=
                            trainerTime(
                                $nextClass[
                                    'end_time'
                                ]
                            )
                        ?>

                    </p>


                    <p>

                        <?=
                            trainerEscape(
                                $nextClass[
                                    'location'
                                ]
                            )
                        ?>

                    </p>


                    <div
                        class="
                            trainer-next-class-booking
                        "
                    >

                        <strong>

                            <?=
                                (int)
                                $nextClass[
                                    'booked_members'
                                ]
                            ?>

                            /

                            <?=
                                (int)
                                $nextClass[
                                    'capacity'
                                ]
                            ?>

                        </strong>

                        <span>
                            members booked
                        </span>

                    </div>


                    <a
                        href="../attendance/index.php?class_id=<?=
                            (int)
                            $nextClass[
                                'class_id'
                            ]
                        ?>"
                        class="
                            trainer-dashboard-action
                        "
                    >
                        Manage Attendance
                    </a>


                </div>


            <?php else: ?>


                <div
                    class="
                        trainer-empty-schedule
                    "
                >

                    <span>
                        📅
                    </span>

                    <strong>
                        No upcoming classes
                    </strong>

                    <p>
                        Your next assigned class will
                        appear here.
                    </p>

                </div>


            <?php endif; ?>


        </article>


        <!-- TRAINER PROFILE -->

        <article class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        PROFILE
                    </span>

                    <h2>
                        Trainer Information
                    </h2>

                </div>

            </div>


            <div class="detail-list">


                <div>

                    <span>
                        Full Name
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'first_name'
                                ] .
                                ' ' .
                                $trainer[
                                    'last_name'
                                ]
                            )
                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Email
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'email'
                                ]
                            )
                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Phone
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'phone'
                                ]
                            )
                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Specialisation
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'specialisation'
                                ]
                            )
                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Qualification
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'qualification'
                                ]
                            )
                        ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Availability
                    </span>

                    <strong>

                        <?=
                            trainerEscape(
                                $trainer[
                                    'availability'
                                ]
                            )
                        ?>

                    </strong>

                </div>


            </div>

        </article>


    </section>


    <!-- TRAINER TOOLS -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    QUICK ACCESS
                </span>

                <h2>
                    Trainer Tools
                </h2>

            </div>

        </div>


        <div class="trainer-improved-actions">


            <a
                href="#schedule"
                class="quick-action"
            >

                <span class="quick-icon">
                    📅
                </span>

                <div>

                    <strong>
                        My Schedule
                    </strong>

                    <small>
                        View your assigned classes
                    </small>

                </div>

            </a>


            <a
                href="../attendance/index.php"
                class="quick-action"
            >

                <span class="quick-icon">
                    ✅
                </span>

                <div>

                    <strong>
                        Attendance
                    </strong>

                    <small>
                        Record member attendance
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

                        <?= $unreadNotifications ?>

                        unread
                        <?=
                            $unreadNotifications === 1
                                ? 'message'
                                : 'messages'
                        ?>

                    </small>

                </div>

            </a>


        </div>

    </section>


    <!-- UPCOMING CLASSES -->

    <section
        class="
            dashboard-panel
            trainer-schedule-panel
        "
        id="schedule"
    >


        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    SCHEDULE
                </span>

                <h2>
                    Upcoming Classes
                </h2>

            </div>

        </div>


        <?php if ($classSchedule): ?>


            <div class="trainer-class-list">


                <?php foreach (
                    $classSchedule
                    as $class
                ): ?>


                    <div class="trainer-class-row">


                        <div
                            class="
                                trainer-class-date
                            "
                        >

                            <strong>

                                <?=
                                    date(
                                        'd',
                                        strtotime(
                                            $class[
                                                'class_date'
                                            ]
                                        )
                                    )
                                ?>

                            </strong>


                            <span>

                                <?=
                                    strtoupper(
                                        date(
                                            'M',
                                            strtotime(
                                                $class[
                                                    'class_date'
                                                ]
                                            )
                                        )
                                    )
                                ?>

                            </span>

                        </div>


                        <div
                            class="
                                trainer-class-info
                            "
                        >

                            <strong>

                                <?=
                                    trainerEscape(
                                        $class[
                                            'class_name'
                                        ]
                                    )
                                ?>

                            </strong>


                            <span>

                                <?=
                                    trainerTime(
                                        $class[
                                            'start_time'
                                        ]
                                    )
                                ?>

                                -

                                <?=
                                    trainerTime(
                                        $class[
                                            'end_time'
                                        ]
                                    )
                                ?>

                                ·

                                <?=
                                    trainerEscape(
                                        $class[
                                            'location'
                                        ]
                                    )
                                ?>

                            </span>

                        </div>


                        <div
                            class="
                                trainer-class-bookings
                            "
                        >

                            <strong>

                                <?=
                                    (int)
                                    $class[
                                        'booked_members'
                                    ]
                                ?>

                                /

                                <?=
                                    (int)
                                    $class[
                                        'capacity'
                                    ]
                                ?>

                            </strong>

                            <span>
                                Booked
                            </span>

                        </div>


                        <span
                            class="
                                trainer-class-status
                            "
                        >

                            <?=
                                strtoupper(
                                    trainerEscape(
                                        $class[
                                            'status'
                                        ]
                                    )
                                )
                            ?>

                        </span>


                        <a
                            href="../attendance/index.php?class_id=<?=
                                (int)
                                $class[
                                    'class_id'
                                ]
                            ?>"
                            class="
                                trainer-row-action
                            "
                        >
                            Attendance
                        </a>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div
                class="
                    trainer-empty-schedule
                "
            >

                <span>
                    📅
                </span>

                <strong>
                    No upcoming classes
                </strong>

                <p>
                    Classes assigned to this trainer
                    will appear here.
                </p>

            </div>


        <?php endif; ?>


    </section>


    <!-- RECENT ATTENDANCE -->

    <section class="dashboard-panel">


        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    ATTENDANCE
                </span>

                <h2>
                    Recent Attendance
                </h2>

            </div>


            <a
                href="../attendance/index.php"
                class="panel-action-link"
            >
                Manage Attendance
            </a>

        </div>


        <?php if ($recentAttendance): ?>


            <div
                class="
                    trainer-attendance-list
                "
            >


                <?php foreach (
                    $recentAttendance
                    as $attendance
                ): ?>


                    <div
                        class="
                            trainer-attendance-row
                        "
                    >


                        <div>

                            <strong>

                                <?=
                                    trainerEscape(
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
                                    trainerEscape(
                                        $attendance[
                                            'class_name'
                                        ]
                                    )
                                ?>

                            </span>

                        </div>


                        <div>

                            <span>

                                <?=
                                    trainerDate(
                                        $attendance[
                                            'attendance_date'
                                        ]
                                    )
                                ?>

                            </span>


                            <?php if (
                                !empty(
                                    $attendance[
                                        'check_in_time'
                                    ]
                                )
                            ): ?>

                                <small>

                                    <?=
                                        trainerTime(
                                            $attendance[
                                                'check_in_time'
                                            ]
                                        )
                                    ?>

                                </small>

                            <?php endif; ?>

                        </div>


                        <span
                            class="
                                trainer-attendance-status
                                trainer-attendance-<?=
                                    trainerEscape(
                                        $attendance[
                                            'status'
                                        ]
                                    )
                                ?>
                            "
                        >

                            <?=
                                strtoupper(
                                    trainerEscape(
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


            <div
                class="
                    trainer-empty-schedule
                "
            >

                <span>
                    ✅
                </span>

                <strong>
                    No attendance records yet
                </strong>

                <p>
                    Recorded class attendance will
                    appear here.
                </p>

            </div>


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
            Trainer Portal
        </p>

    </div>

</footer>


</body>

</html>