<?php

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
    echo '<p><b>Success.</b> ' . $topictotal . ' threads migrated.</p>';
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

// Formats XenForo's post text to Flarum's text format
function formatText($connection, $text)
{
    $text = convertBb($text);
    $text = XenForoBundle::parse($text);
    $text = replaceStaleBb($text);

    return $connection->real_escape_string($text);
}

/**
 * Convert XenForo BBcode to Flarum equivalents
 *
 * @param string $text
 * @return void
 */
function convertBb(string $text)
{
    $text = preg_replace('#\[PHP](.+?)\[\/PHP]#is', "[CODE]$1[/CODE]", $text);
    $text = preg_replace('#\[HTML](.+?)\[\/HTML]#is', "[CODE]$1[/CODE]", $text);
    $text = preg_replace('#\[PLAIN](.+?)\[\/PLAIN]#is', "[CODE]$1[/CODE]", $text);
    // Tidy quotes
    $text = preg_replace('#\[QUOTE](.+?)\[\/QUOTE]#is', "[QUOTE]$1[/QUOTE]", $text);
    $text = preg_replace('#\[QUOTE=\"(.+?)\"](.+?)\[\/QUOTE]#is', "[QUOTE]$2[/QUOTE]", $text);
    // Sizes in XenForo only go 1-7... and convert badly, so let's fix that
    $text = preg_replace_callback(
        '#\[SIZE=(\d+?)](.+?)\[\/SIZE]#is',
        function($matches)
        {
            $num = 0 - $matches[1];
            $sizes = range(7, 1);
            $size = array_slice($sizes, $num, 1);
            $size = ($size[0] * 6);

            return "[SIZE=" . $size . "]" .$matches[2]. "[/SIZE]";
        },
        $text
    );

    return $text;
}

/**
 * Remove unsupported XenForo BBCode
 *
 * @param string $text
 * @return void
 */
function replaceStaleBb(string $text)
{
    $tabs    = '\[TABS]|\[\/TABS]';
    $tab     = '\[TAB]|\[\/TAB]';
    $indent  = '\[INDENT]|\[\/INDENT]';
    $left    = '\[LEFT]|\[\/LEFT]';
    $right   = '\[RIGHT]|\[\/RIGHT]';
    // [ATTACH] - Attachment Insertion
    // [SPOILER]

    // Splort
    $regex   = "/$tabs|$tab|$indent|$left|$right/";
    $text = preg_replace($regex, '', $text);

     // [USER=ID] - Profile Linking
    $text = preg_replace('#\[USER=(.+?)]@(.+?)\[\/USER]#is', "<USERMENTION displayname=\"$2\" id=\"$1\" username=\"$2\">$2</USERMENTION>", $text);

    // @TODO Replace internal discussion links

    return $text;
}
