<?php

/**
 * Original script by robrotheram from discuss.flarum.org
 * Modified by VIRUXE
 * Modified by Reflic
 * Modified by TidbitSoftware
 * Modified by Jodie Struthers for XenForo
 */

require_once('xenforo_connection.php');

echo "<h1>XenForo 1.5 to Flarum</h1>";

$connection = new Database();
$exportDbConnection = $connection->connectExport();
$importDbConnection = $connection->connectImport();

$truncateTables = true;
if ($truncateTables)
{
    $tableNames  = [
        'discussions',
        'discussion_tag',
        'discussion_user',
        'group_user',
        'posts',
        'tags',
        'tag_user',
        'users'
    ];

    foreach ($tableNames as $tableName)
    {
        $importDbConnection->query("TRUNCATE " .$connection->importDBPrefix . $tableName);
    }
}

// Run migration scripts
require 'xfscripts/users.php';
require 'xfscripts/forums.php';
require 'xfscripts/threads.php';

// Convert user posted topics to user discussions?
echo "<h2>Step 5 - Update User Content Counts</h2>";
$result = $importDbConnection->query("SELECT id FROM users");
if ($result->num_rows > 0)
{
    $total = $result->num_rows;
    $i     = 1;
    while ($row = $result->fetch_assoc())
    {
        $comma     = $i == $total ? ";" : ",";
        $userID    = $row["id"];
        $res       = $importDbConnection->query("select * from discussion_user where user_id = '$userID' ");
        $numTopics = $res->num_rows;

        $res1     = $importDbConnection->query("select * from posts where user_id = '$userID' ");
        $numPosts = $res1->num_rows;

        $query = "UPDATE " . $connection->importDBPrefix . "users SET discussion_count = '$numTopics',  comment_count = '$numPosts' WHERE id = '$userID' ";
        $res   = $importDbConnection->query($query);
        if ($res === false)
        {
            echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
        }
    }
    echo "<p><b>Success.</b> User post counts updated successfully.</p>";
}
else
{
    echo "Table is empty";
}

echo "<h2>Step 6 - Adding user_id# ".$connection->adminUserID." to Flarum Admin Group</h2>";
$query = "INSERT INTO " . $connection->importDBPrefix . "group_user (user_id, group_id) VALUES( '$connection->adminUserID', 1)";
$res   = $importDbConnection->query($query);
if ($res === false)
{
    echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
} else {
    echo "<p><b>Success.</b> Admin user added to admin group.</p>";
}

// Close connections to the databases
$exportDbConnection->close();
$importDbConnection->close();

function slugify($text)
{
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    // Multibyte String Functions in case of emojis
    $text = mb_strtolower($text);
    $text = iconv('utf-8', 'utf-8//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);

    if (empty($text))
        return 'n-a';

    return $text;
}

function rand_color()
{
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

function mysql_escape_mimic($inp)
{
    if (is_array($inp))
    {
        return array_map(__METHOD__, $inp);
    }

    if (!empty($inp) && is_string($inp))
    {
        return str_replace(array(
            '\\',
            "\0",
            "\n",
            "\r",
            "'",
            '"',
            "\x1a"
        ), array(
            '\\\\',
            '\\0',
            '\\n',
            '\\r',
            "\\'",
            '\\"',
            '\\Z'
        ), $inp);
    }

    return $inp;
}

// Used to convert Categories to Tags
function stripBBCode($text_to_search)
{
    $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
    $replace = '';

    return preg_replace($pattern, $replace, $text_to_search);
}
