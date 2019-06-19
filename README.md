# MySQL S3 Backup

> Manages backups of a MySQL database to Amazon S3



## Requirements

- PHP 7.2+
- MySQL 4.1.0+
- Composer 1.6.5+



## Installation

1. Create an empty, non-versioned S3 bucket. The first time that the script runs, it will create 4 folders for you ("yearly", "monthly", "daily", and "hourly").

2. Copy `config.yaml.dist` to a new file named `config.yaml` in the same directory and configure it to your needs, per the Configuration Reference below.

3. Install the dependencies:

   ```
   composer install
   ```

4. Set up a single `cron` job to execute `MySQLS3Backup.php` once every hour. Using `crontab`, an example line would be:

   ```
   0 * * * * php /path/to/mysql-s3-backup/MySQLS3Backup.php
   ```

The backup files will be created in the format `YYYY-MM-DD_HH-MM-SS.EXT`, where `EXT` is the file extension based on the compression method chosen (`sql` for "None", `gz` for "Gzip", and `bz2` for "Bzip2"). The file names should not be changed inside the bucket, or else the script will not be able to recognize the files.



## Configuration Reference

- `s3`
  -  `version` The S3 version to use
  -  `region` The S3 region to use
  -  `credentials`
     -  `key` Your S3 key
     -  `secret` Your S3 secret
- `sns`
  - `enabled` If set to true, then exceptions will be sent to an Amazon SNS Topic. If set to false, exceptions will simply be outputted.
  - `arguments`
    -  `version`
    - `region`
    - `credentials`
      - `key`
      - `secret`
  - `topic_arn` The ARN of the Amazon SNS Topic to use. Typically, this will begin with "arn:aws:sns".
- `mysql`
  -  `host` The host on which your database is hosted
  -  `dbname` The name of your database
  -  `username` The username to be used by MySQL
  -  `password` The password to be used by MySQL
- `app`
  -  `output` If set to true, then information will be outputted. If set to false, the script will run silently (except for exceptions).
  -  `compression` The compression algorithm to use. This should be 'None', 'Gzip', or 'Bzip2'. Note that bzip2 support is [not enabled by default in PHP](http://php.net/manual/en/bzip2.installation.php).
  -  `maximum_backup_counts` This is the maximum number of backups to keep based on each time period. For example, setting 'yearly' to '7' will keep **one** backup for each of the past 7 years. When a day rolls over, the most recent 'hourly' backup will be used.
    -   `yearly`
    -   `monthly`
    -   `daily`
    -   `hourly`
  -  `mirror_default_opt` If set to true, then the dump settings will mirror the default `--opt` [setting](https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_opt) of MySQL's original `mysqldump`.
  -  `add_sql_extension` If set to true, then `.sql` will be added before the compression extension (e.g. `.gz`) in compressed backups.

