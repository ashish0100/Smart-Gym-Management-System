<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-17 - User Notifications
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
        'Please log in to access notifications.';

    header('Location: ../auth/login.php');
    exit;
}


$userId =
    (int) $_SESSION['user_id'];

$role =
    strtolower(
        trim(
            (string) ($_SESSION['role'] ?? '')
        )
    );


$isAdmin =
    $role === 'admin';

$isMember =
    $role === 'member';

$isTrainer =
    $role === 'trainer';


if (
    !$isAdmin &&
    !$isMember &&
    !$isTrainer
) {

    http_response_code(403);

    exit(
        'Access denied. Valid user access is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function notificationEscape(
    ?string $value
): string {

    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function notificationDate(
    ?string $value
): string {

    if (!$value) {
        return '-';
    }


    $timestamp =
        strtotime($value);


    if (
        $timestamp === false
    ) {

        return notificationEscape(
            $value
        );
    }


    return date(
        'd M Y, g:i A',
        $timestamp
    );
}


function notificationTypeClass(
    ?string $type
): string {

    $clean =
        preg_replace(
            '/[^a-z0-9_-]+/',
            '-',
            strtolower(
                trim(
                    (string) $type
                )
            )
        );


    return
        $clean ?: 'general';
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION[
            'notification_csrf_token'
        ]
    )
) {

    $_SESSION[
        'notification_csrf_token'
    ] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    (string)
    $_SESSION[
        'notification_csrf_token'
    ];


/*
|--------------------------------------------------------------------------
| Notification Database Structure
|--------------------------------------------------------------------------
*/

try {

    $columnStatement =
        $pdo->query(
            'SHOW COLUMNS FROM notifications'
        );


    $notificationColumns =
        $columnStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    $columnMap = [];


    foreach (
        $notificationColumns
        as $column
    ) {

        $columnMap[
            (string) $column['Field']
        ] = $column;
    }


} catch (
    PDOException $exception
) {

    error_log(
        'Notification schema error: ' .
        $exception->getMessage()
    );


    http_response_code(500);


    exit(
        'Unable to load notification database structure.'
    );
}


/*
|--------------------------------------------------------------------------
| Required Notification Columns
|--------------------------------------------------------------------------
*/

$requiredColumns = [

    'notification_id',
    'user_id',
    'title',
    'message',
    'notification_type',
    'is_read',
    'created_at'

];


foreach (
    $requiredColumns
    as $requiredColumn
) {

    if (
        !isset(
            $columnMap[
                $requiredColumn
            ]
        )
    ) {

        http_response_code(500);


        exit(
            'Notification database structure is incomplete.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Notification Type ENUM Values
|--------------------------------------------------------------------------
*/

$typeOptions = [];


$typeDefinition =
    (string) (
        $columnMap[
            'notification_type'
        ]['Type'] ?? ''
    );


if (
    stripos(
        $typeDefinition,
        'enum('
    ) === 0
) {

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $typeDefinition,
        $matches
    );


    $typeOptions =
        array_map(
            static function (
                string $value
            ): string {

                return
                    stripcslashes(
                        $value
                    );
            },
            $matches[1] ?? []
        );
}


if (!$typeOptions) {

    $typeOptions = [

        'membership',
        'booking',
        'payment',
        'class',
        'general'

    ];
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage =
    (string) (
        $_SESSION[
            'notification_success'
        ] ?? ''
    );


$errorMessage =
    (string) (
        $_SESSION[
            'notification_error'
        ] ?? ''
    );


unset(
    $_SESSION[
        'notification_success'
    ],
    $_SESSION[
        'notification_error'
    ]
);


/*
|--------------------------------------------------------------------------
| POST Requests
|--------------------------------------------------------------------------
*/

if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        (string) (
            $_POST[
                'csrf_token'
            ] ?? ''
        );


    if (
        $submittedToken === '' ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $_SESSION[
            'notification_error'
        ] =
            'Invalid request. Refresh the page and try again.';


        header(
            'Location: index.php'
        );


        exit;
    }


    $action =
        trim(
            (string) (
                $_POST[
                    'action'
                ] ?? ''
            )
        );


    /*
    |--------------------------------------------------------------------------
    | SEND NOTIFICATION
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'send_notification'
    ) {


        /*
        |--------------------------------------------------------------------------
        | Only Administrator Can Send
        |--------------------------------------------------------------------------
        */

        if (!$isAdmin) {

            http_response_code(403);


            exit(
                'Only administrators can send notifications.'
            );
        }


        $targetUser =
            trim(
                (string) (
                    $_POST[
                        'target_user'
                    ] ?? ''
                )
            );


        $title =
            trim(
                (string) (
                    $_POST[
                        'title'
                    ] ?? ''
                )
            );


        $message =
            trim(
                (string) (
                    $_POST[
                        'message'
                    ] ?? ''
                )
            );


        $notificationType =
            trim(
                (string) (
                    $_POST[
                        'notification_type'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validate Recipient
        |--------------------------------------------------------------------------
        */

        if (
            $targetUser === ''
        ) {

            $_SESSION[
                'notification_error'
            ] =
                'Please select a notification recipient.';


            header(
                'Location: index.php'
            );


            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Title
        |--------------------------------------------------------------------------
        */

        if (
            $title === '' ||
            mb_strlen(
                $title
            ) > 150
        ) {

            $_SESSION[
                'notification_error'
            ] =
                'Enter a valid notification title.';


            header(
                'Location: index.php'
            );


            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Message
        |--------------------------------------------------------------------------
        */

        if (
            $message === '' ||
            mb_strlen(
                $message
            ) > 2000
        ) {

            $_SESSION[
                'notification_error'
            ] =
                'Enter a valid notification message.';


            header(
                'Location: index.php'
            );


            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Type
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $notificationType,
                $typeOptions,
                true
            )
        ) {

            $_SESSION[
                'notification_error'
            ] =
                'Invalid notification type.';


            header(
                'Location: index.php'
            );


            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Send Notification
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Send To All Active Users
            |--------------------------------------------------------------------------
            */

            if (
                $targetUser === 'all'
            ) {


                $usersStatement =
                    $pdo->query(
                        "SELECT
                            user_id

                         FROM users

                         WHERE status =
                            'active'

                         ORDER BY
                            user_id"
                    );


                $targetUsers =
                    $usersStatement->fetchAll(
                        PDO::FETCH_COLUMN
                    );


                if (!$targetUsers) {

                    throw new RuntimeException(
                        'No active users are available.'
                    );
                }


                $insertStatement =
                    $pdo->prepare(
                        'INSERT INTO notifications

                        (
                            user_id,
                            title,
                            message,
                            notification_type,
                            is_read,
                            created_at
                        )

                        VALUES

                        (
                            :user_id,
                            :title,
                            :message,
                            :notification_type,
                            0,
                            CURRENT_TIMESTAMP
                        )'
                    );


                foreach (
                    $targetUsers
                    as $targetUserId
                ) {

                    $insertStatement->execute(
                        [

                            'user_id' =>
                                (int)
                                $targetUserId,

                            'title' =>
                                $title,

                            'message' =>
                                $message,

                            'notification_type' =>
                                $notificationType

                        ]
                    );
                }


                $sentCount =
                    count(
                        $targetUsers
                    );


                $_SESSION[
                    'notification_success'
                ] =
                    "Notification sent to {$sentCount} users.";


            } else {


                /*
                |--------------------------------------------------------------------------
                | Send To Individual User
                |--------------------------------------------------------------------------
                */

                $targetUserId =
                    filter_var(
                        $targetUser,
                        FILTER_VALIDATE_INT
                    );


                if (!$targetUserId) {

                    throw new RuntimeException(
                        'Invalid notification recipient.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Confirm User Exists
                |--------------------------------------------------------------------------
                */

                $userCheck =
                    $pdo->prepare(
                        'SELECT
                            COUNT(*)

                         FROM users

                         WHERE user_id =
                            :user_id'
                    );


                $userCheck->execute(
                    [
                        'user_id' =>
                            $targetUserId
                    ]
                );


                if (
                    (int)
                    $userCheck->fetchColumn()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'Selected user does not exist.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Insert Notification
                |--------------------------------------------------------------------------
                */

                $insertStatement =
                    $pdo->prepare(
                        'INSERT INTO notifications

                        (
                            user_id,
                            title,
                            message,
                            notification_type,
                            is_read,
                            created_at
                        )

                        VALUES

                        (
                            :user_id,
                            :title,
                            :message,
                            :notification_type,
                            0,
                            CURRENT_TIMESTAMP
                        )'
                    );


                $insertStatement->execute(
                    [

                        'user_id' =>
                            $targetUserId,

                        'title' =>
                            $title,

                        'message' =>
                            $message,

                        'notification_type' =>
                            $notificationType

                    ]
                );


                $_SESSION[
                    'notification_success'
                ] =
                    'Notification sent successfully.';
            }


            $pdo->commit();


        } catch (
            RuntimeException $exception
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $_SESSION[
                'notification_error'
            ] =
                $exception->getMessage();


        } catch (
            PDOException $exception
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            error_log(
                'Notification sending error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'notification_error'
            ] =
                'Unable to send notification.';
        }


        header(
            'Location: index.php'
        );


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | MARK SINGLE NOTIFICATION READ
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'mark_read'
    ) {


        $notificationId =
            filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );


        if (!$notificationId) {

            $_SESSION[
                'notification_error'
            ] =
                'Invalid notification selected.';


            header(
                'Location: index.php'
            );


            exit;
        }


        try {

            $statement =
                $pdo->prepare(
                    'UPDATE notifications

                     SET
                        is_read = 1

                     WHERE notification_id =
                        :notification_id

                     AND user_id =
                        :user_id'
                );


            $statement->execute(
                [

                    'notification_id' =>
                        $notificationId,

                    'user_id' =>
                        $userId

                ]
            );


            $_SESSION[
                'notification_success'
            ] =
                'Notification marked as read.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Notification read error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'notification_error'
            ] =
                'Unable to update notification.';
        }


        header(
            'Location: index.php'
        );


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | MARK SINGLE NOTIFICATION UNREAD
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'mark_unread'
    ) {


        $notificationId =
            filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );


        if (!$notificationId) {

            $_SESSION[
                'notification_error'
            ] =
                'Invalid notification selected.';


            header(
                'Location: index.php'
            );


            exit;
        }


        try {

            $statement =
                $pdo->prepare(
                    'UPDATE notifications

                     SET
                        is_read = 0

                     WHERE notification_id =
                        :notification_id

                     AND user_id =
                        :user_id'
                );


            $statement->execute(
                [

                    'notification_id' =>
                        $notificationId,

                    'user_id' =>
                        $userId

                ]
            );


            $_SESSION[
                'notification_success'
            ] =
                'Notification marked as unread.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Notification unread error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'notification_error'
            ] =
                'Unable to update notification.';
        }


        header(
            'Location: index.php'
        );


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | MARK ALL READ
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'mark_all_read'
    ) {


        try {

            $statement =
                $pdo->prepare(
                    'UPDATE notifications

                     SET
                        is_read = 1

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


            $_SESSION[
                'notification_success'
            ] =
                'All notifications marked as read.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Notification mark-all error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'notification_error'
            ] =
                'Unable to update notifications.';
        }


        header(
            'Location: index.php'
        );


        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'delete_notification'
    ) {


        $notificationId =
            filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );


        if (!$notificationId) {

            $_SESSION[
                'notification_error'
            ] =
                'Invalid notification selected.';


            header(
                'Location: index.php'
            );


            exit;
        }


        try {

            $statement =
                $pdo->prepare(
                    'DELETE FROM notifications

                     WHERE notification_id =
                        :notification_id

                     AND user_id =
                        :user_id'
                );


            $statement->execute(
                [

                    'notification_id' =>
                        $notificationId,

                    'user_id' =>
                        $userId

                ]
            );


            $_SESSION[
                'notification_success'
            ] =
                'Notification deleted successfully.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Notification delete error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'notification_error'
            ] =
                'Unable to delete notification.';
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

    $_SESSION[
        'notification_error'
    ] =
        'Invalid notification action.';


    header(
        'Location: index.php'
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| Load Users For Administrator
|--------------------------------------------------------------------------
*/

$users = [];


if ($isAdmin) {

    try {

        $usersStatement =
            $pdo->query(
                'SELECT

                    user_id,
                    first_name,
                    last_name,
                    email,
                    role,
                    status

                 FROM users

                 ORDER BY
                    first_name,
                    last_name'
            );


        $users =
            $usersStatement->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (
        PDOException $exception
    ) {

        error_log(
            'Notification user list error: ' .
            $exception->getMessage()
        );


        $errorMessage =
            'Unable to load notification recipients.';
    }
}


/*
|--------------------------------------------------------------------------
| Load Current User Notifications
|--------------------------------------------------------------------------
*/

try {

    $notificationStatement =
        $pdo->prepare(
            'SELECT

                notification_id,
                user_id,
                title,
                message,

                notification_type
                    AS type,

                is_read,
                created_at

             FROM notifications

             WHERE user_id =
                :user_id

             ORDER BY

                created_at DESC,
                notification_id DESC'
        );


    $notificationStatement->execute(
        [
            'user_id' =>
                $userId
        ]
    );


    $notifications =
        $notificationStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $exception
) {

    error_log(
        'Notification loading error: ' .
        $exception->getMessage()
    );


    $notifications = [];


    $errorMessage =
        'Unable to load notifications.';
}


/*
|--------------------------------------------------------------------------
| Administrator Notification History
|--------------------------------------------------------------------------
*/

$sentNotifications = [];


if ($isAdmin) {

    try {

        $sentStatement =
            $pdo->query(
                'SELECT

                    n.notification_id,
                    n.title,
                    n.message,

                    n.notification_type
                        AS type,

                    n.is_read,
                    n.created_at,

                    u.first_name,
                    u.last_name,
                    u.email,
                    u.role

                 FROM notifications n

                 INNER JOIN users u

                    ON n.user_id =
                        u.user_id

                 ORDER BY

                    n.created_at DESC,
                    n.notification_id DESC

                 LIMIT 30'
            );


        $sentNotifications =
            $sentStatement->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (
        PDOException $exception
    ) {

        error_log(
            'Notification admin history error: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalNotifications =
    count(
        $notifications
    );


$unreadNotifications = 0;

$readNotifications = 0;


foreach (
    $notifications
    as $notification
) {

    if (
        (int)
        $notification[
            'is_read'
        ] === 1
    ) {

        $readNotifications++;

    } else {

        $unreadNotifications++;
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard Navigation
|--------------------------------------------------------------------------
*/

if ($isAdmin) {

    $dashboardUrl =
        '../admin/dashboard.php';

    $roleLabel =
        'Administrator';


} elseif ($isTrainer) {

    $dashboardUrl =
        '../trainer/dashboard.php';

    $roleLabel =
        'Trainer';


} else {

    $dashboardUrl =
        '../member/dashboard.php';

    $roleLabel =
        'Member';
}


$displayName =
    (string) (
        $_SESSION[
            'first_name'
        ] ?? $roleLabel
    );

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
        Notifications | World Fitness Australia
    </title>


    <link
        rel="stylesheet"
        href="../css/style.css?v=10"
    >

</head>


<body
    class="
        dashboard-page
        notification-page
    "
>


<!--
==========================================================================
HEADER
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
            href="<?=
                notificationEscape(
                    $dashboardUrl
                )
            ?>"
            class="logo"
        >

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Notifications
                </small>

            </div>

        </a>


        <div class="dashboard-user">


            <div class="user-text">

                <span>

                    <?=
                        notificationEscape(
                            $roleLabel
                        )
                    ?>

                </span>


                <strong>

                    <?=
                        notificationEscape(
                            $displayName
                        )
                    ?>

                </strong>

            </div>


            <a
                href="<?=
                    notificationEscape(
                        $dashboardUrl
                    )
                ?>"
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


<!--
==========================================================================
MAIN
==========================================================================
-->

<main class="dashboard-main">

<div class="container">


    <!--
    ======================================================================
    HERO
    ======================================================================
    -->

    <section class="notification-hero">

        <span class="eyebrow">
            NOTIFICATIONS
        </span>


        <h1>

            <?php if ($isAdmin): ?>

                Manage user notifications.

            <?php else: ?>

                Your notifications.

            <?php endif; ?>

        </h1>


        <p>

            <?php if ($isAdmin): ?>

                Send updates to members and trainers
                and review recently delivered messages.

            <?php else: ?>

                View booking, payment, membership
                and gym updates from World Fitness Australia.

            <?php endif; ?>

        </p>

    </section>


    <!--
    ======================================================================
    SUCCESS MESSAGE
    ======================================================================
    -->

    <?php if (
        $successMessage
    ): ?>

        <div
            class="
                notification-alert
                notification-alert-success
            "
        >

            ✓

            <?=
                notificationEscape(
                    $successMessage
                )
            ?>

        </div>

    <?php endif; ?>


    <!--
    ======================================================================
    ERROR MESSAGE
    ======================================================================
    -->

    <?php if (
        $errorMessage
    ): ?>

        <div
            class="
                notification-alert
                notification-alert-error
            "
        >

            !

            <?=
                notificationEscape(
                    $errorMessage
                )
            ?>

        </div>

    <?php endif; ?>


    <!--
    ======================================================================
    SUMMARY
    ======================================================================
    -->

    <section
        class="
            notification-summary-grid
        "
    >


        <article
            class="
                notification-summary-card
            "
        >

            <span>
                Total Notifications
            </span>

            <strong>
                <?= $totalNotifications ?>
            </strong>

            <small>
                Notifications in your inbox
            </small>

        </article>


        <article
            class="
                notification-summary-card
            "
        >

            <span>
                Unread
            </span>

            <strong>
                <?= $unreadNotifications ?>
            </strong>

            <small>
                Require your attention
            </small>

        </article>


        <article
            class="
                notification-summary-card
            "
        >

            <span>
                Read
            </span>

            <strong>
                <?= $readNotifications ?>
            </strong>

            <small>
                Previously viewed messages
            </small>

        </article>


    </section>


    <!--
    ======================================================================
    ADMIN SEND NOTIFICATION
    ======================================================================
    -->

    <?php if ($isAdmin): ?>


        <section
            class="
                notification-panel
            "
        >


            <div
                class="
                    notification-heading
                "
            >

                <span class="eyebrow">
                    NEW NOTIFICATION
                </span>


                <h2>
                    Send Notification
                </h2>


                <p>
                    Send an individual or system-wide
                    notification to Smart Gym users.
                </p>

            </div>


            <form
                method="POST"
                action="index.php"
                class="notification-form"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?=
                        notificationEscape(
                            $csrfToken
                        )
                    ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="send_notification"
                >


                <div
                    class="
                        notification-form-grid
                    "
                >


                    <!-- RECIPIENT -->

                    <div
                        class="
                            notification-field
                        "
                    >

                        <label
                            for="target_user"
                        >
                            Recipient *
                        </label>


                        <select
                            id="target_user"
                            name="target_user"
                            required
                        >

                            <option value="">
                                Select recipient
                            </option>


                            <option value="all">
                                All Active Users
                            </option>


                            <?php foreach (
                                $users
                                as $user
                            ): ?>


                                <option
                                    value="<?=
                                        (int)
                                        $user[
                                            'user_id'
                                        ]
                                    ?>"
                                >

                                    <?=
                                        notificationEscape(

                                            $user[
                                                'first_name'
                                            ] .

                                            ' ' .

                                            $user[
                                                'last_name'
                                            ] .

                                            ' — ' .

                                            $user[
                                                'email'
                                            ] .

                                            ' (' .

                                            ucfirst(
                                                $user[
                                                    'role'
                                                ]
                                            ) .

                                            ')'

                                        )
                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- TYPE -->

                    <div
                        class="
                            notification-field
                        "
                    >

                        <label
                            for="notification_type"
                        >
                            Notification Type *
                        </label>


                        <select
                            id="notification_type"
                            name="notification_type"
                            required
                        >


                            <?php foreach (
                                $typeOptions
                                as $typeOption
                            ): ?>


                                <option
                                    value="<?=
                                        notificationEscape(
                                            $typeOption
                                        )
                                    ?>"
                                >

                                    <?=
                                        notificationEscape(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $typeOption
                                                )
                                            )
                                        )
                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- TITLE -->

                    <div
                        class="
                            notification-field
                            notification-field-full
                        "
                    >

                        <label
                            for="title"
                        >
                            Title *
                        </label>


                        <input
                            id="title"
                            name="title"
                            type="text"
                            maxlength="150"
                            placeholder="Example: Membership Reminder"
                            required
                        >

                    </div>


                    <!-- MESSAGE -->

                    <div
                        class="
                            notification-field
                            notification-field-full
                        "
                    >

                        <label
                            for="message"
                        >
                            Message *
                        </label>


                        <textarea
                            id="message"
                            name="message"
                            rows="5"
                            maxlength="2000"
                            placeholder="Enter notification message..."
                            required
                        ></textarea>

                    </div>


                </div>


                <div
                    class="
                        notification-form-actions
                    "
                >

                    <button
                        type="submit"
                        class="
                            notification-primary-button
                        "
                    >

                        Send Notification

                    </button>

                </div>


            </form>


        </section>


    <?php endif; ?>


    <!--
    ======================================================================
    USER NOTIFICATION INBOX
    ======================================================================
    -->

    <section
        class="
            notification-panel
        "
    >


        <div
            class="
                notification-heading-row
            "
        >


            <div
                class="
                    notification-heading
                "
            >

                <span class="eyebrow">
                    INBOX
                </span>


                <h2>
                    My Notifications
                </h2>


                <p>
                    Messages sent to your Smart Gym account.
                </p>

            </div>


            <?php if (
                $unreadNotifications > 0
            ): ?>


                <form
                    method="POST"
                    action="index.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?=
                            notificationEscape(
                                $csrfToken
                            )
                        ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >


                    <button
                        type="submit"
                        class="
                            notification-secondary-button
                        "
                    >

                        Mark All Read

                    </button>

                </form>


            <?php endif; ?>


        </div>


        <?php if (!$notifications): ?>


            <div
                class="
                    notification-empty
                "
            >

                You currently have no notifications.

            </div>


        <?php else: ?>


            <div
                class="
                    notification-list
                "
            >


                <?php foreach (
                    $notifications
                    as $notification
                ): ?>


                    <?php

                    $isRead =
                        (int)
                        $notification[
                            'is_read'
                        ] === 1;

                    ?>


                    <article
                        class="
                            notification-card
                            <?=
                                $isRead

                                    ? 'notification-card-read'

                                    : 'notification-card-unread'
                            ?>
                        "
                    >


                        <div
                            class="
                                notification-card-top
                            "
                        >


                            <div>


                                <span
                                    class="
                                        notification-type

                                        notification-type-<?=
                                            notificationEscape(
                                                notificationTypeClass(
                                                    (string)
                                                    $notification[
                                                        'type'
                                                    ]
                                                )
                                            )
                                        ?>
                                    "
                                >

                                    <?=
                                        notificationEscape(
                                            strtoupper(
                                                (string)
                                                $notification[
                                                    'type'
                                                ]
                                            )
                                        )
                                    ?>

                                </span>


                                <?php if (!$isRead): ?>


                                    <span
                                        class="
                                            notification-unread-dot
                                        "
                                    >

                                        NEW

                                    </span>


                                <?php endif; ?>


                            </div>


                            <time>

                                <?=
                                    notificationDate(
                                        (string)
                                        $notification[
                                            'created_at'
                                        ]
                                    )
                                ?>

                            </time>


                        </div>


                        <h3>

                            <?=
                                notificationEscape(
                                    (string)
                                    $notification[
                                        'title'
                                    ]
                                )
                            ?>

                        </h3>


                        <p>

                            <?=
                                nl2br(
                                    notificationEscape(
                                        (string)
                                        $notification[
                                            'message'
                                        ]
                                    )
                                )
                            ?>

                        </p>


                        <div
                            class="
                                notification-actions
                            "
                        >


                            <?php if (!$isRead): ?>


                                <form
                                    method="POST"
                                    action="index.php"
                                >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?=
                                            notificationEscape(
                                                $csrfToken
                                            )
                                        ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >


                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?=
                                            (int)
                                            $notification[
                                                'notification_id'
                                            ]
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            notification-small-button
                                        "
                                    >

                                        Mark Read

                                    </button>


                                </form>


                            <?php else: ?>


                                <form
                                    method="POST"
                                    action="index.php"
                                >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?=
                                            notificationEscape(
                                                $csrfToken
                                            )
                                        ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_unread"
                                    >


                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?=
                                            (int)
                                            $notification[
                                                'notification_id'
                                            ]
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            notification-secondary-button
                                        "
                                    >

                                        Mark Unread

                                    </button>


                                </form>


                            <?php endif; ?>


                            <form
                                method="POST"
                                action="index.php"

                                onsubmit="
                                    return confirm(
                                        'Delete this notification?'
                                    );
                                "
                            >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?=
                                        notificationEscape(
                                            $csrfToken
                                        )
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_notification"
                                >


                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?=
                                        (int)
                                        $notification[
                                            'notification_id'
                                        ]
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    class="
                                        notification-delete-button
                                    "
                                >

                                    Delete

                                </button>


                            </form>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </section>


    <!--
    ======================================================================
    ADMIN DELIVERY HISTORY
    ======================================================================
    -->

    <?php if ($isAdmin): ?>


        <section
            class="
                notification-panel
            "
        >


            <div
                class="
                    notification-heading
                "
            >

                <span class="eyebrow">
                    DELIVERY HISTORY
                </span>


                <h2>
                    Recent Notifications
                </h2>


                <p>
                    Recently generated notifications
                    across Smart Gym accounts.
                </p>

            </div>


            <?php if (
                !$sentNotifications
            ): ?>


                <div
                    class="
                        notification-empty
                    "
                >

                    No notifications have been sent yet.

                </div>


            <?php else: ?>


                <div
                    class="
                        notification-table-wrap
                    "
                >


                    <table
                        class="
                            notification-table
                        "
                    >


                        <thead>


                            <tr>

                                <th>
                                    Recipient
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Title
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                        <?php foreach (
                            $sentNotifications
                            as $sentNotification
                        ): ?>


                            <tr>


                                <!-- RECIPIENT -->

                                <td>

                                    <strong>

                                        <?=
                                            notificationEscape(
                                                $sentNotification[
                                                    'first_name'
                                                ] .
                                                ' ' .
                                                $sentNotification[
                                                    'last_name'
                                                ]
                                            )
                                        ?>

                                    </strong>


                                    <small>

                                        <?=
                                            notificationEscape(
                                                $sentNotification[
                                                    'email'
                                                ]
                                            )
                                        ?>

                                    </small>

                                </td>


                                <!-- TYPE -->

                                <td>

                                    <?=
                                        notificationEscape(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string)
                                                    $sentNotification[
                                                        'type'
                                                    ]
                                                )
                                            )
                                        )
                                    ?>

                                </td>


                                <!-- TITLE -->

                                <td>

                                    <?=
                                        notificationEscape(
                                            (string)
                                            $sentNotification[
                                                'title'
                                            ]
                                        )
                                    ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?=
                                        notificationDate(
                                            (string)
                                            $sentNotification[
                                                'created_at'
                                            ]
                                        )
                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>


                                    <?php if (
                                        (int)
                                        $sentNotification[
                                            'is_read'
                                        ] === 1
                                    ): ?>


                                        <span
                                            class="
                                                notification-delivery-read
                                            "
                                        >

                                            READ

                                        </span>


                                    <?php else: ?>


                                        <span
                                            class="
                                                notification-delivery-unread
                                            "
                                        >

                                            UNREAD

                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </section>


    <?php endif; ?>


</div>

</main>


<!--
==========================================================================
FOOTER
==========================================================================
-->

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
            Notification Management
        </p>


    </div>


</footer>


</body>

</html>