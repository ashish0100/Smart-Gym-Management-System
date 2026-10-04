<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-15 - Membership Management Improvements
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
        'Please log in to access membership management.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Role Check
|--------------------------------------------------------------------------
*/

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


if (
    !$isAdmin &&
    !$isMember
) {

    http_response_code(403);

    exit(
        'Access denied. Administrator or member access is required.'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['membership_csrf_token']
    )
) {

    $_SESSION['membership_csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    (string)
    $_SESSION['membership_csrf_token'];


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function membershipEscape(
    ?string $value
): string {

    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function membershipDate(
    ?string $value
): string {

    if (!$value) {
        return 'Not set';
    }

    $timestamp =
        strtotime($value);

    if (
        $timestamp === false
    ) {
        return membershipEscape(
            $value
        );
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function membershipStatusClass(
    ?string $status
): string {

    $status =
        strtolower(
            trim(
                (string) $status
            )
        );

    return match ($status) {

        'active' =>
            'membership-status-active',

        'pending' =>
            'membership-status-pending',

        'paused' =>
            'membership-status-paused',

        'expired' =>
            'membership-status-expired',

        'cancelled' =>
            'membership-status-cancelled',

        default =>
            'membership-status-default'
    };
}


/*
|--------------------------------------------------------------------------
| Read Membership Table Structure
|--------------------------------------------------------------------------
*/

function membershipColumns(
    PDO $pdo
): array {

    $rows =
        $pdo
            ->query(
                'SHOW COLUMNS FROM memberships'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

    $columns = [];

    foreach (
        $rows as $row
    ) {

        $columns[
            (string) $row['Field']
        ] = $row;
    }

    return $columns;
}


/*
|--------------------------------------------------------------------------
| Extract ENUM Values
|--------------------------------------------------------------------------
*/

function membershipEnumValues(
    ?string $type
): array {

    if (
        !$type ||
        stripos(
            $type,
            'enum('
        ) !== 0
    ) {

        return [];
    }

    preg_match_all(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        $type,
        $matches
    );

    return array_map(
        static function (
            string $value
        ): string {

            return stripcslashes(
                $value
            );
        },
        $matches[1] ?? []
    );
}


/*
|--------------------------------------------------------------------------
| Detect Membership Schema
|--------------------------------------------------------------------------
*/

try {

    $membershipColumns =
        membershipColumns(
            $pdo
        );

} catch (
    PDOException $exception
) {

    error_log(
        'Membership schema error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load membership database structure.'
    );
}


/*
|--------------------------------------------------------------------------
| Required Columns
|--------------------------------------------------------------------------
*/

$requiredColumns = [

    'membership_id',
    'member_id',
    'membership_type',
    'access_type',
    'start_date',
    'end_date',
    'status',
    'remaining_visits'

];


foreach (
    $requiredColumns
    as $requiredColumn
) {

    if (
        !isset(
            $membershipColumns[
                $requiredColumn
            ]
        )
    ) {

        http_response_code(500);

        exit(
            'Membership database structure is incomplete.'
        );
    }
}


$hasPausedAt =
    isset(
        $membershipColumns[
            'paused_at'
        ]
    );


/*
|--------------------------------------------------------------------------
| Status Options
|--------------------------------------------------------------------------
*/

$statusOptions =
    membershipEnumValues(
        (string) (
            $membershipColumns[
                'status'
            ]['Type'] ?? ''
        )
    );


if (!$statusOptions) {

    $statusOptions = [

        'pending',
        'active',
        'paused',
        'expired',
        'cancelled'

    ];
}


/*
|--------------------------------------------------------------------------
| Membership Type Options
|--------------------------------------------------------------------------
*/

$membershipTypeOptions =
    membershipEnumValues(
        (string) (
            $membershipColumns[
                'membership_type'
            ]['Type'] ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| Access Type Options
|--------------------------------------------------------------------------
*/

$accessTypeOptions =
    membershipEnumValues(
        (string) (
            $membershipColumns[
                'access_type'
            ]['Type'] ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage =
    (string) (
        $_SESSION[
            'membership_success'
        ] ?? ''
    );


$errorMessage =
    (string) (
        $_SESSION[
            'membership_error'
        ] ?? ''
    );


unset(
    $_SESSION[
        'membership_success'
    ],
    $_SESSION[
        'membership_error'
    ]
);


/*
|--------------------------------------------------------------------------
| Resolve Logged-In Member
|--------------------------------------------------------------------------
*/

$currentMember = null;

$currentMemberId = null;


if ($isMember) {

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
                    ON m.user_id =
                        u.user_id

                 WHERE u.user_id =
                    :user_id

                 LIMIT 1'
            );


        $memberStatement->execute(
            [
                'user_id' =>
                    $userId
            ]
        );


        $currentMember =
            $memberStatement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$currentMember) {

            http_response_code(403);

            exit(
                'Member profile could not be found.'
            );
        }


        $currentMemberId =
            (int)
            $currentMember[
                'member_id'
            ];


    } catch (
        PDOException $exception
    ) {

        error_log(
            'Membership member lookup error: ' .
            $exception->getMessage()
        );

        http_response_code(500);

        exit(
            'Unable to load member information.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF
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
            'membership_error'
        ] =
            'Invalid request. Refresh the page and try again.';

        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Administrator Can Modify Memberships
    |--------------------------------------------------------------------------
    */

    if (!$isAdmin) {

        http_response_code(403);

        exit(
            'Only administrators can modify memberships.'
        );
    }


    $action =
        trim(
            (string) (
                $_POST[
                    'action'
                ] ?? ''
            )
        );


    $membershipId =
        filter_input(
            INPUT_POST,
            'membership_id',
            FILTER_VALIDATE_INT
        );


    if (!$membershipId) {

        $_SESSION[
            'membership_error'
        ] =
            'Invalid membership selected.';

        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'update_status'
    ) {


        $newStatus =
            trim(
                (string) (
                    $_POST[
                        'status'
                    ] ?? ''
                )
            );


        if (
            !in_array(
                $newStatus,
                $statusOptions,
                true
            )
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Invalid membership status.';

            header(
                'Location: index.php'
            );

            exit;
        }


        try {

            if ($hasPausedAt) {

                if (
                    $newStatus ===
                    'paused'
                ) {

                    $sql =
                        'UPDATE memberships

                         SET
                            status = :status,
                            paused_at =
                                CURRENT_TIMESTAMP

                         WHERE membership_id =
                            :membership_id';

                } else {

                    $sql =
                        'UPDATE memberships

                         SET
                            status = :status,
                            paused_at = NULL

                         WHERE membership_id =
                            :membership_id';
                }

            } else {

                $sql =
                    'UPDATE memberships

                     SET
                        status = :status

                     WHERE membership_id =
                        :membership_id';
            }


            $statement =
                $pdo->prepare(
                    $sql
                );


            $statement->execute(
                [
                    'status' =>
                        $newStatus,

                    'membership_id' =>
                        $membershipId
                ]
            );


            $_SESSION[
                'membership_success'
            ] =
                'Membership status updated successfully.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Membership status error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'membership_error'
            ] =
                'Unable to update membership status.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RENEW MEMBERSHIP
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'renew_membership'
    ) {


        $extensionDays =
            filter_input(
                INPUT_POST,
                'extension_days',
                FILTER_VALIDATE_INT
            );


        $allowedExtensions = [

            30,
            90,
            180,
            365

        ];


        if (
            !$extensionDays ||
            !in_array(
                $extensionDays,
                $allowedExtensions,
                true
            )
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Invalid renewal period.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Determine Best Active Status
        |--------------------------------------------------------------------------
        */

        $renewedStatus =
            in_array(
                'active',
                $statusOptions,
                true
            )
                ? 'active'
                : $statusOptions[0];


        try {

            /*
            |--------------------------------------------------------------------------
            | Extension Days Is Safe To Interpolate
            | Because It Is Validated Against Fixed Integers Above
            |--------------------------------------------------------------------------
            */

            if ($hasPausedAt) {

                $sql =

                    "UPDATE memberships

                     SET

                        end_date =
                            DATE_ADD(

                                GREATEST(

                                    COALESCE(
                                        end_date,
                                        CURDATE()
                                    ),

                                    CURDATE()

                                ),

                                INTERVAL
                                {$extensionDays}
                                DAY
                            ),

                        status =
                            :status,

                        paused_at =
                            NULL

                     WHERE membership_id =
                        :membership_id";

            } else {

                $sql =

                    "UPDATE memberships

                     SET

                        end_date =
                            DATE_ADD(

                                GREATEST(

                                    COALESCE(
                                        end_date,
                                        CURDATE()
                                    ),

                                    CURDATE()

                                ),

                                INTERVAL
                                {$extensionDays}
                                DAY
                            ),

                        status =
                            :status

                     WHERE membership_id =
                        :membership_id";
            }


            $statement =
                $pdo->prepare(
                    $sql
                );


            $statement->execute(
                [
                    'status' =>
                        $renewedStatus,

                    'membership_id' =>
                        $membershipId
                ]
            );


            $_SESSION[
                'membership_success'
            ] =
                "Membership renewed for {$extensionDays} days.";


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Membership renewal error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'membership_error'
            ] =
                'Unable to renew membership.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE REMAINING VISITS
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'update_visits'
    ) {


        $visitsRaw =
            trim(
                (string) (
                    $_POST[
                        'remaining_visits'
                    ] ?? ''
                )
            );


        $remainingVisits = null;


        if (
            $visitsRaw !== ''
        ) {

            $validatedVisits =
                filter_var(
                    $visitsRaw,
                    FILTER_VALIDATE_INT
                );


            if (
                $validatedVisits === false ||
                $validatedVisits < 0
            ) {

                $_SESSION[
                    'membership_error'
                ] =
                    'Remaining visits must be zero or greater.';

                header(
                    'Location: index.php'
                );

                exit;
            }


            $remainingVisits =
                (int)
                $validatedVisits;
        }


        try {

            $statement =
                $pdo->prepare(
                    'UPDATE memberships

                     SET remaining_visits =
                        :remaining_visits

                     WHERE membership_id =
                        :membership_id'
                );


            $statement->bindValue(
                ':membership_id',
                $membershipId,
                PDO::PARAM_INT
            );


            if (
                $remainingVisits === null
            ) {

                $statement->bindValue(
                    ':remaining_visits',
                    null,
                    PDO::PARAM_NULL
                );

            } else {

                $statement->bindValue(
                    ':remaining_visits',
                    $remainingVisits,
                    PDO::PARAM_INT
                );
            }


            $statement->execute();


            $_SESSION[
                'membership_success'
            ] =
                'Remaining visits updated successfully.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Membership visits error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'membership_error'
            ] =
                'Unable to update remaining visits.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE MEMBERSHIP DETAILS
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'update_details'
    ) {


        $membershipType =
            trim(
                (string) (
                    $_POST[
                        'membership_type'
                    ] ?? ''
                )
            );


        $accessType =
            trim(
                (string) (
                    $_POST[
                        'access_type'
                    ] ?? ''
                )
            );


        $startDate =
            trim(
                (string) (
                    $_POST[
                        'start_date'
                    ] ?? ''
                )
            );


        $endDate =
            trim(
                (string) (
                    $_POST[
                        'end_date'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if (
            $membershipType === '' ||
            $accessType === '' ||
            $startDate === '' ||
            $endDate === ''
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Complete all membership details.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | ENUM Validation
        |--------------------------------------------------------------------------
        */

        if (
            $membershipTypeOptions &&
            !in_array(
                $membershipType,
                $membershipTypeOptions,
                true
            )
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Invalid membership type.';

            header(
                'Location: index.php'
            );

            exit;
        }


        if (
            $accessTypeOptions &&
            !in_array(
                $accessType,
                $accessTypeOptions,
                true
            )
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Invalid access type.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Date Validation
        |--------------------------------------------------------------------------
        */

        $startTimestamp =
            strtotime(
                $startDate
            );


        $endTimestamp =
            strtotime(
                $endDate
            );


        if (
            $startTimestamp === false ||
            $endTimestamp === false
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'Invalid membership dates.';

            header(
                'Location: index.php'
            );

            exit;
        }


        if (
            $endTimestamp <
            $startTimestamp
        ) {

            $_SESSION[
                'membership_error'
            ] =
                'End date cannot be before start date.';

            header(
                'Location: index.php'
            );

            exit;
        }


        try {

            $statement =
                $pdo->prepare(
                    'UPDATE memberships

                     SET

                        membership_type =
                            :membership_type,

                        access_type =
                            :access_type,

                        start_date =
                            :start_date,

                        end_date =
                            :end_date

                     WHERE membership_id =
                        :membership_id'
                );


            $statement->execute(
                [
                    'membership_type' =>
                        $membershipType,

                    'access_type' =>
                        $accessType,

                    'start_date' =>
                        $startDate,

                    'end_date' =>
                        $endDate,

                    'membership_id' =>
                        $membershipId
                ]
            );


            $_SESSION[
                'membership_success'
            ] =
                'Membership details updated successfully.';


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Membership details error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'membership_error'
            ] =
                'Unable to update membership details.';
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
        'membership_error'
    ] =
        'Invalid membership action.';


    header(
        'Location: index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Membership Records
|--------------------------------------------------------------------------
*/

$membershipSql =

    'SELECT

        ms.membership_id,
        ms.member_id,
        ms.membership_type,
        ms.access_type,
        ms.start_date,
        ms.end_date,
        ms.status,
        ms.remaining_visits';


if ($hasPausedAt) {

    $membershipSql .=
        ', ms.paused_at';

} else {

    $membershipSql .=
        ', NULL AS paused_at';
}


$membershipSql .=

    ',

        u.first_name,
        u.last_name,
        u.email

     FROM memberships ms

     INNER JOIN members m
        ON ms.member_id =
            m.member_id

     INNER JOIN users u
        ON m.user_id =
            u.user_id';


$membershipParameters = [];


/*
|--------------------------------------------------------------------------
| Members Only See Their Own Memberships
|--------------------------------------------------------------------------
*/

if ($isMember) {

    $membershipSql .=

        ' WHERE ms.member_id =
            :member_id';


    $membershipParameters[
        'member_id'
    ] =
        $currentMemberId;
}


$membershipSql .=

    ' ORDER BY
        ms.membership_id DESC';


try {

    $membershipStatement =
        $pdo->prepare(
            $membershipSql
        );


    $membershipStatement->execute(
        $membershipParameters
    );


    $memberships =
        $membershipStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $exception
) {

    error_log(
        'Membership loading error: ' .
        $exception->getMessage()
    );


    $memberships = [];


    $errorMessage =
        'Unable to load memberships.';
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalMemberships =
    count($memberships);


$activeMemberships = 0;

$pendingMemberships = 0;

$pausedMemberships = 0;

$expiredMemberships = 0;


foreach (
    $memberships
    as $membership
) {

    $membershipStatus =
        strtolower(
            (string)
            $membership[
                'status'
            ]
        );


    switch (
        $membershipStatus
    ) {

        case 'active':

            $activeMemberships++;

            break;


        case 'pending':

            $pendingMemberships++;

            break;


        case 'paused':

            $pausedMemberships++;

            break;


        case 'expired':

            $expiredMemberships++;

            break;
    }
}


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

$dashboardUrl =

    $isAdmin

        ? '../admin/dashboard.php'

        : '../member/dashboard.php';


$roleLabel =

    $isAdmin

        ? 'Administrator'

        : 'Member';


$displayName =

    $isAdmin

        ? (
            $_SESSION[
                'first_name'
            ] ?? 'System'
        )

        : (
            $currentMember[
                'first_name'
            ] ?? 'Member'
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
        Membership Management | World Fitness Australia
    </title>


    <link
        rel="stylesheet"
        href="../css/style.css?v=9"
    >

</head>


<body
    class="
        dashboard-page
        membership-management-page
    "
>


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
            href="<?=
                membershipEscape(
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
                    Membership Management
                </small>

            </div>

        </a>


        <div class="dashboard-user">


            <div class="user-text">

                <span>

                    <?=
                        membershipEscape(
                            $roleLabel
                        )
                    ?>

                </span>


                <strong>

                    <?=
                        membershipEscape(
                            (string)
                            $displayName
                        )
                    ?>

                </strong>

            </div>


            <a
                href="<?=
                    membershipEscape(
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


<!-- =====================================================
     MAIN
===================================================== -->

<main class="dashboard-main">

<div class="container">


    <!-- HERO -->

    <section class="membership-management-hero">


        <span class="eyebrow">
            MEMBERSHIP MANAGEMENT
        </span>


        <h1>

            <?php if ($isAdmin): ?>

                Manage gym memberships.

            <?php else: ?>

                Your membership.

            <?php endif; ?>

        </h1>


        <p>

            <?php if ($isAdmin): ?>

                Review membership records, activate or pause
                plans, renew memberships and manage visit limits.

            <?php else: ?>

                Review your membership status, access type,
                validity period and remaining visits.

            <?php endif; ?>

        </p>

    </section>


    <!-- SUCCESS -->

    <?php if ($successMessage): ?>

        <div
            class="
                membership-management-alert
                membership-management-alert-success
            "
        >

            ✓
            <?=
                membershipEscape(
                    $successMessage
                )
            ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($errorMessage): ?>

        <div
            class="
                membership-management-alert
                membership-management-alert-error
            "
        >

            !
            <?=
                membershipEscape(
                    $errorMessage
                )
            ?>

        </div>

    <?php endif; ?>


    <!-- SUMMARY -->

    <section
        class="
            membership-management-summary
        "
    >


        <article
            class="
                membership-management-summary-card
            "
        >

            <span>
                Total
            </span>

            <strong>
                <?= $totalMemberships ?>
            </strong>

            <small>
                Membership records
            </small>

        </article>


        <article
            class="
                membership-management-summary-card
            "
        >

            <span>
                Active
            </span>

            <strong>
                <?= $activeMemberships ?>
            </strong>

            <small>
                Active memberships
            </small>

        </article>


        <article
            class="
                membership-management-summary-card
            "
        >

            <span>
                Pending
            </span>

            <strong>
                <?= $pendingMemberships ?>
            </strong>

            <small>
                Awaiting activation
            </small>

        </article>


        <article
            class="
                membership-management-summary-card
            "
        >

            <span>
                Paused / Expired
            </span>

            <strong>

                <?=
                    $pausedMemberships +
                    $expiredMemberships
                ?>

            </strong>

            <small>
                Inactive memberships
            </small>

        </article>


    </section>


    <!-- MEMBERSHIPS -->

    <section
        class="
            membership-management-panel
        "
    >


        <div
            class="
                membership-management-heading
            "
        >

            <span class="eyebrow">

                <?php if ($isAdmin): ?>

                    MEMBERSHIP RECORDS

                <?php else: ?>

                    MY MEMBERSHIP

                <?php endif; ?>

            </span>


            <h2>

                <?php if ($isAdmin): ?>

                    Member Memberships

                <?php else: ?>

                    Membership Details

                <?php endif; ?>

            </h2>

        </div>


        <?php if (!$memberships): ?>


            <div
                class="
                    membership-management-empty
                "
            >

                No membership records are available.

            </div>


        <?php else: ?>


            <div
                class="
                    membership-management-list
                "
            >


            <?php foreach (
                $memberships
                as $membership
            ): ?>


                <article
                    class="
                        membership-management-card
                    "
                >


                    <!-- CARD HEADER -->

                    <div
                        class="
                            membership-management-card-header
                        "
                    >


                        <div>


                            <span
                                class="
                                    membership-management-status
                                    <?=
                                        membershipStatusClass(
                                            (string)
                                            $membership[
                                                'status'
                                            ]
                                        )
                                    ?>
                                "
                            >

                                <?=
                                    membershipEscape(
                                        strtoupper(
                                            (string)
                                            $membership[
                                                'status'
                                            ]
                                        )
                                    )
                                ?>

                            </span>


                            <h3>

                                <?=
                                    membershipEscape(
                                        (string)
                                        $membership[
                                            'membership_type'
                                        ]
                                    )
                                ?>

                            </h3>


                            <?php if ($isAdmin): ?>

                                <p>

                                    <?=
                                        membershipEscape(
                                            $membership[
                                                'first_name'
                                            ] .
                                            ' ' .
                                            $membership[
                                                'last_name'
                                            ]
                                        )
                                    ?>

                                    ·

                                    <?=
                                        membershipEscape(
                                            (string)
                                            $membership[
                                                'email'
                                            ]
                                        )
                                    ?>

                                </p>

                            <?php endif; ?>


                        </div>


                        <div
                            class="
                                membership-management-id
                            "
                        >

                            Membership

                            <strong>

                                #<?=
                                    (int)
                                    $membership[
                                        'membership_id'
                                    ]
                                ?>

                            </strong>

                        </div>


                    </div>


                    <!-- INFORMATION -->

                    <div
                        class="
                            membership-management-details
                        "
                    >


                        <div>

                            <span>
                                ACCESS TYPE
                            </span>

                            <strong>

                                <?=
                                    membershipEscape(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string)
                                                $membership[
                                                    'access_type'
                                                ]
                                            )
                                        )
                                    )
                                ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                START DATE
                            </span>

                            <strong>

                                <?=
                                    membershipDate(
                                        $membership[
                                            'start_date'
                                        ]
                                    )
                                ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                END DATE
                            </span>

                            <strong>

                                <?=
                                    membershipDate(
                                        $membership[
                                            'end_date'
                                        ]
                                    )
                                ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                REMAINING VISITS
                            </span>

                            <strong>

                                <?php if (
                                    $membership[
                                        'remaining_visits'
                                    ] === null
                                ): ?>

                                    Unlimited

                                <?php else: ?>

                                    <?=
                                        (int)
                                        $membership[
                                            'remaining_visits'
                                        ]
                                    ?>

                                <?php endif; ?>

                            </strong>

                        </div>


                    </div>


                    <?php if (
                        $hasPausedAt &&
                        !empty(
                            $membership[
                                'paused_at'
                            ]
                        )
                    ): ?>


                        <p
                            class="
                                membership-management-paused
                            "
                        >

                            Paused since:

                            <?=
                                membershipDate(
                                    (string)
                                    $membership[
                                        'paused_at'
                                    ]
                                )
                            ?>

                        </p>


                    <?php endif; ?>


                    <!-- ADMIN CONTROLS -->

                    <?php if ($isAdmin): ?>


                        <div
                            class="
                                membership-management-controls
                            "
                        >


                            <!-- STATUS -->

                            <form
                                method="POST"
                                action="index.php"
                                class="
                                    membership-management-form
                                "
                            >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?=
                                        membershipEscape(
                                            $csrfToken
                                        )
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_status"
                                >


                                <input
                                    type="hidden"
                                    name="membership_id"
                                    value="<?=
                                        (int)
                                        $membership[
                                            'membership_id'
                                        ]
                                    ?>"
                                >


                                <label>
                                    Membership Status
                                </label>


                                <div
                                    class="
                                        membership-management-inline
                                    "
                                >


                                    <select
                                        name="status"
                                        required
                                    >


                                    <?php foreach (
                                        $statusOptions
                                        as $statusOption
                                    ): ?>


                                        <option
                                            value="<?=
                                                membershipEscape(
                                                    $statusOption
                                                )
                                            ?>"
                                            <?=
                                                $statusOption ===
                                                $membership[
                                                    'status'
                                                ]

                                                    ? 'selected'

                                                    : ''
                                            ?>
                                        >

                                            <?=
                                                membershipEscape(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $statusOption
                                                        )
                                                    )
                                                )
                                            ?>

                                        </option>


                                    <?php endforeach; ?>


                                    </select>


                                    <button
                                        type="submit"
                                        class="
                                            membership-management-button
                                        "
                                    >
                                        Update Status
                                    </button>


                                </div>

                            </form>


                            <!-- RENEW -->

                            <form
                                method="POST"
                                action="index.php"
                                class="
                                    membership-management-form
                                "
                            >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?=
                                        membershipEscape(
                                            $csrfToken
                                        )
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="action"
                                    value="renew_membership"
                                >


                                <input
                                    type="hidden"
                                    name="membership_id"
                                    value="<?=
                                        (int)
                                        $membership[
                                            'membership_id'
                                        ]
                                    ?>"
                                >


                                <label>
                                    Renew Membership
                                </label>


                                <div
                                    class="
                                        membership-management-inline
                                    "
                                >


                                    <select
                                        name="extension_days"
                                        required
                                    >

                                        <option value="30">
                                            30 Days
                                        </option>

                                        <option value="90">
                                            90 Days
                                        </option>

                                        <option value="180">
                                            180 Days
                                        </option>

                                        <option value="365">
                                            365 Days
                                        </option>

                                    </select>


                                    <button
                                        type="submit"
                                        class="
                                            membership-management-button
                                        "
                                    >
                                        Renew
                                    </button>


                                </div>

                            </form>


                            <!-- VISITS -->

                            <form
                                method="POST"
                                action="index.php"
                                class="
                                    membership-management-form
                                "
                            >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?=
                                        membershipEscape(
                                            $csrfToken
                                        )
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_visits"
                                >


                                <input
                                    type="hidden"
                                    name="membership_id"
                                    value="<?=
                                        (int)
                                        $membership[
                                            'membership_id'
                                        ]
                                    ?>"
                                >


                                <label>
                                    Remaining Visits
                                </label>


                                <div
                                    class="
                                        membership-management-inline
                                    "
                                >


                                    <input
                                        type="number"
                                        name="remaining_visits"
                                        min="0"
                                        placeholder="Blank = unlimited"
                                        value="<?=
                                            $membership[
                                                'remaining_visits'
                                            ] !== null

                                                ? (int)
                                                $membership[
                                                    'remaining_visits'
                                                ]

                                                : ''
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            membership-management-button
                                        "
                                    >
                                        Update Visits
                                    </button>


                                </div>

                            </form>


                            <!-- DETAILS -->

                            <form
                                method="POST"
                                action="index.php"
                                class="
                                    membership-management-form
                                    membership-management-details-form
                                "
                            >


                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?=
                                        membershipEscape(
                                            $csrfToken
                                        )
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_details"
                                >


                                <input
                                    type="hidden"
                                    name="membership_id"
                                    value="<?=
                                        (int)
                                        $membership[
                                            'membership_id'
                                        ]
                                    ?>"
                                >


                                <h4>
                                    Edit Membership Details
                                </h4>


                                <div
                                    class="
                                        membership-management-edit-grid
                                    "
                                >


                                    <div>


                                        <label>
                                            Membership Type
                                        </label>


                                        <?php if (
                                            $membershipTypeOptions
                                        ): ?>


                                            <select
                                                name="membership_type"
                                                required
                                            >


                                                <?php foreach (
                                                    $membershipTypeOptions
                                                    as $typeOption
                                                ): ?>


                                                    <option
                                                        value="<?=
                                                            membershipEscape(
                                                                $typeOption
                                                            )
                                                        ?>"
                                                        <?=
                                                            $typeOption ===
                                                            $membership[
                                                                'membership_type'
                                                            ]

                                                                ? 'selected'

                                                                : ''
                                                        ?>
                                                    >

                                                        <?=
                                                            membershipEscape(
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


                                        <?php else: ?>


                                            <input
                                                type="text"
                                                name="membership_type"
                                                value="<?=
                                                    membershipEscape(
                                                        (string)
                                                        $membership[
                                                            'membership_type'
                                                        ]
                                                    )
                                                ?>"
                                                required
                                            >


                                        <?php endif; ?>


                                    </div>


                                    <div>


                                        <label>
                                            Access Type
                                        </label>


                                        <?php if (
                                            $accessTypeOptions
                                        ): ?>


                                            <select
                                                name="access_type"
                                                required
                                            >


                                                <?php foreach (
                                                    $accessTypeOptions
                                                    as $accessOption
                                                ): ?>


                                                    <option
                                                        value="<?=
                                                            membershipEscape(
                                                                $accessOption
                                                            )
                                                        ?>"
                                                        <?=
                                                            $accessOption ===
                                                            $membership[
                                                                'access_type'
                                                            ]

                                                                ? 'selected'

                                                                : ''
                                                        ?>
                                                    >

                                                        <?=
                                                            membershipEscape(
                                                                ucwords(
                                                                    str_replace(
                                                                        '_',
                                                                        ' ',
                                                                        $accessOption
                                                                    )
                                                                )
                                                            )
                                                        ?>

                                                    </option>


                                                <?php endforeach; ?>


                                            </select>


                                        <?php else: ?>


                                            <input
                                                type="text"
                                                name="access_type"
                                                value="<?=
                                                    membershipEscape(
                                                        (string)
                                                        $membership[
                                                            'access_type'
                                                        ]
                                                    )
                                                ?>"
                                                required
                                            >


                                        <?php endif; ?>


                                    </div>


                                    <div>


                                        <label>
                                            Start Date
                                        </label>


                                        <input
                                            type="date"
                                            name="start_date"
                                            value="<?=
                                                membershipEscape(
                                                    (string)
                                                    $membership[
                                                        'start_date'
                                                    ]
                                                )
                                            ?>"
                                            required
                                        >


                                    </div>


                                    <div>


                                        <label>
                                            End Date
                                        </label>


                                        <input
                                            type="date"
                                            name="end_date"
                                            value="<?=
                                                membershipEscape(
                                                    (string)
                                                    $membership[
                                                        'end_date'
                                                    ]
                                                )
                                            ?>"
                                            required
                                        >


                                    </div>


                                </div>


                                <button
                                    type="submit"
                                    class="
                                        membership-management-button
                                        membership-management-save
                                    "
                                >
                                    Save Membership Details
                                </button>


                            </form>


                        </div>


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
            Membership Management
        </p>

    </div>

</footer>


</body>

</html>