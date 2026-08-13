<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Only accept POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get submitted login details
|--------------------------------------------------------------------------
*/

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| Login error helper
|--------------------------------------------------------------------------
*/

function loginError(string $message): never
{
    $_SESSION['login_error'] = $message;

    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate required fields
|--------------------------------------------------------------------------
*/

if ($email === '' || $password === '') {
    loginError(
        'Please enter your email address and password.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate email format
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    loginError(
        'Please enter a valid email address.'
    );
}


/*
|--------------------------------------------------------------------------
| Find user account
|--------------------------------------------------------------------------
*/

try {

    $statement = $pdo->prepare(
        'SELECT
            user_id,
            first_name,
            last_name,
            email,
            password_hash,
            role,
            status
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $statement->execute([
        'email' => $email
    ]);

    $user = $statement->fetch();


    /*
    |--------------------------------------------------------------------------
    | Check account and password
    |--------------------------------------------------------------------------
    */

    if (
        !$user ||
        !password_verify(
            $password,
            $user['password_hash']
        )
    ) {

        loginError(
            'Incorrect email address or password.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check account status
    |--------------------------------------------------------------------------
    */

    if ($user['status'] !== 'active') {

        loginError(
            'Your account is currently unavailable. Please contact gym staff.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Regenerate session ID
    |--------------------------------------------------------------------------
    |
    | Helps protect against session fixation attacks.
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    /*
    |--------------------------------------------------------------------------
    | Store authenticated user information
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] = (int) $user['user_id'];

    $_SESSION['first_name'] =
        $user['first_name'];

    $_SESSION['last_name'] =
        $user['last_name'];

    $_SESSION['email'] =
        $user['email'];

    $_SESSION['role'] =
        $user['role'];

    $_SESSION['logged_in'] = true;

    $_SESSION['login_time'] = time();


    /*
    |--------------------------------------------------------------------------
    | Redirect according to role
    |--------------------------------------------------------------------------
    */

    switch ($user['role']) {

        case 'admin':

            header(
                'Location: ../admin/dashboard.php'
            );

            break;


        case 'trainer':

            header(
                'Location: ../trainer/dashboard.php'
            );

            break;


        case 'member':

            header(
                'Location: ../member/dashboard.php'
            );

            break;


        default:

            /*
            |--------------------------------------------------------------------------
            | Unexpected role
            |--------------------------------------------------------------------------
            */

            session_unset();
            session_destroy();

            header(
                'Location: login.php'
            );

            exit;
    }


    exit;


} catch (PDOException $exception) {

    error_log(
        'Login database error: ' .
        $exception->getMessage()
    );

    loginError(
        'Login could not be completed. Please try again.'
    );
}