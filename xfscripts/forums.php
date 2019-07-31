<?php

// Convert Categories to Tags

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
    echo '<p><b>Success.</b> ' . $totalCategories . ' categories migrated.</p>';
}
else
    echo "Something went wrong.";
