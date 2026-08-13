<?php

require_once __DIR__ . '/../config/database.php';

try {

    $statement = $pdo->query("SELECT DATABASE() AS database_name");

    $result = $statement->fetch();

    echo "<h2>Database connection successful!</h2>";

    echo "<p>Connected database: "
        . htmlspecialchars($result['database_name'])
        . "</p>";

} catch (PDOException $exception) {

    echo "<h2>Database test failed.</h2>";

}