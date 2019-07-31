<?php

require_once 'xenforo_connection.php';

echo '<h1>XenForo to Flarum Ignored Users</h1>';

$connection = new Database();
$exportDbConnection = $connection->connectExport();
$importDbConnection = $connection->connectImport();

echo '<h2>Importing Ignored Users</h2>';

echo "<p>This script requires the <a href='https://packagist.org/packages/fof/ignore-users'>fof/ignore-users</a> extension.</p>";

$result = $exportDbConnection->query('SELECT user_id, ignored_user_id FROM '.$connection->exportDBPrefix.'user_ignored');
$totalIgnores = $result->num_rows;

if ($totalIgnores) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $ignored_user_id = $row['ignored_user_id'];

        $query = 'INSERT INTO '.$connection->importDBPrefix."ignored_user (user_id, ignored_user_id, ignored_at) VALUES ( $user_id, $ignored_user_id, CURDATE())";
        $importDbConnection->query($query);
    }

    echo '<p><b>Success.</b> '.$totalIgnores.' ignores imported.</p>';
} else {
    echo '<p>No ignore results found.</p>';
}

$exportDbConnection->close();
$importDbConnection->close();
