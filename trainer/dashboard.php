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

    exit('Access denied. Trainer access is required.');
}


$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Load Trainer Information
|--------------------------------------------------------------------------
*/

try {

    $trainerStatement = $pdo->prepare(
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
            ON u.user_id = t.user_id

         WHERE u.user_id = :user_id

         LIMIT 1'
    );

    $trainerStatement->execute([
        'user_id' => $userId
    ]);

    $trainer = $trainerStatement->fetch();


    if (!$trainer) {

        session_unset();
        session_destroy();

        $_SESSION['login_error'] =
            'Trainer profile could not be found.';

        header('Location: ../auth/login.php');
        exit;
    }


    $trainerId = (int) $trainer['trainer_id'];


    /*
    |--------------------------------------------------------------------------
    | Total Assigned Classes
    |--------------------------------------------------------------------------
    */

    $classCountStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM classes
         WHERE trainer_id = :trainer_id'
    );

    $classCountStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $totalClasses =
        (int) $classCountStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Upcoming Classes
    |--------------------------------------------------------------------------
    */

    $upcomingCountStatement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM classes
         WHERE trainer_id = :trainer_id
         AND class_date >= CURDATE()
         AND status IN ('available', 'full')"
    );

    $upcomingCountStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $upcomingClasses =
        (int) $upcomingCountStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Confirmed Bookings For Trainer Classes
    |--------------------------------------------------------------------------
    */

    $bookingCountStatement = $pdo->prepare(
        "SELECT COUNT(*)

         FROM bookings b

         INNER JOIN classes c
            ON b.class_id = c.class_id

         WHERE c.trainer_id = :trainer_id
         AND b.status = 'confirmed'"
    );

    $bookingCountStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $confirmedBookings =
        (int) $bookingCountStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Attendance Recorded For Trainer Classes
    |--------------------------------------------------------------------------
    */

    $attendanceStatement = $pdo->prepare(
        "SELECT COUNT(*)

         FROM attendance a

         INNER JOIN classes c
            ON a.class_id = c.class_id

         WHERE c.trainer_id = :trainer_id
         AND a.status = 'present'"
    );

    $attendanceStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $attendanceCount =
        (int) $attendanceStatement->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Upcoming Class List
    |--------------------------------------------------------------------------
    */

    $upcomingStatement = $pdo->prepare(
        "SELECT
            c.class_id,
            c.class_name,
            c.class_date,
            c.start_time,
            c.end_time,
            c.capacity,
            c.location,
            c.status,

            COUNT(
                CASE
                    WHEN b.status = 'confirmed'
                    THEN 1
                END
            ) AS booked_members

         FROM classes c

         LEFT JOIN bookings b
            ON c.class_id = b.class_id

         WHERE c.trainer_id = :trainer_id
         AND c.class_date >= CURDATE()
         AND c.status IN ('available', 'full')

         GROUP BY
            c.class_id,
            c.class_name,
            c.class_date,
            c.start_time,
            c.end_time,
            c.capacity,
            c.location,
            c.status

         ORDER BY
            c.class_date ASC,
            c.start_time ASC

         LIMIT 5"
    );

    $upcomingStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $classSchedule =
        $upcomingStatement->fetchAll();


} catch (PDOException $exception) {

    error_log(
        'Trainer dashboard error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the trainer dashboard. Please try again later.'
    );
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function trainerEscape(?string $value): string
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


function trainerFormat(?string $value): string
{
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
        href="../css/style.css"
    >

</head>


<body class="dashboard-page">


<!-- =====================================================
     NAVIGATION
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
                    <?php
                    echo trainerEscape(
                        $trainer['first_name']
                    );
                    ?>
                </strong>

            </div>


            <div class="user-avatar">

                <?php
                echo strtoupper(
                    substr(
                        $trainer['first_name'],
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
     MAIN DASHBOARD
===================================================== -->

<main class="dashboard-main">

    <div class="container">


        <!-- WELCOME -->

        <section class="dashboard-welcome">

            <div>

                <span class="eyebrow">
                    TRAINER DASHBOARD
                </span>

                <h1>
                    Welcome,
                    <?php
                    echo trainerEscape(
                        $trainer['first_name']
                    );
                    ?>.
                </h1>

                <p>
                    View your class schedule,
                    member bookings and attendance
                    from your trainer portal.
                </p>

            </div>


            <span
                class="membership-status status-active"
            >
                <?php
                echo strtoupper(
                    trainerFormat(
                        $trainer[
                            'employment_status'
                        ]
                    )
                );
                ?>
            </span>

        </section>


        <!-- SUMMARY CARDS -->

        <section class="dashboard-summary">

            <article class="summary-card">

                <span class="summary-label">
                    Assigned Classes
                </span>

                <strong>
                    <?php echo $totalClasses; ?>
                </strong>

                <small>
                    Total classes assigned
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Upcoming Classes
                </span>

                <strong>
                    <?php echo $upcomingClasses; ?>
                </strong>

                <small>
                    Scheduled upcoming sessions
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Member Bookings
                </span>

                <strong>
                    <?php echo $confirmedBookings; ?>
                </strong>

                <small>
                    Confirmed class bookings
                </small>

            </article>


            <article class="summary-card">

                <span class="summary-label">
                    Attendance
                </span>

                <strong>
                    <?php echo $attendanceCount; ?>
                </strong>

                <small>
                    Recorded member attendance
                </small>

            </article>

        </section>


        <!-- MAIN GRID -->

        <section class="dashboard-content-grid">


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
                            <?php
                            echo trainerEscape(
                                $trainer['first_name']
                                . ' '
                                . $trainer['last_name']
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
                            echo trainerEscape(
                                $trainer['email']
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
                            echo trainerEscape(
                                $trainer['phone']
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Specialisation
                        </span>

                        <strong>
                            <?php
                            echo trainerEscape(
                                $trainer[
                                    'specialisation'
                                ]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Qualification
                        </span>

                        <strong>
                            <?php
                            echo trainerEscape(
                                $trainer[
                                    'qualification'
                                ]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Availability
                        </span>

                        <strong>
                            <?php
                            echo trainerEscape(
                                $trainer[
                                    'availability'
                                ]
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            </article>


            <!-- QUICK ACTIONS -->

            <article class="dashboard-panel">

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


                <div class="quick-actions">

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
                                View assigned gym classes
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
                                Record Attendance
                            </strong>

                            <small>
                                Manage class attendance
                            </small>

                        </div>

                    </a>


                    <a
                        href="../booking/index.php"
                        class="quick-action"
                    >

                        <span class="quick-icon">
                            👥
                        </span>

                        <div>

                            <strong>
                                Member Bookings
                            </strong>

                            <small>
                                View booked members
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
                                View trainer messages
                            </small>

                        </div>

                    </a>

                </div>

            </article>

        </section>


        <!-- UPCOMING CLASSES -->

        <section
            class="dashboard-panel trainer-schedule-panel"
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

                    <?php foreach ($classSchedule as $class): ?>

                        <div class="trainer-class-row">

                            <div class="trainer-class-date">

                                <strong>
                                    <?php
                                    echo date(
                                        'd',
                                        strtotime(
                                            $class[
                                                'class_date'
                                            ]
                                        )
                                    );
                                    ?>
                                </strong>

                                <span>
                                    <?php
                                    echo strtoupper(
                                        date(
                                            'M',
                                            strtotime(
                                                $class[
                                                    'class_date'
                                                ]
                                            )
                                        )
                                    );
                                    ?>
                                </span>

                            </div>


                            <div class="trainer-class-info">

                                <strong>
                                    <?php
                                    echo trainerEscape(
                                        $class['class_name']
                                    );
                                    ?>
                                </strong>

                                <span>

                                    <?php
                                    echo trainerEscape(
                                        $class['start_time']
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo trainerEscape(
                                        $class['end_time']
                                    );
                                    ?>

                                    ·

                                    <?php
                                    echo trainerEscape(
                                        $class['location']
                                    );
                                    ?>

                                </span>

                            </div>


                            <div class="trainer-class-bookings">

                                <strong>
                                    <?php
                                    echo (int)
                                        $class[
                                            'booked_members'
                                        ];
                                    ?>
                                    /
                                    <?php
                                    echo (int)
                                        $class['capacity'];
                                    ?>
                                </strong>

                                <span>
                                    Booked
                                </span>

                            </div>


                            <span
                                class="trainer-class-status"
                            >

                                <?php
                                echo strtoupper(
                                    trainerEscape(
                                        $class['status']
                                    )
                                );
                                ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php else: ?>

                <div class="trainer-empty-schedule">

                    <span>📅</span>

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
            Trainer Portal
        </p>

    </div>

</footer>


</body>

</html>