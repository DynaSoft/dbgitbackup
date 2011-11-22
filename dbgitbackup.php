#!/usr/bin/php -q
<?php
set_time_limit(0);
require_once('lib' . DIRECTORY_SEPARATOR . 'DbGitBackup' . DIRECTORY_SEPARATOR . 'DbGitBackup.php');
$options = DbGitBackup::mapCliOptions();
$dbGitBackup = new DbGitBackup($options);
$dbGitBackup->makeBackups();
