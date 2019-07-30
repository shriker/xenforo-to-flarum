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

// Convert XenForo Users to Flarum Users

echo "<h2>Step 1 - Users</h2>";
$result     = $exportDbConnection->query("SELECT user_id,
from_unixtime(register_date) as user_regdate,
from_unixtime(last_activity) as last_seen_at,
username, email
FROM " .$connection->exportDBPrefix. "user");
$totalUsers = $result->num_rows;

if ($totalUsers)
{
    $i            = 0;
    $usersIgnored = 0;
    while ($row = $result->fetch_assoc())
    {
        $i++;

        if ($row["email"] != NULL)
        {
            $username         = $row["username"];
            $usernameHasSpace = strpos($username, " ");

            if ($usernameHasSpace > 0)
            {
                $formatedUsername = str_replace(" ", NULL, $username);
            }
            else
            {
                $formatedUsername = $username;
            }
            $id           = $row['user_id'];
            $email        = $row['email'];
            $password     = null; // Safer to have unset passwords at the moment
            $jointime     = $row['user_regdate'];
            $last_seen_at = $row['last_seen_at'];
            $query        = "INSERT INTO " . $connection->importDBPrefix . "users (id, username, email, password, joined_at, last_seen_at, is_email_confirmed)
            VALUES ( '$id', '$formatedUsername', '$email', '$password', '$jointime', '$last_seen_at', 1)";
            $res          = $importDbConnection->query($query);
            if ($res === false)
            {
                echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
            }
        }
        else
        {
            $usersIgnored++;
        }
    }
    echo '<p><b>Success.</b> ' . ($i - $usersIgnored) . ' out of ' . $totalUsers . ' total users converted.</p>';
}
else
    echo "Something went wrong.";

//Convert Categories to Tags

echo "<h2>Step 2 - Forum Nodes</h2>";
$result          = $exportDbConnection->query("SELECT node_id as forum_id,
title as forum_name, description as forum_desc,
parent_node_id as parent_id,
display_order as position,
node_name
FROM " .$connection->exportDBPrefix. "node
WHERE node_type_id = 'Forum' OR node_type_id = 'Category' ");
$totalCategories = $result->num_rows;

if ($totalCategories)
{
    $i = 1;
    while ($row = $result->fetch_assoc())
    {
        $id          = $row["forum_id"];
        $name        = mysql_escape_mimic($row["forum_name"]);
        $description = mysql_escape_mimic(strip_tags(stripBBCode($row["forum_desc"])));
        $color       = rand_color();
        $position    = $row["position"];
        $parent_id   = $row["parent_id"];
        if (empty($row["node_name"]))
        {
            // We need to slugify Category names since they are not set in Xenforo
            $slug        = mysql_escape_mimic(slugify($row["forum_name"]));
        } else {
            $slug        = $row["node_name"];
        }

        $query = "INSERT INTO " . $connection->importDBPrefix . "tags (id, name, description, slug, color, position, parent_id) VALUES ( '$id', '$name', '$description', '$slug', '$color', '$position', '$parent_id')";
        $res   = $importDbConnection->query($query);
        if ($res === false)
        {
            echo "Wrong SQL Assumption id Confict now trying a update  <br/>\n";
            $queryupdate = "UPDATE " . $connection->importDBPrefix . "tags SET name = '$name', description = '$description', slug = '$slug' WHERE id = '$id' ;";
            $res         = $importDbConnection->query($queryupdate);
            if ($res === false)
            {
                echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
            }
        }
        $i++;
    }
    echo '<p><b>Success.</b> ' . $totalCategories . ' categories converted.</p>';
}
else
    echo "Something went wrong.";

echo "<h2>Step 3 - Threads and Posts</h2>";
$topicsQuery = $exportDbConnection->query("SELECT thread_id as topic_id,
user_id as topic_poster,
node_id as forum_id,
title as topic_title,
post_date as topic_time,
sticky as is_sticky,
discussion_state,
discussion_open
FROM " .$connection->exportDBPrefix. "thread
ORDER BY topic_id DESC;");
$topicCount  = $topicsQuery->num_rows;

if ($topicCount)
{
    $curTopicCount = 0;
    $insertString  = "INSERT INTO " . $connection->importDBPrefix . "posts (id, user_id, discussion_id, created_at, type, content) VALUES \n";
    // Loop trough all XenForo threads
    $topictotal    = $topicsQuery->num_rows;
    $i             = 1;
    while ($topic = $topicsQuery->fetch_assoc())
    {
        // Convert posts per topic
        $participantsArr = [];
        $lastPosterID = 0;

        $sqlQuery   = sprintf("SELECT * FROM " .$connection->exportDBPrefix. "post WHERE thread_id = %d;", $topic["topic_id"]);
        $postsQuery = $exportDbConnection->query($sqlQuery);
        $postCount  = $postsQuery->num_rows;

        if ($postCount)
        {
            $curPost = 0;

            while ($post = $postsQuery->fetch_assoc())
            {
                $curPost++;

                $posterID = $post['user_id'];
                $date     = new DateTime();
                $date->setTimestamp($post["post_date"]);
                $postDate = $date->format('Y-m-d H:i:s');
                $postText = formatText($exportDbConnection, $post['message']);

                // if user_id is 0, it means we have a guest post
                if ($post['user_id'] == 0)
                {

                    $posterID = 0;

                }
                else
                {

                    $posterID = $post['user_id'];

                    // Add to the array only if unique
                    if (!in_array($posterID, $participantsArr))
                    {
                        $participantsArr[] = $posterID;
                    }

                }

                // Check if it's the last post in the discussion and save the poster id
                if ($curPost == $postCount)
                {
                    $lastPosterID = $posterID;
                }

                // Execute the insert query in the desired database.
                $formattedValuesStr = sprintf("(%d, %d, %d, '%s', 'comment', '%s');", $post['post_id'], $posterID, $topic['topic_id'], $postDate, $postText);
                $query              = $insertString . $formattedValuesStr;
                $res                = $importDbConnection->query($query);
                if ($res === false)
                {
                    echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
                }
            }
        }
        //else
        //    echo "<br>\nTopic ". $topic['topic_id'] ." has zero posts.<br>\n";

        //    Convert topic to Flarum format
        //
        //    This needs to be done at the end because we need to get the post count first
        //
        $date = new DateTime();
        $date->setTimestamp($topic["topic_time"]);
        $discussionDate = $date->format('Y-m-d H:i:s');
        $topicTitle     = $exportDbConnection->real_escape_string($topic["topic_title"]);

        // Link Discussion/Topic to a Tag/Category
        $topicid = $topic["topic_id"];
        $forumid = $topic["forum_id"];

        $query = "INSERT INTO " . $connection->importDBPrefix . "discussion_tag (discussion_id, tag_id) VALUES( '$topicid', '$forumid')";
        $res   = $importDbConnection->query($query);
        if ($res === false)
        {
            echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
        }


        // Check for parent forums
        $parentForum = $exportDbConnection->query("SELECT parent_node_id as parent_id FROM " .$connection->exportDBPrefix. "node WHERE node_id = " . $topic["forum_id"]);
        $result      = $parentForum->fetch_assoc();
        if ($result['parent_id'] > 0)
        {
            $topicid  = $topic["topic_id"];
            $parentid = $result['parent_id'];
            $query    = "INSERT INTO " . $connection->importDBPrefix . "discussion_tag (discussion_id, tag_id) VALUES( '$topicid', '$parentid')";
            $res      = $importDbConnection->query($query);
            if ($res === false)
            {
                echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
            }
        }
        if ($lastPosterID == 0) // Just to make sure it displays an actual username if the topic doesn't have posts? Not sure about this.
        {
            $lastPosterID = $topic["topic_poster"];
        }

        $slug        = mysql_escape_mimic(slugify($topicTitle));
        $count       = count($participantsArr);
        $poster      = $topic["topic_poster"];
        $is_locked   = ($topic["discussion_open"] == 1 ? 0 : 1);
        $is_private  = ($topic["discussion_state"] == "deleted" ? 1 : 0);
        $is_approved = ($topic["discussion_state"] == "moderated" ? 0 : 1);
        $is_sticky   = $topic["is_sticky"];
        $query       = "INSERT INTO " . $connection->importDBPrefix . "discussions (id, title, slug, created_at, comment_count, participant_count, first_post_id, last_post_id, user_id, last_posted_user_id, last_posted_at, is_private, is_approved, is_locked, is_sticky) VALUES( '$topicid', '$topicTitle', '$slug', '$discussionDate', '$postCount', '$count', 1, 1, '$poster', '$lastPosterID', '$discussionDate', '$is_private', '$is_approved', '$is_locked', '$is_sticky')";
        $res         = $importDbConnection->query($query);
        if ($res === false)
        {
            echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
        }

        $i++;
    }
    echo '<p><b>Success.</b> ' . $topictotal . ' threads converted.</p>';
}

// Convert user posted topics to user discussions?
echo "<h2>Step 4 - Link Users to Discussions</h2>";
$result = $exportDbConnection->query("SELECT user_id, thread_id FROM " .$connection->exportDBPrefix. "thread WHERE user_id != 0 ");

if ($result->num_rows > 0)
{
    $total = $result->num_rows;
    $i     = 1;
    while ($row = $result->fetch_assoc())
    {
        $comma   = $i == $total ? ";" : ",";
        $userID  = $row["user_id"];
        $topicID = $row["thread_id"];
        $query   = "INSERT INTO " . $connection->importDBPrefix . "discussion_user (user_id, discussion_id) VALUES ( '$userID', '$topicID')";
        $res     = $importDbConnection->query($query);
        if ($res === false)
        {
            echo "Wrong SQL: " . $query . " Error: " . $importDbConnection->error . " <br/>\n";
        }
        $i++;
    }
    echo "<p><b>Success.</b> Threads successfully linked to users.</p>";
}
else
{
    echo "Table is empty";
}

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

// Formats XenForo's post text to Flarum's text format
function formatText($connection, $text)
{
    $text = preg_replace('#\:\w+#', '', $text);
    // XenForo posts could have unescaped <> tags
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);
    $text = convertBBCodeToHTML($text);
    $text = str_replace("&quot;", "\"", $text);
    $text = preg_replace('|[[\/\!]*?[^\[\]]*?]|si', '', $text);
    $text = trimSmilies($text);

    // Wrap text lines with paragraph tags
    $explodedText = explode("\n", $text);
    foreach ($explodedText as $key => $value)
    {
        // Only wrap in a paragraph tag if the line has actual text
        if (strlen($value) > 1)
        {
            $explodedText[$key] = '<p>' . $value . '</p>';
        }
    }
    $text = implode("\n", $explodedText);

    $wrapTag = strpos($text, '&gt;') > 0 ? "r" : "t"; // Posts with quotes need to be 'richtext'
    $text    = sprintf('<%s>%s</%s>', $wrapTag, $text, $wrapTag);

    return $connection->real_escape_string($text);
}

// Used to convert Categories to Tags
function stripBBCode($text_to_search)
{
    $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
    $replace = '';

    return preg_replace($pattern, $replace, $text_to_search);
}

function convertBBCodeToHTML($bbcode)
{
    // [B], [I], [U], [S] - Bold, Italics, Underline, and Strike-through
    $bbcode = preg_replace('#\[B](.+)\[\/B]#', "<b>$1</b>", $bbcode);
    $bbcode = preg_replace('#\[I](.+)\[\/I]#', "<i>$1</i>", $bbcode);
    $bbcode = preg_replace('#\[U](.+)\[\/U]#', "<u>$1</u>", $bbcode);
    $bbcode = preg_replace('#\[S](.+)\[\/S]#', "<s>$1</s>", $bbcode);
    // [COLOR=color], [FONT=name], [SIZE=size] - Text Color, Font, and Size
    $bbcode = preg_replace('#\[COLOR=\#\w+](.+?)\[\/COLOR]#is', "$1", $bbcode);
    $bbcode = preg_replace('#\[FONT=\#\w+](.+?)\[\/FONT]#is', "$1", $bbcode);
    $bbcode = preg_replace('#\[SIZE=7](.+?)\[\/SIZE]#is', "<h1>$1</h1>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=6](.+?)\[\/SIZE]#is', "<h2>$1</h2>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=5](.+?)\[\/SIZE]#is', "<h3>$1</h3>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=4](.+?)\[\/SIZE]#is', "<h4>$1</h4>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=3](.+?)\[\/SIZE]#is', "<h5>$1</h5>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=2](.+?)\[\/SIZE]#is', "<h6>$1</h6>", $bbcode);
    $bbcode = preg_replace('#\[SIZE=1](.+?)\[\/SIZE]#is', "<small>$1</small>", $bbcode);
    // [URL], [EMAIL] - Linking & [URL=link], [EMAIL=address] - Linking (Advanced)
    $bbcode = preg_replace('#\[URL=\'(.+?)\'](.+?)\[\/URL]#is', "<a href='$1'>$2</a>", $bbcode);
    $bbcode = preg_replace('#\[URL](.+?)\[\/URL]#is', "<a href='$1'>$1</a>", $bbcode);
    $bbcode = preg_replace('#\[EMAIL=\'(.+?)\'](.+?)\[\/EMAIL]#is', "<a href='mailto:$1'>$2</a>", $bbcode);
    $bbcode = preg_replace('#\[EMAIL](.+?)\[\/EMAIL]#is', "<a href='mailto:$1'>$1</a>", $bbcode);
    // @TODO [USER=ID] - Profile Linking
    // [IMG] - Image
    $bbcode = preg_replace('#\[IMG](.+?)\[\/IMG]#is', "<img src='$1'>", $bbcode);
    // @TODO [MEDIA=site] - Embedded Media
    // [LIST] - Lists
    $bbcode = preg_replace('#\[\*](.+?)[\n\r]+#is', "<li>$1</li>", $bbcode);
    $bbcode = preg_replace('#(\[LIST\]([^\r\n]*))(\r\n)*([^\r\n]*\[\/LIST\])#is', "$1", $bbcode); // remove line breaks between tags
    $bbcode = preg_replace('#\[LIST](.+?)\[\/LIST]#is', "<ul>$1</ul>", $bbcode);
    $bbcode = preg_replace('#\[LIST=1](.+?)\[\/LIST]#is', "<ol>$1</ol>", $bbcode);
    // @TODO [LEFT], [CENTER], [RIGHT] - Text Alignment
    // [QUOTE] - Quoted Text
    $bbcode = preg_replace('#\[QUOTE](.+?)\[\/QUOTE]#is', "<QUOTE><i>&gt;</i>$1</QUOTE>", $bbcode);
    $bbcode = preg_replace('#\[QUOTE=\"(.+?)\"](.+?)\[\/QUOTE]#is', "<QUOTE><i>&gt;</i>$2</QUOTE>", $bbcode);
    // [CODE], [PHP], [HTML] - Programming Code Display
    $bbcode = preg_replace('#\[CODE:\w+](.+?)\[\/CODE:\w+]#is', "<CODE class='hljs'>$1<CODE>", $bbcode);
    $bbcode = preg_replace('#\[PLAIN](.+?)\[\/PLAIN]#is', "<code>$1<code>", $bbcode);
    // [INDENT] - Text Indent
    // [PLAIN] - Plain Text
    // [ATTACH] - Attachment Insertion

    return $bbcode;
}

function trimSmilies($postText)
{
    $startStr = "<!--";
    $endStr   = 'alt="';

    $startStr1 = '" title';
    $endStr1   = " -->";

    $emoticonsCount = substr_count($postText, '<img src="{SMILIES_PATH}');

    for ($i = 0; $i < $emoticonsCount; $i++)
    {
        $startPos = strpos($postText, $startStr);
        $endPos   = strpos($postText, $endStr);

        $postText = substr_replace($postText, NULL, $startPos, $endPos - $startPos + strlen($endStr));

        $startPos1 = strpos($postText, $startStr1);
        $endPos1   = strpos($postText, $endStr1);

        $postText = substr_replace($postText, NULL, $startPos1, $endPos1 - $startPos1 + strlen($endStr1));
    }

    return $postText;
}
