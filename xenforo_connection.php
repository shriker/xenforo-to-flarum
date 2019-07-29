<?php

set_time_limit(0);
ini_set('memory_limit', -1);
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

class Database
{
    //***********************************************/
    // ✨ Change these variables ✨
    //***********************************************/
    protected $servername   = "localhost";
    protected $username     = "";
    protected $password     = "";

    // Table migrating from (XenForo)
    public $exportDBName   = "xenforo";
    public $exportDBPrefix = "xf_";

    // Table migrating to (Flarum)
    public $importDBName   = "flarum";
    public $importDBPrefix = "";
    public $adminUserID = 1;
    //***********************************************/

    public function __contruct($servername, $username, $password, $exportDBName, $exportDBPrefix, $importDBName, $importDBPrefix, $adminUserID)
    {
        $this->servername       = $servername;
        $this->username         = $username;
        $this->password         = $password;
        $this->exportDBName     = $exportDBName;
        $this->exportDBPrefix   = $exportDBPrefix;
        $this->importDBName     = $importDBName;
        $this->importDBPrefix   = $importDBPrefix;
        $this->adminUserID      = $adminUserID;
    }

    public function connectExport()
    {
        $exportDbConnection = new mysqli($this->servername, $this->username, $this->password, $this->exportDBName);

        if ($exportDbConnection->connect_error)
        {
            die("Export - Connection failed: " . $exportDbConnection->connect_error);
        }
        else
        {
            echo "Export - Connected successfully<br>\n";

            if (!$exportDbConnection->set_charset("utf8"))
            {
                printf("Error loading character set utf8: %s<br>\n", $exportDbConnection->error);
                exit();
            }
            else
            {
                printf("Current character set: %s<br>\n", $exportDbConnection->character_set_name());
            }
        }

        return $exportDbConnection;
    }

    public function connectImport()
    {
        $importDbConnection = new mysqli($this->servername, $this->username, $this->password, $this->importDBName);

        if ($importDbConnection->connect_error)
        {
            die("Export - Connection failed: " . $importDbConnection->connect_error);
        }
        else
        {
            echo "Import - Connected successfully<br>\n";

            if (!$importDbConnection->set_charset("utf8"))
            {
                printf("Error loading character set utf8: %s<br>\n", $importDbConnection->error);
                exit();
            }
            else
            {
                printf("Current character set: %s<br>\n", $importDbConnection->character_set_name());
            }
        }

        $importDbConnection->query("SET FOREIGN_KEY_CHECKS=0");

        return $importDbConnection;
    }
}
