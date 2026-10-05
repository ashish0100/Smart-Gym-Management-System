<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| Attendance Management
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
        'Please log in to access attendance management.';

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

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function attendanceEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function attendanceFormat(string $value): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $value
        )
    );
}


/*
|--------------------------------------------------------------------------
| Load Logged-In Trainer
|--------------------------------------------------------------------------
*/

try {

    $trainerStatement = $pdo->prepare(
        'SELECT
            t.trainer_id,
            u.first_name,
            u.last_name,
            u.email
         FROM trainers t
         INNER JOIN users u
            ON t.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1'
    );

    $trainerStatement->execute([
        'user_id' => $userId
    ]);

    $trainer = $trainerStatement->fetch();

    if (!$trainer) {

        http_response_code(404);

        exit(
            'Trainer profile could not be found.'
        );
    }

    $trainerId =
        (int) $trainer['trainer_id'];

} catch (PDOException $exception) {

    error_log(
        'Attendance trainer lookup error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load attendance management.'
    );
}


/*
|--------------------------------------------------------------------------
| Record or Update Attendance
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken =
        $_POST['csrf_token'] ?? '';

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

        $errorMessage =
            'Invalid request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Validate Submitted Values
        |--------------------------------------------------------------------------
        */

        $bookingId =
            filter_input(
                INPUT_POST,
                'booking_id',
                FILTER_VALIDATE_INT
            );

        $attendanceStatus =
            $_POST['status'] ?? '';

        $allowedStatuses = [
            'present',
            'late',
            'absent'
        ];

        if (
            !$bookingId ||
            !in_array(
                $attendanceStatus,
                $allowedStatuses,
                true
            )
        ) {

            $errorMessage =
                'Invalid attendance information.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Verify Booking Belongs to Logged-In Trainer
                |--------------------------------------------------------------------------
                */

                $bookingStatement = $pdo->prepare(
                    "SELECT
                        b.booking_id,
                        b.member_id,
                        b.class_id,
                        c.class_date
                     FROM bookings b
                     INNER JOIN classes c
                        ON b.class_id = c.class_id
                     WHERE b.booking_id = :booking_id
                     AND c.trainer_id = :trainer_id
                     AND b.status = 'confirmed'
                     LIMIT 1"
                );

                $bookingStatement->execute([
                    'booking_id' => $bookingId,
                    'trainer_id' => $trainerId
                ]);

                $booking =
                    $bookingStatement->fetch();

                if (!$booking) {

                    $errorMessage =
                        'The selected booking could not be found.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Set Check-In Time
                    |--------------------------------------------------------------------------
                    */

                    $checkInTime = null;

                    if (
                        $attendanceStatus === 'present' ||
                        $attendanceStatus === 'late'
                    ) {
                        $checkInTime =
                            date('H:i:s');
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Check for Existing Attendance Record
                    |--------------------------------------------------------------------------
                    */

                    $existingStatement =
                        $pdo->prepare(
                            'SELECT attendance_id
                             FROM attendance
                             WHERE booking_id = :booking_id
                             LIMIT 1'
                        );

                    $existingStatement->execute([
                        'booking_id' => $bookingId
                    ]);

                    $existingAttendance =
                        $existingStatement->fetch();


                    /*
                    |--------------------------------------------------------------------------
                    | Update Existing Attendance
                    |--------------------------------------------------------------------------
                    */

                    if ($existingAttendance) {

                        $updateStatement =
                            $pdo->prepare(
                                'UPDATE attendance
                                 SET
                                    attendance_date =
                                        :attendance_date,
                                    check_in_time =
                                        :check_in_time,
                                    status =
                                        :status
                                 WHERE attendance_id =
                                    :attendance_id'
                            );

                        $updateStatement->execute([
                            'attendance_date' =>
                                $booking[
                                    'class_date'
                                ],

                            'check_in_time' =>
                                $checkInTime,

                            'status' =>
                                $attendanceStatus,

                            'attendance_id' =>
                                $existingAttendance[
                                    'attendance_id'
                                ]
                        ]);

                        $successMessage =
                            'Attendance updated successfully.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Insert New Attendance Record
                        |--------------------------------------------------------------------------
                        */

                        $insertStatement =
                            $pdo->prepare(
                                'INSERT INTO attendance (
                                    member_id,
                                    class_id,
                                    booking_id,
                                    attendance_date,
                                    check_in_time,
                                    status
                                 ) VALUES (
                                    :member_id,
                                    :class_id,
                                    :booking_id,
                                    :attendance_date,
                                    :check_in_time,
                                    :status
                                 )'
                            );

                        $insertStatement->execute([
                            'member_id' =>
                                $booking[
                                    'member_id'
                                ],

                            'class_id' =>
                                $booking[
                                    'class_id'
                                ],

                            'booking_id' =>
                                $booking[
                                    'booking_id'
                                ],

                            'attendance_date' =>
                                $booking[
                                    'class_date'
                                ],

                            'check_in_time' =>
                                $checkInTime,

                            'status' =>
                                $attendanceStatus
                        ]);

                        $successMessage =
                            'Attendance recorded successfully.';
                    }
                }

            } catch (PDOException $exception) {

                error_log(
                    'Attendance save error: ' .
                    $exception->getMessage()
                );

                $errorMessage =
                    'Unable to save attendance. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Classes Assigned to Trainer
|--------------------------------------------------------------------------
*/

try {

    $classesStatement = $pdo->prepare(
        "SELECT
            c.class_id,
            c.class_name,
            c.class_date,
            c.start_time,
            c.end_time,
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

         GROUP BY
            c.class_id,
            c.class_name,
            c.class_date,
            c.start_time,
            c.end_time,
            c.location,
            c.status

         ORDER BY
            c.class_date DESC,
            c.start_time DESC"
    );

    $classesStatement->execute([
        'trainer_id' => $trainerId
    ]);

    $classes =
        $classesStatement->fetchAll();

} catch (PDOException $exception) {

    error_log(
        'Attendance class loading error: ' .
        $exception->getMessage()
    );

    $classes = [];
}


/*
|--------------------------------------------------------------------------
| Selected Class
|--------------------------------------------------------------------------
*/

$selectedClassId =
    filter_input(
        INPUT_GET,
        'class_id',
        FILTER_VALIDATE_INT
    );

if (
    !$selectedClassId &&
    !empty($classes)
) {
    $selectedClassId =
        (int) $classes[0]['class_id'];
}

$selectedClass = null;

foreach ($classes as $class) {

    if (
        (int) $class['class_id'] ===
        (int) $selectedClassId
    ) {
        $selectedClass = $class;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| Load Confirmed Members for Selected Class
|--------------------------------------------------------------------------
*/

$bookedMembers = [];

if ($selectedClass) {

    try {

        $membersStatement =
            $pdo->prepare(
                "SELECT
                    b.booking_id,
                    b.member_id,

                    u.first_name,
                    u.last_name,
                    u.email,

                    a.attendance_id,
                    a.status
                        AS attendance_status,
                    a.check_in_time

                 FROM bookings b

                 INNER JOIN members m
                    ON b.member_id =
                       m.member_id

                 INNER JOIN users u
                    ON m.user_id =
                       u.user_id

                 LEFT JOIN attendance a
                    ON b.booking_id =
                       a.booking_id

                 WHERE b.class_id =
                    :class_id

                 AND b.status =
                    'confirmed'

                 ORDER BY
                    u.first_name ASC,
                    u.last_name ASC"
            );

        $membersStatement->execute([
            'class_id' =>
                $selectedClass[
                    'class_id'
                ]
        ]);

        $bookedMembers =
            $membersStatement->fetchAll();

    } catch (PDOException $exception) {

        error_log(
            'Attendance member loading error: ' .
            $exception->getMessage()
        );

        $errorMessage =
            'Unable to load booked members.';
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
        Attendance Management | World Fitness Australia
    </title>

    <link
    rel="stylesheet"
    href="../css/style.css?v=3"
>

</head>


<body class="dashboard-page attendance-page">


<!-- =====================================================
     NAVIGATION
===================================================== -->

<header class="dashboard-navbar">

    <div class="container dashboard-nav-container">

        <a
            href="../trainer/dashboard.php"
            class="logo"
        >

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Attendance Management
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
                    echo attendanceEscape(
                        $trainer[
                            'first_name'
                        ]
                    );
                    ?>
                </strong>

            </div>


            <a
                href="../trainer/dashboard.php"
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
                    ATTENDANCE
                </span>

                <h1>
                    Class Attendance Management
                </h1>

                <p>
                    Select one of your assigned classes
                    and record each booked member as
                    present, absent or late.
                </p>

            </div>

        </section>


        <!-- SUCCESS MESSAGE -->

        <?php if ($successMessage): ?>

            <div class="attendance-alert attendance-alert-success">

                <strong>
                    ✓
                </strong>

                <span>
                    <?php
                    echo attendanceEscape(
                        $successMessage
                    );
                    ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if ($errorMessage): ?>

            <div class="attendance-alert attendance-alert-error">

                <strong>
                    !
                </strong>

                <span>
                    <?php
                    echo attendanceEscape(
                        $errorMessage
                    );
                    ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- CLASS SELECTION -->

        <section class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <span class="eyebrow">
                        MY CLASSES
                    </span>

                    <h2>
                        Select Class
                    </h2>

                </div>

            </div>


            <?php if ($classes): ?>

                <form
                    method="get"
                    action="index.php"
                    class="attendance-class-form"
                >

                    <label for="class_id">
                        Class
                    </label>

                    <select
                        name="class_id"
                        id="class_id"
                        class="attendance-status-select"
                        onchange="this.form.submit()"
                    >

                        <?php
                        foreach ($classes as $class):
                        ?>

                            <option
                                value="<?php
                                echo (int)
                                    $class[
                                        'class_id'
                                    ];
                                ?>"
                                <?php
                                echo (
                                    (int)
                                    $class[
                                        'class_id'
                                    ] ===
                                    (int)
                                    $selectedClassId
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo attendanceEscape(
                                    $class[
                                        'class_name'
                                    ]
                                );
                                ?>

                                -

                                <?php
                                echo attendanceEscape(
                                    $class[
                                        'class_date'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            <?php else: ?>

                <p>
                    No classes are currently assigned
                    to this trainer.
                </p>

            <?php endif; ?>

        </section>


        <!-- SELECTED CLASS -->

        <?php if ($selectedClass): ?>

            <section class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            SELECTED CLASS
                        </span>

                        <h2>
                            <?php
                            echo attendanceEscape(
                                $selectedClass[
                                    'class_name'
                                ]
                            );
                            ?>
                        </h2>

                    </div>

                </div>


                <div class="detail-list">

                    <div>

                        <span>
                            Date
                        </span>

                        <strong>
                            <?php
                            echo attendanceEscape(
                                $selectedClass[
                                    'class_date'
                                ]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Time
                        </span>

                        <strong>

                            <?php
                            echo attendanceEscape(
                                $selectedClass[
                                    'start_time'
                                ]
                            );
                            ?>

                            -

                            <?php
                            echo attendanceEscape(
                                $selectedClass[
                                    'end_time'
                                ]
                            );
                            ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            Location
                        </span>

                        <strong>
                            <?php
                            echo attendanceEscape(
                                $selectedClass[
                                    'location'
                                ]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Confirmed Bookings
                        </span>

                        <strong>
                            <?php
                            echo (int)
                                $selectedClass[
                                    'booked_members'
                                ];
                            ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- MEMBER ATTENDANCE -->

            <section class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <span class="eyebrow">
                            MEMBERS
                        </span>

                        <h2>
                            Record Attendance
                        </h2>

                    </div>

                </div>


                <?php if ($bookedMembers): ?>

                    <div class="attendance-member-list">

                        <?php
                        foreach (
                            $bookedMembers as $member
                        ):
                        ?>

                            <div class="attendance-member-row">


                                <!-- MEMBER DETAILS -->

                                <div class="attendance-member-details">

                                    <strong class="attendance-member-name">

                                        <?php
                                        echo attendanceEscape(
                                            $member[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $member[
                                                'last_name'
                                            ]
                                        );
                                        ?>

                                    </strong>

                                    <span class="attendance-member-email">

                                        <?php
                                        echo attendanceEscape(
                                            $member[
                                                'email'
                                            ]
                                        );
                                        ?>

                                    </span>

                                </div>


<!-- CURRENT STATUS -->

<div class="attendance-current-status">

    <span class="attendance-status-label">
        Current Status
    </span>

    <?php

    $currentStatus =
        $member['attendance_status'] ?? null;

    if ($currentStatus === 'present') {

        $statusClass =
            'attendance-status-present';

    } elseif ($currentStatus === 'late') {

        $statusClass =
            'attendance-status-late';

    } elseif ($currentStatus === 'absent') {

        $statusClass =
            'attendance-status-absent';

    } else {

        $statusClass =
            'attendance-status-none';
    }

    ?>

    <span
        class="attendance-status-badge <?php
        echo $statusClass;
        ?>"
    >

        <?php

        if ($currentStatus) {

            echo attendanceEscape(
                attendanceFormat(
                    $currentStatus
                )
            );

        } else {

            echo 'Not Recorded';
        }

        ?>

    </span>

    <?php
    if (
        !empty(
            $member['check_in_time']
        )
    ):
    ?>

        <small class="attendance-checkin">

            Check-in:
            <?php
            echo attendanceEscape(
                $member['check_in_time']
            );
            ?>

        </small>

    <?php endif; ?>

</div>


                                <!-- ATTENDANCE FORM -->

                                <form
                                    method="post"
                                    action="index.php?class_id=<?php
                                    echo (int)
                                        $selectedClass[
                                            'class_id'
                                        ];
                                    ?>"
                                    class="attendance-record-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php
                                        echo attendanceEscape(
                                            $csrfToken
                                        );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?php
                                        echo (int)
                                            $member[
                                                'booking_id'
                                            ];
                                        ?>"
                                    >


                                    <select
                                        name="status"
                                        class="attendance-status-select"
                                        required
                                    >

                                        <option
                                            value="present"
                                            <?php
                                            echo (
                                                $currentStatus ===
                                                'present'
                                            )
                                                ? 'selected'
                                                : '';
                                            ?>
                                        >
                                            Present
                                        </option>


                                        <option
                                            value="late"
                                            <?php
                                            echo (
                                                $currentStatus ===
                                                'late'
                                            )
                                                ? 'selected'
                                                : '';
                                            ?>
                                        >
                                            Late
                                        </option>


                                        <option
                                            value="absent"
                                            <?php
                                            echo (
                                                $currentStatus ===
                                                'absent'
                                            )
                                                ? 'selected'
                                                : '';
                                            ?>
                                        >
                                            Absent
                                        </option>

                                    </select>


                                    <button
                                        type="submit"
                                        class="attendance-save-button"
                                    >
                                        Save Attendance
                                    </button>

                                </form>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="attendance-empty-state">

                        <strong>
                            No confirmed bookings
                        </strong>

                        <p>
                            Members with confirmed bookings
                            for this class will appear here.
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

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
            Attendance Management
        </p>

    </div>

</footer>

</body>

</html>