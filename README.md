# MySQL S3 Backup

*Manages backups of a MySQL database to Amazon S3*



## Requirements

- PHP 7.2+
- MySQL 4.1.0+
- Composer 1.6.5+



## Installation

1. Create an empty, non-versioned S3 bucket. The first time that the script runs, it will create 4 folders for you ("yearly", "monthly", "daily", and "hourly").

2. Copy `config.yaml.dist` to a new file named `config.yaml` in the same directory and configure it to your needs, per the Configuration Reference below.

3. Set up a single `cron` job to execute `MySQLS3Backup.php` once every hour. Using `crontab`, an example line would be:

   ```
   0 * * * * php /path/to/mysql-s3-backup/MySQLS3Backup.php
   ```

The backup files will be created in the format `YYYY-MM-DD_HH-MM-SS.EXT`, where `EXT` is the file extension based on the compression method chosen (`sql` for "None", `gz` for "Gzip", and `bz2` for "Bzip2"). The file names should not be changed inside the bucket, or else the script will not be able to recognize the files.



## Configuration Reference

- `s3`
  -  `version` The S3 version to use
  -  `region` The S3 region to use
  -  `key` Your S3 key
  -  `secret` Your S3 secret
- `mysql`
  -  `host` The host on which your database is hosted
  -  `dbname` The name of your database
  -  `username` The username to be used by MySQL
  -  `password` The password to be used by MySQL
- `app`
  -  `output` If set to true, then information will be outputted. If set to false, the script will run silently.
  -  `compression` The compression algorithm to use. This should be 'None', 'Gzip', or 'Bzip2'. Note that bzip2 support is [not enabled by default in PHP](http://php.net/manual/en/bzip2.installation.php).
  -  `maximum_backup_counts` This is the maximum number of backups to keep based on each time period. For example, setting 'yearly' to '7' will keep **one** backup for each of the past 7 years. When a day rolls over, the most recent 'hourly' backup will be used.
    -   `yearly`
    -   `monthly`
    -   `daily`
    -   `hourly`

