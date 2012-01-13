<?php
/**
 * dbgitbackup : Dumps SQL databases and stores them in GIT repositories (https://github.com/fireflyinteractive/dbgitbackup)
 * Copyright 2011, FireFly Interactive (http://www.fireflyinteractive.pl)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2011, FireFly Interactive (http://www.fireflyinteractive.pl)
 * @link https://github.com/fireflyinteractive/dbgitbackup
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class DbGitBackup_Vendor_MySql {

    public $conn;

    public $mysqldump;

    public $output;

    public $defaults = array(
        'server' => array(
            'host' => 'localhost',
            'port' => 3306,
        )
    );

    public $options = array();

    public function __construct($options) {
        // Locate mysqldump binary
        $output = array();
        exec('which mysqldump', $output);
        $this->mysqldump = $output[0];

        $this->options = DbGitBackup::merge($this->defaults, $options);

        // Init connection
        $this->conn = mysql_connect(
            $this->options['server']['host'] . ':' . $this->options['server']['port'],
            $this->options['server']['user'],
            $this->options['server']['password']
        );
    }

    public function __destruct() {
         if ($this->conn) mysql_close($this->conn);
    }

    public function performBackup() {
        if (!$this->conn) return false;
        if (!mysql_select_db($this->options['db'], $this->conn)) return false;

        exec($this->getDumpStatement(), $this->output, $status);
        if (!$status) return true;
        return false;
    }

/**
 * http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html
 *
 * @return string
 */
    public function getDumpStatement() {
        $options = $this->options;
        $cmd = "{$options['nice']} " .
                $this->mysqldump .
                " --user={$options['server']['user']}" .
                " --password={$options['server']['password']}" .
                " --host={$options['server']['host']}" .
                " --port={$options['server']['port']}" .
                " --skip-extended-insert --comments=0" . // required in git backup mode
                " --add-drop-table --default-character-set=utf8 --quote-names --compress" .
                $options['vendorOptions'] .
                " --result-file={$options['backupDir']}" . DS . "{$options['file']}" .
                " {$options['db']}"
        ;
        if (isset($options['tableNames'])) {
            $cmd .= " {$this->getTableNames()}";
        }
        return $cmd;
    }

/**
 * Returns space delimited table names
 *
 * @return string
 */
    public function getTableNames() {
        $options = $this->options;
        $tableNames = explode(' ', $options['tableNames']);
        $patternTableNames = array();
        foreach ($tableNames as $key => $tableName) {
            if (strpos($tableName, '%') !== false) {
                $patternTableNames[] = $tableName;
                unset($tableNames[$key]);
            }
        }
        if (!empty($patternTableNames)) {
            $tableNames = array_values($tableNames);
            $foundTables = array();
            foreach ($patternTableNames as $patternTableName) {
                $result = mysql_query("SHOW TABLES LIKE '$patternTableName'", $this->conn);
                while($row = mysql_fetch_row($result)) {
                    $foundTables[] = $row[0];
                }
                mysql_free_result($result);
            }
            $tableNames = array_unique(array_merge($tableNames, $foundTables));
        }
        return implode(' ', $tableNames);
    }

/**
 * Returns all non-empty databases on the server
 *
 * @return array
 */
    public function getDatabases() {
        $options = $this->options;
        $databases = array();
        $conn = $this->conn;
        if (!$conn) return $databases;
        $result = mysql_list_dbs($conn);
        while($row = mysql_fetch_object($result)) {
            $database = $row->Database;
            if ($database == 'information_schema') continue;
            $result2 = mysql_query("SHOW TABLES FROM `$database`", $conn);
            if (mysql_num_rows($result2) > 0) $databases[] = $database;
            mysql_free_result($result2);
        }
        mysql_free_result($result);
        return $databases;
    }

}