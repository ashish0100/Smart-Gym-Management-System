<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Smart Gym Management System
| Database Connection
|--------------------------------------------------------------------------
| World Fitness Australia - ICT308 Project 2
|--------------------------------------------------------------------------
*/

$host = 'localhost';
$database = 'smart_gym';
$username = 'root';
$password = '';

$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );

} catch (PDOException $exception) {

    error_log(
        'Database connection error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to connect to the database. Please try again later.'
    );
}