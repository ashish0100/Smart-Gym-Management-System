<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get and clean submitted data
|--------------------------------------------------------------------------
*/

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');
$address = trim($_POST['address'] ?? '');
$membershipType = trim($_POST['membership_type'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$termsAccepted = isset($_POST['terms']);


/*
|--------------------------------------------------------------------------
| Helper function
|--------------------------------------------------------------------------
*/

function registrationError(string $message): never
{
    $_SESSION['register_error'] = $message;

    header('Location: register.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Required field validation
|--------------------------------------------------------------------------
*/

if (
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $phone === '' ||
    $dateOfBirth === '' ||
    $address === '' ||
    $membershipType === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    registrationError('Please complete all required fields.');
}


/*
|--------------------------------------------------------------------------
| Name validation
|--------------------------------------------------------------------------
*/

if (
    !preg_match("/^[a-zA-ZÀ-ÿ' -]{2,50}$/u", $firstName) ||
    !preg_match("/^[a-zA-ZÀ-ÿ' -]{2,50}$/u", $lastName)
) {
    registrationError('Please enter a valid first and last name.');
}


/*
|--------------------------------------------------------------------------
| Email validation
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    registrationError('Please enter a valid email address.');
}


/*
|--------------------------------------------------------------------------
| Phone validation
|--------------------------------------------------------------------------
*/

$phoneClean = preg_replace('/[\s()-]/', '', $phone);

if (
    $phoneClean === null ||
    !preg_match('/^\+?[0-9]{8,15}$/', $phoneClean)
) {
    registrationError('Please enter a valid phone number.');
}


/*
|--------------------------------------------------------------------------
| Date of birth validation
|--------------------------------------------------------------------------
*/

$date = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirth);

if (
    !$date ||
    $date->format('Y-m-d') !== $dateOfBirth
) {
    registrationError('Please enter a valid date of birth.');
}

$today = new DateTimeImmutable('today');

if ($date > $today) {
    registrationError('Date of birth cannot be in the future.');
}


/*
|--------------------------------------------------------------------------
| Membership validation
|--------------------------------------------------------------------------
*/

$allowedMemberships = [
    'weekly',
    'monthly',
    'annual'
];

if (!in_array($membershipType, $allowedMemberships, true)) {
    registrationError('Please select a valid membership type.');
}


/*
|--------------------------------------------------------------------------
| Password validation
|--------------------------------------------------------------------------
*/

if (strlen($password) < 8) {
    registrationError('Password must contain at least 8 characters.');
}

if (!preg_match('/[A-Z]/', $password)) {
    registrationError('Password must contain at least one uppercase letter.');
}

if (!preg_match('/[a-z]/', $password)) {
    registrationError('Password must contain at least one lowercase letter.');
}

if (!preg_match('/[0-9]/', $password)) {
    registrationError('Password must contain at least one number.');
}

if ($password !== $confirmPassword) {
    registrationError('Password and confirm password do not match.');
}


/*
|--------------------------------------------------------------------------
| Terms validation
|--------------------------------------------------------------------------
*/

if (!$termsAccepted) {
    registrationError('You must accept the membership terms to register.');
}


/*
|--------------------------------------------------------------------------
| Create membership dates
|--------------------------------------------------------------------------
*/

$startDate = new DateTimeImmutable('today');

switch ($membershipType) {
    case 'weekly':
        $endDate = $startDate->modify('+7 days');
        break;

    case 'monthly':
        $endDate = $startDate->modify('+1 month');
        break;

    case 'annual':
        $endDate = $startDate->modify('+1 year');
        break;

    default:
        registrationError('Invalid membership type.');
}


/*
|--------------------------------------------------------------------------
| Hash password securely
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($passwordHash === false) {
    registrationError('Unable to process your password. Please try again.');
}


/*
|--------------------------------------------------------------------------
| Database transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Check duplicate email
    |--------------------------------------------------------------------------
    */

    $checkEmail = $pdo->prepare(
        'SELECT user_id
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $checkEmail->execute([
        'email' => $email
    ]);

    if ($checkEmail->fetch()) {

        $pdo->rollBack();

        registrationError(
            'An account with this email address already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert user
    |--------------------------------------------------------------------------
    */

    $insertUser = $pdo->prepare(
        'INSERT INTO users
        (
            first_name,
            last_name,
            email,
            password_hash,
            role,
            status
        )
        VALUES
        (
            :first_name,
            :last_name,
            :email,
            :password_hash,
            :role,
            :status
        )'
    );

    $insertUser->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => $passwordHash,
        'role' => 'member',
        'status' => 'active'
    ]);

    $userId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Insert member profile
    |--------------------------------------------------------------------------
    */

    $insertMember = $pdo->prepare(
        'INSERT INTO members
        (
            user_id,
            phone,
            date_of_birth,
            address,
            registration_date
        )
        VALUES
        (
            :user_id,
            :phone,
            :date_of_birth,
            :address,
            :registration_date
        )'
    );

    $insertMember->execute([
        'user_id' => $userId,
        'phone' => $phoneClean,
        'date_of_birth' => $dateOfBirth,
        'address' => $address,
        'registration_date' => $startDate->format('Y-m-d')
    ]);

    $memberId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Create selected membership
    |--------------------------------------------------------------------------
    |
    | Membership begins as pending.
    | Later, payment processing will change it to active.
    |--------------------------------------------------------------------------
    */

    $insertMembership = $pdo->prepare(
        'INSERT INTO memberships
        (
            member_id,
            membership_type,
            access_type,
            start_date,
            end_date,
            status
        )
        VALUES
        (
            :member_id,
            :membership_type,
            :access_type,
            :start_date,
            :end_date,
            :status
        )'
    );

    $insertMembership->execute([
        'member_id' => $memberId,
        'membership_type' => $membershipType,
        'access_type' => 'all_access',
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),
        'status' => 'pending'
    ]);


    /*
    |--------------------------------------------------------------------------
    | Finish transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Registration successful
    |--------------------------------------------------------------------------
    */

    $_SESSION['register_success'] =
        'Your account has been created successfully. Please log in.';

    header('Location: login.php');

    exit;


} catch (PDOException $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Registration database error: ' .
        $exception->getMessage()
    );

    registrationError(
        'Registration could not be completed. Please try again.'
    );
}