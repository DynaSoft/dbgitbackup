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

class DbGitBackup_Logger_Console {

    const EOL = PHP_EOL;

    public function __construct($options = array()) {
    }

    public function log($message, $newlines = 1, $stream = 'php://stdout') {
        if (is_array($message)) {
            $message = implode(self::EOL, $message);
        }
        $output = fopen($stream, 'w');
        fwrite($output, $message . str_repeat(self::EOL, $newlines));
    }

    public function logError($message, $newlines = 1) {
        $this->log($message, $newlines, 'php://stderr');
    }

    public function hr($newlines = 0, $width = 63) {
        $this->log(null, $newlines);
        $this->log(str_repeat('-', $width));
        $this->log(null, $newlines);
    }

}