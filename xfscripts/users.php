<?php

// Convert XenForo Users to Flarum Users

echo '<h2>Step 1 - Users</h2>';
$result = $exportDbConnection->query('SELECT user_id,
from_unixtime(register_date) as user_regdate,
from_unixtime(last_activity) as last_seen_at,
username, email
FROM '.$connection->exportDBPrefix.'user');
$totalUsers = $result->num_rows;

if ($totalUsers) {
    $i = 0;
    $usersIgnored = 0;
    while ($row = $result->fetch_assoc()) {
        $i++;

        if ($row['email'] != null) {
            $username = $row['username'];
            $usernameHasSpace = strpos($username, ' ');

            if ($usernameHasSpace > 0) {
                $formatedUsername = str_replace(' ', null, $username);
            } else {
                $formatedUsername = $username;
            }
            $id = $row['user_id'];
            $email = $row['email'];
            $password = null; // Safer to have unset passwords at the moment
            $jointime = $row['user_regdate'];
            $last_seen_at = $row['last_seen_at'];
            $query = 'INSERT INTO '.$connection->importDBPrefix."users (id, username, email, password, joined_at, last_seen_at, is_email_confirmed)
            VALUES ( '$id', '$formatedUsername', '$email', '$password', '$jointime', '$last_seen_at', 1)";
            $res = $importDbConnection->query($query);
            if ($res === false) {
                echo 'Wrong SQL: '.$query.' Error: '.$importDbConnection->error." <br/>\n";
            }
        } else {
            $usersIgnored++;
        }
    }
    echo '<p><b>Success.</b> '.($i - $usersIgnored).' out of '.$totalUsers.' total users migrated.</p>';
} else {
    echo 'Something went wrong.';
}

// Bans
echo '<h2>Step 1a - Importing banned users</h2>';
$bannedUsers = $exportDbConnection->query('SELECT user_id, end_date FROM '.$connection->exportDBPrefix.'user_ban');
if ($bannedUsers->num_rows > 0) {
    $bansMigrated = 0;

    foreach ($bannedUsers as $ban) {
        $bannedUser = $ban['user_id'];
        // Convert timestamp to date formats
        $timestamp = strtotime('+50 years');
        $duration = ($ban['end_date'] > 0) ? date('Y-m-d H:i:s', $ban['end_date']) : date('Y-m-d H:i:s', $timestamp);

        $query = 'UPDATE '.$connection->importDBPrefix."users SET suspended_until = '$duration' WHERE id = '$bannedUser'";
        $res = $importDbConnection->query($query);
        $bansMigrated++;
    }

    echo '<p><b>Success.</b> '.$bansMigrated.' bans migrated.</p>';
}
