<?php

declare(strict_types=1);

session_start();
/*
|--------------------------------------------------------------------------
| Prevent Browser Caching
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

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

    exit(
        'Access denied. Administrator access is required.'
    );
}


$adminName =
    $_SESSION['first_name'] ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['class_success'] ?? '';

$errorMessage =
    $_SESSION['class_error'] ?? '';

unset(
    $_SESSION['class_success'],
    $_SESSION['class_error']
);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function classEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function classFormat(string $value): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $value
        )
    );
}


function validDate(string $date): bool
{
    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

    return (
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date
    );
}


/*
|--------------------------------------------------------------------------
| Process Create / Update / Cancel
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validate CSRF
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {
        $_SESSION['class_error'] =
            'Invalid request. Please refresh the page and try again.';

        header('Location: classes.php');
        exit;
    }


    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Cancel Class
    |--------------------------------------------------------------------------
    */

    if ($action === 'cancel') {

        $classId =
            filter_input(
                INPUT_POST,
                'class_id',
                FILTER_VALIDATE_INT
            );

        if (!$classId) {

            $_SESSION['class_error'] =
                'Invalid class selected.';

        } else {

            try {

                $cancelStatement =
                    $pdo->prepare(
                        "UPDATE classes
                         SET status = 'cancelled'
                         WHERE class_id = :class_id"
                    );

                $cancelStatement->execute([
                    'class_id' => $classId
                ]);

                $_SESSION['class_success'] =
                    'Class cancelled successfully.';

            } catch (PDOException $exception) {

                error_log(
                    'Class cancellation error: ' .
                    $exception->getMessage()
                );

                $_SESSION['class_error'] =
                    'Unable to cancel the class.';
            }
        }

        header('Location: classes.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Create / Update Form Values
    |--------------------------------------------------------------------------
    */

    $classId =
        filter_input(
            INPUT_POST,
            'class_id',
            FILTER_VALIDATE_INT
        );

    $className =
        trim(
            (string)
            ($_POST['class_name'] ?? '')
        );

    $description =
        trim(
            (string)
            ($_POST['description'] ?? '')
        );

    $trainerId =
        filter_input(
            INPUT_POST,
            'trainer_id',
            FILTER_VALIDATE_INT
        );

    $classDate =
        trim(
            (string)
            ($_POST['class_date'] ?? '')
        );

    $startTime =
        trim(
            (string)
            ($_POST['start_time'] ?? '')
        );

    $endTime =
        trim(
            (string)
            ($_POST['end_time'] ?? '')
        );

    $capacity =
        filter_input(
            INPUT_POST,
            'capacity',
            FILTER_VALIDATE_INT
        );

    $location =
        trim(
            (string)
            ($_POST['location'] ?? '')
        );

    $status =
        trim(
            (string)
            ($_POST['status'] ?? 'available')
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'available',
        'full',
        'cancelled',
        'completed'
    ];

    $validationErrors = [];


    if (
        $className === '' ||
        strlen($className) > 100
    ) {
        $validationErrors[] =
            'Class name is required and must be under 100 characters.';
    }


    if (
        $classDate === '' ||
        !validDate($classDate)
    ) {
        $validationErrors[] =
            'A valid class date is required.';
    }


    if (
        $startTime === '' ||
        $endTime === ''
    ) {
        $validationErrors[] =
            'Start time and end time are required.';
    }


    if (
        $startTime !== '' &&
        $endTime !== '' &&
        $startTime >= $endTime
    ) {
        $validationErrors[] =
            'End time must be later than start time.';
    }


    if (
        !$capacity ||
        $capacity < 1 ||
        $capacity > 500
    ) {
        $validationErrors[] =
            'Capacity must be between 1 and 500.';
    }


    if (strlen($location) > 100) {
        $validationErrors[] =
            'Location must be under 100 characters.';
    }


    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        $validationErrors[] =
            'Invalid class status.';
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Trainer
    |--------------------------------------------------------------------------
    */

    $trainerValue = null;

    if ($trainerId) {

        try {

            $trainerCheck =
                $pdo->prepare(
                    "SELECT trainer_id
                     FROM trainers
                     WHERE trainer_id = :trainer_id
                     AND employment_status = 'active'
                     LIMIT 1"
                );

            $trainerCheck->execute([
                'trainer_id' => $trainerId
            ]);

            if (!$trainerCheck->fetch()) {

                $validationErrors[] =
                    'The selected trainer is not available.';

            } else {

                $trainerValue =
                    $trainerId;
            }

        } catch (PDOException $exception) {

            error_log(
                'Trainer validation error: ' .
                $exception->getMessage()
            );

            $validationErrors[] =
                'Unable to validate the selected trainer.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validation Failed
    |--------------------------------------------------------------------------
    */

    if ($validationErrors) {

        $_SESSION['class_error'] =
            implode(
                ' ',
                $validationErrors
            );

        $redirect =
            ($action === 'update' && $classId)
            ? 'classes.php?edit=' .
                (int) $classId
            : 'classes.php';

        header(
            'Location: ' . $redirect
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Create Class
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        try {

            $insertStatement =
                $pdo->prepare(
                    'INSERT INTO classes (
                        trainer_id,
                        class_name,
                        description,
                        class_date,
                        start_time,
                        end_time,
                        capacity,
                        location,
                        status
                     ) VALUES (
                        :trainer_id,
                        :class_name,
                        :description,
                        :class_date,
                        :start_time,
                        :end_time,
                        :capacity,
                        :location,
                        :status
                     )'
                );

            $insertStatement->execute([
                'trainer_id' =>
                    $trainerValue,

                'class_name' =>
                    $className,

                'description' =>
                    $description !== ''
                    ? $description
                    : null,

                'class_date' =>
                    $classDate,

                'start_time' =>
                    $startTime,

                'end_time' =>
                    $endTime,

                'capacity' =>
                    $capacity,

                'location' =>
                    $location !== ''
                    ? $location
                    : null,

                'status' =>
                    $status
            ]);

            $_SESSION['class_success'] =
                'Class created successfully.';

        } catch (PDOException $exception) {

            error_log(
                'Class creation error: ' .
                $exception->getMessage()
            );

            $_SESSION['class_error'] =
                'Unable to create the class.';
        }


        header('Location: classes.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Class
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        if (!$classId) {

            $_SESSION['class_error'] =
                'Invalid class selected.';

            header('Location: classes.php');
            exit;
        }

        try {

            $updateStatement =
                $pdo->prepare(
                    'UPDATE classes
                     SET
                        trainer_id =
                            :trainer_id,
                        class_name =
                            :class_name,
                        description =
                            :description,
                        class_date =
                            :class_date,
                        start_time =
                            :start_time,
                        end_time =
                            :end_time,
                        capacity =
                            :capacity,
                        location =
                            :location,
                        status =
                            :status
                     WHERE class_id =
                        :class_id'
                );

            $updateStatement->execute([
                'trainer_id' =>
                    $trainerValue,

                'class_name' =>
                    $className,

                'description' =>
                    $description !== ''
                    ? $description
                    : null,

                'class_date' =>
                    $classDate,

                'start_time' =>
                    $startTime,

                'end_time' =>
                    $endTime,

                'capacity' =>
                    $capacity,

                'location' =>
                    $location !== ''
                    ? $location
                    : null,

                'status' =>
                    $status,

                'class_id' =>
                    $classId
            ]);

            $_SESSION['class_success'] =
                'Class updated successfully.';

        } catch (PDOException $exception) {

            error_log(
                'Class update error: ' .
                $exception->getMessage()
            );

            $_SESSION['class_error'] =
                'Unable to update the class.';
        }


        header('Location: classes.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Load Active Trainers
|--------------------------------------------------------------------------
*/

try {

    $trainerStatement =
        $pdo->query(
            "SELECT
                t.trainer_id,
                t.specialisation,
                u.first_name,
                u.last_name

             FROM trainers t

             INNER JOIN users u
                ON t.user_id = u.user_id

             WHERE
                t.employment_status = 'active'
             AND u.status = 'active'

             ORDER BY
                u.first_name ASC,
                u.last_name ASC"
        );

    $trainers =
        $trainerStatement->fetchAll();

} catch (PDOException $exception) {

    error_log(
        'Class trainer loading error: ' .
        $exception->getMessage()
    );

    $trainers = [];

    $errorMessage =
        'Unable to load trainer information.';
}


/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

try {

    $classStatement =
        $pdo->query(
            "SELECT
                c.class_id,
                c.trainer_id,
                c.class_name,
                c.description,
                c.class_date,
                c.start_time,
                c.end_time,
                c.capacity,
                c.location,
                c.status,
                c.created_at,

                u.first_name
                    AS trainer_first_name,

                u.last_name
                    AS trainer_last_name,

                COALESCE(
                    bc.confirmed_bookings,
                    0
                ) AS confirmed_bookings

             FROM classes c

             LEFT JOIN trainers t
                ON c.trainer_id =
                   t.trainer_id

             LEFT JOIN users u
                ON t.user_id =
                   u.user_id

             LEFT JOIN (
                SELECT
                    class_id,
                    COUNT(*) AS confirmed_bookings

                FROM bookings

                WHERE status = 'confirmed'

                GROUP BY class_id
             ) bc
                ON c.class_id =
                   bc.class_id

             ORDER BY
                c.class_date DESC,
                c.start_time DESC"
        );

    $classes =
        $classStatement->fetchAll();

} catch (PDOException $exception) {

    error_log(
        'Class loading error: ' .
        $exception->getMessage()
    );

    $classes = [];

    $errorMessage =
        'Unable to load gym classes.';
}


/*
|--------------------------------------------------------------------------
| Load Class Being Edited
|--------------------------------------------------------------------------
*/

$editClass = null;

$editId =
    filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

if ($editId) {

    foreach ($classes as $class) {

        if (
            (int) $class['class_id'] ===
            (int) $editId
        ) {
            $editClass = $class;
            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalClasses =
    count($classes);

$availableClasses = 0;
$cancelledClasses = 0;
$totalBookings = 0;

foreach ($classes as $class) {

    if (
        $class['status'] === 'available' ||
        $class['status'] === 'full'
    ) {
        $availableClasses++;
    }

    if ($class['status'] === 'cancelled') {
        $cancelledClasses++;
    }

    $totalBookings +=
        (int) $class['confirmed_bookings'];
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
        Class Management | World Fitness Australia
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css?v=5"
    >

</head>


<body class="dashboard-page class-management-page">


<!-- =====================================================
     NAVIGATION
===================================================== -->

<header class="dashboard-navbar">

    <div class="container dashboard-nav-container">

        <a
            href="dashboard.php"
            class="logo"
        >

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Class Management
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
                    echo classEscape(
                        $adminName
                    );
                    ?>
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


<!-- =====================================================
     MAIN
===================================================== -->

<main class="dashboard-main">

<div class="container">


    <!-- PAGE HEADING -->

    <section class="dashboard-welcome">

        <div>

            <span class="eyebrow">
                CLASS MANAGEMENT
            </span>

            <h1>
                Manage Gym Classes
            </h1>

            <p>
                Create, schedule, assign trainers and
                manage gym classes from the administration portal.
            </p>

        </div>

    </section>


    <!-- SUMMARY -->

    <section class="dashboard-summary">

        <article class="summary-card">

            <span class="summary-label">
                Total Classes
            </span>

            <strong>
                <?php echo $totalClasses; ?>
            </strong>

            <small>
                All recorded classes
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Active Classes
            </span>

            <strong>
                <?php
                echo $availableClasses;
                ?>
            </strong>

            <small>
                Available or full
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Confirmed Bookings
            </span>

            <strong>
                <?php
                echo $totalBookings;
                ?>
            </strong>

            <small>
                Across all classes
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Cancelled
            </span>

            <strong>
                <?php
                echo $cancelledClasses;
                ?>
            </strong>

            <small>
                Cancelled classes
            </small>

        </article>

    </section>


    <!-- SUCCESS -->

    <?php if ($successMessage): ?>

        <div class="attendance-alert attendance-alert-success">

            <strong>✓</strong>

            <span>
                <?php
                echo classEscape(
                    $successMessage
                );
                ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($errorMessage): ?>

        <div class="attendance-alert attendance-alert-error">

            <strong>!</strong>

            <span>
                <?php
                echo classEscape(
                    $errorMessage
                );
                ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- CREATE / EDIT FORM -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">

                    <?php
                    echo $editClass
                        ? 'EDIT CLASS'
                        : 'NEW CLASS';
                    ?>

                </span>

                <h2>

                    <?php
                    echo $editClass
                        ? 'Update Gym Class'
                        : 'Create Gym Class';
                    ?>

                </h2>

            </div>

            <?php if ($editClass): ?>

                <a
                    href="classes.php"
                    class="logout-button"
                >
                    Cancel Editing
                </a>

            <?php endif; ?>

        </div>


        <form
            method="post"
            action="classes.php"
            class="class-management-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                echo classEscape(
                    $csrfToken
                );
                ?>"
            >


            <input
                type="hidden"
                name="action"
                value="<?php
                echo $editClass
                    ? 'update'
                    : 'create';
                ?>"
            >


            <?php if ($editClass): ?>

                <input
                    type="hidden"
                    name="class_id"
                    value="<?php
                    echo (int)
                        $editClass[
                            'class_id'
                        ];
                    ?>"
                >

            <?php endif; ?>


            <div class="class-form-grid">


                <!-- CLASS NAME -->

                <div class="class-form-field">

                    <label for="class_name">
                        Class Name *
                    </label>

                    <input
                        type="text"
                        id="class_name"
                        name="class_name"
                        maxlength="100"
                        required
                        value="<?php
                        echo classEscape(
                            $editClass[
                                'class_name'
                            ] ?? ''
                        );
                        ?>"
                    >

                </div>


                <!-- TRAINER -->

                <div class="class-form-field">

                    <label for="trainer_id">
                        Trainer
                    </label>

                    <select
                        id="trainer_id"
                        name="trainer_id"
                    >

                        <option value="">
                            Unassigned
                        </option>

                        <?php
                        foreach (
                            $trainers as $trainer
                        ):
                        ?>

                            <option
                                value="<?php
                                echo (int)
                                    $trainer[
                                        'trainer_id'
                                    ];
                                ?>"
                                <?php
                                echo (
                                    $editClass &&
                                    (int)
                                    $editClass[
                                        'trainer_id'
                                    ] ===
                                    (int)
                                    $trainer[
                                        'trainer_id'
                                    ]
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo classEscape(
                                    $trainer[
                                        'first_name'
                                    ] .
                                    ' ' .
                                    $trainer[
                                        'last_name'
                                    ]
                                );
                                ?>

                                <?php
                                if (
                                    !empty(
                                        $trainer[
                                            'specialisation'
                                        ]
                                    )
                                ):
                                ?>

                                    -
                                    <?php
                                    echo classEscape(
                                        $trainer[
                                            'specialisation'
                                        ]
                                    );
                                    ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- DATE -->

                <div class="class-form-field">

                    <label for="class_date">
                        Date *
                    </label>

                    <input
                        type="date"
                        id="class_date"
                        name="class_date"
                        required
                        value="<?php
                        echo classEscape(
                            $editClass[
                                'class_date'
                            ] ?? ''
                        );
                        ?>"
                    >

                </div>


                <!-- LOCATION -->

                <div class="class-form-field">

                    <label for="location">
                        Location
                    </label>

                    <input
                        type="text"
                        id="location"
                        name="location"
                        maxlength="100"
                        value="<?php
                        echo classEscape(
                            $editClass[
                                'location'
                            ] ?? ''
                        );
                        ?>"
                    >

                </div>


                <!-- START -->

                <div class="class-form-field">

                    <label for="start_time">
                        Start Time *
                    </label>

                    <input
                        type="time"
                        id="start_time"
                        name="start_time"
                        required
                        value="<?php
                        echo classEscape(
                            $editClass[
                                'start_time'
                            ] ?? ''
                        );
                        ?>"
                    >

                </div>


                <!-- END -->

                <div class="class-form-field">

                    <label for="end_time">
                        End Time *
                    </label>

                    <input
                        type="time"
                        id="end_time"
                        name="end_time"
                        required
                        value="<?php
                        echo classEscape(
                            $editClass[
                                'end_time'
                            ] ?? ''
                        );
                        ?>"
                    >

                </div>


                <!-- CAPACITY -->

                <div class="class-form-field">

                    <label for="capacity">
                        Capacity *
                    </label>

                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        max="500"
                        required
                        value="<?php
                        echo (int)
                            (
                                $editClass[
                                    'capacity'
                                ] ?? 20
                            );
                        ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="class-form-field">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <?php

                        $currentStatus =
                            $editClass[
                                'status'
                            ] ?? 'available';

                        $statuses = [
                            'available',
                            'full',
                            'cancelled',
                            'completed'
                        ];

                        foreach (
                            $statuses as $statusOption
                        ):

                        ?>

                            <option
                                value="<?php
                                echo classEscape(
                                    $statusOption
                                );
                                ?>"
                                <?php
                                echo (
                                    $currentStatus ===
                                    $statusOption
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo classEscape(
                                    classFormat(
                                        $statusOption
                                    )
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- DESCRIPTION -->

                <div class="class-form-field class-form-full">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                    ><?php
                    echo classEscape(
                        $editClass[
                            'description'
                        ] ?? ''
                    );
                    ?></textarea>

                </div>

            </div>


            <div class="class-form-actions">

                <button
                    type="submit"
                    class="attendance-save-button"
                >

                    <?php
                    echo $editClass
                        ? 'Update Class'
                        : 'Create Class';
                    ?>

                </button>

            </div>

        </form>

    </section>


    <!-- CLASS LIST -->

    <section class="dashboard-panel">

        <div class="panel-header">

            <div>

                <span class="eyebrow">
                    CLASSES
                </span>

                <h2>
                    Scheduled Classes
                </h2>

            </div>

        </div>


        <?php if ($classes): ?>

            <div class="class-management-list">

                <?php
                foreach (
                    $classes as $class
                ):
                ?>

                    <article class="class-management-card">


                        <div class="class-card-main">

                            <div>

                                <span class="class-status-badge class-status-<?php
                                echo classEscape(
                                    $class[
                                        'status'
                                    ]
                                );
                                ?>">

                                    <?php
                                    echo classEscape(
                                        classFormat(
                                            $class[
                                                'status'
                                            ]
                                        )
                                    );
                                    ?>

                                </span>


                                <h3>
                                    <?php
                                    echo classEscape(
                                        $class[
                                            'class_name'
                                        ]
                                    );
                                    ?>
                                </h3>


                                <p>
                                    <?php

                                    if (
                                        $class[
                                            'trainer_first_name'
                                        ]
                                    ) {

                                        echo 'Trainer: ' .
                                            classEscape(
                                                $class[
                                                    'trainer_first_name'
                                                ] .
                                                ' ' .
                                                $class[
                                                    'trainer_last_name'
                                                ]
                                            );

                                    } else {

                                        echo 'Trainer: Unassigned';
                                    }

                                    ?>
                                </p>

                            </div>


                            <div class="class-card-bookings">

                                <strong>
                                    <?php
                                    echo (int)
                                        $class[
                                            'confirmed_bookings'
                                        ];
                                    ?>
                                    /
                                    <?php
                                    echo (int)
                                        $class[
                                            'capacity'
                                        ];
                                    ?>
                                </strong>

                                <span>
                                    Booked
                                </span>

                            </div>

                        </div>


                        <div class="class-card-details">

                            <div>

                                <span>
                                    Date
                                </span>

                                <strong>
                                    <?php
                                    echo classEscape(
                                        $class[
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
                                    echo classEscape(
                                        $class[
                                            'start_time'
                                        ]
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo classEscape(
                                        $class[
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
                                    echo classEscape(
                                        $class[
                                            'location'
                                        ] ??
                                        'Not assigned'
                                    );
                                    ?>
                                </strong>

                            </div>

                        </div>


                        <?php
                        if (
                            !empty(
                                $class[
                                    'description'
                                ]
                            )
                        ):
                        ?>

                            <p class="class-card-description">

                                <?php
                                echo classEscape(
                                    $class[
                                        'description'
                                    ]
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                        <div class="class-card-actions">

                            <a
                                href="classes.php?edit=<?php
                                echo (int)
                                    $class[
                                        'class_id'
                                    ];
                                ?>"
                                class="class-edit-button"
                            >
                                Edit
                            </a>


                            <?php
                            if (
                                $class[
                                    'status'
                                ] !== 'cancelled'
                            ):
                            ?>

                                <form
                                    method="post"
                                    action="classes.php"
                                    onsubmit="return confirm('Cancel this class?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php
                                        echo classEscape(
                                            $csrfToken
                                        );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    >

                                    <input
                                        type="hidden"
                                        name="class_id"
                                        value="<?php
                                        echo (int)
                                            $class[
                                                'class_id'
                                            ];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="class-cancel-button"
                                    >
                                        Cancel Class
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="attendance-empty-state">

                <strong>
                    No classes created yet
                </strong>

                <p>
                    Create the first gym class
                    using the form above.
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
            Class Management
        </p>

    </div>

</footer>

</body>

</html>