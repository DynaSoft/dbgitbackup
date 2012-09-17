# dbgitbackup - backups SQL databases and stores them in GIT repositories

dbgitbackup eases the task of performing periodical database backups into plain
SQL files or GIT repositories. You no longer have to struggle with multiple cron
jobs and long console backup statements.

dbgitbackup is a PHP CLI script which should be invoked via cron job. It works by
parsing an easy to read and setup YAML config file and constructing appopriate
backup statements/backup folders.

Single config file gives you opportunity to specify (amongst others):

- multiple database servers to backup from
- what databases/tables to backup (per server)
- how often backups should be taken

Currently only MySQL is supported.

## quickstart

Clone dbgitbackup project into a directory on your server:

```
mkdir /home/user/dbgitbackup
cd /home/user/dbgitbackup
git clone git://github.com/DynaSoft/dbgitbackup.git .
```

Create empty config file inside `configs` folder:

```
cd /home/user/dbgitbackup/configs
touch sampleconfig.yml
```

Put appropriate settings inside config file, for instance:

```
sampleBackupConfiguration:
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:
        database2:
```

See `configs/default.sample.yml` for reference about more possible settings.

Add cron job which runs dbgitbackup with your config file:

```
0 * * * * /usr/bin/php /home/user/dbgitbackup/dbgitbackup.php -c sampleconfig.yml
```

If you do not specify `-c` param, the script will look for `default.yml` file by default.

## advanced usage

#### multiple backup configurations in single config file

You can specify many backup configurations within single config file:

```
sampleBackupConfiguration:
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:
        database2:

otherBackupConfiguration:
    backupsDir: backups/mysql.somedomain.com
    server:
        vendor: MySql
        host: mysql.somedomain.com
        user: user
        password: password
    backup:
        database5:
        database6:
```

#### backup mode: either SQL or GIT

By default, dbgitbackup operates in GIT mode. To create plain SQL backups specify
`backupMode` setting:

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:
        database2:
```

#### backup all databases on the server

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        *:
```

#### flag for disabling certain backup

By specifying `active` setting you can disable certain backup:

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:
            active: 0
        database2:
```

You can easily disable certain configuration:

```
sampleBackupConfiguration:
    active: 0
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:
        database2:
```

#### backup all databases on the server except certain database

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        *:
        database2:
            active: 0
```

#### using global configuration

To share settings amongst various backup configurations you can specyify special
`global` configuration:

```
global:
    backupMode: sql
    backupInterval: +24 hours
    server:
        vendor: MySql

sampleBackupConfiguration:
    backupsDir: backups/mysql.example.com
    server:
        host: mysql.example.com
        user: user
        password: password
    backup:
        database1:

otherBackupConfiguration:
    backupsDir: backups/mysql.somedomain.com
    server:
        host: mysql.somedomain.com
        user: user
        password: password
    backup:
        database5:
        database6:
```

#### using backup interval setting

`backupInterval` setting allows to speficy how often backup should be taken.
For instance if you setup cron job to run dbgitbackup.php every hour you can
indicate that certain backup should be taken only once a day with following:

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    backupInterval: +24 hours
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database2:
```

#### using backup directory rotation feature

When `backupDirInterval` setting is specified, backups are placed under
date-based directories. For following configuration:

```
sampleBackupConfiguration:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    backupDirInterval: +24 hours
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password
    backup:
        database2:
```

backup directories will be created in following manner:

```
backups/mysql.example.com/database2/2011-11-10-13-25-29/...
backups/mysql.example.com/database2/2011-11-11-13-25-29/...
```

#### using `file` configuration key

`file` configuration key allows to specify the name of backup file. By default
backup date will be prepended to backup filename when using SQL mode.
In following configuration we are dumping all tables with `tableprefix1_`
prefix to `tableprefix1.sql` and all tables with `tableprefix2_` prefix to
`tableprefix2.sql`:

```
global:
    backupMode: sql
    backupsDir: backups/mysql.example.com
    server:
        vendor: MySql
        host: mysql.example.com
        user: user
        password: password

sampleBackupConfiguration1:
    backup:
        database1:
            tableNames: tableprefix1_%
            file: tableprefix1.sql

sampleBackupConfiguration2:
    backup:
        database1:
            tableNames: tableprefix2_%
            file: tableprefix2.sql
```

## copyright & license

Copyright 2011, DynaSoft (http://www.dynasoft.pl).
The code is distributed under the terms of the MIT License.
For the full license see http://www.opensource.org/licenses/mit-license.php.
