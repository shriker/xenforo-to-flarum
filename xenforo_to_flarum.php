<?php

// Original script by robrotheram from discuss.flarum.org
// Modified by VIRUXE
// Modified by Reflic
// Modified by TidbitSoftware
// Modified by Jodie Struthers for XenForo

set_time_limit(0);
ini_set('memory_limit', -1);

ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

/**
 * Databases connection
 */
$servername = "localhost";
$username = "";
$password = "";

/**
 * Migrating from (XenForo)
 */
$exportDBName = "xenforo";
$exportDBPrefix = "xf_";

/**
 * Migrating to (Flarum)
 */
$importDBName = "flarum";
$importDBPrefix = "";

// Establish connections to the server where the XenForo and Flarum databases exists
$exportDbConnection = new mysqli($servername, $username, $password, $exportDBName);
$importDbConnection = new mysqli($servername, $username, $password, $importDBName);

echo "<h1>XenForo 1.5 to Flarum</h1>";

// Check connection
if ($exportDbConnection->connect_error)
	die("Export - Connection failed: " . $exportDbConnection->connect_error);
else
{
	echo "Export - Connected successfully<br>\n";

	if(!$exportDbConnection->set_charset("utf8"))
	{
	    printf("Error loading character set utf8: %s\n", $exportDbConnection->error);
	    exit();
	}
	else
	    printf("Current character set: %s\n", $exportDbConnection->character_set_name());
}

// Check connection
if ($importDbConnection->connect_error)
	die("Import - Connection failed: " . $importDbConnection->connect_error);
else
{
	echo "Import - Connected successfully<br>\n";

	if(!$importDbConnection->set_charset("utf8"))
	{
	    printf("Error loading character set utf8: %s\n", $importDbConnection->error);
	    exit();
	}
	else
	    printf("Current character set: %s\n", $importDbConnection->character_set_name());
}


//Disable foreing keys check

$importDbConnection->query("SET FOREIGN_KEY_CHECKS=0");


// Convert XenForo Users to Flarum Users

echo "<hr>Step 1 - Users<hr>";
$result = $exportDbConnection->query("SELECT user_id,
from_unixtime(register_date) as user_regdate,
from_unixtime(last_activity) as last_seen_at,
username, email
FROM ${exportDBPrefix}user");
$totalUsers = $result->num_rows;

if ($totalUsers)
{
	$i = 0;
	$usersIgnored = 0;
	while($row = $result->fetch_assoc())
	{
		$i++;

		if($row["email"] != NULL)
		{
			$username = $row["username"];
			$usernameHasSpace = strpos($username, " ");

			if($usernameHasSpace > 0)
			{
				$formatedUsername = str_replace(" ", NULL, $username);
			}
			else{
				$formatedUsername = $username;
			}
			$id = $row['user_id'];
			$email = $row['email'];
			$password = sha1(md5(time()));
            $jointime = $row['user_regdate'];
            $last_seen_at = $row['last_seen_at'];
            $query = "INSERT INTO " . $importDBPrefix . "users (id, username, email, password, joined_at, last_seen_at, is_email_confirmed)
            VALUES ( '$id', '$formatedUsername', '$email', '$password', '$jointime', '$last_seen_at', 1)";
			$res = $importDbConnection->query($query);
			if($res === false) {
				echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
			}
		}
		else {
			$usersIgnored++;
		}
	}
	echo $i-$usersIgnored . ' out of '. $totalUsers .' Total Users converted';
}
else
	echo "Something went wrong.";

//Convert Categories to Tags

echo "<hr>Step 2 - Categories<hr>";
$result = $exportDbConnection->query("SELECT node_id as forum_id,
title as forum_name, description as forum_desc
FROM ${exportDBPrefix}node
WHERE node_type_id = 'Forum' ");
$totalCategories = $result->num_rows;

if ($totalCategories)
{
	$i = 1;
	while($row = $result->fetch_assoc())
	{
		$id = $row["forum_id"];
		$name = mysql_escape_mimic($row["forum_name"]);
		$description = mysql_escape_mimic(strip_tags(stripBBCode($row["forum_desc"])));
		$color = rand_color();
		$position = $i;
		$slug = mysql_escape_mimic(slugify($row["forum_name"]));

		$query = "INSERT INTO " . $importDBPrefix . "tags (id, name, description, slug, color, position) VALUES ( '$id', '$name', '$description', '$slug', '$color', '$position')";
		$res = $importDbConnection->query($query);
		if($res === false) {
			echo "Wrong SQL Assumption id Confict now trying a update  <br/>\n";
			$queryupdate = "UPDATE " . $importDBPrefix . "tags SET name = '$name', description = '$description', slug = '$slug' WHERE id = '$id' ;";
			$res = $importDbConnection->query($queryupdate);
			if($res === false) { echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n"; }
		}
		$i++;
	}
	echo $totalCategories . ' Categories converted.';
}
else
	echo "Something went wrong.";

echo "<hr>Step 3 - Threads and Posts<hr>";
$topicsQuery = $exportDbConnection->query("SELECT thread_id as topic_id, user_id as topic_poster, node_id as forum_id, title as topic_title, post_date as topic_time
FROM ${exportDBPrefix}thread
ORDER BY topic_id DESC;");
$topicCount = $topicsQuery->num_rows;

if($topicCount)
{
	$curTopicCount = 0;
	$insertString = "INSERT INTO " . $importDBPrefix . "posts (id, user_id, discussion_id, created_at, type, content) VALUES \n";
	//	Loop trough all PHPBB topics
	$topictotal = $topicsQuery->num_rows;
	$i = 1;
	while($topic = $topicsQuery->fetch_assoc())
	{
		//	Convert posts per topic
		$participantsArr = [];
		$lastPosterID = 0;

		$sqlQuery = sprintf("SELECT * FROM ${exportDBPrefix}post WHERE thread_id = %d;", $topic["topic_id"]);
		$postsQuery = $exportDbConnection->query($sqlQuery);
		$postCount = $postsQuery->num_rows;

		if($postCount)
		{
			$curPost = 0;

			//fwrite($sqlScript_posts, $insertString);
			while($post = $postsQuery->fetch_assoc())
			{
				$curPost++;

				$posterID = $post['user_id'];
				$date = new DateTime();
				$date->setTimestamp($post["post_date"]);
				$postDate =  $date->format('Y-m-d H:i:s');
				$postText = formatText($exportDbConnection, $post['message']);

				// if($post['post_id'] == 913){echo $postText;}

				if (empty($post['username']))// If the post_username field has text it means it's a "ghost" post. Therefore we should set the poster id to 0 so Flarum knows it's an invalid user
				{
					$posterID = $post['user_id'];

					// Add to the array only if unique
					if(!in_array($posterID, $participantsArr))
						$participantsArr[] = $posterID;
				}

				if($curPost == $postCount)// Check if it's the last post in the discussion and save the poster id
					$lastPosterID = $posterID;

				// Execute the insert query in the desired database.
				$formattedValuesStr = sprintf("(%d, %d, %d, '%s', 'comment', '%s');", $post['post_id'], $posterID, $topic['topic_id'], $postDate, $postText);
				$query = $insertString . $formattedValuesStr;
				$res = $importDbConnection->query($query);
				if($res === false) {
					echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
				}
			}
		}
		//else
		//	echo "<br>\nTopic ". $topic['topic_id'] ." has zero posts.<br>\n";

		//	Convert topic to Flarum format
		//
		//	This needs to be done at the end because we need to get the post count first
		//
		$date = new DateTime();
		$date->setTimestamp($topic["topic_time"]);
		$discussionDate = $date->format('Y-m-d H:i:s');
		$topicTitle = $exportDbConnection->real_escape_string($topic["topic_title"]);

		// Link Discussion/Topic to a Tag/Category
		$topicid = $topic["topic_id"];
		$forumid = $topic["forum_id"];

		$query = "INSERT INTO " . $importDBPrefix . "discussion_tag (discussion_id, tag_id) VALUES( '$topicid', '$forumid')";
		$res = $importDbConnection->query($query);
		if($res === false) {
			echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
		}


		// Check for parent forums
		$parentForum = $exportDbConnection->query("SELECT parent_node_id as parent_id FROM ${exportDBPrefix}node WHERE node_id = " . $topic["forum_id"]);
		$result = $parentForum->fetch_assoc();
		if($result['parent_id'] > 0){
			$topicid = $topic["topic_id"];
			$parentid = $result['parent_id'];
			$query = "INSERT INTO " . $importDBPrefix . "discussion_tag (discussion_id, tag_id) VALUES( '$topicid', '$parentid')";
			$res = $importDbConnection->query($query);
			if($res === false) {
				echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
			}
	 	}
		if($lastPosterID == 0)// Just to make sure it displays an actual username if the topic doesn't have posts? Not sure about this.
			$lastPosterID = $topic["topic_poster"];

		$slug = mysql_escape_mimic(slugify($topicTitle));
		$count =  count($participantsArr);
		$poster = $topic["topic_poster"];
		$query = "INSERT INTO " . $importDBPrefix . "discussions (id, title, slug, created_at, comment_count, participant_count, first_post_id, last_post_id, user_id, last_posted_user_id, last_posted_at) VALUES( '$topicid', '$topicTitle', '$slug', '$discussionDate', '$postCount', '$count', 1, 1, '$poster', '$lastPosterID', '$discussionDate')";
		$res = $importDbConnection->query($query);
		if($res === false) {
			echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
		}

		$i++;
	}
}

// Convert user posted topics to user discussions?
echo "<hr> User Discussions<hr/>";
$result = $exportDbConnection->query("SELECT user_id, thread_id FROM ${exportDBPrefix}thread WHERE user_id != 0 ");
var_dump($exportDbConnection);
if ($result->num_rows > 0)
{
	$total = $result->num_rows;
	$i = 1;
	while($row = $result->fetch_assoc()) {
		$comma =  $i == $total ? ";" : ",";
		$userID = $row["user_id"];
		$topicID = $row["thread_id"];
		$query = "INSERT INTO " . $importDBPrefix . "discussion_user (user_id, discussion_id) VALUES ( '$userID', '$topicID')";
		$res = $importDbConnection->query($query);
		if($res === false) {
			echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
		}
		$i++;
	}
	echo "Success";
}
else
	echo "Table is empty";


	// Convert user posted topics to user discussions?
echo "<hr>last Step Update User table<hr/>";
$result = $importDbConnection->query("SELECT id FROM users");
if ($result->num_rows > 0)
{
	$total = $result->num_rows;
	$i = 1;
	while($row = $result->fetch_assoc()) {
		$comma =  $i == $total ? ";" : ",";
		$userID = $row["id"];
		$res = $importDbConnection->query("select * from discussion_user where user_id = '$userID' ");
		$numTopics =  $res->num_rows;

		$res1 = $importDbConnection->query("select * from posts where user_id = '$userID' ");
		$numPosts =  $res1->num_rows;

		$query = "UPDATE " . $importDBPrefix . "users SET discussion_count = '$numTopics',  comment_count = '$numPosts' WHERE id = '$userID' ";
		$res = $importDbConnection->query($query);
		if($res === false) {
			echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
		}
	}
	echo "Success";
}
else
	echo "Table is empty";



// Close connection to the database
$exportDbConnection->close();
$importDbConnection->close();

// Functions
function print_r2($val)
{
	echo '<pre>';
	print_r($val);
	echo  '</pre>';
}

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
	if(is_array($inp))
			return array_map(__METHOD__, $inp);

	if(!empty($inp) && is_string($inp)) {
			return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
	}
	return $inp;
}

// Formats PHPBB's text to Flarum's text format
function formatText($connection, $text)
{
	$text = preg_replace('#\:\w+#', '', $text);
	$text = convertBBCodeToHTML($text);
	$text = str_replace("&quot;","\"",$text);
	$text = preg_replace('|[[\/\!]*?[^\[\]]*?]|si', '', $text);
	$text = trimSmilies($text);

	// Wrap text lines with paragraph tags
	$explodedText = explode("\n", $text);
	foreach ($explodedText as $key => $value)
	{
		if(strlen($value) > 1)// Only wrap in a paragraph tag if the line has actual text
			$explodedText[$key] = '<p>' . $value . '</p>';
	}
	$text = implode("\n", $explodedText);

	$wrapTag = strpos($text, '&gt;') > 0 ? "r" : "t";// Posts with quotes need to be 'richtext'
	$text = sprintf('<%s>%s</%s>', $wrapTag, $text, $wrapTag);

	return $connection->real_escape_string($text);
}

// Used to convert Categories to Tags
function stripBBCode($text_to_search) {
    $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
    $replace = '';
    return preg_replace($pattern, $replace, $text_to_search);
}

function convertBBCodeToHTML($bbcode)
{
	$bbcode = preg_replace('#\[b](.+)\[\/b]#', "<b>$1</b>", $bbcode);
	$bbcode = preg_replace('#\[i](.+)\[\/i]#', "<i>$1</i>", $bbcode);
	$bbcode = preg_replace('#\[u](.+)\[\/u]#', "<u>$1</u>", $bbcode);
	$bbcode = preg_replace('#\[img](.+?)\[\/img]#is', "<img src='$1'\>", $bbcode);
	$bbcode = preg_replace('#\[quote=(.+?)](.+?)\[\/quote]#is', "<QUOTE><i>&gt;</i>$2</QUOTE>", $bbcode);
	$bbcode = preg_replace('#\[code:\w+](.+?)\[\/code:\w+]#is', "<CODE class='hljs'>$1<CODE>", $bbcode);
	$bbcode = preg_replace('#\[pre](.+?)\[\/pre]#is', "<code>$1<code>", $bbcode);
	$bbcode = preg_replace('#\[u](.+)\[\/u]#', "<u>$1</u>", $bbcode);
	$bbcode = preg_replace('#\[\*](.+?)\[\/\*]#is', "<li>$1</li>", $bbcode);
	$bbcode = preg_replace('#\[color=\#\w+](.+?)\[\/color]#is', "$1", $bbcode);
	$bbcode = preg_replace('#\[url=(.+?)](.+?)\[\/url]#is', "<a href='$1'>$2</a>", $bbcode);
	$bbcode = preg_replace('#\[url](.+?)\[\/url]#is', "<a href='$1'>$1</a>", $bbcode);
	$bbcode = preg_replace('#\[list](.+?)\[\/list]#is', "<ul>$1</ul>", $bbcode);
	$bbcode = preg_replace('#\[size=200](.+?)\[\/size]#is', "<h1>$1</h1>", $bbcode);
	$bbcode = preg_replace('#\[size=170](.+?)\[\/size]#is', "<h2>$1</h2>", $bbcode);
	$bbcode = preg_replace('#\[size=150](.+?)\[\/size]#is', "<h3>$1</h3>", $bbcode);
	$bbcode = preg_replace('#\[size=120](.+?)\[\/size]#is', "<h4>$1</h4>", $bbcode);
	$bbcode = preg_replace('#\[size=85](.+?)\[\/size]#is', "<h5>$1</h5>", $bbcode);

	return $bbcode;
}

function trimSmilies($postText)
{
	$startStr = "<!--";
	$endStr = 'alt="';

	$startStr1 = '" title';
	$endStr1 = " -->";

	$emoticonsCount = substr_count($postText, '<img src="{SMILIES_PATH}');

	for ($i=0; $i < $emoticonsCount; $i++)
	{
		$startPos = strpos($postText, $startStr);
		$endPos = strpos($postText, $endStr);

		$postText = substr_replace($postText, NULL, $startPos, $endPos-$startPos+strlen($endStr));

		$startPos1 = strpos($postText, $startStr1);
		$endPos1 = strpos($postText, $endStr1);

		$postText = substr_replace($postText, NULL, $startPos1, $endPos1-$startPos1+strlen($endStr1));
	}

	return $postText;
}
