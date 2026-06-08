<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/units_helpers.php';

$pdo = getDBConnection();
$result = seedProgramUnits($pdo);

if ($result['ok']) {
    echo $result['message'] . PHP_EOL;
    exit(0);
}

echo 'Error: ' . $result['error'] . PHP_EOL;
exit(1);
