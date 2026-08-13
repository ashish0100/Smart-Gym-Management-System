<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Remove all session variables
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| Remove the session cookie
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


/*
|--------------------------------------------------------------------------
| Destroy session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Return to login page
|--------------------------------------------------------------------------
*/

session_start();

$_SESSION['login_success'] =
    'You have been logged out successfully.';

header('Location: login.php');

exit;