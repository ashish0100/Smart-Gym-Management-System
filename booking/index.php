<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| Member Class Booking and Cancellation
|--------------------------------------------------------------------------
| Jira: SGMS-13
| Responsible Member: Ashish Karki
|--------------------------------------------------------------------------
*/


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
        'Please log in to access class bookings.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Member Role Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'member'
) {
    http_response_code(403);

    exit(
        'Access denied. Member access is required.'
    );
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function bookingEscape(
    ?string $value
): string {

    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function bookingFormatDate(
    ?string $date
): string {

    if (!$date) {
        return '-';
    }

    $timestamp =
        strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function bookingFormatTime(
    ?string $time
): string {

    if (!$time) {
        return '-';
    }

    $timestamp =
        strtotime($time);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'g:i A',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['booking_success']
    ?? '';

$errorMessage =
    $_SESSION['booking_error']
    ?? '';

unset(
    $_SESSION['booking_success'],
    $_SESSION['booking_error']
);


/*
|--------------------------------------------------------------------------
| Load Logged-In Member
|--------------------------------------------------------------------------
*/

try {

    $memberStatement =
        $pdo->prepare(
            'SELECT
                m.member_id,
                u.first_name,
                u.last_name,
                u.email

             FROM members m

             INNER JOIN users u
                ON m.user_id = u.user_id

             WHERE u.user_id = :user_id

             LIMIT 1'
        );

    $memberStatement->execute([
        'user_id' => $userId
    ]);

    $member =
        $memberStatement->fetch();


    if (!$member) {

        session_unset();
        session_destroy();

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }


    $memberId =
        (int) $member['member_id'];


} catch (PDOException $exception) {

    error_log(
        'Booking member lookup error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the booking system.'
    );
}


/*
|--------------------------------------------------------------------------
| Handle POST Requests
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $submittedToken =
        $_POST['csrf_token']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate CSRF Token
    |--------------------------------------------------------------------------
    */

    if (
        !is_string($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $_SESSION['booking_error'] =
            'Invalid request. Please refresh the page and try again.';

        header(
            'Location: index.php'
        );

        exit;
    }


    $action =
        $_POST['action']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | BOOK CLASS
    |--------------------------------------------------------------------------
    */

    if ($action === 'book') {

        $classId =
            filter_input(
                INPUT_POST,
                'class_id',
                FILTER_VALIDATE_INT
            );


        if (!$classId) {

            $_SESSION['booking_error'] =
                'Invalid class selected.';

            header(
                'Location: index.php'
            );

            exit;
        }


        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Load and Lock Class
            |--------------------------------------------------------------------------
            */

            $classStatement =
                $pdo->prepare(
                    'SELECT
                        class_id,
                        class_name,
                        class_date,
                        start_time,
                        end_time,
                        capacity,
                        status

                     FROM classes

                     WHERE class_id =
                        :class_id

                     LIMIT 1

                     FOR UPDATE'
                );

            $classStatement->execute([
                'class_id' => $classId
            ]);

            $class =
                $classStatement->fetch();


            if (!$class) {

                throw new RuntimeException(
                    'The selected class could not be found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Check Class Status
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $class['status'],
                    [
                        'available',
                        'full'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    'This class is not available for booking.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent Booking Past Classes
            |--------------------------------------------------------------------------
            */

            $classStart =
                strtotime(
                    $class['class_date'] .
                    ' ' .
                    $class['start_time']
                );


            if (
                $classStart === false ||
                $classStart <= time()
            ) {

                throw new RuntimeException(
                    'This class has already started or finished.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Count Confirmed Bookings
            |--------------------------------------------------------------------------
            */

            $countStatement =
                $pdo->prepare(
                    "SELECT COUNT(*)

                     FROM bookings

                     WHERE class_id =
                        :class_id

                     AND status =
                        'confirmed'"
                );

            $countStatement->execute([
                'class_id' => $classId
            ]);

            $confirmedCount =
                (int)
                $countStatement->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Capacity Validation
            |--------------------------------------------------------------------------
            */

            if (
                $confirmedCount >=
                (int) $class['capacity']
            ) {

                $fullStatement =
                    $pdo->prepare(
                        "UPDATE classes

                         SET status = 'full'

                         WHERE class_id =
                            :class_id

                         AND status =
                            'available'"
                    );

                $fullStatement->execute([
                    'class_id' => $classId
                ]);


                throw new RuntimeException(
                    'Sorry, this class is already full.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Check Existing Member Booking
            |--------------------------------------------------------------------------
            */

            $existingStatement =
                $pdo->prepare(
                    'SELECT
                        booking_id,
                        status

                     FROM bookings

                     WHERE member_id =
                        :member_id

                     AND class_id =
                        :class_id

                     LIMIT 1

                     FOR UPDATE'
                );

            $existingStatement->execute([
                'member_id' => $memberId,
                'class_id'  => $classId
            ]);

            $existingBooking =
                $existingStatement->fetch();


            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Confirmed Booking
            |--------------------------------------------------------------------------
            */

            if (
                $existingBooking &&
                $existingBooking['status']
                === 'confirmed'
            ) {

                throw new RuntimeException(
                    'You have already booked this class.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Restore Previously Cancelled Booking
            |--------------------------------------------------------------------------
            */

            if (
                $existingBooking &&
                $existingBooking['status']
                === 'cancelled'
            ) {

                $restoreStatement =
                    $pdo->prepare(
                        "UPDATE bookings

                         SET
                            status =
                                'confirmed',

                            booking_date =
                                CURRENT_TIMESTAMP,

                            cancelled_at =
                                NULL

                         WHERE booking_id =
                            :booking_id"
                    );

                $restoreStatement->execute([
                    'booking_id' =>
                        $existingBooking[
                            'booking_id'
                        ]
                ]);

            }


            /*
            |--------------------------------------------------------------------------
            | Create New Booking
            |--------------------------------------------------------------------------
            */

            elseif (!$existingBooking) {

                $insertStatement =
                    $pdo->prepare(
                        "INSERT INTO bookings (
                            member_id,
                            class_id,
                            status
                         )

                         VALUES (
                            :member_id,
                            :class_id,
                            'confirmed'
                         )"
                    );

                $insertStatement->execute([
                    'member_id' =>
                        $memberId,

                    'class_id' =>
                        $classId
                ]);

            }


            else {

                throw new RuntimeException(
                    'This booking cannot be created.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update Class Status if Capacity Reached
            |--------------------------------------------------------------------------
            */

            $newBookingCount =
                $confirmedCount + 1;


            if (
                $newBookingCount >=
                (int) $class['capacity']
            ) {

                $statusStatement =
                    $pdo->prepare(
                        "UPDATE classes

                         SET status = 'full'

                         WHERE class_id =
                            :class_id

                         AND status =
                            'available'"
                    );

                $statusStatement->execute([
                    'class_id' =>
                        $classId
                ]);
            }


            $pdo->commit();


            $_SESSION['booking_success'] =
                'Class booked successfully.';


        } catch (RuntimeException $exception) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }


            $_SESSION['booking_error'] =
                $exception->getMessage();


        } catch (PDOException $exception) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }


            error_log(
                'Class booking error: ' .
                $exception->getMessage()
            );


            $_SESSION['booking_error'] =
                'Unable to book the class. Please try again.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL BOOKING
    |--------------------------------------------------------------------------
    */

    if ($action === 'cancel') {

        $bookingId =
            filter_input(
                INPUT_POST,
                'booking_id',
                FILTER_VALIDATE_INT
            );


        if (!$bookingId) {

            $_SESSION['booking_error'] =
                'Invalid booking selected.';

            header(
                'Location: index.php'
            );

            exit;
        }


        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Verify Booking Belongs to Logged-In Member
            |--------------------------------------------------------------------------
            */

            $bookingStatement =
                $pdo->prepare(
                    'SELECT
                        b.booking_id,
                        b.member_id,
                        b.class_id,
                        b.status,

                        c.class_name,
                        c.class_date,
                        c.start_time,
                        c.status
                            AS class_status

                     FROM bookings b

                     INNER JOIN classes c
                        ON b.class_id =
                           c.class_id

                     WHERE b.booking_id =
                        :booking_id

                     AND b.member_id =
                        :member_id

                     LIMIT 1

                     FOR UPDATE'
                );

            $bookingStatement->execute([
                'booking_id' =>
                    $bookingId,

                'member_id' =>
                    $memberId
            ]);

            $booking =
                $bookingStatement->fetch();


            if (!$booking) {

                throw new RuntimeException(
                    'Booking could not be found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Confirm Booking is Active
            |--------------------------------------------------------------------------
            */

            if (
                $booking['status']
                !== 'confirmed'
            ) {

                throw new RuntimeException(
                    'This booking is not currently active.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent Cancellation of Past Classes
            |--------------------------------------------------------------------------
            */

            $bookingStart =
                strtotime(
                    $booking[
                        'class_date'
                    ] .
                    ' ' .
                    $booking[
                        'start_time'
                    ]
                );


            if (
                $bookingStart === false ||
                $bookingStart <= time()
            ) {

                throw new RuntimeException(
                    'Past classes cannot be cancelled.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Cancel Booking
            |--------------------------------------------------------------------------
            */

            $cancelStatement =
                $pdo->prepare(
                    "UPDATE bookings

                     SET
                        status =
                            'cancelled',

                        cancelled_at =
                            CURRENT_TIMESTAMP

                     WHERE booking_id =
                        :booking_id

                     AND member_id =
                        :member_id"
                );

            $cancelStatement->execute([
                'booking_id' =>
                    $bookingId,

                'member_id' =>
                    $memberId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Make Class Available Again if Previously Full
            |--------------------------------------------------------------------------
            */

            $classStatusStatement =
                $pdo->prepare(
                    "UPDATE classes

                     SET status =
                        'available'

                     WHERE class_id =
                        :class_id

                     AND status =
                        'full'"
                );

            $classStatusStatement->execute([
                'class_id' =>
                    $booking['class_id']
            ]);


            $pdo->commit();


            $_SESSION['booking_success'] =
                'Booking cancelled successfully.';


        } catch (RuntimeException $exception) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }


            $_SESSION['booking_error'] =
                $exception->getMessage();


        } catch (PDOException $exception) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }


            error_log(
                'Booking cancellation error: ' .
                $exception->getMessage()
            );


            $_SESSION['booking_error'] =
                'Unable to cancel the booking. Please try again.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Invalid Action
    |--------------------------------------------------------------------------
    */

    $_SESSION['booking_error'] =
        'Invalid booking action.';

    header(
        'Location: index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Upcoming Classes
|--------------------------------------------------------------------------
*/

try {

    $classesStatement =
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

                CONCAT(
                    COALESCE(
                        u.first_name,
                        ''
                    ),
                    ' ',
                    COALESCE(
                        u.last_name,
                        ''
                    )
                )
                AS trainer_name,

                (
                    SELECT COUNT(*)

                    FROM bookings cb

                    WHERE cb.class_id =
                        c.class_id

                    AND cb.status =
                        'confirmed'
                )
                AS booked_count,

                mb.booking_id
                    AS member_booking_id,

                mb.status
                    AS member_booking_status

             FROM classes c

             LEFT JOIN trainers t
                ON c.trainer_id =
                   t.trainer_id

             LEFT JOIN users u
                ON t.user_id =
                   u.user_id

             LEFT JOIN bookings mb
                ON mb.class_id =
                   c.class_id

                AND mb.member_id =
                   :member_id

             WHERE
                (
                    c.class_date >
                        CURDATE()

                    OR
                    (
                        c.class_date =
                            CURDATE()

                        AND c.start_time >
                            CURTIME()
                    )
                )

             AND c.status IN (
                'available',
                'full'
             )

             ORDER BY
                c.class_date ASC,
                c.start_time ASC"
        );


    $classesStatement->execute([
        'member_id' =>
            $memberId
    ]);


    $classes =
        $classesStatement->fetchAll();


} catch (PDOException $exception) {

    error_log(
        'Booking class loading error: ' .
        $exception->getMessage()
    );


    $classes = [];


    $errorMessage =
        'Unable to load available classes.';
}


/*
|--------------------------------------------------------------------------
| Load Member Booking History
|--------------------------------------------------------------------------
*/

try {

    $myBookingsStatement =
        $pdo->prepare(
            "SELECT
                b.booking_id,
                b.status
                    AS booking_status,
                b.booking_date,
                b.cancelled_at,

                c.class_id,
                c.class_name,
                c.class_date,
                c.start_time,
                c.end_time,
                c.location,
                c.status
                    AS class_status,

                CONCAT(
                    COALESCE(
                        u.first_name,
                        ''
                    ),
                    ' ',
                    COALESCE(
                        u.last_name,
                        ''
                    )
                )
                AS trainer_name

             FROM bookings b

             INNER JOIN classes c
                ON b.class_id =
                   c.class_id

             LEFT JOIN trainers t
                ON c.trainer_id =
                   t.trainer_id

             LEFT JOIN users u
                ON t.user_id =
                   u.user_id

             WHERE b.member_id =
                :member_id

             ORDER BY
                c.class_date DESC,
                c.start_time DESC"
        );


    $myBookingsStatement->execute([
        'member_id' =>
            $memberId
    ]);


    $myBookings =
        $myBookingsStatement->fetchAll();


} catch (PDOException $exception) {

    error_log(
        'Member booking loading error: ' .
        $exception->getMessage()
    );


    $myBookings = [];
}


/*
|--------------------------------------------------------------------------
| Available Class Count
|--------------------------------------------------------------------------
*/

$availableClassCount = 0;


foreach ($classes as $class) {

    $bookedCount =
        (int) $class[
            'booked_count'
        ];

    $capacity =
        (int) $class[
            'capacity'
        ];


    if (
        $class['status'] ===
            'available' &&
        $bookedCount < $capacity
    ) {

        $availableClassCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Active Booking Count
|--------------------------------------------------------------------------
| Only future confirmed bookings are counted.
|--------------------------------------------------------------------------
*/

$confirmedBookingCount = 0;


foreach ($myBookings as $booking) {

    $bookingStart =
        strtotime(
            $booking[
                'class_date'
            ] .
            ' ' .
            $booking[
                'start_time'
            ]
        );


    if (
        $booking[
            'booking_status'
        ] === 'confirmed' &&

        $bookingStart !== false &&

        $bookingStart > time()
    ) {

        $confirmedBookingCount++;
    }
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
        Bookings | World Fitness Australia
    </title>

 <link
    rel="stylesheet"
    href="../css/style.css?v=6"

    >

</head>


<body class="dashboard-page booking-page">


<!-- =====================================================
     NAVIGATION
===================================================== -->

<header class="dashboard-navbar">

    <div
        class="
            container
            dashboard-nav-container
        "
    >

        <a
            href="../member/dashboard.php"
            class="logo"
        >

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Class Bookings
                </small>

            </div>

        </a>


        <div class="dashboard-user">

            <div class="user-text">

                <span>
                    Member
                </span>

                <strong>

                    <?=
                    bookingEscape(
                        $member[
                            'first_name'
                        ]
                    )
                    ?>

                </strong>

            </div>


            <a
                href="../member/dashboard.php"
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


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="dashboard-main">

    <div class="container">


        <!-- PAGE INTRODUCTION -->

        <section class="dashboard-welcome">

            <div>

                <span class="eyebrow">
                    CLASS BOOKINGS
                </span>

                <h1>
                    Find your next class.
                </h1>

                <p>
                    Browse upcoming gym classes,
                    check available spaces and
                    manage your bookings.
                </p>

            </div>

        </section>


        <!-- SUCCESS MESSAGE -->

        <?php if ($successMessage): ?>

            <div
                class="
                    booking-alert
                    booking-alert-success
                "
            >

                ✓

                <?= bookingEscape(
                    $successMessage
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if ($errorMessage): ?>

            <div
                class="
                    booking-alert
                    booking-alert-error
                "
            >

                !

                <?= bookingEscape(
                    $errorMessage
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             SUMMARY CARDS
        ===================================================== -->

        <section class="booking-summary-grid">


            <article class="booking-summary-card">

                <span>
                    Available Classes
                </span>

                <strong>
                    <?= $availableClassCount ?>
                </strong>

                <small>
                    Upcoming classes with spaces
                </small>

            </article>


            <article class="booking-summary-card">

                <span>
                    My Active Bookings
                </span>

                <strong>
                    <?= $confirmedBookingCount ?>
                </strong>

                <small>
                    Future confirmed class bookings
                </small>

            </article>


        </section>


        <!-- =====================================================
             AVAILABLE CLASSES
        ===================================================== -->

        <section class="booking-panel">


            <div class="booking-section-heading">

                <span class="eyebrow">
                    AVAILABLE CLASSES
                </span>

                <h2>
                    Upcoming Classes
                </h2>

            </div>


            <?php if (empty($classes)): ?>


                <div class="booking-empty">

                    No upcoming classes are
                    currently available.

                </div>


            <?php else: ?>


                <div class="booking-class-list">


                    <?php foreach (
                        $classes
                        as $class
                    ): ?>


                        <?php

                        $bookedCount =
                            (int)
                            $class[
                                'booked_count'
                            ];


                        $capacity =
                            (int)
                            $class[
                                'capacity'
                            ];


                        $remaining =
                            max(
                                0,
                                $capacity -
                                $bookedCount
                            );


                        $alreadyBooked =
                            $class[
                                'member_booking_status'
                            ] === 'confirmed';


                        $isFull =
                            $remaining <= 0 ||
                            $class[
                                'status'
                            ] === 'full';

                        ?>


                        <article
                            class="
                                booking-class-card
                            "
                        >


                            <div class="booking-class-top">


                                <div>


                                    <span
                                        class="
                                            booking-status
                                            booking-status-<?=
                                            bookingEscape(
                                                $isFull
                                                    ? 'full'
                                                    : 'available'
                                            )
                                            ?>
                                        "
                                    >

                                        <?=
                                        $isFull
                                            ? 'FULL'
                                            : 'AVAILABLE'
                                        ?>

                                    </span>


                                    <h3>

                                        <?= bookingEscape(
                                            $class[
                                                'class_name'
                                            ]
                                        ) ?>

                                    </h3>


                                    <p>

                                        Trainer:

                                        <?= bookingEscape(
                                            trim(
                                                $class[
                                                    'trainer_name'
                                                ]
                                            )
                                            ?: 'To be assigned'
                                        ) ?>

                                    </p>


                                </div>


                                <div class="booking-capacity">

                                    <strong>

                                        <?= $bookedCount ?>

                                        /

                                        <?= $capacity ?>

                                    </strong>

                                    <span>
                                        Booked
                                    </span>

                                </div>


                            </div>


                            <div class="booking-details-grid">


                                <div>

                                    <span>
                                        DATE
                                    </span>

                                    <strong>

                                        <?= bookingFormatDate(
                                            $class[
                                                'class_date'
                                            ]
                                        ) ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        TIME
                                    </span>

                                    <strong>

                                        <?= bookingFormatTime(
                                            $class[
                                                'start_time'
                                            ]
                                        ) ?>

                                        -

                                        <?= bookingFormatTime(
                                            $class[
                                                'end_time'
                                            ]
                                        ) ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        LOCATION
                                    </span>

                                    <strong>

                                        <?= bookingEscape(
                                            $class[
                                                'location'
                                            ]
                                            ?: '-'
                                        ) ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        SPACES LEFT
                                    </span>

                                    <strong>
                                        <?= $remaining ?>
                                    </strong>

                                </div>


                            </div>


                            <?php if (
                                !empty(
                                    $class[
                                        'description'
                                    ]
                                )
                            ): ?>


                                <p class="booking-description">

                                    <?= bookingEscape(
                                        $class[
                                            'description'
                                        ]
                                    ) ?>

                                </p>


                            <?php endif; ?>


                            <div class="booking-actions">


                                <?php if (
                                    $alreadyBooked
                                ): ?>


                                    <span
                                        class="
                                            booking-already-booked
                                        "
                                    >

                                        ✓ Already Booked

                                    </span>


                                <?php elseif (
                                    $isFull
                                ): ?>


                                    <button
                                        type="button"
                                        class="
                                            booking-button
                                            booking-button-disabled
                                        "
                                        disabled
                                    >
                                        Class Full
                                    </button>


                                <?php else: ?>


                                    <form
                                        method="POST"
                                        action="index.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?=
                                                bookingEscape(
                                                    $csrfToken
                                                )
                                            ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="book"
                                        >


                                        <input
                                            type="hidden"
                                            name="class_id"
                                            value="<?=
                                                (int)
                                                $class[
                                                    'class_id'
                                                ]
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="booking-button"
                                        >
                                            Book Class
                                        </button>


                                    </form>


                                <?php endif; ?>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================================
             BOOKING HISTORY
        ===================================================== -->

        <section class="booking-panel">


            <div class="booking-section-heading">

                <span class="eyebrow">
                    MY BOOKINGS
                </span>

                <h2>
                    Booking History
                </h2>

            </div>


            <?php if (
                empty($myBookings)
            ): ?>


                <div class="booking-empty">

                    You have not booked
                    any classes yet.

                </div>


            <?php else: ?>


                <div class="booking-history-list">


                    <?php foreach (
                        $myBookings
                        as $booking
                    ): ?>


                        <?php

                        $bookingStart =
                            strtotime(
                                $booking[
                                    'class_date'
                                ] .
                                ' ' .
                                $booking[
                                    'start_time'
                                ]
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Past classes cannot be cancelled
                        |--------------------------------------------------------------------------
                        */

                        $canCancelBooking =
                            $booking[
                                'booking_status'
                            ] === 'confirmed' &&

                            $bookingStart
                                !== false &&

                            $bookingStart
                                > time();

                        ?>


                        <article
                            class="
                                booking-history-card
                            "
                        >


                            <div>


                                <span
                                    class="
                                        booking-status
                                        booking-status-<?=
                                        bookingEscape(
                                            $booking[
                                                'booking_status'
                                            ]
                                        )
                                        ?>
                                    "
                                >

                                    <?= bookingEscape(
                                        strtoupper(
                                            $booking[
                                                'booking_status'
                                            ]
                                        )
                                    ) ?>

                                </span>


                                <h3>

                                    <?= bookingEscape(
                                        $booking[
                                            'class_name'
                                        ]
                                    ) ?>

                                </h3>


                                <p>

                                    <?= bookingFormatDate(
                                        $booking[
                                            'class_date'
                                        ]
                                    ) ?>

                                    ·

                                    <?= bookingFormatTime(
                                        $booking[
                                            'start_time'
                                        ]
                                    ) ?>

                                    -

                                    <?= bookingFormatTime(
                                        $booking[
                                            'end_time'
                                        ]
                                    ) ?>

                                </p>


                                <p>

                                    <?= bookingEscape(
                                        $booking[
                                            'location'
                                        ]
                                        ?: '-'
                                    ) ?>

                                    · Trainer:

                                    <?= bookingEscape(
                                        trim(
                                            $booking[
                                                'trainer_name'
                                            ]
                                        )
                                        ?: 'To be assigned'
                                    ) ?>

                                </p>


                                <?php if (
                                    $booking[
                                        'booking_status'
                                    ] === 'confirmed' &&
                                    !$canCancelBooking
                                ): ?>

                                    <p
                                        style="
                                            margin-top: 10px;
                                            color: #8396ae;
                                        "
                                    >
                                        Past class
                                    </p>

                                <?php endif; ?>


                            </div>


                            <?php if (
                                $canCancelBooking
                            ): ?>


                                <form
                                    method="POST"
                                    action="index.php"

                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to cancel this booking?'
                                        );
                                    "
                                >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?=
                                            bookingEscape(
                                                $csrfToken
                                            )
                                        ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    >


                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?=
                                            (int)
                                            $booking[
                                                'booking_id'
                                            ]
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            booking-cancel-button
                                        "
                                    >
                                        Cancel Booking
                                    </button>


                                </form>


                            <?php endif; ?>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>


    </div>

</main>


<!-- =====================================================
     FOOTER
===================================================== -->

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
            Class Booking Management
        </p>

    </div>

</footer>


</body>

</html>