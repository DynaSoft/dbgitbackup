<?php
class DbGitBackup_Logger_Console {

    const EOL = PHP_EOL;

    public function __construct($options = array()) {
    }

    public function log($message, $newlines = 1) {
        if (is_array($message)) {
            $message = implode(self::EOL, $message);
        }
        $output = fopen('php://stdout', 'w');
        fwrite($output, $message . str_repeat(self::EOL, $newlines));
    }

    public function hr($newlines = 0, $width = 63) {
        $this->log(null, $newlines);
        $this->log(str_repeat('-', $width));
        $this->log(null, $newlines);
    }

}