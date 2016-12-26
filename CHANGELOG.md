# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
### Changed
### Deprecated
### Removed
### Fixed
### Security

## 2.0.0 - 2016-12-23
Initial commit of fork


## 1.1.0 - 2013-03-11

**Note:** This release is compatible with php-resque 1.0 through 1.2.

### Added
- added composer.json and submit to Packagist (rayward)
- implemented ResqueScheduler::removeDelayed and ResqueScheduler::removeDelayedJobFromTimestamp (tonypiper)

## Changed
- Update declarations for methods called statically to actually be static methods (atorres757)

### Fixed
- Corrected spelling for ResqueScheduler_InvalidTimestampException (biinari)
- Corrected spelling of beforeDelayedEnqueue event (cballou)
- Correct issues with documentation (Chuan Ma) 


## 1.0.0 - 2011-03-27
Initial release