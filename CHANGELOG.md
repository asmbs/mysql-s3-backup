# Changelog

Since v1.1.0, the format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

This project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).



## [Unreleased]



## [2.0.0] - 2019-06-19

### Added

- Support for Amazon SNS for alerting of exceptions.
- Support for uploading and downloading with client-side encryption with an AWS KMS-managed customer master key.

### Changed

- **[BC Break]** Changed the program structure to work as a set of Symfony Commands (so `crontab` configurations have to be altered slightly).
- Updated dependencies.



## [1.1.0] - 2018-09-28

### Added

- Added a `mirror_default_opt` option for mirroring the default `--opt` setting in MySQL's original `mysqldump`.
- Added an `add_sql_extension` option for adding `.sql` before the compression extension (.e.g `.gz`) in compressed backups.



## [1.0.0]

- Change default compression algorithm
- Fix hourly backups being skipped due to lag time
- Fix warning when no folders exist yet
- Fix config file path
- Improve installation documentation



## [0.0.1]

- Initial commit