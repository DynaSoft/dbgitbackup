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

if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

class DbGitBackup {

/**
 * DbGitBackup class instance options
 *
 * @var array
 */
    public $options;

/**
 * Loaded backup configurations
 *
 * @var array
 */
    public $config;

/**
 * Full path to the config file
 *
 * @var string
 */
    public $configFile;

/**
 * Loaded output loggers
 *
 * @var array
 */
    public $loggers = array();

/**
 * Current directory for writing backups
 *
 * @var string
 */
    public $backupDir;

/**
 * Constructor
 *
 * @param array $options
 */
    public function __construct($options = array()) {
        $defaults = array(
            'config' => 'default.yml',
            'configDirName' => 'configs',
            'loggers' => array('Console'),
        );
        $defaults['baseDir'] = dirname(dirname(dirname(__FILE__))); // used for all relative paths
        $this->options = array_merge($defaults, $options);
        $this->initLoggers();
        $this->loadConfig();
    }

/**
 * Maps CLI options to options used by class constructor
 *
 */
    public static function mapCliOptions() {
        $cliOptions = getopt("c:");
        $mappings = array(
            'c' => 'config'
        );
        $options = array();
        foreach ($cliOptions as $k => $v) if ($v && isset($mappings[$k])) $options[$mappings[$k]] = $v;
        return $options;
    }

/**
 * Constructs loggers objects and stores them inside $loggers class variable
 *
 */
    public function initLoggers() {
        foreach ($this->options['loggers'] as $logger => $options) {
            if (!is_array($options)) {
                $logger = $options;
                $options = array();
            }
            $className = implode('_', array(__CLASS__, 'Logger', $logger));
            $classFile = dirname(__FILE__) . DS . 'Logger' . DS . $logger . '.php';
            if (!file_exists($classFile)) return;
            require_once($classFile);
            $this->loggers[] = new $className($options);
        }
    }

/**
 * Loads configuration from array or file into $config class variable
 *
 */
    public function loadConfig() {
        if (is_array($this->options['config'])) {
            $this->config = $this->options['config'];
            return;
        }
        $this->configFile = ($this->isAbsolute($this->options['config'])) ? $this->options['config'] : $this->options['baseDir'] . DS . $this->options['configDirName'] . DS . $this->options['config'];
        if (!file_exists($this->configFile)) $this->error('Unable to load config file: ' . $this->configFile);
        $ext = pathinfo($this->configFile, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'yml':
            case 'yaml':
                require_once(dirname(dirname(__FILE__)) . DS . 'sfYaml' . DS . 'sfYaml.php');
                $this->config = sfYaml::load($this->configFile);
                break;
            default:
                $this->error('Unable to load config file: ' . $this->configFile);
        }
    }

/**
 * Parses configuration
 *
 * @param array $config
 * @return array
 */
    public function parseConfig($config) {
        $backups = array();
        $parentConfig = array();
        if (isset($config['global'])) {
            $parentConfig = $config['global'];
            unset($config['global']);
        }
        foreach ($config as $backupConfigKey => $backupConfig) {
            $backupConfig = self::merge($parentConfig, $backupConfig);
            $parentConfig2 = $backupConfig;
            unset($parentConfig2['backup']);

            $databases = array_keys($backupConfig['backup']);
            if (in_array('*', $databases)) {
                unset($backupConfig['backup']['*']);
                $vendorObject = $this->getVendorObject($backupConfig);
                $allDatabases = $vendorObject->getDatabases();
                unset($vendorObject);
                if (!empty($allDatabases)) {
                    foreach ($allDatabases as $database) {
                        $allDatabasesConfig[$database] = null;
                    }
                    $backupConfig['backup'] = array_merge($allDatabasesConfig, $backupConfig['backup']);
                }
            }

            foreach ($backupConfig['backup'] as $database => $details) {
                $details = self::merge($parentConfig2, $details);

                // Set defaults
                $details['backupMode'] = (isset($details['backupMode'])) ? $details['backupMode'] : 'git';
                $details['backupsDir'] = (isset($details['backupsDir'])) ? $details['backupsDir'] : 'backups' . DS . $backupConfigKey;
                $details['server']['vendor'] = (isset($details['server']['vendor'])) ? $details['server']['vendor'] : 'MySql';
                $details['time'] = self::merge(array(
                    'format' => 'Y-m-d H:i:s',
                    'offset' => 0,
                ), (isset($details['time']) ? $details['time'] : array()));

                $details['db'] = $database;
                $details['dirName'] = (isset($details['dirName'])) ? $details['dirName'] : $database;
                $details['file'] = (isset($details['file'])) ? $details['file'] : $database . '.sql';
                $details['nice'] = (isset($details['nice']) && ($details['nice'] <= 19 || $details['nice'] >= -20)) ? 'nice -n ' . $details['nice'] : '';
                $details['vendorOptions'] = (isset($details['vendorOptions'])) ? ' ' . $details['vendorOptions'] : '';

                $backups[$backupConfigKey][$database] = $details;
            }
        }
        return $backups;
    }

/**
 * Main class method - performs backups
 *
 */
    public function makeBackups() {
        if ($this->configFile) {
            $this->log('Processing following config file: ' . $this->configFile);
        }
        $backups = $this->parseConfig($this->config);
        foreach ($backups as $backupConfigKey => $backupConfig) {
            foreach ($backupConfig as $database => $details) {

                // Skip inactive backups
                if (isset($details['active']) && (!$details['active'])) continue;

                // Set current backup directory
                $this->setBackupDir($details);
                if (!$this->createDir($this->backupDir)) {
                    $this->log('Unable to create backup directory: ' . $this->backupDir);
                    $this->log('Skipping...');
                    continue;
                }

                // Verify timestamp
                if (isset($details['backupInterval'])) {
                    if (!$this->verifyTimestamp($this->backupDir, $details['backupInterval'])) continue;
                }

                // Set backup filename
                $this->setBackupFile($details);

                // Perform backup
                $vendorObject = $this->getVendorObject($details);
                $status = $vendorObject->performBackup();

                if ($status) {
                    // GIT it!
                    $this->gitIt($details);

                    $this->log("[configuration: $backupConfigKey] [db: {$details['db']}] -> backup created", $this->now($details['time']['offset']));
                    if (isset($details['backupInterval'])) {
                        $this->writeTimestamp($this->backupDir);
                    }
                    if (isset($details['backupDirInterval'])) {
                        $this->writeTimestamp(dirname($this->backupDir));
                    }
                } else {
                    $this->logError("$backupConfigKey [$database] -> problems during backup", $this->now($details['time']['offset']));
                }
                unset($vendorObject);
            }
        }
    }

/**
 * Inits backup git repository, commits backup
 *
 * @param type $details
 */
    public function gitIt($details) {
        if (!($details['backupMode'] == 'git')) return;

        chdir($this->backupDir);

        // Check if repo has already been initialized
        if (!is_dir('.git')) {
            $f = '.gitignore';
            $fh = fopen($f, 'w');
            fwrite($fh, 'dbgitbackup.timestamp');
            fclose($fh);
            exec("{$details['nice']} git init");
        }

         exec("{$details['nice']} git add .");

        $now = $this->now($details['time']['offset'], $details['time']['format']);
        $out = array();
        exec("{$details['nice']} git commit -m 'backup from: $now'", $out, $status);
        if ($status == 0) {
            exec("{$details['nice']} git gc");
        }

        // Create tag with backup date in following format Y-m-d
        $tagName = $this->now($details['time']['offset'], 'Y-m-d');
        exec("{$details['nice']} git tag $tagName 1> /dev/null 2> /dev/null");
    }

/**
 * Performs check if action should be taken (true) based on
 * timestamp file and interval setting
 *
 * @param string $dir
 * @param string $interval
 * @return bool
 */
    public function verifyTimestamp($dir, $interval) {
        if (!is_dir($dir)) return;
        $timestamp = $this->readTimestamp($dir);
        $nextTimestamp = strtotime($interval, $timestamp);
        if (time() > $nextTimestamp) return true;
        return false;
    }

/**
 * Writes timestamp file to specified dir
 *
 * @param string $dir
 */
    public function writeTimestamp($dir) {
        if (!is_dir($dir)) return;
        $fh = fopen($dir . DS . 'dbgitbackup.timestamp', 'w');
        fwrite($fh, time());
        fclose($fh);
    }

/**
 * Returns timestamp from timestamp file from specified dir
 *
 * @param string $dir
 */
    public function readTimestamp($dir) {
        if (!is_dir($dir)) return;
        $f = $dir . DS . 'dbgitbackup.timestamp';
        if (!file_exists($f)) return false;
        $timestamp = 1 * (file_get_contents($f));
        return $timestamp;
    }

/**
 * Gets vendor object for current database
 *
 * @param array
 * @return object
 */
    public function getVendorObject($details) {
        $vendor = $details['server']['vendor'];
        $className = implode('_', array(__CLASS__, 'Vendor', $vendor));
        $classFile = dirname(__FILE__) . DS . 'Vendor' . DS . $vendor . '.php';
        if (!file_exists($classFile)) $this->error('Missing required file: ' . $classFile);
        require_once($classFile);
        return new $className($details);
    }

/**
 * Prepends backup date to backup filename
 *
 * @param array
 */
    public function setBackupFile(&$details) {
        if (isset($details['time']['active']) && (!$details['time']['active'])) return;
        if ($details['backupMode'] == 'sql') {
            $now = $this->now($details['time']['offset'], $details['time']['format']);
            $now = str_replace(array(' ', ':', '-'), '_', $now);
            $details['file'] =  $now . '_' . $details['file'];
        }
    }

/**
 * Sets backup directory for currently processed configuration
 *
 * @param string
 * @param array
 */
    public function setBackupDir(&$details) {
        if ($this->isAbsolute($details['backupsDir'])) {
            $this->backupDir = $details['backupsDir'] . DS . $details['dirName'];
        } else {
            $this->backupDir = $this->options['baseDir'] . DS . $details['backupsDir'] . DS . $details['dirName'];
        }
        // Rotate backup directory
        if (isset($details['backupDirInterval'])) {
            if (is_dir($this->backupDir)) {
                if ($this->verifyTimestamp($this->backupDir, $details['backupDirInterval'])) {
                    $this->backupDir .= DS . $this->now($details['time']['offset'], 'Y-m-d-H-i-s');
                } else {
                    if ($dh = opendir($this->backupDir)) {
                        $dirs = array();
                        while (($file = readdir($dh)) !== false) {
                            if (filetype($this->backupDir . DS . $file) == 'dir' && ($file != '.') && ($file != '..')) {
                                $dirs[strtotime($file)] = $file;
                            }
                        }
                        closedir($dh);
                        $this->backupDir .= DS . $dirs[max(array_keys($dirs))];
                    }
                }
            } else {
                $this->backupDir .= DS . $this->now($details['time']['offset'], 'Y-m-d-H-i-s');
            }
        }
        $details['backupDir'] = $this->backupDir;
    }

/**
 * Creates nested directory structure
 *
 * @param string $dir
 * @return bool
 */
    public function createDir($dir) {
        if (!is_dir($dir)) {
            exec('mkdir -p ' . $dir);
        }
        return is_dir($dir);
    }

/**
 * Tells whether given path is absolute
 *
 * @param string $path
 * @return bool
 */
    public function isAbsolute($path) {
        return (substr($path, 0, 1) == '/');
    }

/**
 * Gets current timestamp
 *
 * @param int $timeOffset
 * @return string
 */
    public function now($timeOffset = 0, $format = 'Y-m-d H:i:s') {
        $timeOffset = $timeOffset * 60 * 60;
        return gmdate($format, time() + $timeOffset);
    }

/**
 * Logs script output and errors via all loaded loggers
 *
 * @param string|array $message
 * @param string $time
 */
    public function log($message, $time = null) {
        if (!is_null($time)) $time .= ' ';
        foreach ($this->loggers as $loggerObj) {
            if (method_exists($loggerObj, 'log')) $loggerObj->log($time . $message);
        }
    }

    public function logError($message, $time = null) {
        if (!is_null($time)) $time .= ' ';
        foreach ($this->loggers as $loggerObj) {
            if (method_exists($loggerObj, 'logError')) $loggerObj->logError($time . $message);
        }
    }

    public function hr() {
        foreach ($this->loggers as $loggerObj) {
            if (method_exists($loggerObj, 'hr')) $loggerObj->hr();
        }
    }

/**
 * Logs $errorMessage, terminates script execution
 *
 * @param string|array $errorMessage
 */
    public function error($errorMessage) {
        $this->logError($errorMessage);
        $this->logError('Giving up...');
        exit;
    }

/**
 * https://github.com/cakephp/cakephp/blob/2.0/lib/Cake/Utility/Set.php
 *
 * @param array $arr1
 * @param array $arr2
 * @return array
 */
    public static function merge($arr1, $arr2 = null) {
        $args = func_get_args();
        $r = (array) current($args);
        while (($arg = next($args)) !== false) {
            foreach ((array) $arg as $key => $val) {
                if (!empty($r[$key]) && is_array($r[$key]) && is_array($val)) {
                    $r[$key] = self::merge($r[$key], $val);
                } elseif (is_int($key)) {
                    $r[] = $val;
                } else {
                    $r[$key] = $val;
                }
            }
        }
        return $r;
    }

}