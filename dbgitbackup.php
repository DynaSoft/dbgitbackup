#!/usr/bin/php -q
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

set_time_limit(0);
require_once('lib' . DIRECTORY_SEPARATOR . 'DbGitBackup' . DIRECTORY_SEPARATOR . 'DbGitBackup.php');
$options = DbGitBackup::mapCliOptions();
$dbGitBackup = new DbGitBackup($options);
$dbGitBackup->makeBackups();
