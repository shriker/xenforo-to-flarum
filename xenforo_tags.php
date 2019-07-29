<?php

require_once('xenforo_connection.php');

echo "<h1>XenForo 1.5 to Flarum Tags</h1>";

$connection = new Database();
$exportDbConnection = $connection->connectExport();
$importDbConnection = $connection->connectImport();

echo "<h2>Importing Tags and Associating Content</h2>";

// Select XenForo tags that have been used more than once
$result = $exportDbConnection->query("SELECT tag_id, tag, tag_url FROM " .$connection->exportDBPrefix. "tag WHERE use_count > 1 ");
$totalTags = $result->num_rows;

if ($totalTags)
{
    while ($row = $result->fetch_assoc())
    {
        $name = $row['tag'];
        $slug = $row['tag_url'];
        $color = rand_color();

        // Get previously tagged XenForo content
        $taggedContent = $exportDbConnection->query("SELECT content_id FROM " .$connection->exportDBPrefix. "tag_content WHERE tag_id = " . $row['tag_id'] ." ");
        $totalTaggedContent = $taggedContent->num_rows;

        // Create new tags in Flarum
        $query = "INSERT INTO " . $connection->importDBPrefix . "tags (name, slug, color, position, parent_id) VALUES ( '$name', '$slug', '$color', NULL, NULL)";
        $importDbConnection->query($query);

        // Associate new Flarum tags with old XenForo content
        $last_id = $importDbConnection->insert_id;

        while ($content = $taggedContent->fetch_assoc())
        {
            $content_id = $content['content_id'];
            $queryContent = "INSERT INTO " . $connection->importDBPrefix . "discussion_tag (discussion_id, tag_id) VALUES ( '$content_id', '$last_id')";
            $importDbConnection->query($queryContent);
        }
    }

    echo '<p><b>Success.</b> ' . $totalTags . ' tags imported and linked.</p>';
}
else
{
    echo "<p>No tags found.</p>";
}

$exportDbConnection->close();
$importDbConnection->close();

function rand_color()
{
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}
