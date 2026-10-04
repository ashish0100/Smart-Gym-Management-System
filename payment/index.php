<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| SGMS-16 - Payment Management
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
        'Please log in to access payment management.';

    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| User and Role
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];

$role = strtolower(
    trim(
        (string) ($_SESSION['role'] ?? '')
    )
);


/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Existing Smart Gym system uses:
|
| admin
| member
| trainer
|--------------------------------------------------------------------------
*/

$isAdmin =
    $role === 'admin';

$isMember =
    $role === 'member';


/*
|--------------------------------------------------------------------------
| Role Protection
|--------------------------------------------------------------------------
*/

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
        $_SESSION['payment_csrf_token']
    )
) {

    $_SESSION['payment_csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    (string)
    $_SESSION['payment_csrf_token'];


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function paymentEscape(
    ?string $value
): string {

    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function paymentMoney(
    float $amount
): string {

    return '$' .
        number_format(
            $amount,
            2
        );
}


function paymentDate(
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

        return paymentEscape(
            $value
        );
    }

    return date(
        'd M Y, g:i A',
        $timestamp
    );
}


function paymentStatusClass(
    string $status
): string {

    $clean =
        preg_replace(
            '/[^a-z0-9_-]+/',
            '-',
            strtolower($status)
        );

    return
        $clean ?: 'unknown';
}


/*
|--------------------------------------------------------------------------
| Read Table Columns
|--------------------------------------------------------------------------
*/

function paymentTableColumns(
    PDO $pdo,
    string $table
): array {

    $statement =
        $pdo->query(
            "SHOW COLUMNS FROM `{$table}`"
        );

    $rows =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

    $columns = [];

    foreach (
        $rows as $row
    ) {

        $columns[
            (string)
            $row['Field']
        ] = $row;
    }

    return $columns;
}


/*
|--------------------------------------------------------------------------
| Extract ENUM Values
|--------------------------------------------------------------------------
*/

function paymentEnumValues(
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
| Detect Payment Table Structure
|--------------------------------------------------------------------------
*/

try {

    $paymentColumns =
        paymentTableColumns(
            $pdo,
            'payments'
        );

} catch (
    PDOException $exception
) {

    error_log(
        'Payment schema error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the payment database structure.'
    );
}


/*
|--------------------------------------------------------------------------
| Detect Status Column
|--------------------------------------------------------------------------
*/

$statusColumn = null;

if (
    isset(
        $paymentColumns[
            'payment_status'
        ]
    )
) {

    $statusColumn =
        'payment_status';

} elseif (
    isset(
        $paymentColumns[
            'status'
        ]
    )
) {

    $statusColumn =
        'status';
}


if (
    $statusColumn === null
) {

    http_response_code(500);

    exit(
        'Payment status column could not be detected.'
    );
}


/*
|--------------------------------------------------------------------------
| Detect Payment Method Column
|--------------------------------------------------------------------------
*/

$methodColumn = null;

if (
    isset(
        $paymentColumns[
            'payment_method'
        ]
    )
) {

    $methodColumn =
        'payment_method';

} elseif (
    isset(
        $paymentColumns[
            'method'
        ]
    )
) {

    $methodColumn =
        'method';
}


/*
|--------------------------------------------------------------------------
| Optional Payment Fields
|--------------------------------------------------------------------------
*/

$hasMembershipColumn =
    isset(
        $paymentColumns[
            'membership_id'
        ]
    );

$hasReferenceColumn =
    isset(
        $paymentColumns[
            'transaction_reference'
        ]
    );

$hasPaymentDateColumn =
    isset(
        $paymentColumns[
            'payment_date'
        ]
    );


/*
|--------------------------------------------------------------------------
| Payment Status Values
|--------------------------------------------------------------------------
*/

$statusOptions =
    paymentEnumValues(
        (string) (
            $paymentColumns[
                $statusColumn
            ]['Type'] ?? ''
        )
    );


if (!$statusOptions) {

    $statusOptions = [
        'pending',
        'completed',
        'failed'
    ];
}


/*
|--------------------------------------------------------------------------
| Payment Method Values
|--------------------------------------------------------------------------
*/

$methodOptions = [];

if (
    $methodColumn !== null
) {

    $methodOptions =
        paymentEnumValues(
            (string) (
                $paymentColumns[
                    $methodColumn
                ]['Type'] ?? ''
            )
        );

    if (!$methodOptions) {

        $methodOptions = [
            'cash',
            'card',
            'bank_transfer'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage =
    (string) (
        $_SESSION[
            'payment_success'
        ] ?? ''
    );

$errorMessage =
    (string) (
        $_SESSION[
            'payment_error'
        ] ?? ''
    );


unset(
    $_SESSION[
        'payment_success'
    ],
    $_SESSION[
        'payment_error'
    ]
);


/*
|--------------------------------------------------------------------------
| Find Logged-In Member
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
                    ON m.user_id = u.user_id

                 WHERE u.user_id = :user_id

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


        if (
            !$currentMember
        ) {

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
            'Payment member lookup error: ' .
            $exception->getMessage()
        );

        http_response_code(500);

        exit(
            'Unable to load the member payment profile.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Process POST Requests
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
            'payment_error'
        ] =
            'Invalid request. Refresh the page and try again.';

        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Members Cannot Modify Payments
    |--------------------------------------------------------------------------
    */

    if (!$isAdmin) {

        http_response_code(403);

        exit(
            'Only administrators can change payment records.'
        );
    }


    $action =
        (string) (
            $_POST[
                'action'
            ] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'create_payment'
    ) {


        /*
        |--------------------------------------------------------------------------
        | Member
        |--------------------------------------------------------------------------
        */

        $memberId =
            filter_input(
                INPUT_POST,
                'member_id',
                FILTER_VALIDATE_INT
            );


        /*
        |--------------------------------------------------------------------------
        | Membership
        |--------------------------------------------------------------------------
        */

        $membershipRaw =
            trim(
                (string) (
                    $_POST[
                        'membership_id'
                    ] ?? ''
                )
            );


        $membershipId = null;


        if (
            $membershipRaw !== ''
        ) {

            $validatedMembershipId =
                filter_var(
                    $membershipRaw,
                    FILTER_VALIDATE_INT
                );


            if (
                $validatedMembershipId ===
                false
            ) {

                $_SESSION[
                    'payment_error'
                ] =
                    'Invalid membership selected.';

                header(
                    'Location: index.php'
                );

                exit;
            }


            $membershipId =
                (int)
                $validatedMembershipId;
        }


        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        $amountRaw =
            trim(
                (string) (
                    $_POST[
                        'amount'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        $method =
            trim(
                (string) (
                    $_POST[
                        'method'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        $status =
            trim(
                (string) (
                    $_POST[
                        'status'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Transaction Reference
        |--------------------------------------------------------------------------
        */

        $reference =
            trim(
                (string) (
                    $_POST[
                        'transaction_reference'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validate Member
        |--------------------------------------------------------------------------
        */

        if (!$memberId) {

            $_SESSION[
                'payment_error'
            ] =
                'Please select a member.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Amount
        |--------------------------------------------------------------------------
        */

        if (
            $amountRaw === '' ||
            !is_numeric(
                $amountRaw
            ) ||
            (float)
            $amountRaw <= 0 ||
            (float)
            $amountRaw >
                1000000
        ) {

            $_SESSION[
                'payment_error'
            ] =
                'Enter a valid payment amount greater than $0.';

            header(
                'Location: index.php'
            );

            exit;
        }


        $amount =
            round(
                (float)
                $amountRaw,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $status,
                $statusOptions,
                true
            )
        ) {

            $_SESSION[
                'payment_error'
            ] =
                'Invalid payment status.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Method
        |--------------------------------------------------------------------------
        */

        if (
            $methodColumn !== null &&
            !in_array(
                $method,
                $methodOptions,
                true
            )
        ) {

            $_SESSION[
                'payment_error'
            ] =
                'Invalid payment method.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Generate Transaction Reference
        |--------------------------------------------------------------------------
        */

        if (
            $hasReferenceColumn &&
            $reference === ''
        ) {

            $reference =
                'SGMS-' .
                date(
                    'YmdHis'
                ) .
                '-' .
                strtoupper(
                    bin2hex(
                        random_bytes(2)
                    )
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Save Payment
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Check Member Exists
            |--------------------------------------------------------------------------
            */

            $memberCheck =
                $pdo->prepare(
                    'SELECT
                        COUNT(*)

                     FROM members

                     WHERE member_id =
                        :member_id'
                );


            $memberCheck->execute(
                [
                    'member_id' =>
                        $memberId
                ]
            );


            if (
                (int)
                $memberCheck
                    ->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'Selected member does not exist.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validate Membership Ownership
            |--------------------------------------------------------------------------
            */

            if (
                $hasMembershipColumn &&
                $membershipId !== null
            ) {

                $membershipCheck =
                    $pdo->prepare(
                        'SELECT
                            COUNT(*)

                         FROM memberships

                         WHERE membership_id =
                            :membership_id

                         AND member_id =
                            :member_id'
                    );


                $membershipCheck->execute(
                    [
                        'membership_id' =>
                            $membershipId,

                        'member_id' =>
                            $memberId
                    ]
                );


                if (
                    (int)
                    $membershipCheck
                        ->fetchColumn()
                    !== 1
                ) {

                    throw new RuntimeException(
                        'The selected membership does not belong to this member.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Build Insert
            |--------------------------------------------------------------------------
            */

            $columns = [
                'member_id'
            ];


            $values = [
                ':member_id'
            ];


            $parameters = [
                'member_id' =>
                    $memberId
            ];


            /*
            |--------------------------------------------------------------------------
            | Membership ID
            |--------------------------------------------------------------------------
            */

            if (
                $hasMembershipColumn
            ) {

                $columns[] =
                    'membership_id';

                $values[] =
                    ':membership_id';

                $parameters[
                    'membership_id'
                ] =
                    $membershipId;
            }


            /*
            |--------------------------------------------------------------------------
            | Amount
            |--------------------------------------------------------------------------
            */

            $columns[] =
                'amount';

            $values[] =
                ':amount';

            $parameters[
                'amount'
            ] =
                $amount;


            /*
            |--------------------------------------------------------------------------
            | Payment Method
            |--------------------------------------------------------------------------
            */

            if (
                $methodColumn !== null
            ) {

                $columns[] =
                    $methodColumn;

                $values[] =
                    ':payment_method';

                $parameters[
                    'payment_method'
                ] =
                    $method;
            }


            /*
            |--------------------------------------------------------------------------
            | Reference
            |--------------------------------------------------------------------------
            */

            if (
                $hasReferenceColumn
            ) {

                $columns[] =
                    'transaction_reference';

                $values[] =
                    ':transaction_reference';

                $parameters[
                    'transaction_reference'
                ] =
                    $reference;
            }


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $columns[] =
                $statusColumn;

            $values[] =
                ':payment_status';

            $parameters[
                'payment_status'
            ] =
                $status;


            /*
            |--------------------------------------------------------------------------
            | Payment Date
            |--------------------------------------------------------------------------
            */

            if (
                $hasPaymentDateColumn
            ) {

                $columns[] =
                    'payment_date';

                $values[] =
                    'CURRENT_TIMESTAMP';
            }


            /*
            |--------------------------------------------------------------------------
            | Quote Column Names
            |--------------------------------------------------------------------------
            */

            $quotedColumns =
                array_map(
                    static function (
                        string $column
                    ): string {

                        return
                            "`{$column}`";
                    },
                    $columns
                );


            /*
            |--------------------------------------------------------------------------
            | Final SQL
            |--------------------------------------------------------------------------
            */

            $insertSql =

                'INSERT INTO payments (' .

                implode(
                    ', ',
                    $quotedColumns
                ) .

                ') VALUES (' .

                implode(
                    ', ',
                    $values
                ) .

                ')';


            $insertStatement =
                $pdo->prepare(
                    $insertSql
                );


            $insertStatement->execute(
                $parameters
            );


            $pdo->commit();


            $_SESSION[
                'payment_success'
            ] =
                'Payment recorded successfully.';


        } catch (
            RuntimeException $exception
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $_SESSION[
                'payment_error'
            ] =
                $exception
                    ->getMessage();


        } catch (
            PDOException $exception
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            error_log(
                'Payment insert error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'payment_error'
            ] =
                'Unable to record the payment. Check the payment details and try again.';
        }


        header(
            'Location: index.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $action ===
        'update_status'
    ) {


        $paymentId =
            filter_input(
                INPUT_POST,
                'payment_id',
                FILTER_VALIDATE_INT
            );


        $newStatus =
            trim(
                (string) (
                    $_POST[
                        'status'
                    ] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validate Payment
        |--------------------------------------------------------------------------
        */

        if (!$paymentId) {

            $_SESSION[
                'payment_error'
            ] =
                'Invalid payment selected.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $newStatus,
                $statusOptions,
                true
            )
        ) {

            $_SESSION[
                'payment_error'
            ] =
                'Invalid payment status.';

            header(
                'Location: index.php'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Update Database
        |--------------------------------------------------------------------------
        */

        try {

            $updateSql =

                "UPDATE payments

                 SET `{$statusColumn}` =
                    :status

                 WHERE payment_id =
                    :payment_id";


            $updateStatement =
                $pdo->prepare(
                    $updateSql
                );


            $updateStatement->execute(
                [
                    'status' =>
                        $newStatus,

                    'payment_id' =>
                        $paymentId
                ]
            );


            if (
                $updateStatement
                    ->rowCount() > 0
            ) {

                $_SESSION[
                    'payment_success'
                ] =
                    'Payment status updated successfully.';

            } else {

                $_SESSION[
                    'payment_success'
                ] =
                    'Payment status is already up to date.';
            }


        } catch (
            PDOException $exception
        ) {

            error_log(
                'Payment status update error: ' .
                $exception->getMessage()
            );


            $_SESSION[
                'payment_error'
            ] =
                'Unable to update the payment status.';
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
        'payment_error'
    ] =
        'Invalid payment action.';


    header(
        'Location: index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Members For Administrator
|--------------------------------------------------------------------------
*/

$members = [];

$memberships = [];


if ($isAdmin) {

    try {


        /*
        |--------------------------------------------------------------------------
        | Members
        |--------------------------------------------------------------------------
        */

        $memberQuery =
            $pdo->query(
                'SELECT

                    m.member_id,

                    u.first_name,
                    u.last_name,
                    u.email

                 FROM members m

                 INNER JOIN users u
                    ON m.user_id =
                        u.user_id

                 ORDER BY
                    u.first_name,
                    u.last_name'
            );


        $members =
            $memberQuery->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | Memberships
        |--------------------------------------------------------------------------
        */

        if (
            $hasMembershipColumn
        ) {

            $membershipQuery =
                $pdo->query(
                    'SELECT

                        ms.membership_id,
                        ms.member_id,
                        ms.membership_type,
                        ms.status,

                        u.first_name,
                        u.last_name

                     FROM memberships ms

                     INNER JOIN members m
                        ON ms.member_id =
                            m.member_id

                     INNER JOIN users u
                        ON m.user_id =
                            u.user_id

                     ORDER BY
                        ms.membership_id DESC'
                );


            $memberships =
                $membershipQuery
                    ->fetchAll(
                        PDO::FETCH_ASSOC
                    );
        }


    } catch (
        PDOException $exception
    ) {

        error_log(
            'Payment form data error: ' .
            $exception->getMessage()
        );


        $errorMessage =
            'Unable to load members or memberships.';
    }
}


/*
|--------------------------------------------------------------------------
| Payment SELECT Fields
|--------------------------------------------------------------------------
*/

$membershipSelect =

    $hasMembershipColumn

        ? 'p.membership_id'

        : 'NULL AS membership_id';


$membershipTypeSelect =

    $hasMembershipColumn

        ? 'ms.membership_type'

        : 'NULL AS membership_type';


$methodSelect =

    $methodColumn !== null

        ? "p.`{$methodColumn}` AS payment_method"

        : 'NULL AS payment_method';


$referenceSelect =

    $hasReferenceColumn

        ? 'p.transaction_reference'

        : 'NULL AS transaction_reference';


$dateSelect =

    $hasPaymentDateColumn

        ? 'p.payment_date'

        : 'NULL AS payment_date';


/*
|--------------------------------------------------------------------------
| Payment History SQL
|--------------------------------------------------------------------------
*/

$paymentSql =

    "SELECT

        p.payment_id,

        p.member_id,

        {$membershipSelect},

        p.amount,

        {$methodSelect},

        {$referenceSelect},

        p.`{$statusColumn}`
            AS payment_status,

        {$dateSelect},

        u.first_name,

        u.last_name,

        u.email,

        {$membershipTypeSelect}

     FROM payments p

     INNER JOIN members m

        ON p.member_id =
            m.member_id

     INNER JOIN users u

        ON m.user_id =
            u.user_id";


/*
|--------------------------------------------------------------------------
| Membership Join
|--------------------------------------------------------------------------
*/

if (
    $hasMembershipColumn
) {

    $paymentSql .=

        ' LEFT JOIN memberships ms

            ON p.membership_id =
                ms.membership_id';
}


$paymentParameters = [];


/*
|--------------------------------------------------------------------------
| Member Can Only See Own Payments
|--------------------------------------------------------------------------
*/

if ($isMember) {

    $paymentSql .=

        ' WHERE p.member_id =
            :member_id';


    $paymentParameters[
        'member_id'
    ] =
        $currentMemberId;
}


/*
|--------------------------------------------------------------------------
| Sort
|--------------------------------------------------------------------------
*/

$paymentSql .=

    ' ORDER BY
        p.payment_id DESC';


/*
|--------------------------------------------------------------------------
| Execute Payment History
|--------------------------------------------------------------------------
*/

try {

    $paymentStatement =
        $pdo->prepare(
            $paymentSql
        );


    $paymentStatement->execute(
        $paymentParameters
    );


    $payments =
        $paymentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    PDOException $exception
) {

    error_log(
        'Payment history error: ' .
        $exception->getMessage()
    );


    $payments = [];


    $errorMessage =
        'Unable to load payment history.';
}


/*
|--------------------------------------------------------------------------
| Payment Statistics
|--------------------------------------------------------------------------
*/

$totalTransactions =
    count($payments);


$completedTotal =
    0.0;


$pendingCount =
    0;


$failedCount =
    0;


foreach (
    $payments as $payment
) {

    $paymentStatus =
        strtolower(
            (string)
            $payment[
                'payment_status'
            ]
        );


    if (
        $paymentStatus ===
        'completed'
    ) {

        $completedTotal +=
            (float)
            $payment[
                'amount'
            ];
    }


    if (
        $paymentStatus ===
        'pending'
    ) {

        $pendingCount++;
    }


    if (
        $paymentStatus ===
        'failed'
    ) {

        $failedCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard Navigation
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
        Payment Management | World Fitness Australia
    </title>


    <!--
    Payment CSS has been added to the bottom
    of the existing style.css file.
    -->

    <link
        rel="stylesheet"
        href="../css/style.css?v=7"
    >

</head>


<body
    class="
        dashboard-page
        payment-page
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
            href="<?= paymentEscape(
                $dashboardUrl
            ) ?>"
            class="logo"
        >

            <div>

                <strong>
                    World Fitness Australia
                </strong>

                <small>
                    Payment Management
                </small>

            </div>

        </a>


        <div class="dashboard-user">


            <div class="user-text">

                <span>
                    <?= paymentEscape(
                        $roleLabel
                    ) ?>
                </span>


                <strong>
                    <?= paymentEscape(
                        (string)
                        $displayName
                    ) ?>
                </strong>

            </div>


            <a
                href="<?= paymentEscape(
                    $dashboardUrl
                ) ?>"
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

    <section class="payment-hero">

        <span class="eyebrow">
            PAYMENT MANAGEMENT
        </span>


        <h1>

            <?php if ($isAdmin): ?>

                Manage gym payments.

            <?php else: ?>

                Your payment history.

            <?php endif; ?>

        </h1>


        <p>

            <?php if ($isAdmin): ?>

                Record transactions, review payment history
                and update payment status from one place.

            <?php else: ?>

                Review your recorded membership payments
                and transaction status.

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
                payment-alert
                payment-alert-success
            "
        >

            ✓

            <?= paymentEscape(
                $successMessage
            ) ?>

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
                payment-alert
                payment-alert-error
            "
        >

            !

            <?= paymentEscape(
                $errorMessage
            ) ?>

        </div>

    <?php endif; ?>


    <!--
    ======================================================================
    SUMMARY
    ======================================================================
    -->

    <section
        class="
            payment-summary-grid
        "
    >


        <!-- TRANSACTIONS -->

        <article
            class="
                payment-summary-card
            "
        >

            <span>
                Transactions
            </span>


            <strong>
                <?= $totalTransactions ?>
            </strong>


            <small>

                <?php if ($isAdmin): ?>

                    Recorded payment transactions

                <?php else: ?>

                    Your recorded transactions

                <?php endif; ?>

            </small>

        </article>


        <!-- COMPLETED -->

        <article
            class="
                payment-summary-card
            "
        >

            <span>
                Completed
            </span>


            <strong>

                <?= paymentMoney(
                    $completedTotal
                ) ?>

            </strong>


            <small>
                Completed payment value
            </small>

        </article>


        <!-- PENDING -->

        <article
            class="
                payment-summary-card
            "
        >

            <span>
                Pending
            </span>


            <strong>
                <?= $pendingCount ?>
            </strong>


            <small>
                Awaiting completion
            </small>

        </article>


        <!-- FAILED -->

        <article
            class="
                payment-summary-card
            "
        >

            <span>
                Failed
            </span>


            <strong>
                <?= $failedCount ?>
            </strong>


            <small>
                Unsuccessful transactions
            </small>

        </article>


    </section>


    <!--
    ======================================================================
    ADMIN PAYMENT FORM
    ======================================================================
    -->

    <?php if ($isAdmin): ?>


        <section
            class="
                payment-panel
            "
        >


            <div
                class="
                    payment-section-heading
                "
            >


                <span class="eyebrow">
                    NEW PAYMENT
                </span>


                <h2>
                    Record Payment
                </h2>


                <p>
                    Create a payment record
                    for a registered gym member.
                </p>


            </div>


            <form
                method="POST"
                action="index.php"
                class="payment-form"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= paymentEscape(
                        $csrfToken
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="create_payment"
                >


                <div
                    class="
                        payment-form-grid
                    "
                >


                    <!--
                    ==========================================================
                    MEMBER
                    ==========================================================
                    -->

                    <div class="payment-field">


                        <label for="member_id">

                            Member *

                        </label>


                        <select
                            id="member_id"
                            name="member_id"
                            required
                        >


                            <option value="">

                                Select member

                            </option>


                            <?php foreach (
                                $members
                                as $member
                            ): ?>


                                <option
                                    value="<?=
                                        (int)
                                        $member[
                                            'member_id'
                                        ]
                                    ?>"
                                >


                                    <?= paymentEscape(

                                        $member[
                                            'first_name'
                                        ] .

                                        ' ' .

                                        $member[
                                            'last_name'
                                        ] .

                                        ' — ' .

                                        $member[
                                            'email'
                                        ]

                                    ) ?>


                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>


                    <!--
                    ==========================================================
                    MEMBERSHIP
                    ==========================================================
                    -->

                    <?php if (
                        $hasMembershipColumn
                    ): ?>


                        <div
                            class="
                                payment-field
                            "
                        >


                            <label
                                for="membership_id"
                            >

                                Membership

                            </label>


                            <select
                                id="membership_id"
                                name="membership_id"
                            >


                                <option value="">

                                    Not linked to a membership

                                </option>


                                <?php foreach (
                                    $memberships
                                    as $membership
                                ): ?>


                                    <option
                                        value="<?=
                                            (int)
                                            $membership[
                                                'membership_id'
                                            ]
                                        ?>"
                                    >


                                        <?= paymentEscape(

                                            '#' .

                                            $membership[
                                                'membership_id'
                                            ] .

                                            ' — ' .

                                            $membership[
                                                'first_name'
                                            ] .

                                            ' ' .

                                            $membership[
                                                'last_name'
                                            ] .

                                            ' — ' .

                                            $membership[
                                                'membership_type'
                                            ] .

                                            ' (' .

                                            $membership[
                                                'status'
                                            ] .

                                            ')'

                                        ) ?>


                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>


                    <?php endif; ?>


                    <!--
                    ==========================================================
                    AMOUNT
                    ==========================================================
                    -->

                    <div class="payment-field">


                        <label for="amount">

                            Amount ($) *

                        </label>


                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            min="0.01"
                            max="1000000"
                            step="0.01"
                            placeholder="49.99"
                            required
                        >


                    </div>


                    <!--
                    ==========================================================
                    PAYMENT METHOD
                    ==========================================================
                    -->

                    <?php if (
                        $methodColumn !== null
                    ): ?>


                        <div
                            class="
                                payment-field
                            "
                        >


                            <label for="method">

                                Payment Method *

                            </label>


                            <select
                                id="method"
                                name="method"
                                required
                            >


                                <?php foreach (
                                    $methodOptions
                                    as $methodOption
                                ): ?>


                                    <option
                                        value="<?=
                                            paymentEscape(
                                                $methodOption
                                            )
                                        ?>"
                                    >


                                        <?= paymentEscape(

                                            ucwords(

                                                str_replace(

                                                    '_',

                                                    ' ',

                                                    $methodOption

                                                )

                                            )

                                        ) ?>


                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>


                    <?php endif; ?>


                    <!--
                    ==========================================================
                    STATUS
                    ==========================================================
                    -->

                    <div class="payment-field">


                        <label for="status">

                            Status *

                        </label>


                        <select
                            id="status"
                            name="status"
                            required
                        >


                            <?php foreach (
                                $statusOptions
                                as $statusOption
                            ): ?>


                                <option
                                    value="<?=
                                        paymentEscape(
                                            $statusOption
                                        )
                                    ?>"
                                    <?=
                                        $statusOption ===
                                        'completed'

                                            ? 'selected'

                                            : ''
                                    ?>
                                >


                                    <?= paymentEscape(

                                        ucwords(

                                            str_replace(

                                                '_',

                                                ' ',

                                                $statusOption

                                            )

                                        )

                                    ) ?>


                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>


                    <!--
                    ==========================================================
                    TRANSACTION REFERENCE
                    ==========================================================
                    -->

                    <?php if (
                        $hasReferenceColumn
                    ): ?>


                        <div class="payment-field">


                            <label
                                for="
                                    transaction_reference
                                "
                            >

                                Transaction Reference

                            </label>


                            <input
                                id="
                                    transaction_reference
                                "
                                name="
                                    transaction_reference
                                "
                                type="text"
                                maxlength="100"
                                placeholder="Leave blank to auto-generate"
                            >


                        </div>


                    <?php endif; ?>


                </div>


                <div
                    class="
                        payment-form-actions
                    "
                >


                    <button
                        type="submit"
                        class="
                            payment-primary-button
                        "
                    >

                        Record Payment

                    </button>


                </div>


            </form>


        </section>


    <?php endif; ?>


    <!--
    ======================================================================
    PAYMENT HISTORY
    ======================================================================
    -->

    <section
        class="
            payment-panel
        "
    >


        <div
            class="
                payment-section-heading
            "
        >


            <span class="eyebrow">

                TRANSACTIONS

            </span>


            <h2>

                <?php if ($isAdmin): ?>

                    Payment History

                <?php else: ?>

                    My Payments

                <?php endif; ?>

            </h2>


            <p>

                <?php if ($isAdmin): ?>

                    Review all recorded member
                    payment transactions.

                <?php else: ?>

                    Only payments linked to your
                    member account are shown.

                <?php endif; ?>

            </p>


        </div>


        <?php if (!$payments): ?>


            <div
                class="
                    payment-empty
                "
            >

                No payment transactions
                are available yet.

            </div>


        <?php else: ?>


            <div
                class="
                    payment-table-wrap
                "
            >


                <table
                    class="
                        payment-table
                    "
                >


                    <thead>


                    <tr>


                        <th>
                            ID
                        </th>


                        <?php if ($isAdmin): ?>

                            <th>
                                Member
                            </th>

                        <?php endif; ?>


                        <th>
                            Membership
                        </th>


                        <th>
                            Amount
                        </th>


                        <th>
                            Method
                        </th>


                        <th>
                            Reference
                        </th>


                        <th>
                            Date
                        </th>


                        <th>
                            Status
                        </th>


                        <?php if ($isAdmin): ?>

                            <th>
                                Manage
                            </th>

                        <?php endif; ?>


                    </tr>


                    </thead>


                    <tbody>


                    <?php foreach (
                        $payments
                        as $payment
                    ): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                #<?= (int)
                                    $payment[
                                        'payment_id'
                                    ]
                                ?>

                            </td>


                            <!-- MEMBER -->

                            <?php if ($isAdmin): ?>


                                <td>


                                    <strong>

                                        <?= paymentEscape(

                                            $payment[
                                                'first_name'
                                            ] .

                                            ' ' .

                                            $payment[
                                                'last_name'
                                            ]

                                        ) ?>


                                    </strong>


                                    <small>

                                        <?= paymentEscape(
                                            $payment[
                                                'email'
                                            ]
                                        ) ?>

                                    </small>


                                </td>


                            <?php endif; ?>


                            <!-- MEMBERSHIP -->

                            <td>


                                <?php if (
                                    $payment[
                                        'membership_id'
                                    ]
                                ): ?>


                                    #<?= (int)
                                        $payment[
                                            'membership_id'
                                        ]
                                    ?>


                                    <?php if (
                                        $payment[
                                            'membership_type'
                                        ]
                                    ): ?>


                                        <small>

                                            <?= paymentEscape(
                                                $payment[
                                                    'membership_type'
                                                ]
                                            ) ?>

                                        </small>


                                    <?php endif; ?>


                                <?php else: ?>


                                    <span
                                        class="
                                            payment-muted
                                        "
                                    >

                                        —

                                    </span>


                                <?php endif; ?>


                            </td>


                            <!-- AMOUNT -->

                            <td
                                class="
                                    payment-amount
                                "
                            >


                                <?= paymentMoney(

                                    (float)
                                    $payment[
                                        'amount'
                                    ]

                                ) ?>


                            </td>


                            <!-- PAYMENT METHOD -->

                            <td>


                                <?php if (
                                    $payment[
                                        'payment_method'
                                    ]
                                ): ?>


                                    <?= paymentEscape(

                                        ucwords(

                                            str_replace(

                                                '_',

                                                ' ',

                                                (string)
                                                $payment[
                                                    'payment_method'
                                                ]

                                            )

                                        )

                                    ) ?>


                                <?php else: ?>


                                    —


                                <?php endif; ?>


                            </td>


                            <!-- REFERENCE -->

                            <td>


                                <span
                                    class="
                                        payment-reference
                                    "
                                >


                                    <?php if (
                                        $payment[
                                            'transaction_reference'
                                        ]
                                    ): ?>


                                        <?= paymentEscape(

                                            (string)
                                            $payment[
                                                'transaction_reference'
                                            ]

                                        ) ?>


                                    <?php else: ?>


                                        —


                                    <?php endif; ?>


                                </span>


                            </td>


                            <!-- DATE -->

                            <td>


                                <?= paymentDate(

                                    $payment[
                                        'payment_date'
                                    ]

                                        ? (string)
                                        $payment[
                                            'payment_date'
                                        ]

                                        : null

                                ) ?>


                            </td>


                            <!-- STATUS -->

                            <td>


                                <span
                                    class="
                                        payment-status

                                        payment-status-<?=
                                            paymentEscape(

                                                paymentStatusClass(

                                                    (string)
                                                    $payment[
                                                        'payment_status'
                                                    ]

                                                )

                                            )
                                        ?>
                                    "
                                >


                                    <?= paymentEscape(

                                        strtoupper(

                                            (string)
                                            $payment[
                                                'payment_status'
                                            ]

                                        )

                                    ) ?>


                                </span>


                            </td>


                            <!-- ADMIN UPDATE -->

                            <?php if ($isAdmin): ?>


                                <td>


                                    <form
                                        method="POST"
                                        action="index.php"
                                        class="
                                            payment-status-form
                                        "
                                    >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?=
                                                paymentEscape(
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
                                            name="payment_id"
                                            value="<?=
                                                (int)
                                                $payment[
                                                    'payment_id'
                                                ]
                                            ?>"
                                        >


                                        <select
                                            name="status"
                                            aria-label="
                                                Payment status
                                            "
                                        >


                                            <?php foreach (
                                                $statusOptions
                                                as $statusOption
                                            ): ?>


                                                <option
                                                    value="<?=
                                                        paymentEscape(
                                                            $statusOption
                                                        )
                                                    ?>"
                                                    <?=
                                                        $statusOption ===
                                                        $payment[
                                                            'payment_status'
                                                        ]

                                                            ? 'selected'

                                                            : ''
                                                    ?>
                                                >


                                                    <?= paymentEscape(

                                                        ucwords(

                                                            str_replace(

                                                                '_',

                                                                ' ',

                                                                $statusOption

                                                            )

                                                        )

                                                    ) ?>


                                                </option>


                                            <?php endforeach; ?>


                                        </select>


                                        <button
                                            type="submit"
                                            class="
                                                payment-small-button
                                            "
                                        >

                                            Update

                                        </button>


                                    </form>


                                </td>


                            <?php endif; ?>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


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
            Payment Management
        </p>


    </div>


</footer>


</body>

</html>